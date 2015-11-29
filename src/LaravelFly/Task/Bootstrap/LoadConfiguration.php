<?php
/**
 * Created by PhpStorm.
 * User: ivy
 * Date: 2015/11/28
 * Time: 1:51
 */

namespace LaravelFly\Task\Bootstrap;


use LaravelFly\Task\Application;
use Symfony\Component\Finder\Finder;

class LoadConfiguration extends \Illuminate\Foundation\Bootstrap\LoadConfiguration
{
    protected $fileName = '/[app|mail]\.php$/' ;

    protected function getConfigurationFiles(Application $app)
    {
        $files = [];

        $configPath = realpath($app->configPath());

        foreach (Finder::create()->files()->name($this->fileName)->in($configPath) as $file) {
            $nesting = $this->getConfigurationNesting($file, $configPath);

            $files[$nesting.basename($file->getRealPath(), '.php')] = $file->getRealPath();
        }

        return $files;
    }
}