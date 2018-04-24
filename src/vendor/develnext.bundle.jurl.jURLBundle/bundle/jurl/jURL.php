<?php
namespace bundle\jurl;

    use bundle\jurl\jURLAbortException,
        bundle\jurl\jURLException,
        bundle\jurl\jURLFile,
        EngineException,
        Exception,
        php\format\ProcessorException,
        php\framework\Logger,
        php\gui\UXApplication,
        php\io\File,
        php\io\FileStream,
        php\io\IOException,
        php\io\MemoryStream,
        php\io\Stream,
        php\lang\System,
        php\lang\Thread,
        php\lib\fs,
        php\lib\Str,
        php\net\Proxy,
        php\net\SocketException,
        php\net\URL,
        php\net\URLConnection,
        php\time\Time,
        php\time\TimeFormat,
        php\util\Locale,
        php\util\Regex;

    /**
     * @packages jurl
     */
    class jURL {

        const VERSION = '2.0.0.10-dev',
              CRLF = "\r\n";
              
        private static $debug = false;

        private $opts = [],         // Параметры подключения
                $URLConnection,     // Соединеие
                $thread,
                $outLog,
        
                // Характеристики буфера
                $buffer,            // Буфер получаемых данных
                $requestLength,     // Размер отправленных данных
                $responseLength,    // Размер полученных данных

                // Характеристики соединения
                $outStream,
                $boundary,
                $charset,           // Кодировка принимаемых данных
                $connectionInfo = [], // Информация о последнем запросе

                $lastError,         // Информация об ошибках последнего запроса
                $requestHeaders,    // Отправленные заголовки
                $responseHeaders,   // Полученные заголовки
                $timeStart,         // Время начала запроса (для таймера)
                $requestHeaderLength = 0,         // Размер полученных заголовков
                $contentHeaderSend = false; // Был ли отправлен серверу заголовок content-type

        
        public function __construct($url = null){
            // Установка параметров по умолчанию
            $this->reset();
            $this->setUrl($url);
            $this->log('construct');
        }

        /**
         * --RU--
         * Сбросить параметры текущего соединения на стандартные
         */
        public function reset(){
            $this->setOpts([    
                'url'               =>    NULL,
                'connectTimeout'    =>    10000,
                'readTimeout'       =>    60000,
                'requestMethod'     =>    'GET',
                'followRedirects'   =>    false,
                'autoReferer'       =>    false,
                'userAgent'         =>    $this->genUserAgent(),
                'proxy'             =>    false,
                'proxyType'         =>    'HTTP',
                'bufferSize'        =>    48 * 1024, // 48 KiB
                'cookieFile'        =>    false,    // instanceof File
                'httpHeader'        =>    [],
                'basicAuth'         =>    false,
                'proxyAuth'         =>    false,
                'httpReferer'       =>    false,

                'progressFunction'  =>    null,
                'returnHeaders'     =>    false,    
                'returnBody'        =>    true,       
                
                'outputFile'        =>    false,    // Файл, куда будут записываться данные (вместо того, чтобы их вернуть) // 'fileStream'
                'inputFile'         =>    false,    // Файл, откуда будут счиываться данные в body // bodyFile

                'body'              =>    null,     // Отправляемые данные
                'postData'          =>    [],       // Переформатирут данные в формат query, сохранит их в body
                'postFiles'         =>    [],       // Отправляемые файлы, которые будут отправлены по стандартам "multipart/form-data
            ]); 

            $this->connectionInfo = [  // Информация о последнем запросе
                'url' => null,
                'responseCode' => null,
                'responseMessage' => null,
                'contentLength' => null,
                'contentType' => null,
                'contentEncoding' => null,
                'expiration' => null,
                'lastModified' => null,
                'usingProxy' => null,
                'executeTime' => 0,
                'connectTime' => 0,
                'redirectUrls' => [],
                'redirectNum' => 0,
                'requestHeaders' => null,
                'responseHeaders' => null,
                'requestLength' => null,
                'responseLength' => null,
                'requestHeaderLength' => 0,
                'host' => null,
                'port' => null,
            ];
        }

        /**
         * --RU--
         * Выполнить запрос асинхронно
         * @param callable $callback - функция будет вызвана по окончанию запроса - function($result, jURL $this)
         * @throws bundle\jurl\jURLException
         */
        public function asyncExec($callback = null){
            $this->thread = new Thread(function () use ($callback){
                try{   
                    $result = $this->Exec();
                } catch (jURLException $e) {
                    $result = false;
                }

                if(is_callable($callback)){
                    UXApplication::runLater(function () use ($result, $callback) {
                        $callback($result, $this);
                    });
                }
            });

            $this->thread->start();
        }

        /**
         * --RU--
         * Выполнить запрос синхронно
         * @return mixed
         * @throws bundle\jurl\jURLException
         */
        public function exec($byRedirect = false){
            $cookies = NULL;
            $this->boundary = '----' . Str::random(34);
            $answer = false;

            // Если был редирект, сбрасываем только некоторые параметры
            if($byRedirect){
                $this->clearConnectionParams();
            }            
            else {
                $this->destroyConnection();
                $this->resetConnectionParams();
            }

            try {    
                $url = new URL($this->opts['url']);
                $this->createConnection();

                    /**
                     * @todo Авторизация прокси
                     * 
                    if(!$byRedirect){
                        $this->sendOutStream(
                            'CONNECT '. $this->callConnectionFunc('getRequestProperties')['Host'][0] .':443 HTTP/1.0' . self::CRLF .
                            'User-agent: ' . $this->opts['userAgent'] . self::CRLF .
                            'Proxy-Authorization: Basic ' . base64_encode($this->opts['proxyAuth']) . self::CRLF
                        );
                        
                        var_dump(['code' => $this->getConnectionParam('responseCode')]);
                        //return $this->exec(true);
                    }*/

                    
                // Параметры подключения
                foreach($this->opts as $key => $value){
                    if(!$value || sizeof($value) == 0) continue;
                    switch($key){
                        case 'connectTimeout':
                            $this->setConnectionParam('connectTimeout', $value);
                        break;
        
                        case 'readTimeout':
                            $this->setConnectionParam('readTimeout', $value);
                        break;
        
                        case 'requestMethod':
                            $this->setConnectionParam('requestMethod', $value);
                        break;

                        case 'cookieFile':
                            $cookies = $this->loadCookies($value);
                            $this->callConnectionFunc('setRequestProperty', ['Cookie', $this->getCookiesForDomain($cookies, $url->getHost())]);
                        break;
        
                        case 'userAgent':
                            $this->callConnectionFunc('setRequestProperty', ['User-Agent', $value]);
                        break;

                        case 'httpHeader':
                            $this->log(['Send user\'s httpHeader' => $value]);
                            foreach($value as $h){
                                if(strtolower($h[0]) == 'content-type'){
                                    // у пользовательских заголовков повышенный приоритет
                                    $this->setContentTypeHeader($h[1], true);
                                }
                                else $this->callConnectionFunc('setRequestProperty', [$h[0], $h[1]]);
                            }
                        break;

                        case 'basicAuth':
                            $this->callConnectionFunc('setRequestProperty', [ 'Authorization', 'Basic ' . base64_encode($value) ]);
                        break;

                        case 'proxyAuth':
                           /*                 if(!$byRedirect){
                        $this->sendOutStream(
                            'CONNECT '. $this->callConnectionFunc('getRequestProperties')['Host'][0] .' HTTP/1.1' . self::CRLF .
                            'User-agent: ' . $this->opts['userAgent'] . self::CRLF .
                            'Proxy-authorization: basic ' . base64_encode($this->opts['proxyAuth']) . self::CRLF
                        );*/

                            //$this->callConnectionFunc('setRequestProperty', [ '', 'CONNECT '. $this->callConnectionFunc('getRequestProperties')['Host'][0] .':443 HTTP/1.1' ]);
                            $this->callConnectionFunc('setRequestProperty', [ 'Proxy-Authorization', 'Basic ' . base64_encode($value) ]);
                        break;

                        case 'postData':
                            if($this->isMultipart()) break;
                            //$this->callConnectionFunc('setRequestProperty', [ 'Content-Type', 'application/x-www-form-urlencoded' ]);
                            $this->setContentTypeHeader('application/x-www-form-urlencoded');
                        break; 
                        
                        case 'httpReferer':
                            $this->callConnectionFunc('setRequestProperty', [ 'Referer', $value ]);
                        break;

                        case 'postFiles':                        
                            //$this->callConnectionFunc('setRequestProperty', [ 'Content-Type', 'multipart/form-data; boundary=' . $this->boundary ]);
                            $this->setContentTypeHeader('multipart/form-data; boundary=' . $this->boundary, true);
                        break;

                    }
                }
                $this->requestHeaders = $this->callConnectionFunc('getRequestProperties');
                $this->log(['Connected to' => $this->opts['url']]);

                // Подключились. Отправляем данные на сервер.
                $this->setConnectionInfo('connectTime', Time::Now()->getTime() - $this->timeStart);
                
                foreach($this->opts as $key => $value){
                    if(!$value || sizeof($value) == 0 || is_null($value)) continue;

                    switch($key){                        
                        case 'postData':
                            if($this->isMultipart()){
                                foreach($value as $k=>$v){
                                    $this->sendMultipartData($k, $v);
                                }
                                break;
                            }
							
							if(is_array($value)){
								$value = http_build_query($value);
							}
                        case 'body':
                            $this->log('sendBody/body -> ' . $key . ":" . $value);
                            $this->sendOutStream($value);
                            $this->requestLength += Str::Length($value);
                        break; 
                        
                        case 'inputFile':
                            $this->log('sendBody/inputFile -> '.$key);                        
                            $fileStream = ($this->opts['inputFile'] instanceof FileStream)?$this->opts['inputFile']:FileStream::of($this->opts['inputFile'], 'r+');
                            
                            $this->log('Sending bodyFile, size = ' . $fileStream->length());

                            $this->sendData($fileStream, $fileStream->length());
                            while(!$fileStream->eof()){
                                $this->sendData($fileStream, $fileStream->length());
                            }

                            $fileStream->close();
                        break;

                        case 'postFiles':
                            $this->log('sendBody/postFiles -> '.$key);
                            $this->sendOutputData($value);
                        break;
                    }
                }

                // Завершить отправку multipart
                if($this->isMultipart()) $this->sendMultipartEnd();

                /**
                 * Данные отправлены. Читаем заголовки с сервера.
                 *
                 * Нельзя использовать switch case, т.к. если первым будет followRedirects,
                 * а после него cookieFile, то куки не будут прочитаны и сохранены
                 */
                
                $this->responseHeaders = $this->callConnectionFunc('getHeaderFields');
                foreach($this->responseHeaders as $headerKey => $headerValue){
                    unset($this->responseHeaders[$headerKey]);

                    $headerKey = str::join(array_map(function($q){
                        return str::upperFirst($q);
                    }, str::split($headerKey, '-')), '-');

                    $this->responseHeaders[$headerKey] = $headerValue;
                }

                $this->log(['responseHeaders' => $this->responseHeaders]);

                // Извлечение кук
                if(isset($this->opts['cookieFile']) and $this->opts['cookieFile'] !== false){
                    $setCookies = (isset($this->responseHeaders['Set-Cookie']) && is_array($this->responseHeaders['Set-Cookie']))?$this->responseHeaders['Set-Cookie']:[];
                            
                    $newCookies = $this->parseCookies($setCookies, $url->getHost());
                    $saveCookies = $this->uniteCookies($cookies, $newCookies);

                    Stream::putContents($this->opts['cookieFile'], $saveCookies);
                    
                }
    
                // Добавление заголовков в вывод
                $returnHeader = ($this->opts['returnHeaders'] ?? false) === true;
                // Если в foreach засунуть $headers, после цикла все данные куда-то исчезнут >:(
                foreach($this->responseHeaders as $hk=>$hv){
                    foreach($hv as $kk => $s){
                        $headString = $hk. ((strlen($hk) > 0) ? ': ' : '') . $s . self::CRLF;
                        $this->requestHeaderLength += str::length($headString);
                        if($returnHeader){
                          $this->writeBufferStream($headString);
                        }
                    }
                }
                
                $this->requestHeaderLength += str::length(self::CRLF);
                if($returnHeader){
                    $this->writeBufferStream(self::CRLF);
                }

                /**
                 * Поддержка перенаправлений
                 * пришлось писать свои перенаправления, т.к. со встроенным followredirects
                 * не удаётся прочитать отправляемые куки или заголовки перед перенаправлением
                 */
                if(isset($this->opts['followRedirects']) and $this->opts['followRedirects'] === true and isset($this->responseHeaders['Location'][0])){
                    if($this->opts['autoReferer'] === true){
                        $this->setOpt('httpReferer', $this->opts['url']);
                    }

                    $redirectUrl = $this->getLocationUrl($this->opts['url'], $this->responseHeaders['Location'][0]);
                    $this->log(['Relocation', $redirectUrl]);
                    $this->setOpt('url', $redirectUrl);
                    $this->loadConnectionInfo();
                    return $this->Exec(true);
                }

                /*/ Необходима авторизация на прокси сервере
                if($this->getConnectionParam('responseCode') == 407){
                    $this->log('Proxy-auth required');
                    $this->log([ 'requestHeaders' => $this->callConnectionFunc('getRequestProperties'),
                    ]);
                    if(!$byRedirect){
                        $this->sendOutStream(
                            'CONNECT '. $this->callConnectionFunc('getRequestProperties')['Host'][0] .':443 HTTP/1.0' . self::CRLF .
                            'User-agent: ' . $this->opts['userAgent'] . self::CRLF .
                            'Proxy-Authorization: Basic ' . base64_encode($this->opts['proxyAuth']) . self::CRLF
                        );
                        
                        //var_dump(['code' => $this->getConnectionParam('responseCode')]);
                        //return $this->exec(true);
                    }
                }*/
                
                // По заголовку content-type получаем кодировку, чтоб потом декодировать данные
                $this->detectCharset($this->getConnectionParam('contentType'));

                if($this->opts['returnBody'] === true) $this->getInputData();

                if($this->isMemoryBuffer() and $this->buffer->length() > 0){
                    $this->buffer->seek(0);
                    $answer = $this->buffer->readFully();
                }
                else $answer = NULL;

                if($this->charset != 'UTF-8'){
                    $answer = Str::Decode($answer, $this->charset);
                }

                $this->loadConnectionInfo();

                $this->log(['connectionInfo', $this->connectionInfo]);
                // $this->log(['Output Log', $this->outLog]);
                $this->log(['Answer Length', str::length($answer)]);
                
                if($errorStream = $this->callConnectionFunc('getErrorStream')->readFully() and str::length($errorStream) > 0){
                    $this->throwError('errorStream: ' . $errorStream, 1);
                }
            } catch (SocketException | ProcessorException | IOException | EngineException | Exception | jURLException $e){
                $this->throwError(get_class($e) . ': ' . $e->getMessage() . " at [" . $e->getFile() . ":" . $e->getLine(). "]\n" . $e->getTraceAsString(), $e->getCode());
            }//*/ 
            catch (jURLAbortException $e){
                $this->close();
                return false;
            } 
            
            $this->close();
            return $answer;
        }

        /**
         * Функция для отправки на сервер заголовка Content-Type
         * т.к. при различных обстоятельствах может быть отправлено несколько заголовков,
         * учитывается только последний отправленный.
         * Нам важно чтоб, отправлялся только один первый заголовок.
         * @param string       $value      Значение заголовка
         * @param bool|boolean $concurrent если true, заголовок будет отправлен в любом случае
         */
        private function setContentTypeHeader(string $value, bool $concurrent = false){
            $this->Log('[setContentType]' . $value);
            if(!$this->contentHeaderSend || $concurrent){
                $this->callConnectionFunc('setRequestProperty', ['Content-Type', $value]);
            }

            $this->contentHeaderSend = true;
        }
        /**
         * Загрузка раметров соединения
         */
        private function loadConnectionInfo(){
            $this->setConnectionInfo('url', $this->opts['url']);
            $this->setConnectionInfo('responseCode', $this->getConnectionParam('responseCode'));
            $this->setConnectionInfo('responseMessage', $this->getConnectionParam('responseMessage'));
            $this->setConnectionInfo('contentLength', $this->getConnectionParam('contentLength'));
            $this->setConnectionInfo('contentType', $this->getConnectionParam('contentType'));
            $this->setConnectionInfo('contentEncoding', $this->charset);
            $this->setConnectionInfo('expiration', $this->getConnectionParam('expiration'));
            $this->setConnectionInfo('lastModified', $this->getConnectionParam('lastModified'));
            $this->setConnectionInfo('usingProxy', $this->getConnectionParam('usingProxy'));
            $this->setConnectionInfo('executeTime', $this->getExecuteTime());
            $this->setConnectionInfo('requestHeaders', $this->requestHeaders);
            $this->setConnectionInfo('responseHeaders', $this->responseHeaders);
            $this->setConnectionInfo('requestLength', $this->requestLength); 
            $this->setConnectionInfo('responseLength', $this->responseLength);
            $this->setConnectionInfo('requestHeaderLength', $this->requestHeaderLength);

            // $this->setConnectionInfo('host', $this->URLConnection->url->getHost());
            // $this->setConnectionInfo('port', $this->URLConnection->url->getPort());
            // $this->setConnectionInfo('protocol', $this->URLConnection->url->getProtocol());
        }

        private function setConnectionInfo($key, $value){
            switch ($key) {
                case 'url':
                    if(isset($this->connectionInfo[$key]) and !is_null($this->connectionInfo[$key])){
                        $this->connectionInfo['redirectUrls'][] = $this->connectionInfo[$key];
                        $this->connectionInfo['redirectNum']++;
                    }
                    $this->connectionInfo[$key] = $value;
                    break;
                
                case 'executeTime':
                    $this->connectionInfo[$key] += $value;

                default:
                    $this->connectionInfo[$key] = $value;
            }
        }

        public function __destruct(){
            $this->destroyConnection();
        }

        /**
         * --RU--
         * Закрыть соединение
         */
        private function destroyConnection(){
            $this->log('destroyConnection');
            
            if($this->URLConnection instanceof URLConnection){
                $this->URLConnection->disconnect();
            }
            //$this->URLConnection = null;
        }
		
		private function isMultipart(){
			return (is_array($this->opts['postFiles']) and (sizeof($this->opts['postFiles']) > 0));
		}
		
		private function isError(){
            $responseCode = $this->getConnectionParam('responseCode');
            $this->log(['responseCode' => $responseCode]);
			return $responseCode >= 400;
		}
		
        private function closeThreads(){
            $this->log('closeThreads');
            if($this->thread instanceof Thread)                 $this->thread->interrupt();
            if($this->opts['inputFile'] instanceof FileStream)  $this->opts['inputFile']->close();
            if($this->opts['outputFile'] instanceof FileStream) $this->opts['outputFile']->close();
            if($this->buffer instanceof MemoryStream)           $this->buffer->close();
        }

        /**
         * --RU--
         * Остановить выполнение запроса
         */
        public function close(){
            $this->destroyConnection();
            $this->closeThreads();
        }

        /**
         * --RU--
         * Получить время выполнения запроса (в миллисекундах)
         * @return int
         */
        public function getExecuteTime(){
            return Time::Now()->getTime() - $this->timeStart;
        }
        
        /**
         * --RU--
         * Получить информацию о запросе
         * @return array [url, responseCode, responseMessage, contentLength, contentEncoding, expiration, lastModified, usingProxy, executeTime, requestHeaders, responseHeaders, requestLength]
         */
        public function getConnectionInfo(){
            return $this->connectionInfo;
        }

        /**
         * --RU--
         * Получить информацию об ошибках
         * @return array [code, error] || false
         */
        public function getError(){
            return $this->lastError;
        }        

        /**
         * --RU--
         * Установка URL
         */
        public function setUrl($url){
            $this->opts['url'] = $url;
        }

        /**
         * --RU--
         * Установка таймаута подключения (мс)
         */
        public function setConnectTimeout($timeout){
            $this->opts['connectTimeout'] = $timeout;
        }

        /**
         * --RU--
         * Установка таймаута чтения данных (мс)
         */
        public function setReadTimeout($timeout){
            $this->opts['readTimeout'] = $timeout;
        }

        /**
         * --RU--
         * Установка типа HTTP запроса
         * @param string $method - GET|POST|PUT|DELETE|etc...
         */
        public function setRequestMethod($method){
            $this->opts['requestMethod'] = $method;
        }

        /**
         * --RU--
         * Вкл/выкл переадресацию по заголовкам Location: ...
         */
        public function setFollowRedirects($follow){
            $this->opts['followRedirects'] = $follow;
        }

        /**
         * --RU--
         * Вкл/выкл автоматическую подстановку заголовков Referer: ...
         */
        public function setAutoReferer($follow){
            $this->opts['autoReferer'] = $follow;
        }

        /**
         * --RU--
         * Установка user-agent
         */
        public function setUserAgent($ua){
            $this->opts['userAgent'] = $ua;
        }

        /**
         * --RU--
         * Установка типа прокси сервера
         * @param string $type - HTTP|SOCKS
         */
        public function setProxyType($type){
            $this->opts['proxyType'] = $type;
        }

        /**
         * --RU--
         * Установка адреса прокси сервера
         * @param string $proxy - ip:port (127.0.0.1:8080)
         */
        public function setProxy($proxy){
            $this->opts['proxy'] = $proxy;
        }

        /**
         * --RU--
         * Установка Basic-авторизации
         * @param string $auth - "login:password" || false
         */
        public function setProxyAuth($auth){
            $this->opts['proxyAuth'] = $auth;
        }

        /**
         * --RU--
         * Установка размера буфера обмена данными
         */
        public function setBufferSize($type){
            $this->opts['bufferSize'] = $type;
        }

        // alias setBufferSize
        public function setBufferLength($type){
            $this->opts['bufferSize'] = $type;
        }

        /**
         * --RU--
         * Установка файла для хранения кук
         * @param string $file
         */
        public function setCookieFile($file){
            if(is_string($file)){
                $this->opts['cookieFile'] = $file;

                if(!fs::exists($file)){
                    fs::makeFile($file);
                }
            }
            else $this->opts['cookieFile'] = false;
        }

        /**
         * --RU--
         * Установка отправляемых HTTP-заголовков
         * @param array $headers [['Header1', 'Value1'], ['Header2', 'Value2']]
         */
        public function setHttpHeaders($headers){
            $this->opts['httpHeader'] = $headers;
        }
        
        // alias //
        public function setHttpHeader($headers){
            $this->opts['httpHeader'] = $headers;
        }

        /**
         * --RU--
         * Добавляет отправляемый HTTP-заголовок
         * @param string $header - имя заголовка
         * @param string $value - значение
         */
        public function addHttpHeader($header, $value){
            $this->opts['httpHeader'][] = [$header, $value];
        }

        /**
         * --RU--
         * Установка Basic-авторизации
         * @param string $auth - "login:password" || false
         */
        public function setBasicAuth($auth){
            $this->opts['basicAuth'] = $auth;
        }

        /**
         * --RU--
         * Установка заголовка Referer
         * @param string $ref - http://site.com/
         */
        public function setHttpReferer($ref){
            $this->opts['httpReferer'] = $ref;
        }

        /**
         * --RU--
         * Добавлять HTTP-заголовки к ответу
         * @param bool $return
         */
        public function setReturnHeaders($return){
            $this->opts['returnHeaders'] = $return;
        }

        /**
         * --RU--
         * Скачивать тело запроса
         * @param bool $return
         */
        public function setReturnBody($return){
            $this->opts['returnBody'] = $return;
        }

        /**
         * --RU--
         * Установка файла, куда будет сохранён ответ с сервера (например, при скачивании файла)
         * @param string $file - path/to/file
         * @throws IOException
         */
        public function setOutputFile($file){
            $this->opts['outputFile'] = ($file === false || $file === null) ? $file : FileStream::of($file, 'w+');
        }
        // alias //
        public function setFileStream($file){
            $this->setOutputFile($file);
        }

        /**
         * --RU--
         * Установка файла, откуда будут считываться данные в тело запроса (например, при загрузка файла на сервер методом PUT)
         * @param string $file - path/to/file
         */
        public function setInputFile($file){
            $this->opts['inputFile'] = $file;
        }
        // alias //
        public function setBodyFile($file){
            $this->opts['inputFile'] = $file;
        }

        /**
         * --RU--
         * Данные, которые будут отправлены в теле запроса
         * @param string $data
         */
        public function setBody($data){
            $this->opts['body'] = $data;
        }

        /**
         * --RU--
         * Отправляемые данные, которые нужно преобразовать в POST-запрос
         * @param mixed $data Массив либо строка с данными
         */
        public function setPostData($data){
            // if(is_string($data))$data = parse_str($data);
            $this->opts['postData'] = $data;
        }

        /**
         * --RU--
         * Файлы, которые будут отправлены на сервер с заголовком "multipart/form-data" (например, при POST-загрузке файлов)
         * @param array $files - ['name' => 'path/to/file']
         */
        public function setPostFiles($files){
            foreach($files as $k => $file){
                $files[$k] = self::createPostFile($file);
            }
            
            $this->opts['postFiles'] = $files;
        }

        /**
         * --RU--
         * Добавляет файлы, которые будут отправлены на сервер с заголовком "multipart/form-data" (например, при POST-загрузке файлов)
         * @param array $files - ['name' => 'path/to/file']
         */
        public function addPostFiles($files){
            foreach ($files as $key => $value) {
                $this->addPostFile($key, $value);
            }
        }

        /**
         * --RU--
         * Добавляет файл, который будет отправлен на сервер с заголовком "multipart/form-data" (например, при POST-загрузке файлов)
         * @param string $name - имя
         * @param string|jURLFile $file - путь к файлу
         */
        public function addPostFile($name, $file){
            $this->opts['postFiles'][$name] = self::createPostFile($file);
        }

        private static function createPostFile($input){
            if($input instanceof jURLFile) return $input;
            elseif(str::sub($input, 0, 1) == '@') return new jURLFile(Str::Sub($input, 1, Str::Length($input)));

            return new jURLFile($input);
        }

        /**
         * --RU--
         * Установка функции, которая будет вызываться при скачивании/загрузке файлов
         * @param callable $func
         */
        public function setProgressFunction($func){
            $this->opts['progressFunction'] = $func;
        }

        /**
         * --RU--
         * Установить массив параметроа
         * @param array $data [$key => $value]
         */
        public function setOpts($data){
            foreach($data as $k=>$v){
                $this->setOpt($k, $v);
            }
        }
        
        /**
         * --RU--
         * Установить значение параметра
         * @param string $key - параметр
         * @param mixed $value - значение
         */
        public function setOpt($key, $value){
            $func = 'set' . $key;
            if(method_exists($this, $func)){
                call_user_func_array([$this, $func], [$value]);
            }
        }

        private function throwError($message, $code){
            $this->lastError = [
                'error' => $message,
                'code' => $code
            ];
            return new jURLException($message, $code);
        }

        private function sendOutStream($out){
            if(!($this->outStream instanceof Stream)) $this->outStream = $this->callConnectionFunc('getOutputStream');
            if(self::$debug) $this->outLog .= $out . self::CRLF;
            $this->outStream->write($out);
        }

        private function getConnectionParam($param){
            if(!is_object($this->URLConnection)) throw new jURLAbortException("Aborted");
            
            return $this->URLConnection->{$param};
        }

        private function setConnectionParam($param, $value){
            if(!is_object($this->URLConnection)) throw new jURLAbortException("Aborted");
            
            return $this->URLConnection->{$param} = $value;
        }

        private function callConnectionFunc($func, $params = []){
            if(!is_object($this->URLConnection)) throw new jURLAbortException("Aborted");
            $this->log(['callConnectionFunc', [$func, $params]]);
            return call_user_func_array([$this->URLConnection, $func], $params);
        }

        private function arrayMerge($a1, $a2){
            foreach($a2 as $k=>$v){
                if(isset($a1[$k]) and is_array($a1[$k])){
                    $a1[$k] = $this->arrayMerge($a1[$k], $v);
                }
                elseif(is_numeric($k)) $a1[] = $v;
                else $a1[$k] = $v;
            }

            return $a1;
        }

        private function detectCharset($header){
            if(is_string($header)){
                $reg = 'charset=([a-zA-Z0-9-_]+)';
                $regex = Regex::of($reg, Regex::CASE_INSENSITIVE)->with($header);
                if($regex->find()){
                    return $this->charset = Str::Upper(Str::Trim($regex->group(1)));
                }
            }
            return $this->charset = 'UTF-8';
        }

        private function createConnection(){
            // Устанавливаем прокси-соединение
            if(isset($this->opts['proxy']) and $this->opts['proxy'] !== false){
                $ex = Str::Split($this->opts['proxy'], ':');
                $proxy = new Proxy($this->opts['proxyType'], $ex[0], $ex[1]);
            }
                
            $this->URLConnection = URLConnection::Create($this->opts['url'], $proxy);
            $this->URLConnection->doInput = true;
            $this->URLConnection->doOutput = ($this->opts['body'] !== false || $this->opts['postData'] !== false || $this->opts['postFiles'] !== false);       
            $this->URLConnection->followRedirects = false; // Встроенные редиректы не дают возможность обработать куки, придётся вручную обрабатывать заголовки Location: ... 

            $this->log(['createConnection', $this->opts['url'], ['proxy' => $proxy]]);
        }

        /*
         * Сброс всех переменных, характеризующих даннное соединение
         * При полном разрыве соеинения
         */
        private function resetConnectionParams(){
            $this->log('resetConnectionParams');
            $this->clearConnectionParams();

            $this->lastError = false;

            $this->timeStart = Time::Now()->getTime();
            $this->charset = NULL;

            $this->connectionInfo = 
            $this->requestHeaders = 
            $this->responseHeaders = [];

            $this->requestLength = 
            $this->responseLength =
            $this->requestHeaderLength = 0;
            $this->contentHeaderSend = false;

            if($this->buffer instanceof MemoryStream){
                $this->buffer->close(); 
            }
        }

        /**
         * Сброс некоторых параметров
         * необходимо при перенаправлении, но при сохранении основного соединения
         */
        private function clearConnectionParams(){
            $this->log('clearConnectionParams');
            if($this->outStream instanceof Stream){
                $this->outStream->close(); 
            }
            $this->outStream = null;

            /* Нужно ли очищать буфер? ведь по сути запрос не завершён
            if($this->buffer instanceof MemoryStream){
                $this->buffer->close(); 
            }
            */

            if(!$this->buffer instanceof MemoryStream) $this->buffer = new MemoryStream;
        }

        /*
         * Читает данные из входящего потока в буфер
         */
        private function loadToBuffer(Stream $input){
            try{
                $data = $input->read($this->opts['bufferSize']);
            } catch (IOException $e) {
                return false;
            }

            $this->responseLength += Str::Length($data);

            $this->writeBufferStream($data);

            $this->callProgressFunction($this->getConnectionParam('contentLength'), $this->responseLength, $this->responseLength, $this->responseLength);
        }

        /*
         * Читает входной поток данных
         */
        private function getInputData(){
            // мб переделать под try-catch?
            //$input = $this->callConnectionFunc('getInputStream');
            //$error = $this->callConnectionFunc('getErrorStream');


            $in = $this->isError() ? $this->callConnectionFunc('getErrorStream') : $this->callConnectionFunc('getInputStream');
            $this->loadToBuffer($in);

            while(!$in->eof()){
                $this->loadToBuffer($in);
            }  

           /* while(!$error->eof()){
                $this->loadToBuffer($error);
            }//*/  

            $this->callProgressFunction($this->getConnectionParam('contentLength'), $this->getConnectionParam('contentLength'), $this->responseLength, $this->responseLength);
        }

        /**
         * Отправляет заголовки, завершающие передачу multipart
         */
        private function sendMultipartEnd(){
            $this->sendOutStream("--" . $this->boundary . "--");
            $this->sendOutStream(self::CRLF);
        }

        /**
         * Отправляет данные в выходной поток в формате multipart
         * @param string|jURLFile $data
         */
        private function sendMultipartData($key, $data, $totalSize = 0){
            $isFile = ($data instanceof jURLFile);
            $contentType = ($isFile) ? $data->getMimeType() : 'text/plain';

            $this->sendOutStream("--" . $this->boundary);
            $this->sendOutStream(self::CRLF);
            $this->sendOutStream("Content-Disposition: form-data; name=\"$key\"" . ($isFile ? "; filename=\"".$data->getPostFilename()."\"": NULL));
            $this->sendOutStream(self::CRLF);
            $this->sendOutStream("Content-Type: " . $contentType);
            $this->sendOutStream(self::CRLF);

            if($isFile){
                $source = $data->getStream();
                $this->sendOutStream("Content-Transfer-Encoding: binary");
                $this->sendOutStream(self::CRLF);
                $this->sendOutStream(self::CRLF);

                $this->sendData($source, $totalSize);
                while(!$source->eof()){
                    $this->sendData($source, $totalSize);
                }
                $source->close();
            } else {
                $this->sendOutStream(self::CRLF);
                $this->sendOutStream($data);
            }

            $this->sendOutStream(self::CRLF);
        }

        /*
         * Отправляет файлы в выходной поток данных
         */
        private function sendOutputData($files){
            // Для начала узнаем общий размер файлов для progressFunction
            $totalSize = 0;
            if($this->issetProgressFunction()){           
                foreach ($files as $file) {
                    $s = $file->getStream();
                    $totalSize += $s->length();
                    $s->close();
                }
            }

            $this->log(['totalSize' => $totalSize]);

            foreach ($files as $fKey => $file) {
                $this->sendMultipartData($fKey, $file, $totalSize);
            }            
        }

        private function isMemoryBuffer(){
            return !(isset($this->opts['outputFile']) and $this->opts['outputFile'] instanceof FileStream);
        }

        private function writeBufferStream($data){
            $this->getBufferStream()->write($data);
        }

        private function getBufferStream(){
            if($this->isMemoryBuffer()){
                return $this->buffer;
            }
            
            return $this->opts['outputFile'];
        }

        /*
         * Через буфер отправляет данные в исходящий поток
         */
        private function sendData($fileStream, $totalSize){
            $data = $fileStream->read($this->opts['bufferSize']);
            $this->requestLength += Str::Length($data);

            $this->sendOutStream($data);

            $this->callProgressFunction(0, 0, $totalSize, $this->requestLength);
        }

        /*
         * На основе текущего url и полученного заголовка Location
         * генерирует новое URL для перенаправления
         * @return string
         */
        private function getLocationUrl($url, $location){            
            if(Str::contains($location, '://')){
                return $location;
            }
            
            $tmp1 = Str::Split($url, '://', 2);
            
            if(Str::Sub($location, 0, 1) == '/'){    
                return $tmp1[0] . '://' . explode('/', $tmp1[1])[0] . $location;
            }
            elseif(Str::Sub($location, 0, 1) == '?'){    
                return Str::Split($url, '?', 2)[0] . $location;
            }
            elseif(Str::Sub($location, 0, 2) == './'){
                $location = Str::Sub($location, 2, Str::Length($location));
            }

            $tmp2 = explode('/', $tmp1[1]);
            $tmp2[sizeof($tmp2)-1] = $location;

            return $tmp1[0] . '://' . implode('/', $tmp2);
        }
        
        /*
         * Парсит json-файл с куками
         * @return array
         */
        private function loadCookies($file){
            if(Stream::Exists($file)){
                $cookies = Stream::getContents($file);
                $r = json_decode($cookies, true);
                if(is_array($r)) return $r;
            }    
            
            return [];
        }

        /*
         * Выбирает из массива кук только для определенного домена
         * @return string - строка с куками для header
         */
        private function getCookiesForDomain($cookies, $domain){
            $subDomain = explode('.', $domain);
            unset($subDomain[0]);
            $subDomain = implode('.', $subDomain);
 
            $cooks = [];
            foreach(['.', '*', $domain, '.' . $subDomain, '*.' . $subDomain] as $d) {
                if(isset($cookies[$d])){
                    $cooks[] = $cookies[$d];
                }
            }

            $cookieString = [];
            $now = Time::Now()->getTime() / 1000;
            
            foreach($cooks as $c){
                foreach($c as $key => $value){
                    if($value['expires'] >= $now){
                        $cookieString[] = $key . '=' . $value['value'];
                    }
                }
            }

            $this->log(['getCookiesForDomain' => $cookieString]);

            if(sizeof($cooks) == 0 || sizeof($cookieString) == 0) return null;
            
            return implode('; ', $cookieString) . ';';
        }

        /*
         * Объединяет те куки, которые были сохранены
         * с новыми, полученными с сайта + удаляет просроченные куки
         *
         * @return string - json строка для хранения кук
         */
        private function uniteCookies($oldCookies, $newCookies){
            $now = Time::Now()->getTime() / 1000;
            
            foreach($newCookies as $domain => $cooks){
                foreach($cooks as $key => $value){
                    if($value['expires'] < $now){
                        if(isset($oldCookies[$domain][$key])) return $oldCookies[$domain][$key];
                    }else{
                        $oldCookies[$domain][$key] = $value;
                    }
                }

                if(sizeof($oldCookies[$domain]) == 0) unset($oldCookies[$domain]);
            }

            return json_encode($oldCookies);
        }

        /*
         * Парсит полученные с сервера куки в формат для хранения
         * $defaultDomain - для какого домена по умолчанию установить куки
         * return array
         */
        private function parseCookies($cookies, $defaultDomain){
            $return = [];
            
            foreach($cookies as $cookie){
                $parts = Str::split($cookie, ';');
                
                $tmp = [];
                $key = null;
                $value = null;
                
                foreach($parts as $k => $part){
                    $ex = Str::split(Str::Trim($part), '=');
                    if($k == 0){
                        $key = $ex[0];
                        $value = $ex[1];
                        continue;
                    }
                    $tmp[$ex[0]] = $ex[1];
                }

                $domain = isset($tmp['domain']) ? $tmp['domain'] : $defaultDomain;
                $dFormatA = 'EEE, dd-MMM-yyyy HH:mm:ss zzz';
                $dFormatB = 'EEE, dd MMM yyyy HH:mm:ss zzz';

                $time = (new TimeFormat($dFormatA, new Locale('en')))->parse($tmp['expires']);
                if(!is_object($time)){
                    $time = (new TimeFormat($dFormatB, new Locale('en')))->parse($tmp['expires']);
                }

                $expires = (isset($tmp['expires']) AND is_object($time))
                    ? ($time->getTime()) 
                    : (Time::Now()->getTime() + 60*60*24*365*1000);
                    
                $expires = round($expires / 1000);

                $return[$domain][$key] = [
                    'value' => $value,
                    'expires' => $expires
                ];
            }

            return $return;
        }

        /**
         * Генерирует дефолтный User-Agent
         */
        private function genUserAgent(){
            return 'jURL/'. self::VERSION .' (Java/'. System::getProperty('java.version') .'; '. System::getProperty('os.name') .'; DevelNext)';
        }

        /**
         * Вызывает функцию, переданную для определения прогресса загрузки файла
         */
        private function callProgressFunction($dlTotal, $dl, $ulTotal, $ul){
            if(!$this->issetProgressFunction()) {
                return;
            }

            if(UXApplication::isUiThread()){
                $this->opts['progressFunction']($this, $dlTotal, $dl, $ulTotal, $ul);
            }
            else{
                UXApplication::runLater(function() use ($dlTotal, $dl, $ulTotal, $ul){
                    $this->opts['progressFunction']($this, $dlTotal, $dl, $ulTotal, $ul);
                });
            }
        }

        private function issetProgressFunction(){
            return is_callable($this->opts['progressFunction']);
        }

        private function Log($data){
            if(self::$debug) Logger::Info('[jURL] ' . var_export($data, true));
        }

        /**
         * @deprecated
         */
        public static function requreVersion($version){
            $c = explode('.', self::VERSION);
            $r = explode('.', $version);

            for($i = 0; $i < 4; ++$i){
                $c[$i] = str::format('%03d', intval(isset($c[$i])?$c[$i]:0));
                $r[$i] = str::format('%03d', intval(isset($r[$i])?$r[$i]:0));
            }

            $current = intval(implode('', $c));
            $require = intval(implode('', $r));

            if($require > $current) {
                if(
                    uiConfirm("Нужно обновить пакет расширений jURL! \nНеобходимая версия: $version \nУстановлен пакет версии: " . self::VERSION. "\n\n Завершить приложение и обновиться?")
                ) {
                    browse('https://github.com/TsSaltan/DevelNext-jURL/releases/latest');
                    app()->shutdown();
                } 
                return false;
             }

            
            return true;
        }

        /**
         * Установить прокси для всех сетевых соединений
         * @param string $host 
         * @param int    $port
         * @param string $type = HTTP | HTTPS | SOCKS
         * @param string $user = ""
         * @param string $pass = ""
         */
        public static function setGlobalProxy(string $host, int $port, string $type, string $user = '', string $pass = ''){
            switch($type){           
                case 'HTTPS':
                    System::setProperty('https.proxyHost', $host);
                    System::setProperty('https.proxyPort', $port);
                    System::setProperty('https.proxyUser', $user);
                    System::setProperty('https.proxyPassword', $pass); 
                    // Если поддерживается https, то заработает и с http, поэтому не ставлю break
                case 'HTTP':
                    System::setProperty('http.proxyHost', $host);
                    System::setProperty('http.proxyPort', $port);
                    System::setProperty('http.proxyUser', $user);
                    System::setProperty('http.proxyPassword', $pass); 
                break;    
                             
                case 'SOCKS':
                    System::setProperty('socksProxyHost', $host);
                    System::setProperty('socksProxyPort', $port);
                    System::setProperty('java.net.socks.username', $user);
                    System::setProperty('java.net.socks.password', $pass);
                break;
            }
        }

        /**
         * Импорт DER сертификата в список доверенных
         * @param string $certDer Путь к сертификату
         * @param string $storePass Пароль от хранилища сертификатов java
         * @todo curl-syntax support
         */
        public static function importCert(string $certDer, string $storePass = "changeit"){
            $alias = 'jurl-' . fs::hash($certDer, 'MD5');
            $certDer = realpath($certDer);
            $javaHome = System::getProperty('java.home');
            return `{$javaHome}/bin/keytool -importcert -alias {$alias} -keystore \"{$javaHome}/lib/security/cacerts\" -storepass {$storePass} -file \"{$certDer}\" -noprompt`;    
        }
        
        public static function setDebugMode(bool $mode){
            self::$debug = $mode;
        }
    }