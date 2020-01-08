<?php

    function april_autoload( $sClassName_ ) {
        if (substr($sClassName_, 0, 1) === '\\')
            $sClassName_ =  substr($sClassName_, strpos($sClassName_, '\\') +1);

        if (substr($sClassName_, 0, 5) === 'April') {
            $sClassName_ = substr($sClassName_, +6);
            $sFilename = __DIR__ . "/april/src/" . str_replace("\\", "/", $sClassName_) . '.php';
            if (file_exists($sFilename) && !class_exists($sClassName_, false)) {
                /** @noinspection PhpIncludeInspection */
                require_once($sFilename);
            }
        }
    }
    spl_autoload_register('april_autoload');