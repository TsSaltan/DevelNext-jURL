<?php
namespace app\modules;

use framework;
use std;
use bundle\http\HttpClient;
use bundle\jurl\jURL;
use php\gui\framework\AbstractModule;
use php\gui\framework\ScriptEvent; 


class AppModule extends AbstractModule
{

  /**
     * @event action 
     */
    function doAction(ScriptEvent $e = null)
    {    
        spl_autoload_register(function($called){ 
            $initPath = self::getCurrentDir() . '..\\src\\vendor\\develnext.bundle.jurl.jURLBundle\\' . $called . '.php';
            if(fs::exists($initPath)){
                Logger::info('Import bundle class "' . $called . '" from "' . $initPath . '"');
                include $initPath;
                return true;
            }
            return false;
        });
        
        include(self::getCurrentDir() . '..\\src\\vendor\\develnext.bundle.jurl.jURLBundle\\.inc\\jurl.php');

        
        //$this->test();
    }
    
    public static function getCurrentDir(){
        $path = System::getProperty("java.class.path");
        $sep = System::getProperty("path.separator");
        return dirname(realpath(str::split($path, $sep)[0])) . '\\';
    }
    
    public function test(){
        jURL::setDebugMode(false);
        $file = 'F:\WebServer\home\www\localhost\longpoll.zip';
       // $file = 'F:\DevelNextBundles\DevelNext-jURL\README.MD';
        $ch = curl_init('http://localhost/curl_server.php');

        $boundary = str::random(12);
	//curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HTTPHEADER, Array('Content-Type: multipart/form-data; boundary=' . $boundary));
        $postStream = new MemoryStream;
	$postStream->write("\n--$boundary\nContent-Disposition: form-data; name=\"file\"; filename=\"file.zip\"\n\n");
	$postStream->write(file_get_contents($file));
	$postStream->write("\r\n--" . $boundary . "--\r\n");
        $postStream->seek(0);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postStream->readFully());
			$data = curl_exec($ch);

			var_dump($data);
    }



}
