<?php

namespace April\Project\Cache;
use April\Project\Logger;
use April\Project\Config;

class MemCache {
	/**#@+
	 * @ignore
	 * @internal
	 * This class should not be instantiated directly by the implementor. The recommended way is through the use of a
	 * dbc_Cache instance.
	 */
	static private $_aInstances = array();
	private $_oMemCached = NULL;
	private $_sPrefix = '';
	/**#@-*/

	/**
	 * @ignore
	 * @internal
	 * This function is private, which means that it is impossible to instantiate this class directly. Instead the
	 * programmer is forced to use the getInstance method.
	 */
	private function __construct(Config\MemCache $oConfig_) {
		$this->_oMemCached = new \Memcached();
		$aHosts = $oConfig_->get('hosts');
		foreach($aHosts as $i => $aHost) $aHosts[$i] = array_values($aHost);
		$this->_oMemCached->addServers($aHosts);
		self::$_aInstances[$oConfig_->id()] = & $this;
		$this->_sPrefix = $oConfig_->get( 'prefix' );
		if (! is_string( $this->_sPrefix ) ) $this->_sPrefix = '';
	}

	/**
	 * YYY: Todo Martin
	 */
	public function setOption($iOption, $mValue) {
		return $this->_oMemCached->setOption($iOption, $mValue);
	}

	/**
	 * @ignore
	 * @internal
	 * This function returns the data identified by the provided key. If no such key is found it will return NULL. This
	 * allows for actual FALSE values to be stored in the cache. In case of a connection failure this function will throw
	 * an exception which causes the parent dbc_Cache object to try a failover cacher object.
	 */
	public function get($sKey_) {
		try {
			$mResult = @$this->_oMemCached->get($this->_sPrefix . (string) $sKey_);
			$iResultCode = $this->_oMemCached->getResultCode();
		} catch (\Exception $e) {
			return NULL;
		}
		switch($iResultCode) {
			case \Memcached::RES_NOTSTORED:
			case \Memcached::RES_NOTFOUND:
				return NULL;
			case \Memcached::RES_SUCCESS:
				return $mResult;
			default:
				Logger::warn('memcache connection not ready');
				throw new \Exception('memcache connection not ready');
		}
	}

	/**
	 * @ignore
	 * @internal
	 * This function stores the supplied data with the supplied key, optionally allowing for an expiration timeout. The
	 * function returns TRUE when the data was successfully stored, or FALSE in case of a connection failure of some kind.
	 */
	public function set($sKey_, $mData_, $iTTL = 0) {
		Logger::debug("set in memcache: {$this->_sPrefix}{$sKey_} ({$iTTL})");
		//if ( memory_get_usage(true)      > ((30*1024) * 1024) ) mail('dev@linuks.nl', "MEMCACHE VERY LARGE MEMUSAGE DETECTED!", memory_get_usage(true)."\n\n".var_export($sKey_,1)."\n\n".var_export($_SERVER,1)."\n\n".var_export($_REQUEST,1));
		//if ( strlen( serialize($mData_) ) > ((2*1024) * 1024) ) mail('dev@linuks.nl', "MEMCACHE VERY LARGE VALUE DETECTED!", strlen( serialize($mData_) )."\n\n".var_export($sKey_,1)."\n\n".var_export($_SERVER,1)."\n\n".var_export($_REQUEST,1));
		$bResult = $this->_oMemCached->set($this->_sPrefix . (string) $sKey_, $mData_, $iTTL);
		if ($bResult == false) Logger::warn('memcache connection not ready');
		return $bResult;
	}

	/**
	 * @ignore
	 * @internal
	 * This function stores the supplied data with the supplied key. Add() is similar to set() except that the operation
	 * will fail if the key already exists. Returns TRUE on success, NULL when key exists or FALSE on failure (error).
	 */
	public function add($sKey_, $mData_, $iTTL = 0) {
		Logger::debug("add in memcache: {$sKey_} ({$iTTL})");
		$bResult = $this->_oMemCached->add($this->_sPrefix . (string) $sKey_, $mData_, $iTTL);
		switch ($this->_oMemCached->getResultCode()) {
			case \Memcached::RES_SUCCESS:
				return true;
			case \Memcached::RES_NOTSTORED:
				return NULL;
			default:
				Logger::warn('memcache connection failure '.$this->_oMemCached->getResultCode());
				return false;
		}
	}

	/**
	 * @ignore
	 * @internal
	 * As fas as I know the only way to determine wether a variable is present in the memcache is to fetch it. Quite
	 * ugly, but it will do for now :)
	 */
	public function contains($sKey_) {
		try {
			$sResult = @$this->_oMemCached->get($this->_sPrefix . (string) $sKey_);
			$iResultCode = $this->_oMemCached->getResultCode();
		} catch (\Exception $e) {
			return false;
		}
		switch($iResultCode) {
			case \Memcached::RES_SUCCESS:
				return true;
			case \Memcached::RES_NOTSTORED:
			case \Memcached::RES_NOTFOUND:
				return false;
			default:
				Logger::warn('memcache connection not ready');
				throw new \Exception('memcache connection not ready');
		}		
	}

	/**
	 * @ignore
	 * @internal
	 * This function removes the key from the memcache. This function returns TRUE wether the key could be found or not.
	 * It will return FALSE if the connection to the memcache server is not ready or usable.
	 */
	public function remove($sKey_) {
		Logger::debug('remove in memcache: '.$sKey_);

		$this->_oMemCached->delete($this->_sPrefix . (string) $sKey_);
		$iResultCode = $this->_oMemCached->getResultCode();
		switch($iResultCode) {
			case \Memcached::RES_NOTSTORED:
			case \Memcached::RES_NOTFOUND:
			case \Memcached::RES_SUCCESS:
				return true;
			default:
				return false;
		}		
	}

	/**
	 * @ignore
	 * @internal
	 * This function returns a dbc_Cache_MemCache instance with the specified settings. If one of those instances was
	 * previously created it will return that one.
	 */
	static public function getInstance(Config\MemCache $oConfig_) {
		if (self::hasInstance($oConfig_)) return self::$_aInstances[$oConfig_->id()];
		return new self($oConfig_);
	}	

	/**
	 * @ignore
	 * @internal
	 * This function returns TRUE of FALSE depending on wether a Cache_MemCache instance already exists with the
	 * specified settings.
	 */
	static public function hasInstance(Config\MemCache $oConfig_) {
		return isset(self::$_aInstances[$oConfig_->id()]);
	}
}