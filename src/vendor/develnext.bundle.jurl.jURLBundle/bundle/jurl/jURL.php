<?php
namespace bundle\jurl;

    use bundle\jurl\jURLAbortException,
        bundle\jurl\jURLException,
        php\framework\Logger,
        php\gui\UXApplication,
        php\io\File,
        php\io\FileStream,
        php\io\MemoryStream,
        php\io\Stream,
        php\lang\System,
        php\lang\Thread,
        php\lib\Str,
        php\net\Proxy,
        php\net\URL,
        php\net\URLConnection,
        php\time\Time,
        php\time\TimeFormat,
        php\util\Locale,
        php\util\Regex;

    class jURL
    {
        public $version = '0.6.1';

        const CRLF = "\r\n",
              LOG = false;

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
                $connectionInfo,    // Информация о последнем запросе
                $lastError,         // Информация об ошибках последнего запроса
                $requestHeaders,    // Отправленные заголовки
                $responseHeaders,   // Полученные заголовки
                $timeStart;         // Время начала запроса (для таймера)

        
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
                'cookieFile'        =>    false,
                'httpHeader'        =>    [],
                'basicAuth'         =>    false,
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
        }

        /**
         * --RU--
         * Выполнить запрос асинхронно
         * @param callable $callback - функция будет вызвана по окончанию запроса - function($result, jURL $this)
         * @throws bundle\jurl\jURLException
         */
        public function asyncExec($callback = null){
            $this->thread = new Thread(function () use ($callback){
                $result = $this->Exec();
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
            $url = new URL($this->opts['url']);
            $cookies = NULL;
            $this->boundary = Str::random(90);
            $answer = false;
            $useBuffer = !(isset($this->opts['outputFile']) and $this->opts['outputFile'] !== false);
            $isMultipart = (is_array($this->opts['postFiles']) and (sizeof($this->opts['postFiles']) > 0));

            // Если был редирект, ничего не сбрасываем
            if(!$byRedirect){
                $this->resetConnectionParams();
            }            
            else $this->destroyConnection();
    
            try {    
                $this->createConnection();

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
                            foreach($value as $h){
                                $this->callConnectionFunc('setRequestProperty', [$h[0], $h[1]]);
                            }
                        break;

                        case 'basicAuth':
                            $this->callConnectionFunc('setRequestProperty', [ 'Authorization', 'Basic ' . base64_encode($value) ]);
                        break;

                        case 'postData':
                            if($isMultipart) break;
                            $this->callConnectionFunc('setRequestProperty', [ 'Content-Type', 'application/x-www-form-urlencoded' ]);
                        break; 
                        
                        case 'httpReferer':
                            $this->callConnectionFunc('setRequestProperty', [ 'Referer', $value ]);
                        break;

                        case 'postFiles':                        
                            $this->callConnectionFunc('setRequestProperty', [ 'Content-Type', 'multipart/form-data; boundary=' . $this->boundary ]);
                        break;

                    }
                }
                $this->requestHeaders = $this->callConnectionFunc('getRequestProperties');
                $this->log(['Connected to' => $this->opts['url']]);

                // Подключились. Отправляем данные на сервер.
                foreach($this->opts as $key => $value){
                    if(!$value || sizeof($value) == 0 || is_null($value)) continue;

                    switch($key){                        
                        case 'postData':
                            if($isMultipart){
                                foreach($value as $k=>$v){
                                    $this->sendMultipartData($k, $v);
                                }
                                break;
                            }

                            $value = http_build_query($value);
                        case 'body':
                            $this->log('sendBody -> '.$key);
                            $this->sendOutStream($value);
                            $this->requestLength += Str::Length($value);
                        break; 
                        
                        case 'inputFile':
                            $this->log('sendBody -> '.$key);                        
                            $fileStream = ($this->opts['inputFile'] instanceof FileStream)?$this->opts['inputFile']:FileStream::of($this->opts['inputFile'], 'r+');
                            
                            $this->log('Sending bodyFile, size = ' . $fileStream->length());

                            $this->sendData($fileStream, $fileStream->length());
                            while(!$fileStream->eof()){
                                $this->sendData($fileStream, $fileStream->length());
                            }

                            $fileStream->close();
                        break;

                        case 'postFiles':
                            $this->log('sendBody -> '.$key);
                            $this->sendOutputData((is_array($value))?$value:[$value]);
                        break;
                    }
                }

                // Завершить отправку multipart
                if($isMultipart) $this->sendMultipartEnd();

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

                    if(File::of($this->opts['cookieFile'])->canWrite()){
                        Stream::putContents($this->opts['cookieFile'], $saveCookies);
                    }
                }
    
                // Добавление заголовков в вывод
                if(isset($this->opts['returnHeaders']) and $this->opts['returnHeaders'] === true){
                    // Если в foreach засунуть $headers, после цикла все данные куда-то исчезнут >:(
                    $hs = [];
                    //foreach($this->callConnectionFunc('getHeaderFields') as $k=>$v){
                    foreach($this->responseHeaders as $hk=>$hv){
                        foreach($hv as $kk => $s){
                            $hs[] = $hk. ((strlen($hk) > 0) ? ': ' : '') . $s;
                        }
                    }

                    if(sizeof($hs) > 0) {
                        $h = implode(self::CRLF, $hs);
                        $h.= self::CRLF . self::CRLF;
                        $this->buffer->write($h);
                    }
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
                    return $this->Exec(true);
                }
                
                // По заголовку content-type получаем кодировку, чтоб потом декодировать данные
                $this->detectCharset($this->getConnectionParam('contentType'));

                if($this->opts['returnBody'] === true) $this->getInputData();

                if($useBuffer and $this->buffer->length() > 0){
                    $this->buffer->seek(0);
                    $answer = $this->buffer->readFully();
                }
                else $answer = NULL;
                
                $this->buffer->close();

                if($this->charset != 'UTF-8'){
                    $answer = Str::Decode($answer, $this->charset);
                }
                
               // $this->log(['URLConnection' => $this->URLConnection]);

                $this->connectionInfo = [
                    'url' => (object) $url,
                    'responseCode' => $this->getConnectionParam('responseCode'),
                    'responseMessage' => $this->getConnectionParam('responseMessage'),
                    'contentLength' => $this->getConnectionParam('contentLength'),
                    'contentType' => $this->getConnectionParam('contentType'),
                    'contentEncoding' => $this->charset,
                    'expiration' => $this->getConnectionParam('expiration'),
                    'lastModified' => $this->getConnectionParam('lastModified'),
                    'usingProxy' => $this->getConnectionParam('usingProxy'),
                    'executeTime' => $this->getExecuteTime(),
                    'requestHeaders' => $this->requestHeaders,
                    'responseHeaders' => $this->responseHeaders,
                    'requestLength' => $this->requestLength
                ];

                $this->log(['connectionInfo', $this->connectionInfo]);
                $this->log(['Output Log', $this->outLog]);
                $this->log(['Answer', $answer]);
                
                if($errorStream = $this->callConnectionFunc('getErrorStream')->readFully() and str::length($errorStream) > 0){
                    $this->throwError('errorStream: ' . $errorStream, 1);
                }


            }/** catch (\php\net\SocketException $e){
                $this->throwError('SocketException: ' . $e->getMessage(), 2);
            } catch (\php\format\ProcessorException $e){
                $this->throwError('ProcessorException: ' . $e->getMessage(), 3);
            } catch (\php\io\IOException $e){
                $this->throwError('IOException: ' . $e->getMessage(), 4);
            } catch (\EngineException $e){
                $this->throwError('EngineException: ' . $e->getMessage(), 5);
            } catch (\Exception $e){
                $this->throwError('Exception: ' . $e->getMessage(), 6);
            } catch (jURLException $e){
                $this->throwError($e->getMessage(), $e->getCode);
            }//*/ 
            catch (jURLAbortException $e){
                $this->close();
                return false;
            } 
            
            $this->close();
            return $answer;
        }


        public function __destruct(){
            $this->destroyConnection();
        }

        /**
         * --RU--
         * Закрыть соединение
         */
        public function destroyConnection(){
            $this->log('destroyConnection');
            if($this->thread instanceof Thread)                 $this->thread->interrupt();
            if($this->opts['inputFile'] instanceof FileStream)  $this->opts['inputFile']->close();
            if($this->opts['outputFile'] instanceof FileStream) $this->opts['outputFile']->close();
            if($this->URLConnection instanceof URLConnection)   $this->URLConnection->disconnect();
            if($this->buffer instanceof MemoryStream)           $this->buffer->close();
        }

        /**
         * --RU--
         * Остановить выполнение запроса
         */
        public function close(){
            return $this->destroyConnection();
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
            $this->opts['cookieFile'] = $file;
        }

        /**
         * --RU--
         * Установка отправляемых HTTP-заголовков
         * @param array $headers [['Header1', 'Value1'], ['Header2', 'Value2']]
         */
        public function setHttpHeaders($headers){
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
         */
        public function setOutputFile($file){
            $this->opts['outputFile'] = $file;
        }
        // alias //
        public function setFileStream($file){
            $this->opts['outputFile'] = $file;
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
         * @param array $data - ['key' => 'value']
         */
        public function setPostData($data){
            if(is_string($data))$data = parse_str($data);
            $this->opts['postData'] = $data;
        }

        /**
         * --RU--
         * Файлы, которые будут отправлены на сервер с заголовком "multipart/form-data" (например, при POST-загрузке файлов)
         * @param array $files - ['name' => 'path/to/file']
         */
        public function setPostFiles($file){
            $this->opts['postFiles'] = $file;
        }

        /**
         * --RU--
         * Добавляет файлы, которые будут отправлены на сервер с заголовком "multipart/form-data" (например, при POST-загрузке файлов)
         * @param array $files - ['name' => 'path/to/file']
         */
        public function addPostFiles($files){
            foreach ($files as $key => $value) {
                $this->opts['postFiles'][$key] = $value;
            }
        }

        /**
         * --RU--
         * Добавляет файл, который будет отправлен на сервер с заголовком "multipart/form-data" (например, при POST-загрузке файлов)
         * @param string $name - имя
         * @param string $filepath - пуит к файлу
         */
        public function addPostFile($name, $filepath){
            $this->opts['postFiles'][$name] = $filepath;
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
                $this->$func($value);
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
            if(self::LOG) $this->outLog .= $out . self::CRLF;
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
            //Устанавливаем прокси-соединение
            if(isset($this->opts['proxy']) and $this->opts['proxy'] !== false){
                $ex = Str::Split($this->opts['proxy'], ':');
                $proxy = new Proxy($this->opts['proxyType'], $ex[0], $ex[1]);
            }
                
            // $this->log(['Options' => $this->opts]);

            $this->URLConnection = URLConnection::Create($this->opts['url'], $proxy);
            $this->URLConnection->doInput = true;
            $this->URLConnection->doOutput = ($this->opts['body'] !== false || $this->opts['postData'] !== false || $this->opts['postFiles'] !== false);       
            $this->URLConnection->followRedirects = false; // Встроенные редиректы не дают возможность обработать куки, придётся вручную обрабатывать заголовки Location: ... 

            $this->log(['createConnection', $this->opts['url'], ['proxy' => $proxy]]);
        }

        /*
         * Сброс переменных, отвечающих за буфер, его размер и размер пересылаемых и получаемых данных
         */
        private function resetBufferParams(){
            $this->requestLength = 0;
            $this->responseLength = 0;
            $this->buffer = new MemoryStream;
        }

        /*
         * Сброс всех переменных, характеризующих даннное соединение
         */
        private function resetConnectionParams(){
            $this->resetBufferParams();
            $this->lastError = false;

            $this->timeStart = Time::Now()->getTime();
            $this->charset = NULL;
            $this->connectionInfo = [];
            $this->requestHeaders = [];
            $this->responseHeaders = [];
        }

        /*
         * Читает данные из входящего потока в буфер
         */
        private function loadToBuffer($input){
            $data = $input->read($this->opts['bufferSize']);
            $this->responseLength += Str::Length($data);

            if($this->opts['outputFile'] instanceof FileStream){
                $this->opts['outputFile']->write($data);
            }
            else $this->buffer->write($data);

            $this->callProgressFunction($this->getConnectionParam('contentLength'), $this->responseLength, $this->responseLength, $this->responseLength);
        }

        /*
         * Читает входной поток данных
         */
        private function getInputData(){
            $this->resetBufferParams();

            if(isset($this->opts['outputFile']) and $this->opts['outputFile'] !== false){
                $this->opts['outputFile'] = ($this->opts['outputFile'] instanceof FileStream) ? $this->opts['outputFile'] : FileStream::of($this->opts['outputFile'], 'w+');
            }
            else $this->opts['outputFile'] = false;

            $in = $this->callConnectionFunc('getInputStream');
            $this->loadToBuffer($in);

            while(!$in->eof()){
                $this->loadToBuffer($in);
            }   
            $this->callProgressFunction($this->getConnectionParam('contentLength'), $this->getConnectionParam('contentLength'), $this->responseLength, $this->responseLength);
         

            return $this->buffer;
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
         * @param string|FileStream $source
         */
        private function sendMultipartData($key, $source, $fileName = NULL, $totalSize = 0){
            $isFile = $source instanceof FileStream;
            $contentType = ($isFile) ? URLConnection::guessContentTypeFromName($fileName) : 'text/plain';

            $this->sendOutStream("--" . $this->boundary);
            $this->sendOutStream(self::CRLF);
            $this->sendOutStream("Content-Disposition: form-data; name=\"$key\"" . ($isFile ? "; filename=\"$fileName\"": NULL));
            $this->sendOutStream(self::CRLF);
            $this->sendOutStream("Content-Type: $contentType");
            $this->sendOutStream(self::CRLF);
                                    
            if($isFile){
                $this->sendOutStream("Content-Transfer-Encoding: binary");
                $this->sendOutStream(self::CRLF);
                $this->sendOutStream(self::CRLF);

                $this->sendData($source, $totalSize);
                while(!$source->eof()){
                    $this->sendData($source, $totalSize);
                }
            } else {
                $this->sendOutStream(self::CRLF);
                $this->sendOutStream($source);
            }

            $this->sendOutStream(self::CRLF);
        }

        /*
         * Отправляет файлы в выходной поток данных
         */
        private function sendOutputData($files){
            $this->resetBufferParams();

            // Для начала узнаем общий размер файлов для progressFunction
            $totalSize = 0;
            if($this->issetProgressFunction()){           
                foreach ($files as $file) {
                    $s = new FileStream($file, 'r+');
                    $totalSize += $s->length();
                    $s->close();
                }
            }

            foreach ($files as $fKey => $file) {

                $fileName = File::of($file)->getName();
                $fStream = new FileStream($file, 'r+');

                $this->sendMultipartData($fKey, $fStream, $fileName, $totalSize);

            }
        
            
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
            $cooks = isset($cookies[$domain])?$cookies[$domain]:[];
            $cookieString = [];
            $now = Time::Now()->getTime() / 1000;
            
            foreach($cooks as $key=>$value){
                if($value['expires'] >= $now){
                    $cookieString[] = $key . '=' . $value['value'];
                }
            }

            if(sizeof($cooks) == 0)return null;
            
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
            return 'jURL/'.$this->version.' (Java/'. System::getProperty('java.version') .'; '. System::getProperty('os.name') .'; DevelNext)';
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
            if(self::LOG) Logger::Debug('[jURL] ' . var_export($data, true));
        }
    }