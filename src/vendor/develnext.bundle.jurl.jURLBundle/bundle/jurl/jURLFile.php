<?php
namespace bundle\jurl;

use bundle\jurl\jURLException,
    php\io\FileStream,
    php\lib\Str,
    php\net\URLConnection;
    
/**
 * @packages jurl
 */
class jURLFile{
    public $filename,
            $mimetype,
            $postname;

    public function __construct($filename, $mimetype = null, $postname = null){
        if(!is_string($filename) || !file_exists($filename)) return false;// throw new jURLException("File '$filename' not exists");
        
        $this->filename = $filename;
        $this->mimetype = (!is_null($mimetype)) ? $mimetype : URLConnection::guessContentTypeFromName($filename);
        $this->postname = (!is_null($postname)) ? $postname : basename($filename);
    }

    public function getFilename(){
        return $this->filename;
    }

    public function getStream($mode = 'r+'){
        return new FileStream($this->filename, $mode);
    }

    public function getMimeType(){
        return $this->mimetype;
    }
    
    public function getPostFilename(){
        return $this->postname;
    }

    public function setMimeType($mimetype){
        $this->mimetype = $mimetype;
    }

    public function setPostFilename($postname){
        $this->postname = $postname;
    }

    public function __toString(){
        return '@' . $this->file;
    }
}