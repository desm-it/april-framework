<?php

namespace April\Project;

class ServerPark {
	const MYSQL_DB1 = 1;
    const MYSQL_LCL = 100;
    const MYSQL_STG = 101;

	const MC_DATA1 = 1;
    const MC_DATA2 = 2;
	const MC_THUMB1 = 1;
	const MC_THUMB_LW1 = 1; 
	const MC_CHAT_THUMB = 2;

	static public $MYSQL_SERVERS = array(
		1 => array('master' => '127.0.0.1'),
        100 => array('master' => '127.0.0.1', 'slave' => '127.0.0.1'),
        101 => array('master' => '127.0.0.1', 'slave' => '127.0.0.1')
    );

	static public $MC_SERVERS = array(
		1 => array(
			array('host' => 'localhost', 'port' => 11211, 'weight' => 100),
        ),
        2 => array(
            array('host' => 'localhost', 'port' => 11211, 'weight' => 100),
//			array('host' => 'localhost', 'port' => 11211, 'weight' => 50),
        ),
	);

	// Device constants (used in: Network_Object)
	const DEVTYPE_PERIPHERAL	= 0;
	const DEVTYPE_DISKARRAY		= 1;
	const DEVTYPE_SERVER		= 2;
	const DEVTYPE_SWITCH		= 3;
	const DEVTYPE_STUFF		    = 4;
	const DEVTYPE_APC		    = 5;

    const DEVTASK_NOTHING		= 0;
    const DEVTASK_WEB		    = 1;
    const DEVTASK_DATABASE		= 2;
    const DEVTASK_FMS		    = 3;
    const DEVTASK_RED5		    = 4;
    const DEVTASK_HOSTING		= 5;
    const DEVTASK_FILER		    = 6;
    const DEVTASK_OTHER		    = 7;
    const DEVTASK_DEVICE		= 8;
    const DEVTASK_FTP		    = 9;
    const DEVTASK_LEVEL3		= 10;
    const DEVTASK_LOADBALANCER	= 11;
    const DEVTASK_DNS		    = 12;
    const DEVTASK_PHPSRV		= 13;
    const DEVTASK_MEMCACHED		= 14;
    const DEVTASK_VPN		    = 15;
}
?>
