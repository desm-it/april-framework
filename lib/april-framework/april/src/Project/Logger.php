<?php
namespace April\Project;

/**
 * Logger object, used to allow logging of errors, warnings or ejaculations. Defaults are taken from the config file
 * loaded with {@link Config::loadConfig()}.
 *
 * Configurable options:
 *
 * <pre>
 * logger = default | dummy
 * loglevel = debug | info | warn | error | disabled
 * </pre>
 *
 * Usage:
 *
 * <code>
 * Logger::error('errormessage');
 * </code>
 *
 * Result:
 *
 * <pre>
 * </pre>
 *
 */
final class Logger {
	const LOG_DISABLED = 0;
	const LOG_ERROR = 1;
	const LOG_WARN = 2;
	const LOG_INFO = 3;
	const LOG_DEBUG = 4;

	/**
	 * @ignore
	 */
	protected static $_aTimeMarkers = array();
	
	/**
	 * @ignore
	 */
	public static $_CONFIG_LEVELS = array(
		'disabled' => 0,
		'error' => 1,
		'warn' => 2,
		'info' => 3,
		'debug' => 4
	);

	/**
	 * @ignore
	 */
	public static $iLoggerLoaded = null;

	/**
     * @ignore
     * @internal
     * @var String hostname, just for debugging purposes
     */
    static private $sHostname;

    /**
     * @ignore
     * @internal
     * @var array loglevels, just for debugging purposes
     */
    static private $aLoglevel2Text;

	/**
	 * @ignore
	 * @internal
	 * Defining the constructor private prevents this class form being instantiated. All methods should be used in a
	 * static manner.
	 */
	private function __construct() {}

	/**
	 * @ignore
	 */
	static private function __log($sMessage_, $iLevel_) {
		$iLogLevel = self::LOG_DISABLED;
		if (! is_null(Config::$DEFAULT)) {
			if (
				Config::$DEFAULT->contains('loglevel') &&
				isset(self::$_CONFIG_LEVELS[Config::$DEFAULT->get('loglevel')])
			) $iLogLevel = self::$_CONFIG_LEVELS[Config::$DEFAULT->get('loglevel')];
		}
		if ($iLogLevel >= $iLevel_){

            if (!self::$aLoglevel2Text) self::$aLoglevel2Text = array_flip(self::$_CONFIG_LEVELS);
            if (!self::$sHostname) self::$sHostname = ( is_readable('/etc/hostname') ? trim(file_get_contents('/etc/hostname')) : 'unknown' );

            $sLog = '<strong>[' . strtoupper(self::$aLoglevel2Text[$iLevel_]) . ']</strong> ';
            $sLog .= '@' . self::$sHostname . ' - ';
            $sLog .= self::getExecutionTime() . ' - ';
            $sLog .= $sMessage_ . '<br />';
            echo '<pre>' . preg_replace("/\t+/i", "\t", $sLog) . '</pre>';
        }

        return true;
	}

	/**
	 * Generates a user-level debug message.
	 * @return Void
	 */
	static public function debug($sMessage_) { self::__log($sMessage_, self::LOG_DEBUG); }

	/**
	 * Generates a user-level info/notice message.
	 * @return Void
	 */
	static public function info($sMessage_) { self::__log($sMessage_, self::LOG_INFO); }

	/**
	 * Generates a user-level warning message.
	 * @return Void
	 */
	static public function warn($sMessage_) { self::__log($sMessage_, self::LOG_WARN); }

	/**
	 * Generates a user-level error message.
	 * @return Void
	 */
	static public function error($sMessage_) { self::__log($sMessage_, self::LOG_ERROR); }

    /**
     * Overrides the current loglevel which is used for all future log actions.
     * @param Integer $iLogLevel_. Use one of the following {@link Logger::LOG_DEBUG}, {@link Logger::LOG_INFO},
     * {@link Logger::LOG_WARN}, {@link Logger::LOG_ERROR}.
     * @throws \Exception
     */
	static public function setLogLevel($iLogLevel_) {
		if (is_null(Config::$DEFAULT)) Config::$DEFAULT = new Config();
		$aLevels = array_flip(self::$_CONFIG_LEVELS);
		if (! isset($aLevels[$iLogLevel_])) throw new \Exception('undefined log level');
		Config::$DEFAULT->set('loglevel', $aLevels[$iLogLevel_]);
	}

	/**
	 * Returns the current loglevel
	 * @return Integer The current log level
	 */
	static public function getLogLevel() {
		$iLogLevel = self::LOG_DEBUG;
		if (! is_null(Config::$DEFAULT)) {
			if (
				Config::$DEFAULT->contains('loglevel') &&
				isset(self::$_CONFIG_LEVELS[Config::$DEFAULT->get('loglevel')])
			) $iLogLevel = self::$_CONFIG_LEVELS[Config::$DEFAULT->get('loglevel')];
		}
		return $iLogLevel;
	}

	/**
	 * Overrides the current logger which is used for all future log actions.
	 * @param String $sLogger_ Name of the new log executor, for example: 'default' or 'dummy'.
	 * @return Void
	 */
	static public function setLogger($sLogger_) {
		if (is_null(Config::$DEFAULT)) Config::$DEFAULT = new Config();
		Config::$DEFAULT->set('logger', $sLogger_);
	}

	/**
	 * Get execution time since dbc_Logger class was first included.
	 * @return Float Returns the number of seconds since dbc_Logger was first included.
	 */
	static public function getExecutionTime() {
		return microtime(true) - self::$iLoggerLoaded;
	}

	/**
	 * Creates a time measure marker with the supplied id.
	 * @param String $sId_ Name of the timemarker. Skip or set to null to let the class create a unique id.
	 * @param String $sDescription_ An optional description of the timemarker.
	 * @return String Returns the timemarker id.
	 */
	static public function startTimeMarker($sId_ = null, $sDescription_ = '') {
		$fTime = microtime(true);
		if (is_null($sId_)) $sId_ = rand(100,999) . '-' . $fTime;
		self::$_aTimeMarkers[$sId_] = array(
			'start' => $fTime,
			'description' => $sDescription_,
			'id' => $sId_
		);
		return $sId_;
	}
	
	/**
	 * Stops the time measure marker with the supplied id.
	 * @param String $sId_ Name of the timemarker to stop.
	 * @return array Returns an array with 5 elements: id, description, start, end and time.
	 */
	static public function stopTimeMarker($sId_) {
		$fTime = microtime(true);
		if (! isset(self::$_aTimeMarkers[$sId_])) return false;
		$aMarker = & self::$_aTimeMarkers[$sId_];
		$aMarker['end'] = $fTime;
		$aMarker['time'] = $aMarker['end'] - $aMarker['start'];
		return $aMarker;
	}

	/**
	 * Returns the time measure marker with the supplied id. If the marker is still running it will be stopped.
	 * @param String $sId_ Name of the timemarker to stop.
	 * @return array Returns an array with 5 elements: id, description, start, end and time.
	 */
	static public function getTimeMarker($sId_) {
		if (! isset(self::$_aTimeMarkers[$sId_])) return false;
		if (! isset(self::$_aTimeMarkers[$sId_]['end'])) {
			return self::stopTimeMarker($sId_);
		} else return self::$_aTimeMarkers[$sId_];
	}

	/**
	 * Logs the details of the timemarker with a custom message and loglevel. If no level is supplied the debug
	 * log level will be used.
	 * @param Mixed $mIdOrMarker_ Name or timemarker array of the timemarker to stop.
	 * @param String $sMessage_ Custom log message to be added.
	 * @param Integer $iLevel_ Loglevel to use for this log action.
	 * @return Void
	 */
	static public function logTimeMarker($mIdOrMarker_, $sMessage_ = '', $iLevel_ = self::LOG_DEBUG) {
		if (! is_array($mIdOrMarker_)) {
			$aMarker = self::getTimeMarker($mIdOrMarker_);
		} else $aMarker = $mIdOrMarker_;
		
		self::__log(sprintf('%0.10f', $aMarker['time']) . " seconds ({$aMarker['id']}) - {$aMarker['description']}" . ($sMessage_ != '' ? ' - ' . $sMessage_ :''), $iLevel_);
	}
}

Logger::$iLoggerLoaded = microtime(true);