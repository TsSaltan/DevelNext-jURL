<?php
namespace develnext\bundle\jurl\components;

use bundle\jurl\jURLDownloader;
use ide\scripts\AbstractScriptComponent;

class jURLDownloaderComponent extends AbstractScriptComponent
{
    public function isOrigin($any)
    {
        return $any instanceof jURLDownloaderComponent;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Загрузчик файлов';
    }

    public function getIcon()
    {
        return 'develnext/bundle/jurl/jurl-download.png';
    }

    public function getIdPattern()
    {
        return "jdownloader%s";
    }

    public function getGroup()
    {
        return 'Интернет и сеть';
    }

    /**
     * @return string
     */
    public function getType()
    {
        return jURLDownloader::class;
    }

    public function getDescription()
    {
        return null;
    }
}