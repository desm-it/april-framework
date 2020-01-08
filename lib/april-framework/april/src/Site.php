<?php
/**
 * Created by PhpStorm.
 * User: joeldesmit
 * Date: 07/01/2020
 * Time: 14:01
 */

namespace April;

use April\Project\Config;
use April\Project\Cache;


class Site
{
    CONST SSH1	= 1;
    CONST SSH2	= 2;

    private static $_oDB = null; 			//@var Database_Queryable
    private static $_oCache = null;			//@var Cache
    private static  $_oConfig = null;		//@var Project\Config

    /**
     * Prepares the static helper class.
     * @param Config $oConfig
     */
    public static function parseConfig(Config $oConfig) {
        try {
            self::$_oConfig = $oConfig;
            self::$_oDB 	= Project::getMySQLi('mysql', $oConfig);
            self::$_oCache 	= self::getCache();
        } catch (\Exception $e) {

        }
    }


    /**
     * Get database object
     * @return Database_MySQL database
     * @throws \Exception
     */
    public static function getDatabase(){
        if(is_null(self::$_oDB)) throw new \Exception("Helper class not initialized or config error!");
        return self::$_oDB;
    }


    /**
     * Get cache object
     */
    public static function getCache(){
        if(is_null(self::$_oCache)){
            try {
                self::$_oCache = new Cache(self::getConfig()->get('memcache', Config::TYPE_MEMCACHE));
            }catch(\Exception $e){
                return false;
            }
        }

        return self::$_oCache;
    }


    /**
     * Get config object
     * @return Config config
     * @throws \Exception
     */
    public static function getConfig(){
        if(is_null(self::$_oConfig)) throw new \Exception("Helper class not initialized or config error!");
        return self::$_oConfig;
    }


    public static function getConfigVar($sVar_){
        $o = self::getConfig();
        $m = $o->get($sVar_);
        return isset($m) ? $m : false;
    }


    public static function getSQLBuilder($oSB_ = false){
        return $oSB_!==false && $oSB_ instanceof SQL_Builder ? clone $oSB_ : new SQL_Builder();  // TODO: SQL
    }


}