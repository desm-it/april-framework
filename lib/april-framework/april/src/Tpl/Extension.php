<?php
namespace April\Tpl;
use Twig\TwigFunction;
use Twig\Extension as TwigExtension;
use April\Site as Site;
use April\Tpl as Tpl;

class Extension extends TwigExtension\AbstractExtension{

    public function getFunctions(){
        return array(
            new TwigFunction('_t', array($this, '_t')),
            new TwigFunction('_t2', array($this, '_t2')),
            new TwigFunction('localePath', array($this, 'localePath')),
            new TwigFunction('getBlock', array($this, 'getBlock')),
            new TwigFunction('getCSS', array($this, 'getCSS')),
            new TwigFunction('getJS', array($this, 'getJS')),


        );
    }

    public function _t($msgid, $msgid_plural="", $count=1, $ucfirst = 0, $var1 = "", $var2 = "", $var3 = "", $var4 = ""){
        return _t($msgid, $msgid_plural, $count, $ucfirst, $var1, $var2, $var3, $var4);
    }

    public function _t2($msgid, $msgid_plural = "", $count = 1, $ucfirst = 0, $var1 = "", $var2 = "", $var3 = "", $var4 = ""){
        return _t2($msgid, $msgid_plural, $count, $ucfirst, $var1, $var2, $var3, $var4);
    }

    public function localePath($sFile_,$sLocale){
        return Tpl::getPathWithLocale($sFile_,$sLocale);
    }

    public function getBlock(){
        return Tpl::getBlock(func_get_args());
    }

    public function getCSS($aFiles = array()){
        return Site\Assets::getCSS($aFiles);
    }

    public function getJS($aFiles = array()){
        return Site\Assets::getJS($aFiles);
    }

    public function getName(){
        return "Site_Tpl_Extension";
    }
}