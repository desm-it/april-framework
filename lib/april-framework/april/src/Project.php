<?php

namespace April;

use April\Project\Config;

final class Project {
	/**
	 * @ignore
	 * @internal
	 * Defining the constructor private prevents this class form being instantiated. All methods should be used in a
	 * static manner.
	 */
	private function __construct() {}

	static public function loadConfig($sProject_, $bOverwrite_ = false) {
		return Config::loadConfig($sProject_, $bOverwrite_);
	}


	static public function getMySQLi($sKey_ = 'mysqli', Config $oSettings_ = NULL) {
		if (is_null($oSettings_)) {
			if (is_null(Config::$DEFAULT)) throw new \Exception('no configuration loaded');
			$oSettings_ = & Config::$DEFAULT;
		}
		
		if (! $oSettings_->contains($sKey_)) throw new \Exception("settings {$sKey_} not found");
		$oSettingsMySQL = $oSettings_->get($sKey_, Config::TYPE_MYSQL);
		// We're going to return a Database_MySQLi *OR* a Database_MySQLi_Director object, depending on wether both
		// master and slave variables are present.
		if ($oSettingsMySQL->hasSlave()) {
			return Project\Database\MySQLi\Director::getInstance($oSettingsMySQL);
		} else {
			return Project\Database\MySQLi::getInstance($oSettingsMySQL);
		}
	}


}
?>