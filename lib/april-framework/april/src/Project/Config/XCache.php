<?php

namespace April\Project\Config;
use April\Project as Project;

class XCache extends Project\Config {
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

	public function __construct($sPrefix_) {
		parent::__construct();
		$this->_id = self::$_counter++;
		
		# Let's copy the input parameter to the settings array here.
		$this->_aConfig['prefix'] = $sPrefix_;
	}

	/**
	 * Returns the unique id for a database config instance
	 * @return integer Returns the unique id for the config instance
	 */
	public function id() { return $this->_id; }
}