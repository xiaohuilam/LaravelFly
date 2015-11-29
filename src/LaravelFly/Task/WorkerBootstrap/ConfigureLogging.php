<?php
/**
 * Created by PhpStorm.
 * User: ivy
 * Date: 2015/11/27
 * Time: 23:15
 */

namespace LaravelFly\Task\WorkerBootstrap;

use LaravelFly\Application;
use LaravelFly\Task\Log\WorkerLog as Log;

class ConfigureLogging
{

    public function bootstrap(Application $app)
    {
        $app->instance('log', $log = new Log($app->server));
    }
}