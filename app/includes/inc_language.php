<?php
/**
 * Language include file.
 *
 */

    $bSaveLocale = false;
    if (isset($_GET["locale"])) {
        $locale = $_GET["locale"];
        $bSaveLocale = true;
    }elseif(isset($_SESSION["locale"])){
        $locale  = $_SESSION["locale"];
    }else{
        $locale = "nl_NL";
    }

    $_SESSION["locale"] = $locale;
    putenv("LANG=" . $locale);
    setlocale(LC_TIME, $locale);
    setlocale(LC_ALL, $locale);

    $sDomain = "april";
    bindtextdomain($sDomain, "locale");
    bind_textdomain_codeset($sDomain, 'UTF-8');
    textdomain($sDomain);

    // Override certain translations for whitelabels if needed
    $sOverrideDomain = Site::getConfigVar('OVERRIDE_LANG');
    if($sOverrideDomain){
        bindtextdomain($sOverrideDomain, "locale");
        bind_textdomain_codeset($sOverrideDomain, 'UTF-8');
    }


    function _t($msgid, $msgid_plural = "", $count = 1, $ucfirst = 0, $var1 = "", $var2 = "", $var3 = "", $var4 = ""){
        if (($msgid_plural!="")&&($msgid_plural)) {
            $temp = ngettext($msgid, $msgid_plural, $count);
        } elseif ($var1 != "") {
            $arr = array($var1, $var2, $var3, $var4);
            $temp = vsprintf(_($msgid), $arr);
        } else {
            $temp =  _($msgid);
        }
        if ($ucfirst) {
            $temp = ucfirst($temp);
        }
        return $temp;
    }

    function _t2($msgid, $msgid_plural = "", $count = 1, $ucfirst = 0, $var1 = "", $var2 = "", $var3 = "", $var4 = ""){
        global $sOverrideDomain;
        $arr = array($var1, $var2, $var3, $var4);
        if(isset($sOverrideDomain)){
            if (($msgid_plural != "") && ($msgid_plural))
                $sTranslatedString = dngettext($sOverrideDomain, $msgid, $msgid_plural, $count);
            else
                $sTranslatedString = dgettext($sOverrideDomain, $msgid);
        }
        if($sTranslatedString == $msgid || ($msgid_plural != "" && $sTranslatedString == $msgid_plural)){
            $sTranslatedString = _t($msgid, $msgid_plural, $count, $ucfirst, $var1, $var2, $var3, $var4);
        }

        if ($var1 != "")
            $sTranslatedString = vsprintf($sTranslatedString, $arr);

        if ($ucfirst)
            $sTranslatedString = ucfirst($sTranslatedString);
        return $sTranslatedString;
    }