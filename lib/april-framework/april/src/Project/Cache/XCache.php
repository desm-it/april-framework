<?php

namespace April\Project\Cache;
use April\Project\Logger;

class XCache {
 	
 	private $_sPrefix = '';
 	
 	/**
 	 * @ignore
 	 * @internal
 	 * This function is private, which means that it is impossible to instantiate this class directly. Instead the
 	 * programmer is forced to use the getInstance method.
 	 */
 	private function __construct(self $oConfig_){
 		$this->_sPrefix = $oConfig_->get( 'prefix' );
 	}
 	
 	/**
	 * @ignore
	 * @internal
	 * This function returns the data identified by the provided key. If no such key is found it will return false. 
	 */
 	public function get($sKey_){
 		if($this->contains($sKey_)){
 			$mRs = xcache_get($this->_sPrefix.$sKey_);
 			return $mRs;
 		}
 		return NULL;
	}
 	
	/**
	 * @ignore
	 * @internal
	 * This function stores the supplied data with the supplied key, optionally allowing for an expiration timeout. The
	 * function returns TRUE when the data was successfully stored, or FALSE in case of a connection failure of some kind.
	 */
 	public function set($sKey_, $mData, $iTTL = 0){
 		$bRs = xcache_set($this->_sPrefix.$sKey_, $mData, $iTTL);
		return $bRs;
 	}
 	
 	/**
 	 * @ignore
 	 * @internal
 	 * This function stores the supplied data with the supplied key. Add() is similar to set() except that the operation
 	 * will fail if the key already exists. Returns TRUE on success, NULL when key exists or FALSE on failure (error).
 	 */
 	public function add($sKey, $mData, $iTTL=0){
 		if(!$this->contains($sKey)){
 			return $this->set($sKey, $mData, $iTTL);
 		}else{
 			return null;
 		}
 	}
 	
 	/**
 	 * @ignore
 	 * @internal
 	 * This function checks if the given key exists in the cache
 	 */
 	public function contains($sKey_){
 		return xcache_isset($this->_sPrefix.$sKey_);
 	}
 	
 	public function remove($sKey_){
 		xcache_unset($this->_sPrefix.$sKey_);
 		return true;
 	}
 	
 	/**
 	 * @ignore
 	 * @internal
 	 * This function returns a dbc_Cache_XCache instance with the specified settings. If one of those instances was
 	 * previously created it will return that one.
 	 */
 	static public function getInstance(self $oConfig_) {
 		return new self($oConfig_);
 	}
 	
 }