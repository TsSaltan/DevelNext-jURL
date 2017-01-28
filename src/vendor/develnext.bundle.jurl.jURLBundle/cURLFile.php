<?php
namespace;

use php\lib\Str,
    php\net\URLConnection;

class cURLFile{
    private $file;
    public $name,
           $mime,
           $postname;

    public function __construct($file){
        $this->file = $file;
        $this->mime = URLConnection::guessContentTypeFromName($file);
        $this->name = basename($file);
    }

    public function getFilename(){
        return $this->name;
    }

    public function getMimeType(){
        return $this->mime;
    }

    public function __toString(){
        return '@' . $this->file;
    }
}