<?php
/**
 * DBCorpLib
 *
 * @author DBCorp
 * @package DBCorpLib
 * @subpackage Database
 */

/**
 * A MySQL ResultSet class which provides functions to iterate over the records in the resultset.
 *
 * Example:
 *
 * <code>
 * $oDb = dbc_Database_MySQL::getInstance($oDbConf, 'slave');
 * $oResult = $oDb->executeQuery("
 * 	SELECT *
 * 	FROM noobs
 * 	LIMIT 2
 * ");
 * $aNoobs = $oResult->all();
 * print_r ($aNoobs);
 * </code>
 *
 * Result:
 *
 * <pre>
 * Array
 * (
 * 	[0] => Bob Kersten
 * 	[1] => Rolf Siebers
 * )
 * </pre>
 *
 * Better example:
 *
 * <code>
 * $oDb = dbc_Database_MySQL::getInstance($oDbConf, 'slave');
 * $oResult = $oDb->executeQuery("
 * 	SELECT *
 * 	FROM noobs
 * 	LIMIT 2
 * ");
 * $aNoob = $oResult->first();
 * while ($aNoob !== false) {
 * 	echo $aNoob['naam'];
 * 	$aNoob = $oResult->next();
 * }
 * </code>
 *
 * Result:
 *
 * <pre>
 * Bob Kersten
 * Rolf Siebers
 * </pre>
 *
 * @package DBCorpLib
 * @subpackage Database
 */
class dbc_Database_MySQLi_Result {
	/**#@+
	 * @ignore
	 * @internal
	 */
	private $_rResult;
	private $_sQuery;
	private $_oDatabase;
	private $_bFirst = true;
	/**#@-*/

	/**
	 * @ignore
	 */
	public function __construct(& $oDatabase_, $sQuery_, $rResult_ = false) {
		if ($rResult_ === false) throw new Exception($oDatabase_->getError());

		$this->_oDatabase = & $oDatabase_;
		$this->_sQuery = $sQuery_;
		$this->_rResult = $rResult_;
	}

	/**
	 * Rewind the resultset. The next call to {@link dbc_Database_MySQL_Result::next()} will return the same record as a
	 * call to  {@link dbc_Database_MySQL_Result::first()}.
	 * @return Void
	 */
	public function rewind() {
		$this->_rResult->data_seek(0);
	}

	/**
	 * Returns the next row in the resultset.
	 * @param Boolean $bAssoc_ Set to TRUE to receive an associative array, FALSE to get a numerical array.
	 * @return Array Returns an array of strings that corresponds to the fetched row, or FALSE if there are no more rows.
	 */
	public function next($bAssoc_ = true) {
		$this->_bFirst = false;
		$oRs = $this->_rResult->fetch_array(($bAssoc_) ? MYSQLI_ASSOC : MYSQLI_NUM);
		if(empty($oRs)) {
			return false;
		}else{
			return $oRs;
		}
		
	}
	
	/**
	 * Returns all the records in the recordset as one multidimensional array, rewinding the interal pointer
	 * if necessary.
	 * @param $bAssoc_ Boolean Set to TRUE to receive an associative array, FALSE to get a numerical array.
	 * @return Array Returns an array of record arrays that corresponds to the resultset, or an empty array if there
	 * are no rows in the resultset.
	 */
	public function all($bAssoc_ = true) {
        $aRecords = array();
		while ($aRow = $this->_rResult->fetch_array(($bAssoc_) ? MYSQLI_ASSOC : MYSQLI_NUM)) {
			$aRecords[] = $aRow;
		}

		return $aRecords;
	}
	
	/**
	 * Returns all the records in the recordset as one multidimensional array, rewinding the interal pointer
	 * if necessary.
	 * @param Boolean Set to TRUE to receive an associative array, FALSE to get a numerical array.
	 * @return Array Returns an array of record arrays that corresponds to the resultset, or an empty array if there
	 * are no rows in the resultset.
	 * @deprecated Use the preferred {@link dbc_Database_MySQL_Result::all()} method instead. This function will
	 * probably never be removed, but just in case.
	 */
	public function getAllRecords($bAssoc_ = true) { return $this->all($bAssoc_); }

	/**
	 * Returns the values from a specific field from all the records in the recordset, rewinding the interal pointer
	 * if necessary.
	 * @param String The field name
	 * @return Array Returns a numerical array containing all the values of the specified field
	 * @deprecated Use the preferred {@link dbc_Database_MySQL_Result::allByField()} method instead. This function will
	 * be removed in the future, when Fred is able to properly escape $_GET variables. (YYY: Jah zeit ie!)
	 */
	function getAllRecordsByField($sFieldName_) { return $this->allByField($sFieldName_); }
	
	/**
	 * Returns the values from a specific field from all the records in the recordset, rewinding the interal pointer
	 * if necessary.
	 *
	 * Example:
	 *
	 * <code>
	 * $oDb = dbc_Database_MySQL::getInstance($oDbConf, 'slave');
	 * $oResult = $oDb->executeQuery("
	 * 	SELECT naam, leeftijd, geslacht
	 * 	FROM escorts
	 * 	ORDER BY leeftijd
	 * 	LIMIT 4
	 * ");
	 * $aEscorts = $oResult->allByField('leeftijd');
	 * print_r ($aEscorts);
	 * </code>
	 *
	 * Result:
	 *
	 * <pre>
	 * Array
	 * (
	 * 	[0] => 12
	 * 	[1] => 25
	 * 	[2] => 32
	 * 	[3] => 71
	 * )
	 * </pre>
	 *
	 * @param String The field name
	 * @return Array Returns a numerical array containing all the values of the specified field
	 */
	function allByField($sFieldName_) {
		$aRecords = array();
		while ($aRecord = $this->next()) $aRecords[] = $aRecord[$sFieldName_];
		return $aRecords;
	}
	
	/**
	 * Returns all the records in the recordset as one multidimensional array, rewinding the interal pointer
	 * if necessary.
	 * @param $mIndexField Mixed (string or int) MySQL resultset identifier (when assoc use string else int).
	 * @param $bAssoc_ Boolean Set to TRUE to receive an associative array, FALSE to get a numerical array.
	 * @return Array Returns an array of record arrays that corresponds to the resultset, or an empty array if there
	 * are no rows in the resultset. As array key, the value of the given fieldname is used.
	 */
	public function allUseIndex($mIndexField, $bAssoc_ = true) {
		$aRecords = array();
		while(false !== ($aRecord = $this->next($bAssoc_))) {
			$aRecords[$aRecord[$mIndexField]] = $aRecord;
		}
		return $aRecords;
	}
	
	/**
	 * Returns the first row in the resultset. If the internal pointer doesn't point to the first record, the
	 * pointer is rewinded.
	 * @param Boolean $bAssoc_ Set to TRUE to receive an associative array, FALSE to get a numerical array.
	 * @return Array Returns an array of strings that corresponds to the fetched row, or FALSE if there are no more rows.
	 */
	public function first($bAssoc_ = true) {
		if (! $this->_bFirst) $this->_rResult->data_seek(0);
		return $this->next($bAssoc_);
	}
	
	/**
	 * Returns the only record in the recordset as one dimensional array
	 * @param $bAssoc_ Boolean Set to TRUE to receive an associative array, FALSE to get a numerical array.
	 * @return Array Returns an array the record that corresponds to the resultset, or an empty array if there
	 * are no rows in the resultset.
	 */
	/*
	public function getOnlyRecord($bAssoc_ = true) {
		if($this->_rResult===true || $this->_rResult===false){
			return true;
		}else{
			return $this->_rResult->fetch_array(($bAssoc_) ? MYSQLI_ASSOC : MYSQLI_NUM);
		}
	}
	*/
	
	/**
	 * Retrieves the number of rows from a result set.
	 * @return Integer The number of rows in a result set on success or FALSE on failure.
	 */
	public function numRows() {
		if($this->_rResult===true || $this->_rResult===false){
			return true;
		}else{
			return $this->_rResult->num_rows;
		}
	
	}

	/**
	 * Get the number of affected rows by the last INSERT, UPDATE, REPLACE or DELETE query.
	 * @return Integer The number of rows in a result set on success or FALSE on failure.
	 * @todo Make this function connection independant (ie.: always return THIS queries' affected rows, even if the
	 * connection has performed another query afterwards.
	 */
	public function affectedRows() {
		return $this->_oDatabase->getConnection()->affected_rows;
	}
		
	/**
	 * Retrieves the last inserted id generated for an AUTO_INCREMENT column by the previous query (usually INSERT).
	 * @return Integer The id generated for an AUTO_INCREMENT column by the previous query on success, 0 if the
	 * previous query does not generate an AUTO_INCREMENT value, or FALSE if no MySQL connection was established.
	 * @todo Make this function connection independant (ie.: always return THIS queries' inserted id, even if the
	 * connection has performed another query afterwards.
	 */
	function getInsertId(){
		return $this->_oDatabase->getConnection()->insert_id;
	}

	/**
	 * Returns the query that generated this resultset.
	 * @return String The query that generated this resultset.
	 */
	function getQuery() {
		return $this->_sQuery;
	}

	/**
	 * @ignore
	 */
	function getDatabaseInstance() {
		return $this->_oDatabase;
	}

	function freeResult() {
		if($this->_rResult===true || $this->_rResult===false){
			return true;
		}else{
			return $this->_rResult->free_result;
		}
	}
}
?>