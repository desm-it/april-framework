<?php

namespace April\Project\Config;
use April\Project as Project;

class MySql extends Project\Config {
	/**
	 * Private static identifier counter
	 * @var $_counter
	 */
	private static $_counter = 0;

	/**
	 * Private identifier
	 * @var $_id
	 */
	private $_id;

	public function __construct($sUsername_, $sPassword_, $sDatabase_, $aHosts_, $sCharSet='utf8') {
		parent::__construct();
		$this->_id = self::$_counter++;

		# Let's copy the input parameters to the settings array here. The parent dbc_Config object will always
		# provide an array with hosts.
		$this->_aConfig['username'] = $sUsername_;
		$this->_aConfig['password'] = $sPassword_;
		$this->_aConfig['database'] = $sDatabase_;
		$this->_aConfig['charset'] = $sCharSet;
		
		if (
			! is_array($aHosts_) ||
			! isset($aHosts_['master']) // the master host should always be present, can't continue if missing
		) {
			throw new \Exception('no master host variable present');
		} else $this->_aConfig['host'] = $aHosts_;
	}

	/**
	 * Returns the unique id for a database config instance
	 * @return integer Returns the unique id for the config instance
	 */
	public function id() { return $this->_id; }

	/**
	 * Can be used to determine wether a Config MYSQL configuration contains information about a slave connection.
	 * @return boolean Returns TRUE if slave connection settings are present or FALSE if not.
	 */
	public function hasSlave() {
		return isset($this->_aConfig['host']['slave']);
	}
}
?>