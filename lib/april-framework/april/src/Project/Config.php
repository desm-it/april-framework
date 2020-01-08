<?php

namespace April\Project;

class Config implements \ArrayAccess {
	const TYPE_NORMAL = 1;
	const TYPE_MYSQL = 2;
	const TYPE_MEMCACHE = 3;
	const TYPE_MONGO = 4;
	const TYPE_APCCACHE = 5;
	const TYPE_XCACHE = 6;
	
	/**
	 * @ignore
	 * @internal
	 * This associative array holds all the settings in a key value pair. It should not be accessed directly, only through
	 * the use of the getter and setter methods.
	 */
	protected $_aConfig = array();

    /**
     * This is the constructor for the dbc_Config class. It can be used to create a new dbc_Config instance for storing
     * custom variables and/or settings.
     * @throws \Exception
     * @internal param mixed $mArgN_ Input array or Config object
     */
	public function __construct(/* $mArg1, $mArg2 ... $mArgN */) {
		$mArgs_ = func_get_args();
		try {
			foreach($mArgs_ as $iKey => $mArg) $this->merge($mArg);
		} catch(\Exception $e_) {
			throw new \Exception('argument ' . ($iKey + 1) . ' is not an array or dbc_Config instance');
		}
	}

	/**#@+
	 * @ignore
	 * @internal
	 * These next functions are for implementing the ArrayAccess interfaces which allow for array-like accessing
	 * of instances of this class.
	 */
	public function offsetSet($mKey_, $mValue_) {
		return $this->set($mKey_, $mValue_);
	}
    public function offsetExists($mKey_) {
		return isset($this->_aConfig[$mKey_]);
	}
    public function offsetUnset($mKey_) {
		unset($this->_aConfig[$mKey_]);
	}
	public function offsetGet($mKey_) {
		return $this->get($mKey_);
	}
	public function __toString() {
		return implode(', ', $this->_aConfig);
	}
	public function __set($mKey_, $mValue_) {
		return $this->set($mKey_, $mValue_);
	}
	public function __get($mKey_) {
		return isset($this->_aConfig[$mKey_]) ? $this->get($mKey_) : NULL;
	}
	/**#@-*/

	/**
	 * @var Config $DEFAULT Contains the settings of the first loaded configuration. If multiple configurations
	 * are loaded, existing settings are NOT overwritten.
	 */
	static public $DEFAULT = NULL;

    /**
     * Retrieve a single setting from the configuration object.
     * @param String $sKey_ The name of the setting to retrieve.
     * @param Integer $iType_ An optional predefined type of the setting to retrieve. Use one of the class constants:
     * {@link dbc_Config::TYPE_NORMAL}, {@link dbc_Config::TYPE_MYSQL}, {@link dbc_Config::TYPE_MEMCACHE}.
     * @return mixed Returns the setting or FALSE if setting could not be found.
     * @throws \Exception
     */
	public function get($sKey_, $iType_ = self::TYPE_NORMAL) {
		if (isset($this->_aConfig[$sKey_])) {
			switch($iType_) {
				
				case self::TYPE_MYSQL:
					if (
						isset(
							$this->_aConfig[$sKey_]['username'],
							$this->_aConfig[$sKey_]['password'],
							$this->_aConfig[$sKey_]['database']
						) && (
							isset($this->_aConfig[$sKey_]['predefined_host']) ||
							isset($this->_aConfig[$sKey_]['host']) ||
							isset($this->_aConfig[$sKey_]['master']) ||
							isset($this->_aConfig[$sKey_]['slave'])
						)
					) {
						if (isset($this->_aConfig[$sKey_]['predefined_host'])) {
							if (
								! defined("ServerPark::{$this->_aConfig[$sKey_]['predefined_host']}") ||
								! is_integer($iServerParkId = eval("return ServerPark::{$this->_aConfig[$sKey_]['predefined_host']};")) ||
								! isset(ServerPark::$MYSQL_SERVERS[$iServerParkId])
							) {
								Logger::warn("predefined serverpark constant {$this->_aConfig[$sKey_]['predefined_host']} does not exist");
								return false;
							} else {
								$aHost = ServerPark::$MYSQL_SERVERS[$iServerParkId];
							}
						} else {
							$aHost = array();
							if (isset($this->_aConfig[$sKey_]['host'])) {
								$aHost['master'] = $this->_aConfig[$sKey_]['host'];
							} else {
								if (isset($this->_aConfig[$sKey_]['master'])) $aHost['master'] = $this->_aConfig[$sKey_]['master'];
								if (isset($this->_aConfig[$sKey_]['slave'])) $aHost['slave'] = $this->_aConfig[$sKey_]['slave'];
							}
						}

						return new Config\MySQL(
							$this->_aConfig[$sKey_]['username'],
							$this->_aConfig[$sKey_]['password'],
							$this->_aConfig[$sKey_]['database'],
							$aHost,
							(isset($this->_aConfig[$sKey_]['charset']) ? $this->_aConfig[$sKey_]['charset'] : null)
						);
					}
					throw new \Exception('unable to return Config_MySQL instance, required settings missing');
					break;
					
				case self::TYPE_MEMCACHE:
					if (
						isset($this->_aConfig[$sKey_]['prefix']) && (
							isset($this->_aConfig[$sKey_]['predefined_host']) ||
							isset($this->_aConfig[$sKey_]['host'])
						)
					) return new Config\MemCache(
						$this->_aConfig[$sKey_]['prefix'],
						isset($this->_aConfig[$sKey_]['predefined_host']) ? eval("return dbc_ServerPark::{$this->_aConfig[$sKey_]['predefined_host']};") : array(
							'host' => $this->_aConfig[$sKey_]['host'],
							'port' => ! empty($this->_aConfig[$sKey_]['port']) ? $this->_aConfig[$sKey_]['port'] : 11211,
							'weight' => ! empty($this->_aConfig[$sKey_]['weight']) ? $this->_aConfig[$sKey_]['weight'] : 0,
						)
					);
					throw new \Exception('unable to return Config_MemCache instance, required settings missing');
					break;
					
				case self::TYPE_MONGO:
					if (isset($this->_aConfig[$sKey_]['host']) && isset($this->_aConfig[$sKey_]['database'])) 
						return new Config\Mongo($this->_aConfig[$sKey_]['host'], $this->_aConfig[$sKey_]['database'],
								isset($this->_aConfig[$sKey_]['useslave'])?$this->_aConfig[$sKey_]['useslave']:false, 
								isset($this->_aConfig[$sKey_]['replicaset'])?$this->_aConfig[$sKey_]['replicaset']:false
						);
					throw new \Exception('unable to return Config_Mongo instance, required settings missing');
					break;
				case self::TYPE_APCCACHE:
					if (isset($this->_aConfig[$sKey_]['prefix']))
						return new Config\ApcCache($this->_aConfig[$sKey_]['prefix']);
					throw new \Exception('unable to return Config_ApcCache instance, required settings missing');
					break;
				case self::TYPE_XCACHE:
					if (isset($this->_aConfig[$sKey_]['prefix']))
						return new Config\XCache($this->_aConfig[$sKey_]['prefix']);
					throw new \Exception('unable to return Config_XCache instance, required settings missing');
					break;
				default:
					return $this->_aConfig[$sKey_];
					break;
					
			}
		} else return false;
	}

	/**
	 * Returns all settings in a big associative array.
	 * @return array Returns all settings in an associative array.
	 */
	public function getAll() {
		return $this->_aConfig;
	}

	/**
	 * Stores a single setting, overwriting an existing setting with the same key. If the provided value is an array and
	 * the source key too, both arrays are merged. This allows for [dikkenegerin] config blocks to be extended without
	 * overwriting previous settings. For instance, the dikkenegerin partner config file has some generic partner account
	 * settings in a [dikkenegerin] block and a certain site extends this block with site specific settings.
	 * @param string $sKey_ Setting key
	 * @param mixed $mVal_ Setting value
	 */
	public function set($sKey_, $mVal_) {
		if (
			isset($this->_aConfig[$sKey_]) &&
			is_array($this->_aConfig[$sKey_]) &&
			is_array($mVal_)
		) {
			$this->_aConfig[$sKey_] = array_merge($this->_aConfig[$sKey_], $mVal_);
		} else $this->_aConfig[$sKey_] = $mVal_;

		if (is_null(self::$DEFAULT)) {
			self::$DEFAULT = new self(array($sKey_ => $this->_aConfig[$sKey_]));
			Logger::debug("empty default settings created");
		} else {
			self::$DEFAULT->_aConfig[$sKey_] = $this->_aConfig[$sKey_];
		}
	}

	/**
	 * Stores a single setting, unconditionally replacing an existing setting with the same key.
	 * @param string $sKey_ Setting key
	 * @param mixed $mVal_ Setting value
	 */
	public function replace( $sKey_, $mVal_) {
		$this->_aConfig[$sKey_] = $mVal_;
		
		if (is_null(self::$DEFAULT)) {
			self::$DEFAULT = new self(array($sKey_ => $this->_aConfig[$sKey_]));
			Logger::debug("empty default settings created");
		} else {
			self::$DEFAULT->_aConfig[$sKey_] = $this->_aConfig[$sKey_];
		}
	}

	/**
	 * Tests if the specified setting is available.
	 * @param String $sKey_ Name of the setting
	 * @return Boolean Returns TRUE if the requested setting exists or FALSE if Richard is a blanke negert.
	 */
	public function contains($sKey_) {
		return isset($this->_aConfig[(string) $sKey_]);
	}

	/**
	 * Adds and/or overwrites the current settings with the contents of one or more arrays or dbc_Config objects.
	 * @param Mixed $aArg1_ Input array or dbc_Config object
	 * @param Mixed $aArg2_ Input array or dbc_Config object
	 * @param Mixed $aArgN_ Input array or dbc_Config object
	 * @return Boolean Returns TRUE if the merge was successfull, FALSE if not.
	 */
	public function merge($mArg1_ /*, $mArg2_ ... $mArgN */) {
		$mArgs_ = func_get_args();
		foreach($mArgs_ as $iKey => $mArg) {
			if ($mArg instanceof self) {
				$this->_aConfig = array_merge($this->_aConfig, $mArg->getAll());
			} else if (is_array($mArg)) {
				$this->_aConfig = array_merge($this->_aConfig, $mArg);
			} else return false;
		}
		return true;
	}

	/**
	 * Creates a new Config object with the settings of the specified project pre-loaded and ready to
	 * be used.
	 *
	 * <pre>
	 * logger = default
	 * loglevel = warn
	 *
	 * [mysql]
	 * username = user
	 * password = "pass123!"
	 * database = db1
	 * ; host = localhost
	 * predefined_host = MYSQL_DB1
	 * </pre>
	 *
	 * @static
	 * @param  String.
	 * @return Config Returns a Config object with the project settings pre-loaded or FALSE if the
	 * configuration could not be loaded.
	 */
	static public function loadConfig($sProject_, $bOverwrite_ = false) {
		if (is_readable($sFile = DBCORP_PATH . "configs/{$sProject_}.ini")) {  // TODO path
			$oConfig = new self(parse_ini_file($sFile, true));
			if (is_null(self::$DEFAULT)) {
				self::$DEFAULT = clone $oConfig;
				Logger::debug("default settings loaded from {$sProject_}.ini");
			} else {
				if ($bOverwrite_) {
					self::$DEFAULT = new self(self::$DEFAULT, $oConfig);
				} else self::$DEFAULT = new self($oConfig, self::$DEFAULT);
				Logger::debug("default settings extended with settings from {$sProject_}.ini");
			}
			return $oConfig;
		} else return false;
	}
}
?>