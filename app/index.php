<?php
    require_once 'includes/autorun.php';

//Create template
$oTpl = new April\Tpl("index.twig");

//Parse our template
die($oTpl->parse());