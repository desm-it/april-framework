<?php 
/*****
	How To use:
		{{getBlock('left-friends',"id:1")}}
		{{getBlock('left-friends',"id:%s",$profile_id)}}
		{{getBlock('left-friends',"id:%s|amount:%s",$profile_id,12)}}

*****/

namespace April\Tpl;
Use \April;

	class Block extends April\Tpl{
		private $_oTpl;
		private $_sName;
				
		public function __construct($sTpl_, $sLocale = false){
            $oTpl_= new parent("block/".$sTpl_.".twig");
			$this->_oTpl = $oTpl_;
			$this->_sName = $sTpl_;
	    }
		
		
	
		public function get($sVars_ = '', $aArgs_ = array()){
			if(strpos($sVars_,'%') && is_array($aArgs_) && count($aArgs_)>2){
				//NOTE:: $aArgs_[0] = $sVars_, $aArgs[1] = $aArgs_
				$aArgs 	= array_diff($aArgs_, array($sVars_,$this->_sName));
				$sStr 	= vsprintf($sVars_,$aArgs);
				$aVars 	= self::varStringToArray($sStr);
			}else{
				$aVars	= self::varStringToArray($sVars_);
			}
			
			$bParse = true;

			try {
				
				switch($this->_sName){
                    case 'example':

                        // Example Array object
                        $aCars = array(
                            array(
                                "name"=>"Golf",
                                "brand"=>"VW"
                            ),
                            array(
                                "name"=>"Vectra",
                                "brand"=>"Opel"
                            ),
                        );

                        if(!empty($aCars)){
                            foreach($aCars as $aCar){
                                $this->block('bCar',$aCar);
                            }
                        }

                        $this->setGlobals(array(
                            'sSearch'		=>	$aVars['search']
                        ));

                        break;

					default:
						$bParse = false;
				}
				
			}catch(\Exception $e){
			    echo $e->getMessage();
				$bParse = false;
			}
			
			return $bParse ? $this->parse() : false;
		}
		
		
		static public function varStringToArray($sVars_ = ''){
			$aVars = array();
			if(!empty($sVars_)){
				if(strpos($sVars_,'|')!==FALSE){
					$aKV = explode('|',$sVars_);
					if(count($aKV)>0){
						foreach($aKV as $v){
							$aArr = explode(':',$v);
							$aVars[$aArr[0]] = $aArr[1];
						}
					}
				}elseif(strpos($sVars_,':')!==FALSE){
					$aArr = explode(':',$sVars_);
					$aVars[$aArr[0]] = $aArr[1];
				}else{
					$aVars[] = $sVars_;
				}
			}
			
			return count($aVars) ? $aVars : false;
		}
	}
?>