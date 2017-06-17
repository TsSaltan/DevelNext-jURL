<?php
namespace develnext\bundle\jurl;

use ide\bundle\AbstractBundle;
use ide\bundle\AbstractJarBundle;
use ide\formats\ScriptModuleFormat;
use ide\Ide;
use ide\project\Project;
use php\io\Stream;
use php\lib\Str;
use develnext\bundle\jurl\components\jURLDownloaderComponent;

/**
 * Class jURLBundle
 */
class jURLBundle extends AbstractJarBundle
{
    public function onAdd(Project $project, AbstractBundle $owner = null){
        if ($format = Ide::get()->getRegisteredFormat(ScriptModuleFormat::class)) {
            $format->register(new jURLDownloaderComponent());
        }

        parent::onAdd($project, $owner);
    }

    public function onRemove(Project $project, AbstractBundle $owner = null){
        if ($format = Ide::get()->getRegisteredFormat(ScriptModuleFormat::class)) {
            $format->unregister(new jURLDownloaderComponent());
        }

        parent::onRemove($project, $owner);
    }
}