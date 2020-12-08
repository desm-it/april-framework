<?php
/**
 * DBCorpLib
 *
 * @author DBCorp
 * @package DBCorpLib
 * @subpackage Database
 */

/**
 * A MySQL director class which utilizes two MySQL connections, master and slave. The director determines on which
 * connection a query is executed.
 *
 * Example:
 *
 * <code>
 * $oDb = dbc_Database_MySQL_Director::getInstance($oDbConf);
 * echo $oDb->getMaster();
 * echo $oDb->getSlave();
 * </code>
 *
 * @package DBCorpLib
 * @subpackage Database
 */
class dbc_Database_MySQL_Director extends dbc_Database_Queryable {
	/**#@+
	 * @ignore
	 * @internal
	 */
	private $_oDatabaseMaster;
	private $_oDatabaseSlave;
	static private $_aInstances = array();
	/**#@-*/

	/**
	 * @ignore
	 * @internal
	 * This function is private, which means that it is impossible to instantiate this class directly. Instead the
	 * programmer is forced to use the getInstance method.
	 */
	protected function __construct(dbc_Config_MySQL $oConfig_, dbc_Config_MySQL $oConfigSlave_ = NULL) {
		$this->_oDatabaseMaster = dbc_Database_MySQL::getInstance($oConfig_, 'master');
		$aHosts = $oConfig_->get('host');
		if (isset($aHosts['slave'])) {
			$this->_oDatabaseSlave = dbc_Database_MySQL::getInstance($oConfig_, 'slave');
		} else if (! is_null($oConfigSlave_)) {
			$this->_oDatabaseSlave = dbc_Database_MySQL::getInstance($oConfigSlave_, 'master');
		} else $this->_oDatabaseSlave = & $this->_oDatabaseMaster;

		$sId = (string) $oConfig_->id();
		if (! is_null($oConfigSlave_)) $sId .= '-' . (string) $oConfigSlave_->id();
		self::$_aInstances[$oConfig_->id()] = & $this;
	}

	public function setDaemonMode( $bOn_ = true ) {
		$this->_oDatabaseMaster->setDaemonMode( $bOn_ );
		$this->_oDatabaseSlave->setDaemonMode( $bOn_ );
	}

	/**
	 * This function can be used to select a different database using the same connection.
	 * @param String $sDatabase_ The name of the database to connect to.
	 * @return Boolean Returns TRUE if the database was selected successfully, FALSE if not.
	 */
	public function selectDatabase($sDatabase_) {
		return ($this->_oDatabaseMaster->selectDatabase($sDatabase_) && $this->_oDatabaseSlave->selectDatabase($sDatabase_));
	}

	/**
	 * Executes a single MySQL query on either the master or slave connection. The director determines which
	 * connection to use depending on the type of query.
	 * @see dbc_Database_MySQL::executeQuery()
	 * @param String $sQuery_ A single MySQL query. The query string should not end with a semicolon.
	 * @return dbc_Database_MySQL_Result Returns a Database_MySQL_Result instance.
	 */
	public function executeQuery($sQuery_) {
		$mArgs_ = func_get_args();
		
		if ($this->__isSelectQuery($sQuery_)) {
			return call_user_func_array(array($this->_oDatabaseSlave, 'executeQuery'), $mArgs_);
		} else return call_user_func_array(array($this->_oDatabaseMaster, 'executeQuery'), $mArgs_);
	}

	/**
	 * Executes a single MySQL query and returns the first record from the resultset. Note that the query will be sent to the
	 * MySQL server unmodified, which means that the calling process should take care of escaping any user input used in the
	 * query by using {@link dbc_Database_MySQL::escape()}.
	 * @param String $sQuery_ A single MySQL query. The query string should not end with a semicolon.
	 * @param Boolean $bAssoc_ Set to TRUE to receive an associative array, FALSE to get a numerical array.
	 * @return Array Returns an array with the data from the first record in the resultset.
	 */
	public function executeQueryAndGetFirstRecord( $sQuery_, $bAssoc_ = true ) {
		return $this->_oDatabaseSlave->executeQueryAndGetFirstRecord( $sQuery_, $bAssoc_);
	}

	/**
	 * @ignore
	 * @internal Replaced by executeQueryAndGetFirstRecord
	 */
	public function executeOneQueryAndReturnArray( $sQuery_, $bAssoc_ = true) {
		return $this->_oDatabaseSlave->executeQueryAndGetFirstRecord( $sQuery_, $bAssoc_);
	}

	/**
	 * Executes a single MySQL query and returns the first column value from the first record of the resultset. Note that the
	 * query will be sent to the MySQL server unmodified, which means that the calling process should take care of escaping
	 * any user input used in the query by using {@link dbc_Database_MySQL::escape()}.
	 * @param String $sQuery_ A single MySQL query. The query string should not end with a semicolon.
	 * @return Mixed Returns the value of the first column of the first record in the resultset.
	 */
	public function executeQueryAndGetFirstColumn( $sQuery_) {
		return $this->_oDatabaseSlave->executeQueryAndGetFirstColumn( $sQuery_);
	}

	/**
	 * @ignore
	 * @internal Replaced by executeQueryAndGetFirstColumn
	 */
	public function executeOneQueryAndReturnValue( $sQuery_ ) {
		return $this->_oDatabaseSlave->executeQueryAndGetFirstColumn( $sQuery_);
	}

	/**
	 * @ignore
	 * @internal This function should not be used by noobs, so it's not documented or listed in the wiki :-)
	 */
	public function executeUpdateOrInsert($sTable_, $sKey_, $aFieldValues_, $sKeyField_ ='id') {
		return $this->_oDatabaseMaster->executeUpdateOrInsert($sTable_, $sKey_, $aFieldValues_, $sKeyField_);
	}

	/**
	 * Escapes special characters in a string or array of string for use in a SQL statement, taking into account
	 * the current character set of the MASTER connection.
	 * @see dbc_Database_MySQL::escape()
	 * @param Mixed $mUnescaped_ The string or array of strings that need to be escaped.
	 * @return Mixed Returns the array with all strings escaped, a single escaped string, or FALSE on error.
	 */
	public function escape($mUnescaped_) {
		return call_user_func(array($this->_oDatabaseSlave, 'escape'), $mUnescaped_);
	}

	/**
	 * Escapes special characters in a string or array of string for use in a SQL statement, taking into account
	 * the current character set of the MASTER connection.
	 * @deprecated Use the preferred escape method instead. This function will be removed when err ... hell freezes over.
	 * @see dbc_Database_MySQL_Director::escape()
	 * @param Mixed $mUnescaped_ The string or array of strings that need to be escaped.
	 * @return Mixed Returns the array with all strings escaped, a single escaped string, or FALSE on error.
	 */
	public function sanitizeValue($mUnescaped_) { return $this->escape($mUnescaped_); }

	/**
	 * This function returns an array with all the information there is about the specified table. It can
	 * be used to examine database tables, duh.
	 * @param String $sTable_ The MySQL name of the table to retrieve information about.
	 * @return Array Returns an array with all information about the requested table.
	 */
	public function getTableInfo($sTable_) {
		return call_user_func(array($this->_oDatabaseMaster, 'getTableInfo'), $sTable_);
	}

	/**
	 * This function can be used to check if a certain table exists in the database.
	 * @param String $sTable_ The MySQL name of the table.
	 * @return Boolean Returns TRUE if the table exists or FALSE if not.
	 */
	public function hasTable($sTable_) {
		return call_user_func(array($this->_oDatabaseMaster, 'hasTable'), $sTable_);
	}

	/**
	 * This function returns the intersection of an array with the fields in the specified table. It can be
	 * used to create an array with only the keys that have a matching field in the provided table.
	 * @param String $sTable_ The MySQL name of the table to create the intersection with.
	 * @param Array $aValues_ The array with keys to create the intersection with.
	 * @return Array Returns an array with all the key value pairs that have a matching column in the specified
	 * table.
	 */
	public function getFieldIntersection($sTable_, $aValues_) {
		return call_user_func(array($this->_oDatabaseMaster, 'getFieldIntersection'), $sTable_, $aValues_);
	}

	/**
	 * @var String $sQuery_ A Valid MySQL query
	 * @return Boolean returns TRUE if the query uses a select, show or set statement.
	 */
	private function __isSelectQuery($sQuery_) {
		$aWords = str_word_count($sQuery_, 1);
		return strtoupper($aWords[0]) == 'SELECT' || strtoupper($aWords[0]) == 'SHOW' || strtoupper($aWords[0]) == 'SET';
	}

	/**
	 * @ignore
	 */
	public function connect() {
		return ($this->_oDatabaseMaster->connect() && $this->_oDatabaseSlave->connect());
	}

    /**
     * @ignore
     */
    public function disconnect() {
	return ($this->_oDatabaseMaster->disconnect() && $this->_oDatabaseSlave->disconnect());
    }

	/**
	 * @return dbc_Database_MySQL returns the master MySQL connection for this director
	 */
	public function getMaster() {
		return $this->_oDatabaseMaster;
	}

	/**
	 * @return dbc_Database_MySQL returns the slave MySQL connection for this director
	 */
	public function getSlave() {
		return $this->_oDatabaseSlave;
	}

	/**
	 * Preferred method of receiving a dbc_Database_MySQL_Director instance.
	 * @param dbc_Config_MySQL $oConfig_ Configuration object
	 * @param dbc_Config_MySQL $oConfigSlave_ Optional slave configuration object
	 * @return dbc_Database_MySQL_Director Returns a dbc_Database_MySQL_Director instance with the requested config.
	 */
	static public function getInstance(dbc_Config_MySQL $oConfig_, dbc_Config_MySQL $oConfigSlave_ = NULL) {
		if (self::hasInstance($oConfig_, $oConfigSlave_)) {
			$sId = (string) $oConfig_->id();
			if (! is_null($oConfigSlave_)) $sId .= '-' . (string) $oConfigSlave_->id();
			return self::$_aInstances[$sId];
		}
		return new dbc_Database_MySQL_Director($oConfig_, $oConfigSlave_);
	}	

	/**
	 * Checks to see if a dbc_Database_MySQL_Director instance with the supplied configuration has
	 * already been created.
	 * @param dbc_Config_MySQL $oConfig_ Configuration object
	 * @param dbc_Config_MySQL $oConfigSlave_ Optional slave configuration object
	 * @return Boolean Returns TRUE if a dbc_Database_MySQL_Director instance has already been made with the same
	 * config, FALSE if not.
	 */
	static public function hasInstance(dbc_Config_MySQL $oConfig_, dbc_Config_MySQL $oConfigSlave_ = NULL) {
		$sId = (string) $oConfig_->id();
		if (! is_null($oConfigSlave_)) $sId .= '-' . (string) $oConfigSlave_->id();
		return isset(self::$_aInstances[$sId]);
	}
}
?>