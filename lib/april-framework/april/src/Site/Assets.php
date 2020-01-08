<?php
namespace April\Site;
use April\Site as Site;
use April\Libs as Libs;

class Assets {
	static public function get($aFiles_ = array(), $sType_ = 'js'){
		if(!is_array($aFiles_) || count($aFiles_)==0)
			return false;
			
		$oConf = Site::getConfig();
		
		switch($sType_){
			case 'js': $sPath = $oConf->get('ASSETS_JS'); break;
			case 'css': $sPath = $oConf->get('ASSETS_CSS'); break;
		}
		
		if(!isset($sPath))
			return false;
			
		$sDir  = $oConf->get('SITE_PATH').$sPath;
		$aArr  = array();
		$iLastModified = false;
		foreach($aFiles_ as $sFile){
			$bRemote = strstr($sFile,'http://') || strstr($sFile,'https://');
			$sPath = ($bRemote?'':$sDir).$sFile;
			if(!$bRemote && !is_file($sPath))
			    continue;
			
			$aArr[] = $sPath;
			
			if(!$bRemote){
				$iMtime = filemtime($sPath);
				if($iMtime > $iLastModified)
				    $iLastModified = $iMtime;
    		}
		}
		
		if($iLastModified === false)
		  	$iLastModified = filemtime($sDir);

		$sCacheKey = md5(implode('',$aArr).$iLastModified).'.'.$sType_;
		$sCachePath = $oConf->get('SITE_PATH').$oConf->get('ASSETS_CACHE').$sCacheKey;
		if(is_file($sCachePath)){
			//Use the existing cache data
		  	$sContents = file_get_contents($sCachePath);
		}else{
		 	//Create the combined file and cache it	
		 	$sContents = '';
		  	foreach($aArr as $sFile){
				$sContents .= file_get_contents($sFile);
			}
			
			switch($sType_){
				case 'js':  $sPacked = Libs\Minifier::minifyJS($sContents); break;
                case 'css': $sPacked = Libs\Minifier::minifyCSS($sContents); break;
                default:    $sPacked = Libs\Minifier::minifyCSS($sContents);
			}
			
			file_put_contents($sCachePath, $sPacked);
			touch($sCachePath, $iLastModified);
		}
 		
 		return $oConf->get('ASSETS_CACHE').$sCacheKey;
	}
	
	static public function getJS($aFiles_ = array()){
		return self::get($aFiles_,'js');
	}
	
	static public function getCSS($aFiles_ = array()){
		return self::get($aFiles_,'css');
	}
}
?>