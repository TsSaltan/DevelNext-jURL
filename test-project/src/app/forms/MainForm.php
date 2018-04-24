<?php
namespace app\forms;

use bundle\jurl\jURL;
use php\lang\System;
use php\time\Time;
use php\net\Socket;
use php\gui\framework\AbstractForm;
use php\gui\event\UXEvent; 
use php\lib\Str;
use php\gui\event\UXWindowEvent; 

/*
 * В примере встречается как процедурный curl_* стиль, так и ООП
 */
 
class MainForm extends AbstractForm
{
    /**
     * @event buttonGet.action 
     **/
    function doButtonGetAction(UXEvent $event = null)
    {
        $url = 'http://test.tssaltan.ru/curl/get.php';
        $ch = new jURL;
        curl_setopt($ch, CURLOPT_URL, $url);
        var_dump(['curl_getinfo' => curl_getinfo($ch)]);
        
        $ch->asyncExec(function($result, $ch){
            $this->textAreaGet->text = $result;
            
            var_dump(['curl_getinfo' => curl_getinfo($ch)]);
            $ch->close();
        });

    }

    /**
     * @event buttonPost.action 
     **/
    function doButtonPostAction(UXEvent $event = null)
    {
        $url = 'http://test.tssaltan.ru/curl/post.php';

        $ch = new jURL($url);
        $ch->setPostData(['a'=>'b', 'c'=>'d', 'array' => ['yes' => 1, 'no' => 'NulL']]);
        
        $ch->asyncExec(function($result) use ($ch){
        
        //var_dump(['curl_getinfo' => curl_getinfo($ch)]);
            $this->textAreaPost->text = $result;
        });
    }

    /**
     * @event buttonCookie.action 
     **/
    function doButtonCookieAction(UXEvent $event = null)
    {    
        $url = 'http://test.tssaltan.ru/curl/cookie_get.php';

        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.json');
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        curl_exec_async($ch, function($result){
            $this->textAreaCookie->text = $result;
        });
    }

    /**
     * @event buttonCookieSend.action 
     **/
    function doButtonCookieSendAction(UXEvent $event = null)
    {    
        $url = 'http://test.tssaltan.ru/curl/cookie_send.php';

        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.json');
        
        curl_exec_async($ch, function($result){
            $this->textAreaCookieSend->text = $result;
        });
    }

    /**
     * @event buttonUpload.action 
     **/
    function doButtonUploadAction(UXEvent $event = null)
    {    
        $url = 'http://test.tssaltan.ru/curl/upload.php';
        //$url = 'http://localhost/curl/upload.php';

        // Создаём новый файл, который будет загружен на сервер
        $newFile = 'testFile.txt';
        file_put_contents($newFile, 'Hello World 123456789!');

        $ch = curl_init($url);
        
        $post = [
            "action" => '123', 
            "width" => '321', 
            'file[0]' => '@'.$newFile, 
            'file[1]' =>  curl_file_create($newFile),
            'file[2]' =>  new \cURLFile($newFile)
        ];
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_POST, true);

        $this->textAreaUpload->text =  curl_exec($ch);
    }

    /**
     * @event buttonRedirect.action 
     **/
    function doButtonRedirectAction(UXEvent $event = null)
    {    
        
        $url = 'http://test.tssaltan.ru/curl/redirect.php';

        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        
        curl_exec_async($ch, function($result){
            $this->textAreaRedirect->text = $result;
        });
    }

    /**
     * @event buttonBasic.action 
     **/
    function doButtonBasicAction(UXEvent $event = null)
    {    
        $url = 'http://test.tssaltan.ru/curl/basic.php';

        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_USERPWD, 'logЫn:myPassWord');
        
        curl_exec_async($ch, function($result, $ch){
            if(!$result){
                $this->textAreaBasic->text = 'Error: '.curl_error($ch);
            }
            $this->textAreaBasic->text = $result;
        });
    }

    /**
     * @event buttonProxy.action 
     **/
    function doButtonProxyAction(UXEvent $event = null)
    {    
    $ip = '185.167.162.62:8000'; 
$a_poxy = 'SoVQts:J30BNU'; 


$curl = curl_init(); 
curl_setopt($curl, CURLOPT_URL, 'https://2ip.ru/'); 
curl_setopt ($curl, CURLOPT_HEADER, true); 
curl_setopt ($curl, CURLOPT_PROXY, $ip); 
curl_setopt ($curl, CURLOPT_PROXYUSERPWD, $a_poxy); 
curl_setopt ($curl, CURLOPT_PROXYTYPE, CURLPROXY_HTTP); 
$response = curl_exec($curl); 
var_dump($response); 
var_dump(curl_error($curl));
//System::setProperty()
return;
        $url = 'http://ipinfo.io/json';
        $this->buttonProxy->enabled = false;
        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
        curl_setopt($ch, CURLOPT_PROXY, $this->editProxy->text);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        
        curl_exec_async($ch, function($result, $ch){
            if(!$result){
                $this->textAreaProxy->text = 'Error: '.curl_error($ch);
            }
            else $this->textAreaProxy->text = $result;
            
            $this->buttonProxy->enabled = true;
            
            var_dump(curl_getinfo($ch));
        });
    }

    /**
     * @event link.action 
     **/
    function doLinkAction(UXEvent $event = null)
    {
        browse('http://spys.ru/proxylist/');
    }

   
    /**
     * @event showing 
     */
    function doShowing(UXWindowEvent $event = null)
    {    

    }





}
