<?php
/**
 * DBCorpLib
 *
 * @author DBCorp
 * @package DBCorpLib
 * @subpackage Database
 */

/**
 * A class for using the database to store files. This class will automatically create the necessary tables required
 * for storing files. It uses the MyISAM engine due to it's ability to shrink the physical database files on disk when
 * data is removed from the tables. Physical InnoDB files don't shrink when data is removed, making it useless as an
 * engine for storing files. To create these tables the MySQL user requires CREATE TABLE privileges. The class creates
 * several data tables up to a maximum table size, so these privileges are not only required during the first usage,
 * but also subsequent uses of this class.
 *
 * @package DBCorpLib
 * @subpackage Database
 */
class dbc_Database_MySQL_Storage {
	/**#@+
	 * @ignore
	 * @internal
	 */
	const TABLE_PREFIX = '__dbcorplib';
	private $_oDb;
	private $_oMemCache;
	private $_aExtensions = array(
		'jpg' => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'gif' => 'image/gif',
		'png' => 'image/png',
		'pdf' => 'application/pdf',
		'doc' => 'application/msword',
		'xls' => 'application/msexcel',
		'ppt' => 'application/mspowerpoint',
		'pps' => 'application/mspowerpoint',
		'flv' => 'video/x-flv',
		'txt' => 'text/plain',
		'svg' => 'image/svg',
	);
	private $_iMaxTableSize = 500000000; // 500 Mb per table
	private $_iMaxInMemory = 10000000;
	private $_iOptimizeThreshold = 50000000; // 50 Mb allocated but unused per table before optimize
	private $_bUseCache = true;
	private $_aFiles = array();
	/**#@-*/

	/**
	 * @ignore
	 * @internal
	 */
	public function __construct( dbc_Config_MySQL $oConfigMySQL_ = NULL, dbc_Config_MemCache $oConfigMemCache_ = NULL ) {
		if ( is_null( $oConfigMySQL_ ) ) {

			// We need to discover the mysql settings ourself.
			if ( is_null( dbc_Config::$DEFAULT ) ) throw new Exception( 'no configuration loaded' );
			$oSettings = & dbc_Config::$DEFAULT;
			if ( ! $oSettings->contains( 'mysql' ) ) throw new Exception( 'mysql settings not found' );
			$oConfigMySQL_ = $oSettings->get( 'mysql', dbc_Config::TYPE_MYSQL );
		}
		if (
			is_null( $oConfigMemCache_ ) &&
			! is_null( dbc_Config::$DEFAULT )
		) {
			// We might find some memcache settings in the default configuration.
			$oSettings = & dbc_Config::$DEFAULT;
			if ( $oSettings->contains( 'memcache' ) ) $oConfigMemCache_ = $oSettings->get( 'memcache', dbc_Config::TYPE_MEMCACHE );
		}

		// Let's create the database- and memcache object from the received config or the default config.
		$this->_oDb = dbc_Database_MySQL_Director::getInstance( $oConfigMySQL_ );
		if ( ! is_null( $oConfigMemCache_ ) ) $this->_oMemCache = new dbc_Cache( $oConfigMemCache_ );
	}

	/**
	 * Creates a new file from a physical file on disk.
	 * @param $sFilePath_ String The path of the file on disk.
	 * @return Integer The id of the new file in the database.
	 */
	public function newFileFromDisk( $sFilePath_ ) {
		if ( false == ( $rFp = @fopen( $sFilePath_, 'r' ) ) ) throw new Exception( 'file not found on disk' );
		return $this->newFileFromResource( $rFp, basename( $sFilePath_ ) );
	}

	/**
	 * Replaces an existing file in the database with a physical file on disk.
	 * @param $iFileId_ Integer The id of a file in the database.
	 * @param $sFilePath_ String The path of the file on disk.
	 * @return Integer The id of the file in the database.
	 */
	public function replaceFileFromDisk( $iFileId_, $sFilePath_ ) {
		if ( false == ( $rFp = @fopen( $sFilePath_, 'r' ) ) ) throw new Exception( 'file not found on disk' );
		return $this->replaceFileFromResource( $iFileId_, $rFp, basename( $sFilePath_ ) );
	}

	/**
	 * Creates a new file directly from a string.
	 * @param $sString_ String The data for the file.
	 * @param $sFilename_ String The name of the file.
	 * @return Integer The id of the new file in the database.
	 */
	public function newFileFromString( $sString_, $sFilename_ ) {
		$rFp = $this->__createTempResource();
		fwrite( $rFp, $sString_ );
		fseek( $rFp, 0 );
		$iFileId = $this->newFileFromResource( $rFp, $sFilename_ );
		fclose( $rFp );
		return $iFileId;
	}

	/**
	 * Replaces a file in the database with a file directly from a string.
	 * @param $iFileId_ Integer The id of a file in the database.
	 * @param $sString_ String The data for the file.
	 * @param $sFilename_ String The name of the file.
	 * @return Integer The id of the file in the database.
	 */
	public function replaceFileFromString( $iFileId_, $sString_, $sFilename_ ) {
		$rFp = $this->__createTempResource();
		fwrite( $rFp, $sString_ );
		fseek( $rFp, 0 );
		$iFileId = $this->replaceFileFromResource( $iFileId_, $rFp, $sFilename_ );
		fclose( $rFp );
		return $iFileId;
	}

	/**
	 * Creates a file from an open resource, which can point to virtually anywhere.
	 * @param $rResource_ Resource The open resource where the data should be read.
	 * @param $sFilename_ String The name of the file.
	 * @return Integer The id of the new file in the database.
	 */
	public function newFileFromResource( $rResource_, $sFilename_ ) {

		// First we're going to create the file record in the database.
		try {
			$oResultFile = $this->_oDb->executeQuery( "
				INSERT INTO `%s_files`
				SET `filename`='%s',
				`created`=NOW()
			", dbc_Database_MySQL_Storage::TABLE_PREFIX, $sFilename_ );
		} catch( Exception $oException_ ) {

			// It appears as if the insert failed. This might be due to the fact that the database tables haven't
			// been created yet.
			$this->__createFilesTable();
			return $this->newFileFromResource( $rResource_, $sFilename_ );
		}

		$iFileId = $oResultFile->getInsertId();
		$aData = $this->__replaceDataFromResource( $iFileId, $rResource_ );

		// Once we've received the data (and the file isn't too large) we can determine extra information for this file.
		$iWidth = $iHeight = NULL;
		if (
			isset( $aData['data'] ) &&
			function_exists( 'imagecreatefromstring' ) &&
			false !== ( $oImage = @imagecreatefromstring( $aData['data'] ) )
		) {
			$iWidth = imagesx( $oImage );
			$iHeight = imagesy( $oImage );
		}

		// Next we're going to determine the mimetype of the file. The standard mime type is application/octet, but we're
		// trying to find a better one.
		$aParts = preg_split( '/[_\.]/i', $sFilename_ );
		$sExtension = strtolower( array_pop( $aParts ) );
		$sMime = 'application/octet-stream';
		if ( isset( $this->_aExtensions[$sExtension] ) ) $sMime = $this->_aExtensions[$sExtension];

		// Finally we're going to add the file details to the file record we've created above. First we're going to create
		// a basic query.
		$sQuery = sprintf( "
			UPDATE `%s_files`
			SET `filesize`='%d',
			`mimetype`='%s',
			`datatable`='%s'
		", dbc_Database_MySQL_Storage::TABLE_PREFIX, $aData['size'], $sMime, $aData['datatable'] );
		if ( $iWidth !== NULL ) $sQuery .= sprintf( ",
			`width`='%d',
			`height`='%d'
		", $iWidth, $iHeight );
		$this->_oDb->executeQuery( $sQuery . "
			WHERE `id`=%d
		", $iFileId );

		// Finally we're going to return the file id.
		return $iFileId;
	}

	/**
	 * Replaces a file in the database with a file from an open resource, which can point to virtually anywhere.
	 * @param $rResource_ Resource The open resource where the data should be read.
	 * @param $sFilename_ String The name of the file.
	 * @return Integer The id of the file in the database.
	 */
	public function replaceFileFromResource( $iFileId_, $rResource_, $sFilename_ ) {
		$aData = $this->__replaceDataFromResource( $iFileId_, $rResource_ );

		// Once we've received the data (and the file isn't too large) we can determine extra information for this file.
		$iWidth = $iHeight = NULL;
		if (
			isset( $aData['data'] ) &&
			function_exists( 'imagecreatefromstring' ) &&
			false !== ( $oImage = @imagecreatefromstring( $aData['data'] ) )
		) {
			$iWidth = imagesx( $oImage );
			$iHeight = imagesy( $oImage );
		}

		// Next we're going to determine the mimetype of the file. The standard mime type is application/octet, but we're
		// trying to find a better one.
		$aParts = preg_split( '/[_\.]/i', $sFilename_ );
		$sExtension = strtolower( array_pop( $aParts ) );
		$sMime = 'application/octet-stream';
		if ( isset( $this->_aExtensions[$sExtension] ) ) $sMime = $this->_aExtensions[$sExtension];

		// Finally we're going to add the file details to the file record we've created above. First we're going to create
		// a basic query.
		$sQuery = sprintf( "
			UPDATE `%s_files`
			SET `filename`='%s',
			`filesize`='%d',
			`mimetype`='%s',
			`datatable`='%s'
		", dbc_Database_MySQL_Storage::TABLE_PREFIX, $sFilename_, $aData['size'], $sMime, $aData['datatable'] );
		if ( $iWidth !== NULL ) $sQuery .= sprintf( ",
			`width`='%d',
			`height`='%d'
		", $iWidth, $iHeight );
		$this->_oDb->executeQuery( $sQuery . "
			WHERE `id`=%d
		", $iFileId_ );

		// Finally we're going to return the file id.
		return $iFileId_;

	}

	/**
	 * Use this function to return an array with files that match the provided filename.
	 * @param $sFilename_ String The filename to search for in the database.
	 * @return Array An array containing the files that match the filename.
	 */
	public function findFiles( $sFilename_ ) {
		$oResultFiles = $this->_oDb->executeQuery( "
			SELECT *
			FROM `%s_files`
			WHERE `filename`='%s'
		", dbc_Database_MySQL_Storage::TABLE_PREFIX, $sFilename_ );
		return $oResultFiles->all();
	}

	/**
	 * Get an array containing file information for the file with the supplied file id.
	 * @param $iFileId_ Integer The id of the file.
	 * @return Array An array containing file information or FALSE if the file was not found.
	 */
	public function getFileInfo( $iFileId_ ) {
		$aResult = $this->__getFileRecord( $iFileId_ );
		if ( $aResult !== false ) {
			unset( $aResult['datatable'] );
			unset( $aResult['created'] );
			return $aResult;
		} else return false;
	}

	/**
	 * Deletes a file from the database.
	 * @param $iFileId_ Integer The id of the file which should be deleted.
	 * @return Boolean TRUE if the file was deleted, or FALSE if the file could not be found.
	 */
	public function deleteFile( $iFileId_ ) {

		// Let's fetch the file from the database.
		$aFile = $this->__getFileRecord( $iFileId_ );
		if ( $aFile === false ) return false;

		// Let's remove the data and all it's variations from the datatable.
		$this->_oDb->executeQuery( "
			DELETE FROM `%s`
			WHERE `fileid`=%d
		", $aFile['datatable'], $aFile['id'] );

		// Let's remote the file from the files table.
		$this->_oDb->executeQuery( "
			DELETE FROM `%s_files`
			WHERE `id`=%d
		", dbc_Database_MySQL_Storage::TABLE_PREFIX, $aFile['id'] );

		$this->__optimizeWhenNeeded( $aFile['datatable'] );
		return true;
	}

	/**
	 * Deletes all file variations from the database but leaves the original file in tact.
	 * @param $iFileId_ Integer The id of the file which alternatives should be deleted.
	 * @return Boolean TRUE if the file alternatives were deleted, or FALSE if the file could not be found.
	 */
	public function deleteFileAlternatives( $iFileId_ ) {

		// Let's fetch the file from the database.
		$aFile = $this->__getFileRecord( $iFileId_ );
		if ( $aFile === false ) return false;

		// Let's remove all the variations of this file from the datatable.
		$this->_oDb->executeQuery( "
			DELETE FROM `%s`
			WHERE `fileid`=%d
			AND `hash` IS NOT NULL
		", $aFile['datatable'], $aFile['id'] );

		$this->__optimizeWhenNeeded( $aFile['datatable'] );
		return true;
	}

	/**
	 * Enable or disable caching of file alternatives. With caching OFF a new alternative file is created on
	 * every request. This is quite resource intensive and should be avoided in production mode.
	 * @param $bUseCache_ Boolean TRUE to turn caching on (default) or FALSE to turn caching off.
	 * @return Void
	 */
	public function useCache( $bUseCache_ = true ) {
		$this->_bUseCache = $bUseCache_;
	}

	/**
	 * Returns the binary data of an original file from the database.
	 * @param $iFileId_ Integer The id of the file which should be returned.
	 * @param $bFlush_ Boolean If set to TRUE the file is sent to the browser with the proper headers. Note that
	 * script execution is ended when the file is flushed. Flushing a file with getFile supports contant ranges,
	 * which allows a browser to download partial files.
	 * @return Binary Returns the data for the requested file.
	 */
	public function getFile( $iFileId_, $bFlush_ = false ) {

		// If the user wants to flush the file we need to set the proper headers.
		if ( $bFlush_ ) {
			$aFile = $this->__getFileRecord( $iFileId_ );
			if ( $aFile === false ) throw new Exception( 'file not found' );

			// Let's set the proper headers before we're going to flush the file.
			header( 'Content-Transfer-Encoding: binary' );
			header( "Content-Type: {$aFile['mimetype']}" );
			if ( strstr( $_SERVER['HTTP_USER_AGENT'], 'MSIE' ) ) {
				$aFile['filename'] = preg_replace( '/\./', '%2e', $aFile['filename'], substr_count( $aFile['filename'], '.' ) - 1);
			}
			header( "Content-Disposition: inline; filename=\"{$aFile['filename']}\"");
			header( 'Accept-Ranges: bytes' );
			header( 'Cache-Control: private' );
			header( 'Pragma: private' );
			set_time_limit(0);
		}

		// Let's fetch the bytes for this file. If the file was not found we're throwing an exception.
		$bData = $this->__getBytes( $iFileId_, NULL, $bFlush_ );

		// If we need to return the files then let's do so, otherwise we're done flushing.
		if ( ! $bFlush_ ) {
			if ( $bData == false ) throw new Exception( 'file not found' );
			return $bData;
		} else exit; // flushing will end the script
	}

	/**
	 * Returns the binary data of an image or the binary data of an image alternative from the database.
	 * @param $iFileId_ Integer The id of the image which should be returned.
	 * @param $aOptions_ Array The options for the image which should be returned. These options are supported:
	 * <ul>
	 * <li><b>quality</b> : The image jpeg compression to apply ( 1 to 100 ).</li>
	 * <li><b>width</b> : The width of an image.</li>
	 * <li><b>height</b> : The height of an image.</li>
	 * <li><b>zoom</b> : Zooms the image so that it fits exactly within the supplied width and height. This might
	 * cause the image to be cut off on one of it's axes.</li>
	 * <li><b>grow</b> : If the image is smaller than the supplied width and height, the image is made larger.</li>
	 * <li><b>contrast</b> : Changes the contrast of the image ( -100 to +100 ).</li>
	 * <li><b>brightness</b> : Changes the contrast of the image ( -255 to +255 ).</li>
	 * <li><b>unsharp</b> : Applies a unsharp mask to the image ( 0 to 500 ). It is adviced to apply an unsharp
	 * mask after the image is resized.</li>
	 * <li><b>overlay</b> : An image that is to be drawn on top of the original image.</li>
	 * <li><b>valign</b> : Used in combination with overlay, determines the vertical position of the overlay
	 * ( top, middle, bottom ).</li>
	 * <li><b>halign</b> : Used in combination with overlay, determines the horizontal position of the overlay
	 * ( left, center, right ).</li>
	 * </ul>
	 * @param $bFlush_ Boolean If set to TRUE the image is sent to the browser with the proper headers. Note that
	 * script execution is ended when the file is flushed. Flushing an image doesn't support content ranges.
	 * @return Binary Returns the data for the requested image.
	 */
	public function getImage( $iFileId_, $aOptions_ = array(), $bFlush_ = false ) {

		// Let's get this file from the database. We need this later on to determine if transparency is required or
		// not, and maybe for other things aswell. Bottom line is that we need the file, period.
		$aFile = $this->__getFileRecord( $iFileId_ );
		if ( $aFile === false ) {
			throw new Exception( 'file not found' );
		}

		// If we need to flush the file we need to set the proper headers.
		if ( $bFlush_ ) {
			header( 'Content-Transfer-Encoding: binary' );
			header( 'Content-Type: image/jpeg' );
			if ( strstr( $_SERVER['HTTP_USER_AGENT'], 'MSIE' ) ) {
				$aFile['filename'] = preg_replace( '/\./', '%2e', $aFile['filename'], substr_count( $aFile['filename'], '.' ) - 1);
			}
			header( "Content-Disposition: inline; filename=\"{$aFile['filename']}\"");
			header( 'Cache-Control: private' );
			header( 'Pragma: private' );
		}



		// First we're going to create a hash of the options array. If a file with that hash already exists we need
		// to return the existing one. If caching is turned of we should always create a new file.
		if (
			is_array( $aOptions_ ) &&
			count( $aOptions_ ) > 0
		) {
			$sHash = sha1( serialize( $aOptions_ ) );
		} else $sHash = NULL;
		if ( $this->_bUseCache ) {
			$bData = $this->__getBytes( $iFileId_, $sHash );
			if ( $bData !== false ) {

				// Let's see if we need to flush the file or not.
				if ( $bFlush_ ) {
					header( 'Content-Length: ' . mb_strlen( $bData ) );
					echo $bData;
					exit;
				} else return $bData;
			}
		}



		// It appears as if this variant of the image was not yet created, so we're going to do that here. First we're
		// fetching the original file.
		$bData = $this->__getBytes( $iFileId_ );
		if ( $bData == false ) throw new Exception( 'file not found' );

		// Let's see if we can create a valid image from the source file. If not, then the file is probably not a
		// valid image and we're throwing an exception.
		$rImage = @imagecreatefromstring( $bData );
		if ( $rImage === false ) throw new Exception( 'file is not an image' );




		// Depending on the options we need to perform some basic image actions on the image we've created earlier.
		if (
			isset( $aOptions_['width'] ) ||
			isset( $aOptions_['height'] )
		) {

			// Somehow we need to resize this image. Some other parameters might determine HOW the resize should take
			// place. First we're zooming the image, which makes it exactly the specified width and height. This means
			// that the image is most likely to be cut on it's edges.
			if ( isset( $aOptions_['zoom'] ) ) {
				if (
					empty( $aOptions_['width'] ) ||
					empty( $aOptions_['height'] )
				) throw new Exception( 'zooming an image requires a width AND a height' );

				$iFactor = imagesx( $rImage ) / $aOptions_['width'];
				if ( ( imagesy( $rImage ) / $iFactor ) < $aOptions_['height']) $iFactor = imagesy( $rImage ) / $aOptions_['height'];
				$rResized = imagecreatetruecolor( $aOptions_['width'], $aOptions_['height'] );

				// If the original image is a png (which allows for transparency) we need to create a png ourselves aswell.
				if ( $aFile['mimetype'] == 'image/png' ) {
					imagealphablending( $rResized, true );
					imagesavealpha( $rResized, true );
					$oBg = imagecolorallocatealpha( $rResized, 255, 255, 255, 127 );
					imagefill( $rResized, 0, 0 , $oBg );

				}

				imagefill( $rResized, 0, 0, imagecolorallocate( $rResized, 255, 255, 255 ) );
				imagecopyresampled( $rResized, $rImage, 0, 0, ceil( ( imagesx( $rImage ) - ceil( $aOptions_['width'] * $iFactor ) ) / 2), ceil( ( imagesy( $rImage ) - ceil( $aOptions_['height'] * $iFactor ) ) / 2 ), $aOptions_['width'], $aOptions_['height'], ceil( $aOptions_['width'] * $iFactor ), ceil( $aOptions_['height'] * $iFactor ) );
				imagedestroy( $rImage );
				$rImage = & $rResized;
			} else {

				// We don't have to zoom, so a normal resize should take place. The image will fit exactly within the
				// specified boundaries. One of it's axis will most likely be smaller than the specified width or height.
				// IF the grow setting was specified the image will grow so that one axis is exactly the specified width
				// or height.
				if ( ! isset( $aOptions_['width'] ) ) $aOptions_['width'] = isset( $aOptions_['grow'] ) ? 999999 : imagesx( $rImage );
				if ( ! isset( $aOptions_['height'] ) ) $aOptions_['height'] = isset( $aOptions_['grow'] ) ? 999999 : imagesy( $rImage );

				$iFactor = imagesx( $rImage ) / $aOptions_['width'];
				$iNewWidth = round( imagesx( $rImage ) / $iFactor );
				$iNewHeight = round( imagesy( $rImage ) / $iFactor );
				if ( $iNewHeight > $aOptions_['height'] ) {
		            $iFactor = $iNewHeight / $aOptions_['height'];
		            $iNewWidth = round( $iNewWidth / $iFactor);
		            $iNewHeight = round( $iNewHeight / $iFactor);
				}
				$rResized = imagecreatetruecolor( $iNewWidth, $iNewHeight );

				// If the original image is a png (which allows for transparency) we need to create a png ourselves aswell.
				if ( $aFile['mimetype'] == 'image/png' ) {
					imagealphablending( $rResized, true );
					imagesavealpha( $rResized, true );
					$oBg = imagecolorallocatealpha( $rResized, 255, 255, 255, 127 );
					imagefill( $rResized, 0, 0 , $oBg );

				}

				imagecopyresampled( $rResized, $rImage, 0, 0, 0, 0, $iNewWidth, $iNewHeight, imagesx( $rImage ), imagesy( $rImage ) );
				imagedestroy( $rImage );
				$rImage = & $rResized;
			}
		}




		// Let's see if we need to apply filters to this image variation.
		if ( isset( $aOptions_['contrast'] ) && function_exists( 'imagefilter' ) ) imagefilter( $rImage, IMG_FILTER_CONTRAST, $aOptions_['contrast'] );
		if ( isset( $aOptions_['brightness'] ) && function_exists( 'imagefilter' ) ) imagefilter( $rImage, IMG_FILTER_BRIGHTNESS, $aOptions_['brightness'] );
		if ( isset( $aOptions_['unsharp'] ) && function_exists( 'imagefilter' ) ) {

			// The unsharp mask is necessary if the image is scaled. It can produce much nicer results if applied right
			// after the resize.
			if ( $aOptions_['unsharp'] > 500 ) $aOptions_['unsharp'] = 500;
			$aOptions_['unsharp'] = $aOptions_['unsharp'] * 0.016;

			$iWidth = imagesx( $rImage );
			$iHeight = imagesy( $rImage );
			$rCanvas = imagecreatetruecolor( $iWidth, $iHeight );
			$rBlur = imagecreatetruecolor( $iWidth, $iHeight );

			if ( function_exists( 'imageconvolution' ) ) { // PHP >= 5.1
				$aMatrix = array(
					array( 1, 2, 1 ),
					array( 2, 4, 2 ),
					array( 1, 2, 1 )
				);
				imagecopy( $rBlur, $rImage, 0, 0, 0, 0, $iWidth, $iHeight );
				imageconvolution( $rBlur, $aMatrix, 16, 0 );
			} else {

				imagecopy( $rBlur, $rImage, 0, 0, 1, 0, $iWidth - 1, $iHeight );
				imagecopymerge( $rBlur, $rImage, 1, 0, 0, 0, $iWidth, $iHeight, 50 );
				imagecopymerge( $rBlur, $rImage, 0, 0, 0, 0, $iWidth, $iHeight, 50 );
				imagecopy( $rCanvas, $rBlur, 0, 0, 0, 0, $iWidth, $iHeight );
				imagecopymerge( $rBlur, $rCanvas, 0, 0, 0, 1, $iWidth, $iHeight - 1, 33.33333 );
				imagecopymerge( $rBlur, $rCanvas, 0, 1, 0, 0, $iWidth, $iHeight, 25 );
			}
			for ($x = 0; $x < $iWidth; $x++) {
				for ($y = 0; $y < $iHeight; $y++) {
					$rgbOrig = imagecolorat($rImage, $x, $y);
					$rOrig = (($rgbOrig >> 16) & 0xFF);
					$gOrig = (($rgbOrig >> 8) & 0xFF);
					$bOrig = ($rgbOrig & 0xFF);

					$rgbBlur = imagecolorat($rBlur, $x, $y);

					$iRBlur = (($rgbBlur >> 16) & 0xFF);
					$iGBlur = (($rgbBlur >> 8) & 0xFF);
					$iBBlur = ($rgbBlur & 0xFF);

					$rNew = ($aOptions_['unsharp'] * ($rOrig - $iRBlur)) + $rOrig;
					if ($rNew > 255) { $rNew=255; }
					elseif ($rNew < 0) { $rNew=0; }
					$gNew = ($aOptions_['unsharp'] * ($gOrig - $iGBlur)) + $gOrig;
					if ($gNew > 255) { $gNew=255; }
					elseif ($gNew < 0) { $gNew=0; }
					$bNew = ($aOptions_['unsharp'] * ($bOrig - $iBBlur)) + $bOrig;
					if ($bNew > 255) { $bNew=255; }
					elseif ($bNew < 0) { $bNew=0; }
					$rgbNew = ($rNew << 16) + ($gNew <<8) + $bNew;
					imagesetpixel($rImage, $x, $y, $rgbNew);
				}
			}

			imagedestroy($rCanvas);
			imagedestroy($rBlur);
		}

		// Finally we're applying a mask if necessary. If the mask could not be found we're throwing an exception.
		if ( isset( $aOptions_['overlay'] ) ) {
			if ( ! @is_file( $aOptions_['overlay'] ) ) throw new Exception( 'overlay file not found' );
			$rOverlay = @imagecreatefromstring( @file_get_contents( $aOptions_['overlay'] ) );
			if ( ! is_resource( $rOverlay ) ) throw new Exception( 'overlay is not a valid image' );
			if (
				! isset( $aOptions_['valign'] ) ||
				! in_array( $aOptions_['valign'], array( 'top', 'middle', 'bottom' ) )
			) $aOptions_['valign'] = 'middle';
			if (
				! isset( $aOptions_['halign'] ) ||
				! in_array( $aOptions_['halign'], array( 'left', 'center', 'right' ) )
			) $aOptions_['halign'] = 'center';

			// Let's see where we need to add this overlay. You can use the valign and halign parameters.
			switch( $aOptions_['valign'] ) {
				case 'top':
					$iY = 0;
					break;
				case 'middle':
					$iY = round( ( imagesy( $rImage ) / 2) - ( imagesy( $rOverlay ) / 2) );
					break;
				case 'bottom':
					$iY = imagesy( $rImage ) - imagesy( $rOverlay );
					break;
			}
			switch( $aOptions_['halign'] ) {
				case 'left':
					$iX = 0;
					break;
				case 'center':
					$iX = round( ( imagesx( $rImage ) / 2) - ( imagesx( $rOverlay ) / 2) );
					break;
				case 'right':
					$iX = imagesx( $rImage ) - imagesx( $rOverlay );
					break;
			}
			imagecopyresampled( $rImage, $rOverlay, $iX, $iY, 0, 0, imagesx( $rOverlay ), imagesy( $rOverlay ), imagesx( $rOverlay ), imagesy( $rOverlay ) );
			imagedestroy( $rOverlay );
		}




		// Finally we're going to create a new image and have it stored in the database using the hash we've created
		// earler. Next time this function is called with the same parameters we can return this new file instead of
		// creating a brand new one.
		ob_start();



		// Depending on the mime type we're either going to create a jpeg or a png.
		if ( $aFile['mimetype'] == 'image/png' ) {
			imagepng( $rImage );
		} else {
			imagejpeg( $rImage, NULL, isset( $aOptions_['quality'] ) ? (integer) $aOptions_['quality'] : 85 );
		}

		$bData = ob_get_clean();

		$rFpTemp = $this->__createTempResource( $bData );
		$this->__replaceDataFromResource( $iFileId_, $rFpTemp, $sHash );




		// Finally we're going to return the data of this new file.
		if ( $bFlush_ ) {
			header( 'Content-Length: ' . mb_strlen( $bData ) );
			echo $bData;
			exit;
		} else return $bData;
	}

	/**
	 * Flushed the binary data of an original file from the database directly to the browser, using the correct headers.
	 * @param $iFileId_ Integer The id of the file which should be returned. This function offers support for partial
	 * downloads if the proper content-range headers are set by the browser.
	 * @return Void
	 */
	public function flushFile( $iFileId_ ) {
		$this->getFile( $iFileId_, true );
	}

	/**
	 * Flushes the binary data of an image or the binary data of an image alternative from the database directly to
	 * the browser, using the correct headers. Look at the {@link dbc_Database_MySQL_Storage::getImage()} function
	 * for more information about the option possibilities.
	 * @param $iFileId_ Integer The id of the image which should be returned.
	 * @param $aOptions_ Array The options for the image which should be returned. These options are supported:
	 * @return Void
	 */
	public function flushImage( $iFileId_, $aOptions_ = array() ) {
		$this->getImage( $iFileId_, $aOptions_, true );
	}

	/**
	 * @ignore
	 * @internal
	 */
	private function __createFilesTable() {
		try {
			$this->_oDb->executeQuery( "
				CREATE TABLE IF NOT EXISTS `%s_files` (
					`id` mediumint(8) unsigned NOT NULL auto_increment,
					`filename` varchar(255) NOT NULL default 'no name',
					`filesize` int(12) unsigned NOT NULL default '0',
					`mimetype` varchar(255) NOT NULL default 'application/octet-stream',
					`width` smallint(5) unsigned default NULL,
					`height` smallint(5) unsigned default NULL,
					`datatable` varchar(%d) NOT NULL default '%s_filedata000001',
					`created` datetime NOT NULL,
					PRIMARY KEY  (`id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8
			", dbc_Database_MySQL_Storage::TABLE_PREFIX, strlen( dbc_Database_MySQL_Storage::TABLE_PREFIX ) + 15, dbc_Database_MySQL_Storage::TABLE_PREFIX );
		} catch( Exception $oException_ ) {
			throw new Exception( 'unable to create storage tables' );
		}
	}

	/**
	 * @ignore
	 * @internal
	 */
	private function __getFileRecord( $iFileId_ ) {

		// If we've fetched the file before we're going to return that record.
		if ( isset( $this->_aFiles[$iFileId_] ) ) return $this->_aFiles[$iFileId_];

		// The file was not fetched before. Let's fetch it now and store it in our cache array.
		$oResultFile = $this->_oDb->executeQuery( "
			SELECT *
			FROM `%s_files`
			WHERE `id`=%d
		", dbc_Database_MySQL_Storage::TABLE_PREFIX, $iFileId_ );
		if ( $oResultFile !== false ) {
			if ( $oResultFile->numRows() == 0 ) return false;
			return ( $this->_aFiles[$iFileId_] = $oResultFile->first() );
		} else {
			return FALSE;
		}
	}

	/**
	 * @ignore
	 * @internal
	 */
	private function __createTempResource( $sData_ = NULL ) {
		$rFpTemp = @fopen("php://memory", 'rb+');
		if ( ! is_resource( $rFpTemp ) ) $rFpTemp = @tmpfile();
		if ( ! is_resource( $rFpTemp ) ) throw new Exception( 'unable to create temporary file' );
		if ( is_string( $sData_ ) ) {
			fwrite( $rFpTemp, $sData_ );
			fseek( $rFpTemp, 0 );
		}
		return $rFpTemp;
	}

	/**
	 * @ignore
	 * @internal
	 */
	private function __getBytes( $iFileId_, $sHash_ = NULL, $bFlush_ = false ) {

		// Let's fetch the file from the database.
		$aFile = $this->__getFileRecord( $iFileId_ );
		if ( $aFile === false ) return false;

		// First we're going to fetch the filedata_ids. We don't want the data just yet. If the user has provided
		// a hash we need to fetch the data that corresponds to the hash.
		$sQuery = sprintf( "
			SELECT `id`
			FROM `%s`
			WHERE `fileid`='%d'
		", $aFile['datatable'], $aFile['id'] );
		if ( is_string( $sHash_ ) ) $sQuery .= sprintf( "
			AND `hash`='%s'
		", $sHash_ );
		$sQuery .= "
			ORDER BY `id`
		";
		$oResultNodes = $this->_oDb->executeQuery( $sQuery );
		if ( $oResultNodes->numRows() == 0 ) return false;

		// If we need to flush the data we might have to calculate which parts to flush depending on headers.
		if ( $bFlush_ ) {

			if ( isset( $_SERVER['HTTP_RANGE'] ) ) {

				// The HTTP_RANGE header was set, so let's parse it's contents here.
				$aRangeParts = explode( '=', $_SERVER['HTTP_RANGE'] );
				$aRangeParts = explode( ',', $aRangeParts[1], 2 );
				list( $iStart, $iEnd ) = explode( '-', $aRangeParts[0], 2 );

				// If one of the two parameters has been left out we need to use the maximum possible value. So that means
				// zero from the start and filesize - 1 on the end.
				if ( empty( $iStart ) ) $iStart = 0;
				if ( empty( $iEnd ) ) $iEnd = $aFile['filesize'] - 1;

				$iStart = min( $aFile['filesize'] - 1, $iStart, $iEnd );
				$iEnd = min( $aFile['filesize'] - 1, $iEnd );
				$iLength = $iEnd - $iStart + 1;

				// Let's also set the correct headers for this partial content.
				header( "HTTP/1.1 206 Partial Content" );
				header( "Content-Range: bytes {$iStart}-{$iEnd}/{$aFile['filesize']}" );
				header( "Content-Length: {$iLength}" );
			} else {

				// The HTTP_RANGE header was NOT set, so let's send the entire file.
				$iStart = 0;
				$iEnd = $aFile['filesize'] - 1;
				$iLength = $aFile['filesize'];
				header( "Content-Length: {$iLength}" );
			}
		}

		// Then we're going to iterate through the list of nodes and add the data to the result string.
		$sResult = '';
		$iFileOffset = 0;
		while( false !== ( $aNode = $oResultNodes->next() ) ) {

			// Let's fetch the data for this node.
			$oResultData = $this->_oDb->executeQuery( "
				SELECT `data`
				FROM `%s`
				WHERE `id`='%d'
			", $aFile['datatable'], $aNode['id'] );
			list( $sData ) = $oResultData->first( false );

			// If we need to flush the file we need to check if we need to send this part of the file aswell.
			if ( $bFlush_ ) {
				if (
					$iStart < $iFileOffset + mb_strlen( $sData ) &&
					$iEnd >= $iFileOffset
				) {
					echo mb_substr( $sData,
						max( 0, $iStart - $iFileOffset ),
						$iEnd - $iStart + 1
					);
				}
				$iFileOffset += mb_strlen( $sData );
			} else $sResult .= $sData;
		}

		// Let's return the file data.
		return $sResult;
	}

	/**
	 * @ignore
	 * @internal
	 */
	private function __optimizeWhenNeeded( $sDataTable_ ) {
		$oResultStatus = $this->_oDb->executeQuery( "
			SHOW TABLE STATUS LIKE '%s'
		", $sDataTable_ );
		if ( $oResultStatus->numRows() > 0 ) {
			$aDataTable = $oResultStatus->first();
			if ( $aDataTable['Data_free'] > $this->_iOptimizeThreshold ) {
				$this->_oDb->executeQuery( "
					OPTIMIZE TABLE `%s`
				", $sDataTable_ );
				$this->_oDb->executeQuery( "
					OPTIMIZE TABLE `%s_files`
				", dbc_Database_MySQL_Storage::TABLE_PREFIX );
			}
		}
	}

	/**
	 * @ignore
	 * @internal
	 */
	private function __replaceDataFromResource( $iFileId_, $rResource_, $sHash_ = NULL ) {

		// Let's fetch the file from the database.
		$aFile = $this->__getFileRecord( $iFileId_ );
//		if ( $aFile === false ) throw new Exception( 'file not found' );

		// First we're going to remove any existing records in the datatable if there are any.
		if (
			$aFile !== FALSE &&
			! empty( $aFile['datatable'] ) &&
			$this->_oDb->hasTable( $aFile['datatable'] )
		) {
			$sQuery = sprintf( "
				DELETE FROM `%s`
				WHERE `fileid`=%d
			", $aFile['datatable'], $aFile['id'] );
			if ( ! is_null( $sHash_ ) ) $sQuery .= sprintf( "
				AND `hash`='%s'
			", $sHash_ );
			$oResultClear = $this->_oDb->executeQuery( $sQuery );
			if ( $oResultClear->affectedRows() > 0 ) {
				$this->__optimizeWhenNeeded( $aFile['datatable'] );
			}
		}

		// First we need to determine the next free data table. If there's a hash provided we need to make sure
		// the file alternative is stored in the same datatable.
		if (
			is_null( $sHash_ ) ||
			$aFile === FALSE
		) {
			$oResultStatus = $this->_oDb->executeQuery( "
				SHOW TABLE STATUS LIKE '%s_filedata%%'
			", dbc_Database_MySQL_Storage::TABLE_PREFIX );
			$aDataTables = $oResultStatus->all();
			$sDataTable = false;
			foreach( $aDataTables as $aDataTable ) if ( $aDataTable['Data_length'] < $this->_iMaxTableSize ) {
				$sDataTable = $aDataTable['Name'];
				break;
			}
		} else $sDataTable = $aFile['datatable'];

		// If no datatable was found now, we need to create a new one, starting with index one.
		if ( $sDataTable === false ) {
			$sDataTable = dbc_Database_MySQL_Storage::TABLE_PREFIX . '_filedata' . str_pad( count( $aDataTables ) + 1, 6, '0', STR_PAD_LEFT );
			try {
				$this->_oDb->executeQuery( "
					CREATE TABLE IF NOT EXISTS `%s` (
						`id` int(10) unsigned NOT NULL auto_increment,
						`fileid` mediumint(8) unsigned NOT NULL,
						`hash` varchar(64) default NULL,
						`data` blob NOT NULL,
						PRIMARY KEY  (`id`),
						KEY `fileid` (`fileid`),
						KEY `hash` (`hash`)
					) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Created: %s'
				", $sDataTable, date( 'd-m-Y H:i' ) );
			} catch( Exception $oException_ ) {
				throw new Exception( 'unable to create storage tables' );
			}
		}

		// First we're going to create a local copy of the file, because remote file operations are very unreliabe :-(
		$rFpTemp = $this->__createTempResource();
		while ( ! feof( $rResource_ ) ) fwrite( $rFpTemp, fread( $rResource_, 65535 ) );
		fseek( $rFpTemp, 0, SEEK_SET );
		$aStat = fstat( $rFpTemp );

		// Next we're going to store the data of the temporary file in the database.
		$sFileData = '';
		while ( ! feof( $rFpTemp ) ) {
			$sData = fread( $rFpTemp, 65535 );
			if ( $aStat['size'] < $this->_iMaxInMemory ) $sFileData .= $sData;

			// Here we're going to insert the data into the database.
			$oResultData = $this->_oDb->executeQuery( "
				INSERT INTO `%s`
				SET `fileid`='%d',
				`data`='%s'
			", $sDataTable, $iFileId_, $sData );

			// If the user has provided a hash we need to store it along side the data.
			if ( ! empty( $sHash_ ) ) $this->_oDb->executeQuery( "
				UPDATE `%s`
				SET `hash`='%s'
				WHERE `id`=%d
			", $sDataTable, $sHash_, $oResultData->getInsertId() );
		}

		// Let's close the temporaty file.
		fclose( $rFpTemp );

		// Finally we're going to return an array with the characteristics of this new file. If the file was below the
		// threshold we're returning the data of the file aswell.
		$aResult = array(
			'size' => $aStat['size'],
			'datatable' => $sDataTable
		);
		if ( $aStat['size'] < $this->_iMaxInMemory ) $aResult['data'] = $sFileData;
		return $aResult;
	}

}
?>