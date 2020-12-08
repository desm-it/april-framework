<?php
class dbc_Database_Mongo {
	
	/**
	 *
	 * @var unknown_type
	 */
	CONST MODEL_FIELD_NUMBER = 1;
	CONST MODEL_FIELD_STRING = 2;
	CONST MODEL_FIELD_TIMESTAMP = 3;
	CONST MODEL_FIELD_DATE = 4;
	CONST MODEL_FIELD_FLOAT = 5;
	CONST MODEL_FIELD_ARRAY = 6;
	
	/**
	 *
	 * @var unknown_type
	 */
	private static $_aInstances = array ();
	private $_oMongo = null;
	private $_oMongoDB = null;
	private $_sConnectionUri = 'mongodb://';
	private $_bSafeInsert = true;
	private $_bInsertForceSync = true;
	
	/**
	 *
	 * @param dbc_Config_Mongo $oConfig_        	
	 * @throws Exception
	 */
	private function __construct(dbc_Config_Mongo $oConfig_) {
		$sHost = $oConfig_->get ( 'host' );
		if ($sHost == '')
			throw new Exception ( "No host defined" );
		$this->_aOptions ['host'] = $sHost;
		$this->_aOptions ['database'] = $oConfig_->get ( 'database' );
		$this->_aOptions ['useslave'] = $oConfig_->get ( 'useslave' );
		$this->_aOptions ['replicaset'] = $oConfig_->get ( 'replicaset' );
		$sUriParams = '';
		$this->_sConnectionUri = 'mongodb://' . $this->_aOptions ['host'] . '/' . $this->_aOptions ['database'];
		
		dbc_Logger::info("Mongo connection : {$this->_sConnectionUri}");
//		if ($this->_aOptions ['useslave']) $sUriParams.="slaveOk=true&";
//		if ($sUriParams!='') $this->_sConnectionUri.='?'.$sUriParams;
		
		self::$_aInstances [$oConfig_->id ()] = & $this;
	}
	
	/**
	 *
	 * @param dbc_Config_Mongo $oConfig_        	
	 * @return multitype: dbc_Database_Mongo
	 */
	static function getInstance(dbc_Config_Mongo $oConfig_) {
		if (self::hasInstance ( $oConfig_ ))
			return self::$_aInstances [$oConfig_->id ()];
		return new dbc_Database_Mongo ( $oConfig_ );
	}
	
	/**
	 *
	 * @param dbc_Config_Mongo $oConfig_        	
	 */
	static public function hasInstance(dbc_Config_Mongo $oConfig_) {
		return isset ( self::$_aInstances [$oConfig_->id ()] );
	}
	
	/**
	 *
	 * @throws Exception
	 * @return boolean
	 */
	public function connect() {
		try {
			
			$aMongoOps = array('connect'=>true);
			if ( $this->_aOptions ['replicaset'] ) $aMongoOps['replicaSet'] = true;
			$this->_oMongo = new MongoClient ( $this->_sConnectionUri, $aMongoOps );
			
			//$this->_oMongo = new MongoClient ( $this->_sConnectionUri );
			
			if (isset($this->_aOptions['useslave']) && $this->_aOptions['useslave'])
				$this->_oMongo->setReadPreference(MongoClient::RP_SECONDARY); //RP_SECONDARY_PREFERRED
			$this->_oMongoDB = $this->_oMongo->selectDB ( $this->_aOptions ['database'] );
			
			//if (isset($this->_aOptions['useslave']) && $this->_aOptions['useslave'])
			//	$this->_oMongoDB->setReadPreference(MongoClient::RP_SECONDARY_PREFERRED);
			
			return true;
		} catch ( Exception $e ) {
			dbc_Logger::debug ( "unable to connect to Mongo server at {$this->_aOptions['host']}" );
			throw $e;
		}
		return false;
	}
	
	/**
	 *
	 * @param unknown_type $oRegistry_        	
	 * @param unknown_type $oStructure_        	
	 * @return unknown
	 */
	public function convertToMongoObjects($oRegistry_, $oStructure_) {
		foreach ( $oRegistry_ as $rk => &$rv ) {
			$vType = $oStructure_ [$rk];
			
			if ($vType == self::MODEL_FIELD_ARRAY) {
				$rv = $this->convertToMongoObjects ( $oRegistry_ [$rk], $oStructure_ [$rk] );
				continue;
			}
			
			if ($vType == self::MODEL_FIELD_STRING) {
				continue;
			}
			
			if ($vType == self::MODEL_FIELD_FLOAT) {
				$rv = ( float ) $rv;
				continue;
			}
			
			if ($vType == self::MODEL_FIELD_NUMBER) {
				$rv = ( int ) $rv;
				continue;
			}
			
			if ($vType == self::MODEL_FIELD_DATE || $vType == self::MODEL_FIELD_TIMESTAMP) {
				$rv = new MongoDate ( strtotime ( $rv ) );
			}
		}
		return $oRegistry_;
	}
	
	/**
	 *
	 * @param unknown_type $sCollection_        	
	 * @param unknown_type $oRegistry_        	
	 * @param unknown_type $oStructure_        	
	 * @param unknown_type $aOptions_        	
	 * @throws Exception
	 * @return boolean
	 */
	public function insert($sCollection_, $oRegistry_, $oStructure_ = null, $aOptions_ = array()) {
		// Ensure connection
		if (is_null ( $this->_oMongoDB ))
			$this->connect ();

		
		$oCollection = new MongoCollection ( $this->_oMongoDB, $sCollection_ );

		
		$aDefaultOptions = array (
				'safe' => $this->_bSafeInsert,
				'fsync' => $this->_bInsertForceSync 
		);
		$aOptions = array_merge ( $aDefaultOptions, $aOptions_ );
		
		// if 'fsync'=true, 'safe'=true (implicitly)
		if ($aOptions ['fsync'] === true)
			$aOptions ['safe'] = true;
		
		try {
			if ($oStructure_ != null)
				$oRegistry_ = $this->convertToMongoObjects ( $oRegistry_, $oStructure_ );

			
			$aReturn = $oCollection->insert ( $oRegistry_, $aOptions );
			
			if ($aOptions ['safe'] === true) {
				// Check INSERT result (we just can do this when safe=true)
				// If no exception is raised on the ->insert, we can make sure
				// everything went perfect.
				return true;
			} else {
				// if safe=false, $aReturn contains a bool repreesnting if
				// the Array inserted is not empty - we cannot know if the
				// operation succeeded.
				if ($aReturn === false) {
					throw new Exception ( 'aReturn is false' );
				} else {
					return true;
				}
			}
		} catch ( Exception $e ) {
			dbc_Logger::debug ( "unable to insert registry on collection: " . $sCollection_ . "!!" );
			throw $e;
		}
	}
	
	/**
	 *
	 * @param unknown_type $sCollection_        	
	 * @param unknown_type $sCriteria_        	
	 * @param unknown_type $oNewObj_        	
	 * @param unknown_type $aOptions_        	
	 */
	public function update($sCollection_, $sCriteria_, $oNewObj_, $aOptions_ = array()) {
		// Ensure connection
		if (is_null ( $this->_oMongoDB ))
			$this->connect ();
		
		$oCollection = new MongoCollection ( $this->_oMongoDB, $sCollection_ );
		
		$aDefaultOptions = array (
				'safe' => true 
		);
		$aOptions = array_merge ( $aDefaultOptions, $aOptions_ );
		
		return $oCollection->update ( $sCriteria_, $oNewObj_, $aOptions );
	}
	
	/**
	 *
	 * @param unknown_type $sCollection_        	
	 * @param unknown_type $aQuery_        	
	 * @param unknown_type $aFields_        	
	 */
	public function find($sCollection_, $aQuery_ = array(), $aFields_ = array()) {
		// Ensure connection
		if (is_null ( $this->_oMongoDB ))
			$this->connect ();
		
		$oCollection = new MongoCollection ( $this->_oMongoDB, $sCollection_ );
		
		$m= $oCollection->find ( $aQuery_, $aFields_ );
		
		return $m;
	}
	
	// returns Array
	public function findOne($sCollection_, $aQuery_ = array(), $aFields_ = array()) {
		// Ensure connection
		if (is_null ( $this->_oMongoDB ))
			$this->connect ();
		
		$oCollection = new MongoCollection ( $this->_oMongoDB, $sCollection_ );
		return $oCollection->findOne ( $aQuery_, $aFields_ );
	}
	
	/**
	 * The Mongo database server runs a JavaScript engine.
	 * This method allows you to run arbitary JavaScript on the database.
	 * This can be useful if you want touch a number of collections lightly, or
	 * process some results on the database side to reduce
	 * the amount that has to be sent to the client.
	 *
	 * Running JavaScript in the database takes a write lock, meaning it blocks
	 * other operations.
	 * Make sure you consider this before running a long script.
	 *
	 * @param String $sCode_        	
	 * @param Array $aArguments_        	
	 */
	public function execute($sCode_, $aArguments_ = array()) {
		// Ensure connection
		if (is_null ( $this->_oMongoDB ))
			$this->connect ();
		
		return $this->_oMongoDB->execute ( $sCode_, $aArguments_ );
	}
	
	public function count($sCollection_, $aQuery_ = array()){
		if (is_null ( $this->_oMongoDB ))
			$this->connect ();
		//$aOldPref = $this->_oMongo->getReadPreference();
		
		/*if ($aOldPref['type']==MongoClient::RP_SECONDARY || $aOldPref['type']==MongoClient::RP_SECONDARY_PREFERRED) {
			$this->_oMongo->setReadPreference(MongoClient::RP_PRIMARY);
			$this->_oMongoDB->setReadPreference(MongoClient::RP_PRIMARY);
		}*/
		
		$oCollection = new MongoCollection ( $this->_oMongoDB, $sCollection_ );
		//$oCollection->setReadPreference(MongoClient::RP_PRIMARY);
		if ($_SERVER['REMOTE_ADDR']=='62.212.71.77') {
			//var_dump($oCollection->getReadPreference(), $this->_oMongo->getConnections());
		}
		
		$a = $oCollection->count($aQuery_);
		
		
		
		/*if ($aOldPref['type']==MongoClient::RP_SECONDARY || $aOldPref['type']==MongoClient::RP_SECONDARY_PREFERRED) {
			$this->_oMongo->setReadPreference($aOldPref['type']);
			$this->_oMongoDB->setReadPreference($aOldPref['type']);
		}*/
		return $a;
		//return 10;
	}
	
	public function remove($sCollection_, $sCrit){
		if (is_null ( $this->_oMongoDB ))
			$this->connect ();
	
		$oCollection = new MongoCollection ( $this->_oMongoDB, $sCollection_ );
	
		return $oCollection->remove($sCrit, array());
	}
	
	public function unsetField($sCollection_, $sCrit, $aDescription){
		if (is_null ( $this->_oMongoDB ))
			$this->connect ();
		
		$oCollection = new MongoCollection ( $this->_oMongoDB, $sCollection_ );

		return $oCollection->update($sCrit, array('$unset' => $aDescription), array());
	}
	
	
	/**
	 *
	 * @param unknown_type $sCommand_        	
	 * @param unknown_type $aOptions_        	
	 */
	public function command($sCommand_, $aOptions_ = array()) {
		// Ensure connection
		if (is_null ( $this->_oMongoDB ))
			$this->connect ();
		
		return $this->_oMongoDB->command ( $sCommand_, $aOptions_ );
	}
	
	/**
	 *
	 * @param unknown_type $sCollection_        	
	 * @param unknown_type $mKeys_        	
	 * @param array $aOptions_        	
	 */
	public function ensureIndex($sCollection_, $mKeys_, Array $aOptions_ = array()) {
		// Ensure connection
		if (is_null ( $this->_oMongoDB ))
			$this->connect ();
		
		$oCollection = new MongoCollection ( $this->_oMongoDB, $sCollection_ );
		return $oCollection->ensureIndex ( $mKeys_, $aOptions_ );
	}
	
	// Truncates a collection
	public function truncate($sCollection_) {
		return $this->execute ( 'db.' . $sCollection_ . '.remove({});' );
	}
	public function getDBStats() {
		return $this->command ( array (
				'dbStats' => 1 
		) );
	}
}
/*
 * $m=new Mongo('mongodb://'.); $db=$m->selectDB('dbc_affapi'); $database=new
 * MongoCollection($db, 'models'); var_dump($m);
 */
?>