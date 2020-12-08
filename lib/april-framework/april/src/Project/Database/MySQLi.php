<?php
/**
 * DBCorpLib
 *
 * @author DBCorp
 * @package DBCorpLib
 * @subpackage Database
 */

/**
 * A single MySQLi connection class.
 *
 * Example:
 *
 * <code>
 * $oDb = dbc_Database_MySQLi::getInstance($oDbConf, 'slave');
 * echo $oDb->getConnection();
 * </code>
 *
 * Result:
 *
 * <pre>
 * Resource #1
 * </pre>
 *
 * @package DBCorpLib
 * @subpackage Database
 */
class dbc_Database_MySQLi extends dbc_Database_Queryable {
	/**#@+
	 * @ignore
	 * @internal
	 */
	private $_rConnection = false;
	private $_aOptions;
	private static $_aQueries = array();
	static private $_aInstances = array();
	private $_bDaemonMode = false; // !!
	/**#@-*/

	/**
	 * @ignore
	 * @internal
	 * This function is private, which means that it is impossible to instantiate this class directly. Instead the
	 * programmer is forced to use the getInstance method.
	 */
	private function __construct(dbc_Config_MySQL $oConfig_, $sType_ = 'master') {
		$aHosts = $oConfig_->get('host');

		if (! isset($aHosts[$sType_])) 
			throw new Exception("host type '{$sType_}' not found");

		$this->_aOptions['host'] = $aHosts[$sType_];
		$this->_aOptions['username'] = $oConfig_->get('username');
		$this->_aOptions['password'] = $oConfig_->get('password');
		$this->_aOptions['database'] = $oConfig_->get('database');
		$this->_aOptions['charset'] = strtolower( $oConfig_->get('charset') ? $oConfig_->get('charset') : 'utf8' );

		self::$_aInstances[$oConfig_->id()][$sType_] = & $this;
	}

	public function setDaemonMode( $bOn_ = true ) {
		$this->_bDaemonMode = $bOn_;
	}

	/**
	 * Executes a single MySQL query. This method accepts sprintf style parameters which are automatically escaped. The
	 * most commonly used sprintf type specifiers are:
	 *
	 * <ul>
	 * <li><b>%s</b> - the argument is treated as and presented as a string,</li>
	 * <li><b>%d</b> - the argument is treated as an integer, and presented as a (signed) decimal number,</li>
	 * <li><b>%f</b> - the argument is treated as a float, and presented as a floating-point number.</li>
	 * </ul>
	 *
	 * For more information on <b>sprintf</b> see the PHP documentation:
	 * {@link http://www.php.net/manual/function.sprintf.php}
	 * 
	 * Example:
	 *
	 * <code>
	 * $oDb = dbc_Database_MySQLi::getInstance($oDbConf, 'master');
	 * $oResult = $oDb->executeQuery("
	 * 	SELECT *
	 * 	FROM dikkenegerin
	 * 	WHERE categorie='%s'
	 * 	AND dikkenegerin_id=%d
	 * ", "hack'attempt", 4);
	 * $oResult->getQuery();
	 * </code>
	 *
	 * Result:
	 *
	 * <pre>
	 * 	SELECT *
	 * 	FROM dikkenegerin
	 * 	WHERE categorie='hack\'attempt'
	 * 	AND dikkenegerin_id=4
	 * </pre>
	 *
	 * @param String $sQuery_ A single MySQL query. The query string should not end with a semicolon.
	 * @param Mixed $mArg1_ Query parameter, either a string or an integer.
	 * @param Mixed $mArg2_ Query parameter, either a string or an integer.
	 * @param Mixed $mArgN_ Query parameter, either a string or an integer.
	 * @return dbc_Database_MySQL_Result Returns a Database_MySQL_Result instance, or FALSE if the query was rejected (err
	 * no, it throws an exception if the query fails).
	 * by the MySQL connection.
	 */
	public function executeQuery($sQuery_ /*, $mArg1, $mArg2 ... $mArgN */) {
		if (
			! $this->isConnected() &&
			! $this->connect()
		) return false;

		if (func_num_args() > 1) {
			# All input parameters are escaped and then added to the query using sprintf. This means the
			# query should be prepared with %s and/or %d placeholders.
			$mArgs_ = func_get_args();
			for ($i = 1; $i < count($mArgs_); $i++) {
				$mArgs_[$i] = $this->_rConnection->real_escape_string($mArgs_[$i]);
			}
			$sQuery_ = call_user_func_array('sprintf', $mArgs_);
		}
		self::$_aQueries[] = $sQuery_;
		//$this->ClearRecordset();
		dbc_Logger::debug("MySQL query executed: " . htmlentities($sQuery_));
		return new dbc_Database_MySQLi_Result($this, $sQuery_, $this->_rConnection->query($sQuery_));
	}

	function ClearRecordset() {
	    while($this->_rConnection->next_result()){
	      if($l_result = $this->_rConnection->store_result()){
	              $l_result->free();
	      }
	    }
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
		if( $oResult = $this->executeQuery( $sQuery_) ) {
			return $oResult->next( $bAssoc_ );
		} else {
			return false;
		}
	}	

	/**
	 * @ignore
	 * @internal Replaced by executeQueryAndGetFirstRecord
	 */
	public function executeOneQueryAndReturnArray( $sQuery_, $bAssoc_ = true) { return $this->executeQueryAndGetFirstRecord( $sQuery_, $bAssoc_ ); }

	/**
	 * Executes a single MySQL query and returns the first column value from the first record of the resultset. Note that the
	 * query will be sent to the MySQL server unmodified, which means that the calling process should take care of escaping
	 * any user input used in the query by using {@link dbc_Database_MySQL::escape()}.
	 * @param String $sQuery_ A single MySQL query. The query string should not end with a semicolon.
	 * @return Mixed Returns the value of the first column of the first record in the resultset.
	 */
	public function executeQueryAndGetFirstColumn( $sQuery_) {
		if( $a = $this->executeOneQueryAndReturnArray( $sQuery_, false ) ) {
			return $a[0];
		} else {
			return false;
		}
	}

	/**
	 * @ignore
	 * @internal Replaced by executeQueryAndGetFirstColumn
	 */
	public function executeOneQueryAndReturnValue( $sQuery_ ) { return $this->executeQueryAndGetFirstColumn( $sQuery_ ); }

	/**
	 * @ignore
	 * @internal This function should not be used by noobs, so it's not documented or listed in the wiki :-)
	 */
	public function executeUpdateOrInsert( $sTable_, $sKey_, $aFieldValues_, $sKeyField_ ='id' ) {
		if (
			! $this->isConnected() &&
			! $this->connect()
		) return false;

		$query='';
		foreach($aFieldValues_ as $key => $value) {
			if( $query!='' ) $query.=',';
			$query.="`$key`='".$this->_rConnection->real_escape_string($value)."'";
		}

		if( $sKey_ && $this->executeOneQueryAndReturnValue("SELECT `$sKeyField_` FROM `$sTable_` WHERE `$sKeyField_`='$sKey_'")) {
			// Update/overwrite existing record
			$query = "UPDATE `$sTable_` SET $q WHERE `$sKeyField_`='$sKey_'";
		} else {
			// Insert new record
			if( $sKey_ && !isset($aFieldValues_[$sKeyField_])) {
				$query = "`$sKeyField_`='".$this->_rConnection->real_escape_string($sKey_)."',".$query;
			}
			$query = "INSERT INTO `$sTable_` SET ".$query;
		}
		return $this->executeQuery($query);
	}
	
	/**
	 * Escapes special characters in a string or array of string for use in a SQL statement, taking into account
	 * the current character set.
	 * @param Mixed $mUnescaped_ The string or array of strings that need to be escaped.
	 * @return Mixed Returns the array with all strings escaped, a single escaped string, or FALSE on error.
	 */
	public function escape($mUnescaped_) {
		if (
			! $this->isConnected() &&
			! $this->connect()
		) return false;

		if (is_array($mUnescaped_)) {
			array_walk($mUnescaped_, array($this, '__do_escape'), $this->_rConnection);
		} else $this->__do_escape($mUnescaped_, NULL, $this->_rConnection);

		return $mUnescaped_;
	}

	/**
	 * @ignore
	 */
	private function __do_escape(& $sValue_, $mKey_, & $rConnection_) {
		$sValue_ = $this->_rConnection->real_escape_string($sValue_);
	}

	/**
	 * Escapes special characters in a string or array of string for use in a SQL statement, taking into account
	 * the current character set.
	 * @deprecated Use the preferred escape method instead. This function will be removed when err ... poekoe.
	 * @see dbc_Database_MySQL::escape()
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
		$aData = array('fields' => array(), 'keys' => array(), 'database' => $this->_aOptions['database'] );
		$oResult = $this->executeQuery("DESCRIBE `%s`", $sTable_);
		while (false !== ($aRow = $oResult->next())) {
			$aType = $this->__stripType($aRow['Type']);
			$aData['fields'][$aRow['Field']] = array_merge($aType, array(
				'null' => $aRow['Null'],
				'default' => $aRow['Default'],
				'extra' => $aRow['Extra'],
				'key' => $aRow['Key']
			) );
		}

		$oResult = $this->executeQuery("SHOW KEYS FROM `%s`", $sTable_);
		while (false !== ($aRow = $oResult->next())) {
			$aData['keys'][$aRow['Key_name']][] = $aRow['Column_name'];
		}		
		return $aData;
	}

	/**
	 * This function can be used to check if a certain table exists in the database.
	 * @param String $sTable_ The MySQL name of the table.
	 * @return Boolean Returns TRUE if the table exists or FALSE if not.
	 */
	public function hasTable($sTable_) {
		$oResult = $this->executeQuery("SHOW TABLES LIKE '%s'", $sTable_);
		return $oResult->num_rows() > 0;
	}

	/**
	 * @ignore
	 * @internal
	 * This function returns an array containing the properties of the provided raw field from MySQL.
	 */
	private function __stripType($sType_) {
		$aType = explode('(', $sType_);
		$aInfo = array('type' => $aType[0]);
		if (count($aType) > 1) {
			list($sProperty) = explode(')', $aType[1]);
			$aInfo['property'] = explode(',', $sProperty);
		}
		return $aInfo;
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
		$aTableInfo = $this->getTableInfo($sTable_);
		$aResult = array();
		if (is_array($aValues_)) foreach($aValues_ as $sKey => $sValue) {
			if (isset($aTableInfo['fields'][$sKey])) $aResult[$sKey] = $sValue;
		}
		return $aResult;
	}

	/**
	 * Opens a connection to the MySQL server using the supplied login credentials.
	 * @returns Boolean Returns TRUE if the MySQL connection has successfully been made, FALSE if not.
	 */
	public function connect() {
		$this->_rConnection = new mysqli(
			$this->_aOptions['host'], 
			$this->_aOptions['username'], 
			$this->_aOptions['password']
		);

		if ($this->_rConnection !== false) {
    		@$this->_rConnection->set_charset($this->_aOptions['charset']);
			dbc_Logger::debug("database chatset set to {$this->_aOptions['charset']}");
			if (@$this->_rConnection->select_db($this->_aOptions['database']) == false) {
				dbc_Logger::debug("database {$this->_aOptions['database']} does not exist");
				return false;
			} else return true;
		} else {
			dbc_Logger::debug("unable to connect to MySQL server at {$this->_aOptions['host']}");
			return false;
		}
	}

    /**
     * Disconnects from MySQL and frees reference.
     * @return void
     */
    public function disconnect() {
		// Close connection.
		if ($this->isConnected()) {
		    $this->_rConnection->close();
		}
		// Force removal and garbage collection of resources 
		unset($this->_rConnection);

		// Reinitialize variable
		$this->_rConnection = false;
    }

	/**
	 * This function can be used to select a different database using the same connection.
	 * @param String $sDatabase_ The name of the database to connect to.
	 * @return Boolean Returns TRUE if the database was selected successfully, FALSE if not.
	 */
	public function selectDatabase($sDatabase_) {
		// For a database change we have to have a valid connection.
		if (
			! $this->isConnected() &&
			! $this->connect()
		) return false;

		$bResult = @$this->_rConnection->select_db($sDatabase_);
		if ($bResult) $this->_aOptions['database'] = $sDatabase_;
		return $bResult;
	}

	/**
	 * Checks whether or not the connection to the server is working. If it has gone down, an automatic
	 * reconnection is attempted.
	 * @returns Boolean Returns TRUE if the connection to the server MySQL server is working, otherwise FALSE.
	 */
	public function isConnected() {
		if (
			$this->_rConnection == false ||
			!$this->_rConnection->ping()
		) return false;
		
		if ( $this->_bDaemonMode ) {
			$bResult = $this->_rConnection->query('SELECT 1');
			if ( FALSE === $bResult ) return false;
		}
		return true;
	}

	/**
	 * Returns the error number from the last MySQL function.
	 * @return Integer Returns the error number from the last MySQL function, or 0 (zero) if no error occurred.
	 */
	public function getErrorNo() {
		return @$this->_rConnection->errno();
	}

	/**
	 * Returns the error text from the last MySQL function.
	 * @return String Returns the error text from the last MySQL function, or '' (empty string) if no error occurred.
	 */
	public function getError() {
		return @$this->_rConnection->error;
	}

	/**
	 * Returns MySQL connection resource
	 * @return Resource MySQL connection resource
	 */
	public function getConnection() {
		return $this->_rConnection;
	}

	/**
	 * Returns an array with previously executed queries.
	 */
	static public function getQueries() {
		return self::$_aQueries;
	}

	/**
	 * Preferred method of receiving a {@link dbc_Database_MySQLi} instance.
	 * @return dbc_Database_MySQLi Returns a dbc_Database_MySQLi instance with the requested configuration and host type.
	 * @param dbc_Config_MySQL A MySQL config object
	 * @param String Optionally a host type to use, defaults to 'master'.
	 */
	static public function getInstance(dbc_Config_MySQL $oConfig_, $sType_ = 'master') {
		if (self::hasInstance($oConfig_, $sType_)) return self::$_aInstances[$oConfig_->id()][$sType_];
		return new dbc_Database_MySQLi($oConfig_, $sType_);
	}	

	/**
	 * Checks to see if a dbc_Database_MySQL instance with the supplied configuration and host type has
	 * already been created.
	 * @return Boolean Returns TRUE if a dbc_Database_MySQL instance has already been made, FALSE if not.
	 * @param dbc_Config_MySQL A MySQL config object
	 * @param String Optionally a host type to use, defaults to 'master'.
	 */
	static public function hasInstance(dbc_Config_MySQL $oConfig_, $sType_ = 'master') {
		return isset(self::$_aInstances[$oConfig_->id()][$sType_]);
	}
}
?>