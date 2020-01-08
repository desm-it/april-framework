<?php

namespace April\Project;

class Cache {
	/**#@+
	 * @ignore
	 * @internal
	 * These variables determine which cacher to use. The activeCacher variable will point to the first cacher that
	 * was probed successfully.
	 */
	private $_aCachers = array();
	private $_iActiveCacher = 0;
	/**#@-*/

	/**
	 * Creates a shiny new dbc_Cache object which allows for fallback in case a certain cacher is unavailable.
	 *
	 * Example:
	 *
	 * <code>
	 * $oConf = Config::loadConfig('1euro50');
	 * $oConfMemCache = $oConf->get('memcache', Config::TYPE_MEMCACHE);
	 * $oConfMySQL = $oConf->get('mysql', Config::TYPE_MYSQL);
	 * $oCache = new Cache($oConfMemCache, $oConfMySQL);
	 * </code>
	 *
	 * @param Object $oArg1_ dbc_Config derived object, currently suppored are {@link dbc_Config_MemCache} and
	 * {@link Config_MySQL}
	 * @param Object $oArg2_ dbc_Config derived object, currently suppored are {@link dbc_Config_MemCache} and
	 * {@link Config_MySQL}
	 * @param Object $oArgN_ dbc_Config derived object, currently suppored are {@link dbc_Config_MemCache} and
	 * {@link Config_MySQL}
	 * @return Cache Returns a new dbc_Cache object which has its cachers enabled using the
	 * supplied configurations.
	 *
	 * @internal
	 * Using the getInstance method instead of directly creating a new instance allows for reusage of a cacher with
	 * exactly the same settings.
	 */
	public function __construct($oArg1_ /*, $oArg2 ... $oArgN */) {
		$aArgs_ = func_get_args();
		foreach($aArgs_ as $sArg) {
			if ($sArg instanceof Config\MemCache) {
				$this->_aCachers[] = Cache\MemCache::getInstance($sArg);
			} else if ($sArg instanceof Config\MySQL) {
				$this->_aCachers[] = Cache\MySQL::getInstance($sArg);
			} else if ($sArg instanceof Config\ApcCache) {
				$this->_aCachers[] = Cache\ApcCache::getInstance($sArg);
			} else if ($sArg instanceof Config\XCache) {
				$this->_aCachers[] = Cache\XCache::getInstance($sArg);
			} else if ($sArg !== NULL && $sArg !== false) throw new \Exception('invalid cache configuration supplied');
		}
		if (count($this->_aCachers) == 0) throw new \Exception('no valid caches provided');
	}

	public function getActiveCacher() {
		return $this->_iActiveCacher;
	}
	
	/**
	 * Stores data in the currently active cache. Note that this function doesn't guarantee that an item is available
	 * on the server, it might have been removed to make place for other items.
	 *
	 * Example:
	 *
	 * <code>
	 * $aData = array(1 => 'red', 2 => 'green');
	 * $oCache->set('colors', $aData, 3600); // stores the data for one hour
	 * $oCache->set('colors', 'green', 3600);
	 * echo $oCache->get('colors');
	 * </code>
	 *
	 * Result:
	 *
	 * <pre>
	 * green
	 * </pre>
	 *
	 * @param String $sKey_ The name of the key which can be used to fetch the data later.
	 * @param Mixed $mData_ The data to store, can be any type of serializeable data (see php's serialize function to
	 * determine if a data type is storable: {@link http://nl.php.net/manual/en/function.serialize.php}).
	 * @param Integer $iTTL_ The number of seconds before the data should be discarded.
	 * @return Mixed Returns $mData_.
	 *
	 * @internal
	 * This function tries to set the data one cacher at a time. A cacher should always be able to store the variable,
	 * therefore it should always return true. If a false is encountered we're going to use the next failover cacher if
	 * one is available.
	 */
	public function set($sKey_, $mData_, $iTTL_ = 0) {
		while (
			isset($this->_aCachers[$this->_iActiveCacher]) &&
			$this->_aCachers[$this->_iActiveCacher]->set($sKey_, $mData_, $iTTL_) === false
		) $this->_iActiveCacher++;
		return isset($this->_aCachers[$this->_iActiveCacher]);
	}
	
	/**
	 * Stores data in the currently active cache. This function is similar to {@link dbc_Cache::set()} except that the
	 * operation will fail (return NULL) if the key already exists.
	 * This function should be used when obtaining a lock. 
	 * 
	 * <code>
	 * $oCache->remove('stront');
	 * $result1 = $oCache->add('stront', 'poep', 15);
	 * $result2 = $oCache->add('stront', 'poep', 15);
	 * var_dump($result1, $result2);
	 * </code>
	 * 
	 * Expect:
	 * 
	 * <pre>
	 * bool(true)
	 * NULL
	 * </pre>
	 * 
	 * @see dbc_Cache::set()
	 * @param String $sKey_ The name of the key which can be used to fetch the data later.
	 * @param Mixed $mData_ The data to store, can be any type of serializeable data (see php's serialize function to
	 * determine if a data type is storable: {@link http://nl.php.net/manual/en/function.serialize.php}).
	 * @param Integer $iTTL_ The number of seconds before the data should be discarded.
	 * @return TRUE on success, NULL if key exists or FALSE on failure 
	 */
	public function add($sKey_, $mData_, $iTTL_ = 0) {
		$bResult = false;
		while (
			isset($this->_aCachers[$this->_iActiveCacher]) &&
			( $bResult = $this->_aCachers[$this->_iActiveCacher]->add($sKey_, $mData_, $iTTL_) ) === false
		) $this->_iActiveCacher++;
		return $bResult;
	}	

	/**
	 * Returns previously stored data if an item with such key exists. It is possible to provide default data which is
	 * returned in case the key was not found or has expired.
	 *
	 * Example:
	 *
	 * <code>
	 * $aData = array(1 => 'red', 2 => 'green');
	 * $oCache->set('colors', $aData, 3600); // stores the data for one hour
	 * $aData1 = $oCache->get('colors');
	 * $aData2 = $oCache->get('befnichten', array(
	 * 	'Steven Bijl',
	 * 	'Jesper Niessen',
	 * 	'Wojca Dragojlovic'
	 * ));
	 * print_r ($aData1);
	 * print_r ($aData2);
	 * </code>
	 *
	 * Result:
	 *
	 * <pre>
	 * Array
	 * (
	 * 	[1] => red
	 * 	[2] => green
	 * )
	 *
	 * Array
	 * (
	 * 	[0] => Steven Bijl
	 * 	[1] => Jesper Niessen
	 * 	[2] => Wojca Dragojlovic
	 * )
	 * </pre>
	 *
	 * @param Mixed $sKey_ The name of the key to fetch from the cache.
	 * @param Mixed $mDefaultData_ Optional data that should be returned if the key was not found.
	 * @return Mixed Returns the data associated with the key or NULL on failure or if such key was not found. If default
	 * data was provided, this is returned instead of NULL.
	 *
	 * @internal
	 * This function tries to receive the required data from a cacher. If a certain cacher is unable to fetch the data it
	 * should throw an error. This is done because a boolean FALSE might otherwise trigger a fallback to a second cacher.
	 * And then there's the situation that a variable is not available but the connection is, in which case no fallback
	 * should occur either.
	 */
	public function get($sKey_, $mDefaultData_ = null) {
		if (! isset($this->_aCachers[$this->_iActiveCacher])) return $mDefaultData_;
		try {
			$mResult = $this->_aCachers[$this->_iActiveCacher]->get($sKey_);
			return is_null($mResult) ? $mDefaultData_ : $mResult;
		} catch(\Exception $e_) { // an exception is thrown by all cacher classes in case of a connection failure
			$this->_iActiveCacher++;
			if (! isset($this->_aCachers[$this->_iActiveCacher])) {
				return $mDefaultData_;
			} else return $this->get($sKey_, $mDefaultData_);
		}
	}

	/**
	 * This function can be used to check if a certain variable still exists in the cache or not.
	 *
	 * @param Mixed $sKey_ The name of the key to check if it's in the cache.
	 * @return Boolean Returns TRUE if the key exists or FALSE if not.
	 *
	 * @internal
	 * The cachers should trigger an error if they cannot determine wethere a key exists or not. This might happen in case
	 * of a connection failure. We can't rely on a false to be returned because that might trigger an unwanted fallback
	 * to a secondary cacher.
	 */
	public function contains($sKey_) {
		if (! isset($this->_aCachers[$this->_iActiveCacher])) return false;
		try {
			return $this->_aCachers[$this->_iActiveCacher]->contains($sKey_);
		} catch(\Exception $e_) { // an exception is thrown by all cacher classes in case of a connection failure
			$this->_iActiveCacher++;
			return isset($this->_aCachers[$this->_iActiveCacher]) && $this->_aCachers[$this->_iActiveCacher]->contains($sKey_);
		}
	}

	/**
	 * Alias for {@link dbc_Cache::contains()}
	 *
	 * @param Mixed $sKey_ The name of the key to check if it's in the cache.
	 * @return Boolean Returns TRUE if the key exists or FALSE if not.
	 */
	public function has($sKey_) { return $this->contains($sKey_); }

	/**
	 * Removes the data associated with the provided key from the cache.
	 * @param String $sKey_ The name of the key that should be removed from the cache.
	 * @return Boolean Returns TRUE on success or FALSE on failure.
	 *
	 * @internal
	 * This function tries to remove the data one cacher at a time. A cacher should always be able to remove the data. It
	 * should also return true if the data didn't even exist in the cache. Then when a FALSE is encountered it has to be a
	 * connection failure and the next failover cacher is tried.
	 */
	public function remove($sKey_) {
		if (! isset($this->_aCachers[$this->_iActiveCacher])) return false;
		while (
			isset($this->_aCachers[$this->_iActiveCacher]) &&
			$this->_aCachers[$this->_iActiveCacher]->remove($sKey_) === false
		) $this->_iActiveCacher++;
		return isset($this->_aCachers[$this->_iActiveCacher]);
	}

	public function setOption($iOption, $mValue) {
		if (! isset ( $this->_aCachers [$this->_iActiveCacher] ))
			return false;
		try {
			return $this->_aCachers [$this->_iActiveCacher]->setOption ( $iOption, $mValue );
		} catch ( \Exception $e_ ) { // an exception is thrown by all cacher classes
		                         // in case of a connection failure
			$this->_iActiveCacher ++;
			return isset ( $this->_aCachers [$this->_iActiveCacher] ) && $this->_aCachers [$this->_iActiveCacher]->setOption ( $iOption, $mValue );
		}
	}
	
	/**
	 * 
	 * @param String $sLockname
	 * @param Integer $iTTL
	 * @param Integer $iTryCounter
	 * @param Integer $iTryTimeout
	 * @return Boolean TRUE on success or FALSE on failure.
	 */
	public function getLock($sLockname, $iTTL=10, $iTryCounter=10, $iTryTimeout=200000) {
		while (!($bAddResult = $this->add("_lock{$sLockname}", "locked", $iTTL)) && $iTryCounter>0) {
			$iTryCounter--;
			usleep($iTryTimeout);
		}
		return $bAddResult;
	}
	
	/**
	 * 
	 * @param String $sLockname
	 */
	public function releaseLock($sLockname) {
		$this->remove("_lock{$sLockname}");
	}
	
}
?>