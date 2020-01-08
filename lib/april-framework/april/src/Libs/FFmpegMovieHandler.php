<?php

namespace April\Libs;

class FFmpegMovieHandler{

    /**
     * The file we will be working with
     * @var String
     */
    private $_sFile;
    private $_iFrameCount   = false;
    private $_iFrameRate    = false;
    private $_iDuration     = false;
    private $_iWidth        = false;
    private $_iHeight       = false;



    public function __construct($sFile_){
        //Let us check if a file is given
        if(empty($sFile_)) return false;
        //Let us check if the file exists
        if(!file_exists($sFile_)) throw new \Exception('File does not exist');
        //Save the filename to self
        $this->_sFile = $sFile_;
    }

    /**
     * 
     * Get the framecount of the object file
     * @return number framecount
     */
    public function getFrameCount(){
        if(empty($this->_iFrameCount)){
            $sCommand = "ffprobe -v error -count_frames -select_streams v:0 -show_entries stream=nb_read_frames -of default=nokey=1:noprint_wrappers=1 '{$this->_sFile}'";
            exec($sCommand,$aOut);
            if(is_numeric($aOut[0])){
                $this->_iFrameCount = $aOut[0];
            }
        }

        return $this->_iFrameCount;
    }
 
    /**
     * Get the framerate of the object file
     * @return number framerate
     */
    public function getFrameRate(){
        if(empty($this->_iFrameRate)){
            $sCommand = 'ffprobe "'.$this->_sFile.'" 2>&1| grep ",* fps" | cut -d "," -f 5 | cut -d " " -f 2';
            exec($sCommand,$aOut);
            if(is_numeric($aOut[0])){
                $this->_iFrameRate = $aOut[0];
            }
        }

        return $this->_iFrameRate;
    }
    /**
     * Get the duration of the object file
     * @return number duration
     */
    public function getDuration(){
        if(empty($this->_iDuration)){
            $sCommand = "ffprobe '".$this->_sFile."' -show_format 2>&1 | sed -n 's/duration=//p' ";
            exec($sCommand,$aOut);
            if(is_numeric($aOut[0])){
                $this->_iDuration = round($aOut[0], 0, PHP_ROUND_HALF_UP);
            }
        }

        return $this->_iDuration;
    }
    /**
     * Get the framewidth of the object file
     * @return number framewidth
     */
    public function getFrameWidth(){
        if(empty($this->_iWidth)){
            $sCommand = "ffprobe -show_streams '".$this->_sFile."' 2> /dev/null | grep width= | sed '1 s/.*\=//'";
            exec($sCommand,$aOut);
            if(is_numeric($aOut[0])){
                $this->_iWidth = $aOut[0];
            }
        }

        return $this->_iWidth;
    }
    /**
     * Get the frameheight of the object file
     * @return number frameheight
     */
    public function getFrameHeight(){
        if(empty($this->_iHeight)){
            $sCommand = "ffprobe -show_streams '".$this->_sFile."' 2> /dev/null | grep height= | sed '1 s/.*\=//'";
            exec($sCommand,$aOut);
            if(is_numeric($aOut[0])){
                $this->_iHeight = $aOut[0];
            }
        }

        return $this->_iHeight;
    }
}
?>