<?php

    $sDefaultThemeFile = dirname(__FILE__)."/default-theme";
    $bGlobalDebug = true;

    if($bGlobalDebug) {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
    }
    session_start();

//    if(!empty($_SERVER['DOCUMENT_ROOT'])){
//        chdir($_SERVER['DOCUMENT_ROOT']);
//    }else{
//        chdir('/var/www/html/app'); //Default apache2 root
//    }

    include_once "inc_language.php";

    //Autoload classes
    require_once '../../lib/april-framework/autoload.php';
    require_once '../../lib/vendor/autoload.php';

    // Load config
    isset($_SERVER['HTTP_HOST']) ? $sHostName = $_SERVER['HTTP_HOST'] : $sHostName = '' ;
    switch ($sHostName) {
        case "localhost":
            April\Site::parseConfig(April\Project::loadConfig('localhost'));
            break;

        default:
            if(file_exists($sDefaultThemeFile))
                $sDefaultTheme = file_get_contents($sDefaultThemeFile);
            else
                $sDefaultTheme = "default";

            April\Site::parseConfig(April\Project::loadConfig($sDefaultTheme));
            break;
    }

    // Initialize Twig and TwigTool
    $sTplPath = (isset($bIsAdminEnv) && $bIsAdminEnv) ? '../app/admin/tpl' : '../app/tpl';

    $oLoader = new \Twig\Loader\FilesystemLoader($sTplPath);
    $oTwig = new \Twig\Environment($oLoader, [
        'cache' => (April\Site::getConfigVar('TWIG_CACHE'))?'../app/tplcache':false,
        'debug' => $bGlobalDebug,
        'auto_reload' => $bGlobalDebug,
    ]);

    $oTwig->addExtension(new April\Tpl\Extension());

