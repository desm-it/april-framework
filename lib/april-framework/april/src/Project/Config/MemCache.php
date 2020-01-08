<?php

namespace April\Project\Config;
use April\Project as Project;

class MemCache extends Project\Config {
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

	public function __construct($sPrefix_, $mHost_) {
		parent::__construct();
		$this->_id = self::$_counter++;
		
		# Let's copy the input parameter to the settings array here.
		$this->_aConfig['prefix'] = $sPrefix_;

		if (
			is_integer($mHost_) &&
			isset(Project\ServerPark::$MC_SERVERS[$mHost_])
		) {
			# We've received an integer as host, which indicates a host from the serverpark config.
			$this->_aConfig['hosts'] = Project\ServerPark::$MC_SERVERS[$mHost_];
		} else if (is_array($mHost_)) {
			# A single host array is given.
			$this->_aConfig['hosts'] = array($mHost_);
		} else {
			# A single host address is given.
			$this->_aConfig['hosts'] = array(array($mHost_));
		}
	}

	/**
	 * Returns the unique id for a database config instance
	 * @return integer Returns the unique id for the config instance
	 */
	public function id() { return $this->_id; }
}
?>