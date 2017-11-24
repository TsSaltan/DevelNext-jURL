<?php
namespace bundle\jurl;

use bundle\jurl\jURLException,
    bundle\jurl\jURL,
    php\gui\UXFileChooser,
    php\gui\UXLabel,
    php\gui\UXProgressBar,
    php\gui\framework\AbstractScript,
    php\gui\framework\behaviour\TextableBehaviour,
    php\io\File,
    php\io\FileStream,
    php\lang\ThreadPool,
    php\lib\fs,
    php\lib\str,
    php\time\Time,
    php\util\Regex,
    script\support\ScriptHelpers;

/**
 * @packages jurl
 */
class jURLDownloader extends AbstractScript {   

    use ScriptHelpers;
    
    /**
     * Ссылка загружаемого файла
     * @var string
     */
    public $url;
    
    /**
     * Количество потоков
     * @var int
     */
    public $threadCount;
    
    /**
     * Путь, куда будет сохранен файл
     * @var string
     */
    public $savePath = './dwn/';    

    /**
     * Выбирать путь для сохранения вручную
     * @var bool
     */
    public $selectSavePath = false;
    
    /**
     * Объект, откуда будет взят URL
     * @var php\gui\UXTextInputControl
     */
    public $fieldUrl;

    /**
     * Объект, прогресс-бар, куда будет отображён прогресс загрузки
     * @var php\gui\UXProgressBar
     */
    public $progressBar;

    /**
     * Объект, куда будет отображён прогресс загрузки в %
     * @var php\gui\UXLabel
     */
    public $labelProgress;
    
    /**
     * Объект, куда будет отображена скорость загрузки
     * @var UXLabel
     */
     public $labelSpeed;    
     
    /**
     * Объект, куда будет отображено количество используемых потоков
     * @var UXLabel
     */
     public $labelThreads;    
      
    /**
     * Объект, куда будет отображено количество скачаных байтов
     * @var UXLabel
     */
     public $labelDownloaded;      
     
     /**
      * Объект, куда будет отображено полное количество байтов
      * @var UXLabel
      */
     public $labelTotal;     

    /**
     * Объект, куда будет отображено оставшееся время
     * @var UXLabel
     */
     public $labelTimeLeft;
     
    /**
     * Объект, куда будет отображено прошедшее время
     * @var UXLabel
     */
     public $labelTimePassed;  
	 
    /**
     * Объект, куда будет отображено имя файла
     * @var UXLabel
     */
     public $labelFileName;

    /**
     * Кнопка, запускающая загрузку
     * @var UXButton
     */
     public $buttonStart;

    /**
     * Кнопка, останавливающая загрузку
     * @var UXButton
     */
     public $buttonStop;
	 
    /**
     * Параметры jURL
     * @var array
     */
     public $jurlParams = [];
    
	/**
     * jURL connection
     * @var jURL
     */
    private $handle;
	
    private $isStarted = false,
            $contentLength = 0, 
            $startTime,
            $filename = null, 
            $threadPool, 
            $handlePool = [], 
            $progressPool = [], 
            $tmpName, 
            $tmpPath, 
            $cookieFile;


    public function __construct(){
        $this->tmpName = str::uuid();
    }

    /**
     * --RU--
     * Запустить загрузку файла
     * @throws jURLException
     */
    public function start(){
        if($this->isStarted)throw new jURLException('Download already started');

        if(is_object($this->buttonStart) and method_exists($this->buttonStart, 'setenabled')){
            $this->buttonStart->enabled = false;
        }

        if(is_object($this->buttonStop) and method_exists($this->buttonStop, 'setenabled')){
            $this->buttonStop->enabled = true;            
        }

        if(is_object($this->fieldUrl) and method_exists($this->fieldUrl, 'settext')){
            $this->url = $this->fieldUrl->text;            
        }

        $this->isStarted = true;

        $this->checkDownload(function($avaliable){
            $this->onCheckRange($avaliable);
		});
    }
	
	public function checkDownload($callback){
		$this->checkRange(function($avaliable) use ($callback){
            $callback($avaliable);
		});
	}

    protected function applyImpl($target){
        $this->_bindAction($this->buttonStart, function () {
            $this->start();
        });

        $this->_bindAction($this->buttonStop, function () {
            $this->stop();
        });


        $this->fieldUrl = $this->getNode($this->fieldUrl);
        $this->progressBar = $this->getNode($this->progressBar);
        $this->labelProgress = $this->getNode($this->labelProgress);
        $this->labelSpeed = $this->getNode($this->labelSpeed);   
        $this->labelThreads = $this->getNode($this->labelThreads);
        $this->labelDownloaded = $this->getNode($this->labelDownloaded);
        $this->labelTotal = $this->getNode($this->labelTotal);   
        $this->labelTimeLeft = $this->getNode($this->labelTimeLeft);         
        $this->labelTimePassed = $this->getNode($this->labelTimePassed);
        $this->labelFileName = $this->getNode($this->labelFileName);
        $this->buttonStart = $this->getNode($this->buttonStart);
        $this->buttonStop = $this->getNode($this->buttonStop);

        if(is_object($this->buttonStart) and method_exists($this->buttonStart, 'setenabled')){
            $this->buttonStart->enabled = true;
        }

        if(is_object($this->buttonStop) and method_exists($this->buttonStop, 'setenabled')){
            $this->buttonStop->enabled = false;            
        }

    }

    protected function getNode($obj){
        $f = $this->_fetchHelpers($obj);
        return (!is_null($obj) and isset($f[0])) ? $f[0]->root : null;
    }
    
    /**
     * --RU--
     * Получить текущую скорость загрузки
     * @return int
     */
    public function getSpeed(){
        if(!$this->isStarted) return 0;
        return round($this->getBytes() / $this->getExecuteTime() * 100) / 100;
    }
    
    /**
     * --RU--
     * Получить прогресс загрузки от 0 до 100
     * @return int 
     */
    public function getProgress(){
        if(!$this->isStarted) return 0;
        return round($this->getBytes() / $this->contentLength * 100);
    }
    
    /**
     * --RU--
     * Получить количество скачанных байтов
     * @return int 
     */
    public function getBytes(){
        if(!$this->isStarted) return 0;
        return array_sum(array_map(function($a){ return $a['bytes']; }, $this->progressPool));
    }
    
    /**
     * --RU--
     * Получить количество секунд, прошедшее после старта загрузки
     * @return int 
     */
    public function getExecuteTime(){
        if(!$this->isStarted) return 0;
        return round((Time::Now()->getTime() - $this->startTime) / 1000);
    }

    /**
     * --RU--
     * Запущена ли загрузка
     * @return bool 
     */
    public function isStart(){
        return $this->isStarted;
    }
    
    /**
     * --RU--
     * Получить количество задействованных для загрузки потоков
     * @return int 
     */
    public function getThreadsCount(){
        return array_sum(array_map(function($a){ return $a['complete'] ? 0 : 1; }, $this->progressPool));
    }
    
    /**
     * --RU--
     * Форматировать количество байтов в KiB, MiB и т.д.
     * @param int $b - Количество байтов
     * @return sting 
     */
    public function formatBytes($b){
        $f = [
            30 => 'TiB',
            20 => 'MiB', 
            10 => 'KiB'  // 2 ^10 - 1024 - 1 KiB
        ];

        foreach($f as $exp => $postfix){
            $pow = pow(2, $exp);
            if($b >= $pow * 0.9){
                return (round($b / $pow * 100) / 100) . ' ' . $postfix;
            }
        }

        return (round($b * 100) / 100) . ' B';
    }
    
    /**
     * --RU--
     * Форматировать секунды в dd:hh:mm:ss
     * @param int $timestamp - Количество секунд
     * @return sting 
     */
    public function formatSeconds($timestamp){
        $return = [];
        
        $t = [
            24*60*60 => 'd',
            60*60 => 'h',
            60 => 'm'
        ];
        
        foreach($t as $sec => $postfix){
            if($timestamp >= $sec){
                $return[] = ((string) floor($timestamp / $sec)) . $postfix;
                $timestamp = $timestamp % $sec;
            }
        }

        $return[] = ((string) ceil($timestamp)) . 's';
        return Str::Join($return, ' ');
    }

    /**
     * --RU--
     * Остановить выполнение потоков
     */    
    private function close(){
        $this->isStarted = false;
        foreach($this->handlePool as $pool){
            try{            
                $pool->close();
            } catch (jURLException $e) {
                
            }
        }

        if (is_object($this->threadPool)){
            $this->threadPool->shutdown();
            $this->threadPool = false;
        }
        
        fs::clean($this->tmpPath);
        fs::delete($this->tmpPath);

        if(is_object($this->buttonStop) and method_exists($this->buttonStop, 'on')){
            $this->buttonStop->enabled = false;
        }

        if(is_object($this->buttonStart) and method_exists($this->buttonStart, 'on')){
            $this->buttonStart->enabled = true;
        }

        $this->resetCheckResult();
    }
    
    /**
     * --RU--
     * Закрыть соединения и остановить потоки
     */    
    public function stop(){
        $this->trigger('abort');
        $this->close();
    }
     
	private $checkResult = null;
    private function resetCheckResult(){
        $this->checkResult = null;
    }

    private function checkRange($callback){
		if($this->checkResult !== null) return $callback($this->checkResult);
		
        $this->trigger('start', ['url' => $this->url]);

        $this->handle = new jURL($this->url);
        $this->handle->setRequestMethod('GET');
        $this->handle->setReturnHeaders(true);
        $this->handle->setReturnBody(false);
        $this->handle->setFollowRedirects(true);
        $this->handle->setAutoReferer(true);
		$this->handle->setBufferSize(128 * 1024); // 128 KiB    
		
        $this->applyHandleParams();
        
        $this->handle->asyncExec(function($result, $handle) use ($callback){
            $headers = $handle->getConnectionInfo()['responseHeaders'];
            $errors = $handle->getError();
            //$handle->close();

            if($result === false){
                if($this->isStarted) $this->trigger('error', $errors);
                return $this->close();
            }

            $this->findDownloadName($headers);
            
            if(isset($headers['Accept-Ranges'][0]) and $headers['Accept-Ranges'][0] == 'bytes' and isset($headers['Content-Length'][0])){
                $this->contentLength = $headers['Content-Length'][0];
                $this->checkResult = true;
            }
            else $this->checkResult = false;
			
			$callback($this->checkResult);
        });

    }
	
	protected function applyHandleParams(){
		$this->handle->setOpts($this->jurlParams);
	}
	
	/**
	 * Определение имени загружаемого файла
	 * @param array $headers Массив заголовков
	 */
    protected function findDownloadName($headers){
    	// Изначально имя файла берётся из url
    	$this->filename = explode('?', basename($this->url))[0];

    	// Если удаётся распарсить заголовки, то имя будет взято оттуда
        if(isset($headers['Content-Disposition'])){
            // case 1 : Content-Disposition: Attachment; filename=example.html
            // case 2 : Content-Disposition: attachment; filename*= UTF-8''%e2%82%ac%20rates
            // case 3 : Content-Disposition: attachment; filename="%e2%82%ac%20rates"
            
            $regex = Regex::of('filename\*?=["\s]?([^"\r\n]+)["\s\r\n]?', Regex::CASE_INSENSITIVE | Regex::UNICODE_CASE)->with($headers['Content-Disposition'][0]);
            if($regex->find()){
                $this->filename = $regex->group(1);
                if(str::startsWith($this->filename, "UTF-8''")){
                    $this->filename = str::sub($this->filename, 7);
                }
            }
        }
		
		if(is_object($this->labelFileName) and method_exists($this->labelFileName, 'settext')){
            $this->labelFileName->text = $this->filename;
        }
    }

    protected function onProgresss($i, $bytes){
        $this->progressPool[$i]['bytes'] = $bytes;
        $progress = $this->getProgress();
        $speed = $this->getSpeed();
        $bytes = $this->getBytes();
        $threads = $this->getThreadsCount();

        $this->trigger('progress', ['progress' =>$progress, 'speed' => $speed, 'bytes' => $bytes, 'length' => $this->contentLength]);
        if(is_object($this->progressBar) and method_exists($this->progressBar, 'setprogress')){
            $this->progressBar->progress = $progress;
        }

        if(is_object($this->labelProgress) and method_exists($this->labelProgress, 'settext')){
            $this->labelProgress->text = $progress . ' %';
        }
        
        if(is_object($this->labelSpeed) and method_exists($this->labelSpeed, 'settext')){
            $this->labelSpeed->text = $this->formatBytes($this->getSpeed()) . '/s';
        }
        
        if(is_object($this->labelDownloaded) and method_exists($this->labelDownloaded, 'settext')){
            $this->labelDownloaded->text = $this->formatBytes($bytes);
        }        

        if(is_object($this->labelTotal) and method_exists($this->labelTotal, 'settext')){
            $this->labelTotal->text = $this->formatBytes($this->contentLength);
        }
        
        if(is_object($this->labelThreads) and method_exists($this->labelThreads, 'settext')){
            $this->labelThreads->text = $threads;
        }

        if(is_object($this->labelTimeLeft) and method_exists($this->labelTimeLeft, 'settext')){
            if(!isset($bytes)) $bytes = $this->getBytes();
            if(!isset($speed)) $speed = $this->getSpeed();
            
            $secs = round(
                    ($this->contentLength - $bytes) / $speed
                );
                
            $this->labelTimeLeft->text = $this->formatSeconds(
                $secs
            );
        }

        if(is_object($this->labelTimePassed) and method_exists($this->labelTimePassed, 'settext')){
            $this->labelTimePassed->text = $this->formatSeconds($this->getExecuteTime());
        }
    }


    protected function onCheckRange($avaliable){
        if($this->selectSavePath){
            $fc = new UXFileChooser;
            $fc->title = 'Сохранение файла';
            $fc->initialFileName = basename($this->filename);
            $fc->initialDirectory = $this->savePath;
            $fc->extensionFilters = [['description' => 'Все файлы (*.*)', 'extensions' => ['*.*']]];
            $dwnFile = $fc->showSaveDialog();
            
            if(is_null($dwnFile)){
                $this->trigger('abort');
                $this->close();
                return;                
            } 
            
            $this->savePath = fs::parent($dwnFile) . '/';
			$this->filename = basename($dwnFile);
			
			if(is_object($this->labelFileName) and method_exists($this->labelFileName, 'settext')){
				$this->labelFileName->text = $this->filename;
			}
        }

        $this->tmpPath = $this->savePath . $this->tmpName . '/';
        $this->cookieFile = $this->tmpPath . 'cookie.jurl';
        
        fs::makeDir($this->savePath);
        fs::makeDir($this->tmpPath);
        fs::makeFile($this->cookieFile);
        
        $this->threadCount = (!$avaliable || $this->contentLength == 0) ? 1 : $this->threadCount;
        $this->threadPool = ThreadPool::createFixed($this->threadCount);
        $byteNum = (int) ceil($this->contentLength / $this->threadCount);
        $this->startTime = Time::Now()->getTime();
        for($i = 0; $i < $this->threadCount; ++$i){
            $from = ($i * $byteNum) + (($i == 0) ? 0 : 1);
            $to = min( ($i+1) * $byteNum, $this->contentLength);          
            $saveFile = $this->tmpPath . 'range-' . $from . '-' . $to . '.tmp';
            
            fs::makeFile($saveFile);
            $this->progressPool[$i] = [
                'from' => $from,
                'to' => $to,
                'file' => $saveFile,
                'complete' => false,
                'bytes' => 0
            ];

            $this->handlePool[$i] = clone $this->handle;
            $this->handlePool[$i]->addHttpHeader('Range', 'bytes=' . $from . '-' . $to);
            $this->handlePool[$i]->setRequestMethod('GET');
            $this->handlePool[$i]->setOutputFile($saveFile);
            $this->handlePool[$i]->setCookieFile($this->cookieFile);
            $this->handlePool[$i]->setReturnHeaders(false);
            $this->handlePool[$i]->setReturnBody(true);
            $this->handlePool[$i]->setFollowRedirects(true);
            $this->handlePool[$i]->setAutoReferer(true);       
            $this->handlePool[$i]->setProgressFunction(function($ch, $dwnTotal, $dwn, $uplTotal, $upl) use ($i){
                $this->onProgresss($i, $dwn);
            });        
		
		
            $this->threadPool->submit(function() use ($i, $from, $to){
                $this->handlePool[$i]->asyncExec(function($result) use ($i, $from, $to){
					/*var_dump([
						'i' => $i,
						'from' => $from, 
						'to' => $to,
						'heads' => curl_getinfo($this->handlePool[$i])
					]);*/
                    $errors = $this->handlePool[$i]->getError();
                    $this->handlePool[$i]->close();
                    if($result === false){
                        if($this->isStarted) $this->trigger('error', $errors);
                        return $this->close();
                    }
                        
                    $this->onComplete($i);
                });
            });

        }
    }
    
    protected function onComplete($i){
        $this->progressPool[$i]['complete'] = true;
        foreach($this->progressPool as $val){
            if($val['complete'] === false){
                
                return;   
            }
        }
        if(!$this->isStarted) return;

        $outfile = $this->unionFiles();
        $this->trigger('complete', ['file' => $outfile, 'path' => $this->savePath]);
        $this->isStarted = false;
    }
    
    protected function unionFiles(){
       $outfile = $this->savePath . $this->filename;
       $out = FileStream::of($outfile, 'w+');
       
       foreach($this->progressPool as $p){
           $stream = FileStream::of($p['file'], 'r');
           $out->write(
               $stream->read($p['to'] - $p['from'] + 1)
           );
           $stream->close();
       }
       
       $out->close();
       $this->close();
       
       return $outfile;
    }
}