<?php

namespace April\Project\Cache;
use April\Project\Logger;

class MySQL {
	const GC_KEY = '__dbc_Cache_MySQL_GC';
	const GC_TIMEOUT = 3600;
	const TABLE_PREFIX = '__dbcorplib';

	/**#@+
	 * @ignore
	 * @internal
	 * This class should not be instantiated directly by the implementor. The recommended way is through the use of a
	 * dbc_Cache instance.
	 */
	static private $_aInstances = array();
	private $_oConfig = NULL;
	private $_oMySQL = NULL;
	/**#@-*/

	/**
	 * @ignore
	 * @internal
	 * This function is private, which means that it is impossible to instantiate this class directly. Instead the
	 * programmer is forced to use the getInstance method.
	 */
	private function __construct(self $oConfig_) {
		$this->_oConfig = $oConfig_;
	}

	/**
	 * @ignore
	 * @internal
	 * The __init function creates the memory tables. We're using a VARBINARY(255) field because tables in memory cannot
	 * contain BLOB or TEXT fields. Data is therefore spread in chunks over several records.
	 */
	private function __init() {
		$this->_oMySQL = self::getInstance($this->_oConfig, 'master');
		if (
			$this->_oMySQL->executeQuery("
				CREATE TABLE IF NOT EXISTS `%s_cache` (
					`cache_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
					`key` VARCHAR(32) NOT NULL,
					`stored` DATETIME NOT NULL,
					`expires` DATETIME NOT NULL,
					PRIMARY KEY (`cache_id`),
					UNIQUE KEY `u_key_cache` (`key`),
					KEY `i_expires_cache` (`expires`)
				) ENGINE=MEMORY AUTO_INCREMENT=1 COMMENT='dbc_Cache_MySQL support table -- always safe to be removed'
			", self::TABLE_PREFIX) &&
			$this->_oMySQL->executeQuery("
				CREATE TABLE IF NOT EXISTS `%s_cache_data` (
					`cache_id` INT(10) UNSIGNED NOT NULL,
					`index` MEDIUMINT(8) UNSIGNED NOT NULL,
					`data` VARBINARY(255) NOT NULL,
					PRIMARY KEY (`cache_id`,`index`),
					KEY `i_cache_id` (`cache_id`)
				) ENGINE=MEMORY AUTO_INCREMENT=1 COMMENT='dbc_Cache_MySQL support table -- always safe to be removed'
			", self::TABLE_PREFIX)
		) {
			Logger::debug('MySQL cache tables created');
			return true;
		} else return false;
	}

	/**
	 * @ignore
	 * @internal
	 * This function returns the data identified by the provided key. If no such key is found it will return NULL. This
	 * allows for actual FALSE values to be stored in the cache. In case of a connection failure this function will throw
	 * an exception which causes the parent dbc_Cache object to try a failover cacher object.
	 */
	public function get($sKey_) {
		Logger::debug('get in mysql');

		if (is_null($this->_oMySQL) && ! $this->__init()) {
			Logger::warn('mysql connection not ready');
			throw new \Exception('mysql connection not ready');
		} else {
			if (strlen((string) $sKey_) > 32) $sKey_ = md5((string) $sKey_);
			if (
				($oResult = $this->_oMySQL->executeQuery("
					SELECT `cache_id`
					FROM `%s_cache`
					WHERE `key`='%s'
					AND `expires`>=NOW()
				", self::TABLE_PREFIX, (string) $sKey_)) &&
				$oResult->numRows() > 0
			) {
				list($iCacheID) = $oResult->first(false);
				$oResult = $this->_oMySQL->executeQuery("
					SELECT `data`
					FROM `%s_cache_data`
					WHERE `cache_id`='%d'
					ORDER BY `index`
				", self::TABLE_PREFIX, $iCacheID);
				$aChunks = $oResult->allByField('data');
				$sData = implode($aChunks);
				return unserialize($sData);
			} else return NULL;
		}
	}

	/**
	 * @ignore
	 * @internal
	 * This function stores the supplied data with the supplied key, optionally allowing for an expiration timeout. The
	 * function returns TRUE when the data was successfully stored, or FALSE in case of a connection failure of some kind.
	 * Data is stored in chunks because a memory table doesn't allow for TEXT or BLOB fields, so we've used a VARBINARY
	 * field with space for 255 bytes.
	 */
	public function set($sKey_, $mData_, $iTTL = 0) {
		Logger::debug('set in mysql');

		if (is_null($this->_oMySQL) && ! $this->__init()) {
			Logger::warn('mysql connection not ready');
			return false;
		} else {
			if (strlen((string) $sKey_) > 32) $sKey_ = md5((string) $sKey_);
			if (
				$this->_oMySQL->executeQuery("
					INSERT INTO `%s_cache`
					SET `key`='%s',
					`stored`=NOW(),
					`expires`=DATE_ADD(NOW(),INTERVAL %d SECOND)
					ON DUPLICATE KEY
					UPDATE `expires`=DATE_ADD(NOW(),INTERVAL %d SECOND)
				", self::TABLE_PREFIX, (string) $sKey_, $iTTL, $iTTL) &&
				($oResult = $this->_oMySQL->executeQuery("
					SELECT `cache_id`
					FROM `%s_cache`
					WHERE `key`='%s'
				", self::TABLE_PREFIX, (string) $sKey_)) &&
				$oResult->numRows() > 0
			) {
				list($iCacheID) = $oResult->first(false);
				$this->_oMySQL->executeQuery("
					DELETE FROM `%s_cache_data`
					WHERE `cache_id`='%d'
				", self::TABLE_PREFIX, $iCacheID);
				$aChunks = str_split(serialize($mData_), 255);
				foreach($aChunks as $iIndex => $sChunk) {
					$this->_oMySQL->executeQuery("
						INSERT INTO `%s_cache_data`
							SET `cache_id`='%d',
						`index`='%d',
						`data`='%s'
					", self::TABLE_PREFIX, $iCacheID, $iIndex, $sChunk);
				}
				return true;
			} else return false;
		}
	}

	public function add($sKey_, $mData_, $iTTL = 0) {
		Logger::debug('set in mysql');

		if (is_null($this->_oMySQL) && ! $this->__init()) {
			Logger::warn('mysql connection not ready');
			return false;
		} else {
			if (strlen((string) $sKey_) > 32) $sKey_ = md5((string) $sKey_);
			if (
				$this->_oMySQL->executeQuery("
					INSERT INTO `%s_cache`
					SET `key`='%s',
					`stored`=NOW(),
					`expires`=DATE_ADD(NOW(),INTERVAL %d SECOND)
				", self::TABLE_PREFIX, (string) $sKey_, $iTTL) &&
				($oResult = $this->_oMySQL->executeQuery("
					SELECT `cache_id`
					FROM `%s_cache`
					WHERE `key`='%s'
				", self::TABLE_PREFIX, (string) $sKey_)) &&
				$oResult->numRows() > 0
			) {
				list($iCacheID) = $oResult->first(false);
				$this->_oMySQL->executeQuery("
					DELETE FROM `%s_cache_data`
					WHERE `cache_id`='%d'
				", self::TABLE_PREFIX, $iCacheID);
				$aChunks = str_split(serialize($mData_), 255);
				foreach($aChunks as $iIndex => $sChunk) {
					$this->_oMySQL->executeQuery("
						INSERT INTO `%s_cache_data`
							SET `cache_id`='%d',
						`index`='%d',
						`data`='%s'
					", self::TABLE_PREFIX, $iCacheID, $iIndex, $sChunk);
				}
				return true;
			} else return false;
		}
	}
	
	/**
	 * @ignore
	 * @internal
	 * The destructor of the dbc_Cache_MySQL class performs garbage collection if necessary. We're going to use the class'
	 * own methods to determine wether garbage collection should take place.
	 */
	public function __destruct() {
		if (
			! is_null($this->_oMySQL) &&
			$this->_oMySQL->isConnected() &&
			! $this->contains(self::GC_KEY)
		) {
			Logger::debug('garbage collect in mysql');

			$this->set(self::GC_KEY, true, self::GC_TIMEOUT);
			$oResult = $this->_oMySQL->executeQuery("
				SELECT `cache_id`
				FROM `%s_cache`
				WHERE `expires`<NOW()
			", self::TABLE_PREFIX);
			if ($oResult) {
				$aCacheIds = $oResult->allByField('cache_id');
				foreach($aCacheIds as $iCacheId) {
					$this->_oMySQL->executeQuery("
						DELETE FROM `%s_cache_data`
						WHERE `cache_id`='%d'
					", self::TABLE_PREFIX, $iCacheId);
					$this->_oMySQL->executeQuery("
						DELETE FROM `%s_cache`
						WHERE `cache_id`='%d'
					", self::TABLE_PREFIX, $iCacheId);
				}
			}
			$this->_oMySQL->executeQuery("
				OPTIMIZE TABLE `%s_cache`
			", self::TABLE_PREFIX);
			$this->_oMySQL->executeQuery("
				OPTIMIZE TABLE `%s_cache_data`
			", self::TABLE_PREFIX);
		}
	}

	/**
	 * @ignore
	 * @internal
	 * This function checks to see if the data associated with the provided key exists in the cache database. If the
	 * connection is not ready it throws an error.
	 */
	public function contains($sKey_) {
		if (is_null($this->_oMySQL) && ! $this->__init()) {
			Logger::warn('mysql connection not ready');
			throw new \Exception('mysql connection not ready');
		} else {
			if (strlen((string) $sKey_) > 32) $sKey_ = md5((string) $sKey_);
			if (
				($oResult = $this->_oMySQL->executeQuery("
					SELECT COUNT(*)
					FROM `%s_cache`
					WHERE `key`='%s'
					AND `expires`>=NOW()
				", self::TABLE_PREFIX, (string) $sKey_)) &&
				$oResult->numRows() > 0
			) {
				list($iTotal) = $oResult->first(false);
				return $iTotal > 0;
			} else return false;
		}
	}

	/**
	 * @ignore
	 * @internal
	 * This function removes the data associated with the provided key from the database. This function returns TRUE
	 * wether the key could be found or not. It will return FALSE if the connection to the memcache server is not
	 * ready or usable.
	 */
	public function remove($sKey_) {
		Logger::debug('remove in mysql');
		if (is_null($this->_oMySQL) && ! $this->__init()) {
			Logger::warn('mysql connection not ready');
			return false;
		} else {
			if (strlen((string) $sKey_) > 32) $sKey_ = md5((string) $sKey_);
			return $this->_oMySQL->executeQuery("
				UPDATE `%s_cache`
				SET `expired`=NOW()
				WHERE `key`='%s'
			", self::TABLE_PREFIX, (string) $sKey_);
		}
	}

	/**
	 * @ignore
	 * @internal
	 * This function returns a dbc_Cache_MySQL instance with the specified settings. If one of those instances was
	 * previously created it will return that one.
	 */
	static public function getInstance(self $oConfig_) {
		if (self::hasInstance($oConfig_)) return self::$_aInstances[$oConfig_->id()];
		return new self($oConfig_);
	}	

	/**
	 * @ignore
	 * @internal
	 * This function returns TRUE of FALSE depending on wether a dbc_Cache_MySQL instance already exists with the
	 * specified settings.
	 */
	static public function hasInstance(self $oConfig_) {
		return isset(self::$_aInstances[$oConfig_->id()]);
	}

	public function setOption($iOption, $mValue) {
		/* null */
	}

}
?>