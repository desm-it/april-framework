<?php

namespace April\Project\Config;
use April\Project as Project;

class Mongo extends Project\Config {
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

	public function __construct($sHost_, $sDatabase_, $bUseSlave_=false, $bReplicaset_=false) {
		parent::__construct();
		$this->_id = self::$_counter++;
		$this->_aConfig['host'] = $sHost_;
		$this->_aConfig['useslave'] = $bUseSlave_;
		$this->_aConfig['database'] = $sDatabase_;
		$this->_aConfig['replicaset'] = $bReplicaset_;
	}

	/**
	 * Returns the unique id for a database config instance
	 * @return integer Returns the unique id for the config instance
	 */
	public function id() { return $this->_id; }
}
?>
