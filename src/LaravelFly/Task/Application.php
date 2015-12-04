<?php
/**
 * Created by PhpStorm.
 * User: ivy
 * Date: 2015/11/28
 * Time: 1:28
 */

namespace LaravelFly\Task;

use Illuminate\Database\Connectors\ConnectionFactory;
use  Illuminate\Database\DatabaseManager;


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

        // ready for db, but db service and db connection has not been created, they should be created in worker
        // because db connection could not be used across process
        $this->singleton('db.factory', function ($app) {
            return new ConnectionFactory($app);
        });
        $this->singleton('db', function ($app) {
            return new DatabaseManager($app, $app['db.factory']);
        });


    }

}