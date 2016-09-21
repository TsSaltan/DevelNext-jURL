<?php
namespace bundle\jurl;

use bundle\jurl\jURLException,
    bundle\jurl\jURL;

class jURLDownloader
{
    /**
     * @var bundle\jurl\jURL
     */
    private $ch;

    /**
     * @var array
     */
    private $events;

    /**
     * @var string
     */
    public $url;

    /**
     * --RU--
     * Включить автоматическое определение имени файла
     * @var bool
     */
    public $autoName;

    public function __construct(){
        $this->ch = new jURL;
    }

    /**
     * --RU--
     * Доступные события: progress, success, fail, complete
     * @param string $event
     * @param callable $action
     */
    public function on($event, $action){
        $this->events[$event] = $action;
    }
}