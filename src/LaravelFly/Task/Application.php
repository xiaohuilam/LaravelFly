<?php
/**
 * Created by PhpStorm.
 * User: ivy
 * Date: 2015/11/28
 * Time: 1:28
 */

namespace LaravelFly\Task;


class Application extends \Illuminate\Foundation\Application
{

    public function __construct($basePath = null)
    {
//        $this->registerBaseBindings();

        if ($basePath) {
            $this->setBasePath($basePath);
        }

        (new \Illuminate\Foundation\Bootstrap\DetectEnvironment)->bootstrap($this);

        (new \LaravelFly\Task\Bootstrap\LoadConfiguration)->bootstrap($this);

    }

}