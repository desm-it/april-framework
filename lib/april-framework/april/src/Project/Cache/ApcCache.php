<?php

namespace April\Project\Cache;
use April\Project\Logger;

 class ApcCache {
 	
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
 		$mRs = apc_fetch($this->_sPrefix.$sKey_, $bSuccess);
 		return ($bSuccess) ? $mRs : false;
	}
 	
	/**
	 * @ignore
	 * @internal
	 * This function stores the supplied data with the supplied key, optionally allowing for an expiration timeout. The
	 * function returns TRUE when the data was successfully stored, or FALSE in case of a connection failure of some kind.
	 */
 	public function set($sKey_, $mData, $iTTL = 0){
        Logger::debug("set in apccache: {$this->_sPrefix}{$sKey_} ({$iTTL})");
 		$bRs = apc_store($this->_sPrefix.$sKey_, $mData, $iTTL);
		return $bRs;
 	}
 	
 	/**
 	 * @ignore
 	 * @internal
 	 * This function stores the supplied data with the supplied key. Add() is similar to set() except that the operation
 	 * will fail if the key already exists. Returns TRUE on success, NULL when key exists or FALSE on failure (error).
 	 */
 	public function add($sKey, $mData, $iTTL=0){
 		Logger::debug("add in apccache: {$this->_sPrefix} ({$iTTL})");

 		if(apc_add($sKey, $mData, $iTTL)){
 			return true;
 		}else{
 			return NULL;
 		}
 	}
 	
 	/**
 	 * @ignore
 	 * @internal
 	 * This function checks if the given key exists in the cache
 	 */
 	public function contains($sKey_){
 		return apc_exists($sKey_);
 	}
 	
 	public function remove($sKey_){
        Logger::debug("remove from apccache: {$this->_sPrefix}{$sKey_}");
 		return apc_delete($sKey_);
 	}
 	
 	/**
 	 * @ignore
 	 * @internal
 	 * This function returns a dbc_Cache_ApcCache instance with the specified settings. If one of those instances was
 	 * previously created it will return that one.
 	 */
 	static public function getInstance(self $oConfig_) {
 		return new self($oConfig_);
 	}
 	
 }