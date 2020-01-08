<?php
/**
 * Created by PhpStorm.
 * User: joeldesmit
 * Date: 07/01/2020
 * Time: 14:01
 */

namespace April;
use April\Tpl\Block;

    class Tpl {


        private static $aVars = array();
        private static $oTpl;

        public function __construct($sTpl_, $sLocale = false){

            global $oTwig;

            if($sLocale)
                $sTpl_ = $this->getPathWithLocale($sTpl_,$sLocale);

            //TODO: path with locale
            //$this->getTplPath($sTpl_);
            self::$oTpl = $oTwig->load($sTpl_);

            if(!$this->hasGlobalVar('TPL_VARS_SET')){
                $this->setVars();
            }
        }


        public function parse(){
            return self::$oTpl->render($this->getGlobals());
        }


        static public function getPathWithLocale($sTpl_, $sLocale = "en_GB"){
            $pos = strrpos($sTpl_, '/');
            if($pos !== false) {
                $sFile = substr($sTpl_, $pos + 1);
                $sPath = substr($sTpl_, 0, $pos);
                $sFullpath = $sPath.'/'.$sLocale.'/'.$sFile;
            }else{
                $sFullpath = $sLocale.'/'.$sTpl_;
            }
            return self::getTplPath($sFullpath) ? $sFullpath : $sTpl_;

        }

        static public function getTplPath($sTpl_){
            $sPath = Site::getConfigVar('PATH_TPL').$sTpl_;
            if(!is_file($sPath))
                return false;

            return $sTpl_;
        }

        public function setGlobals($aVars_ = array()){

            foreach ($aVars_ as $k => $v){
                self::$aVars[$k] = $v;
            }
        }

        public function getGlobals(){
            return self::$aVars;
        }

        public function hasGlobalVar($sName_){
            $aGlobals = $this->getGlobals();
            return is_array($aGlobals) && in_array($sName_, $aGlobals);
        }

        public static function getInitialVars(){

            $oConf = Site::getConfig();

            return array(
                'TPL_VARS_SET'		     => 	true,
                'SITE_URL'			     =>	$oConf->get('SITE_URL'),
                'SITE_NAME'			     =>	$oConf->get('SITE_NAME'),
                'SITE_NAME_URL'		     =>	$oConf->get('SITE_NAME_URL'),
                'SITE_NAME_DOMAIN'	     =>	$oConf->get('SITE_NAME_DOMAIN'),
                'SITE_TITLE_PREFIX'	     => $oConf->get('SITE_TITLE_PREFIX'),
                'SITE_TITLE_SUFFIX'	     => $oConf->get('SITE_TITLE_SUFFIX'),
                'SUPPORT_EMAIL'		     => $oConf->get('CONTACT_EMAIL'),
                'ASSETS_JS_URL'		     =>	$oConf->get('SITE_URL').$oConf->get('ASSETS_JS'),
                'SITE_THEME'             => $oConf->get('SITE_THEME'),
                'SITE_LOGO'              => $oConf->get('SITE_LOGO'),
                'SITE_DESC'              => $oConf->get('SITE_DESC'),
                'SITE_ICO'               => $oConf->get('SITE_ICO'),
                'SITE_SHARE'             => $oConf->get('SITE_SHARE'),
                'LOCALE'                 => $_SESSION["locale"],
            );

        }

        protected function setVars(){

            //Set variables which will always be avaialble in every template
            $this->setGlobals(self::getInitialVars());
        }


        public function block($sName_, $aVars_){

            $aGlobals = $this->getGlobals();
            $aVars = array();
            $aNeedle = &$aVars;
            $aHaystack = $aGlobals;

            $aBlocks = explode("/", $sName_);
            $sLastBlock = end($aBlocks);

            foreach($aBlocks as $sBlock){
                $aHaystack = &$aHaystack[$sBlock];

                if(is_array($aHaystack)){
                    $aNeedle[$sBlock] = $aHaystack;
                    $iIndex = ($sBlock ==  $sLastBlock)? count($aHaystack) : count($aHaystack)-1;
                    $aNeedle = &$aNeedle[$sBlock][$iIndex];
                    $aHaystack = &$aHaystack[$iIndex];
                }else{
                    $aNeedle = &$aNeedle[$sBlock][0];
                    break;
                }
            }

            $aNeedle = $aVars_;
            $this->setGlobals($aVars);

        }

        public function renderBlock($sName_, $aVars_)
        {
            return self::$oTpl->renderBlock($sName_, $aVars_);
        }

        public function setObject($oObject_, $sPrefix_ = null){
            $aObjectVars = array();
            try {
                /** @noinspection PhpUndefinedMethodInspection */
                foreach($oObject_->toArray() as $k => $v){
                    $aObjectVars[$sPrefix_.$k] = $v;
                }
                $this->setGlobals($aObjectVars);

            }catch(\Exception $e){}

        }

        ######## ######## ######## ######## ########
        ## These function can be used inside templates to fetch (template) blocks
        ## - {{getBlock('left-friends',"id:%s|amount:%s",$profile_id,12)}}
        ######## ######## ######## ######## ########
        public static function getBlock(){

            try {
                $Args = func_get_args()[0];
                $sName = $Args[0];
                $sVars = (isset($Args[1]))? $Args[1] : '';

                $o = new Block($sName);
                return $o->get($sVars, $Args);
            }catch(\Exception $e){
                return false;
            }
        }




    }