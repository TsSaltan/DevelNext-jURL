<?php
/**
 * Вклюючает поддержку синтаксиса cURL, как в обычном PHP
 * становятся доступными curl_* функции
 *
 * Обязательно, чтоб функции были объявлены в корневом namespace
 */

namespace;

use php\lib\Str,
    bundle\jurl\jURL;

// Для autoloader'а
class cURL{

}
    
if(!function_exists('curl_init')){

   /**
    * --RU--
    * Инициализирует сеанс cURL
    * @param string $url (optional)
    */
   function curl_init($url = NULL){
       return new jURL($url);
   }

   /**
    * --RU--
    * Устанавливает параметр для сеанса CURL
    * @param jURL $ch - Дескриптор cURL, полученный из curl_init
    * @param string $key - Устанавливаемый параметр CURLOPT_*
    * @param string $value - Значение параметра key
    */
   function curl_setopt(jURL $ch, $key, $value){
       $reKeys = [
           'CURLOPT_URL' => 'url',
           'CURLOPT_CONNECTTIMEOUT' => 'connectTimeout',
           'CURLOPT_CONNECTTIMEOUT_MS' => 'connectTimeout',
           'CURLOPT_TIMEOUT' => 'readTimeout',
           'CURLOPT_TIMEOUT_MS' => 'readTimeout',
           'CURLOPT_CUSTOMREQUEST' => 'requestMethod',
           'CURLOPT_POSTFIELDS' => 'postData', // postFiles //
           'CURLOPT_POST' => 'requestMethod',
           'CURLOPT_PUT' => 'requestMethod',
           'CURLOPT_GET' => 'requestMethod',
           'CURLOPT_REFERER' => 'httpReferer',
           'CURLOPT_AUTOREFERER' => 'autoReferer',
           'CURLOPT_COOKIEFILE' => 'cookieFile',
           'CURLOPT_COOKIEJAR' => 'cookieFile',
           'CURLOPT_USERAGENT' => 'userAgent',
           'CURLOPT_HEADER' => 'returnHeaders',
           'CURLOPT_FOLLOWLOCATION' => 'followRedirects',
           'CURLOPT_HTTPHEADER' => 'httpHeader',
           'CURLOPT_USERPWD' => 'basicAuth',
           'CURLOPT_PROXY' => 'proxy',
           'CURLOPT_PROXYTYPE' => 'proxyType',
           'CURLOPT_PROGRESSFUNCTION' => 'progressFunction',
           'CURLOPT_FILE' => 'outputFile',
           'CURLOPT_BUFFERSIZE' => 'bufferLength',
           'CURLOPT_INFILE' => 'inputFile',
       ];
       
       $jKey = isset($reKeys[$key]) ? $reKeys[$key] : NULL;
       
       if($key == 'CURLOPT_POST' and $value === true){
           $value = 'POST';
       }
       elseif($key == 'CURLOPT_GET' and $value === true){
           $value = 'GET';
       }
       elseif($key == 'CURLOPT_PUT' and $value === true){
           $value = 'PUT';
       }
       elseif($key == 'CURLOPT_HTTPHEADER'){
           $headers = [];
           foreach ($value as $h) {
               $t = Str::Split($h, ':', 2);
               $headers[] = [
                   Str::Trim( $t[0] ),
                   Str::Trim( $t[1] ),
               ];
           }
           $value = $headers;
       }
       elseif($key == 'CURLOPT_POST' AND $value === true){
           return $ch->setOpt('requestMethod', 'POST');
       }
       elseif($key == 'CURLOPT_CONNECTTIMEOUT' OR $key == 'CURLOPT_TIMEOUT'){
           $value = $value * 1000;
       }
       elseif($key == 'CURLOPT_POSTFIELDS' AND is_array($value)){
           $str = [];
           $files = [];
           foreach($value as $k=>$v){
               if(Str::Sub($v, 0, 1) == '@')$files[$k] = Str::Sub($v, 1, Str::Length($v));
               else $str[$k] = $v;
           }
           if(sizeof($files) > 0) return $ch->setOpt('postFiles', $files);
           else $value = $str;
       }
       elseif($key == 'CURLOPT_PROXYTYPE'){
           $proxyTypes = [
               'CURLPROXY_HTTP' => 'HTTP',
               'CURLPROXY_SOCKS5' => 'SOCKS'
           ];
           $value = (isset($proxyTypes[$value]) ? $proxyTypes[$value] : $value);
       }
       
       $ch->setOpt($jKey, $value);
   }

   /**
    * --RU--
    * Устанавливает несколько параметров для сеанса cURL
    * @param jURL $ch - Дескриптор cURL, полученный из curl_init
    * @param array $options - Массив c параметрами вида [CURLOPT_* => 'value']
    */
   function curl_setopt_array(jURL $ch, $options){
       foreach($options as $k=>$v){
           curl_setopt($ch, $k, $v);
       }
   } 

   /**
    * --RU--
    * Выполняет запрос cURL
    * @param jURL $ch - Дескриптор cURL, полученный из curl_init
    * @return mixed
    */
   function curl_exec(jURL $ch){
       return $ch->exec();
   }

   /**
    * --RU--
    * Выполняет запрос cURL асинхронно
    * @param jURL $ch - Дескриптор cURL, полученный из curl_init
    * @param callable $callback - Функция, куда бкдет передан результат запроса
    */
   function curl_exec_async(jURL $ch, $callback = null){
       return $ch->aSyncExec($callback);
   }

   /**
    * --RU--
    * Возвращает строку с описанием последней ошибки текущего сеанса
    * @param jURL $ch - Дескриптор cURL, полученный из curl_init
    * @return string|null
    */
   function curl_error(jURL $ch){
       return ($ch->getError() === false) ? null : $ch->getError()['error'];
   }

   /**
    * --RU--
    * Возвращает код последней ошибки
    * @param jURL $ch - Дескриптор cURL, полученный из curl_init
    * @return int - Код ошибки или 0, если запрос выполнен без ошибок
    */
   function curl_errno(jURL $ch){
       return ($ch->getError() === false) ? 0 : $ch->getError()['code'];
   }

   /**
    * --RU--
    * Возвращает информацию об определенной операции
    * @param jURL $ch - Дескриптор cURL, полученный из curl_init
    * @return array
    */
   function curl_getinfo(jURL $ch){
       return $ch->getConnectionInfo();
   }
   
   /**
    * --RU--
    * Завершает сеанс cURL
    * @param jURL $ch - Дескриптор cURL, полученный из curl_init
    */
   function curl_close(jURL $ch){
       return $ch->destroyConnection();
   }
}