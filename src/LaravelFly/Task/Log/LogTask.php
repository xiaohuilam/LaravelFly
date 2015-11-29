<?php
/**
 * Created by PhpStorm.
 * User: ivy
 * Date: 2015/11/28
 * Time: 0:04
 */

namespace LaravelFly\Task\Log;

use Monolog\Logger as Monolog;
use Illuminate\Log\Writer;
use Illuminate\Foundation\Application;

class LogTask extends  \Illuminate\Foundation\Bootstrap\ConfigureLogging
{

    protected $monolog;

    function __construct(Application $app)
    {
        $log = $this->registerLogger($app);

        if ($app->hasMonologConfigurator()) {
            call_user_func(
                $app->getMonologConfigurator(), $log
            );
        } else {
            $this->configureHandlers($app, $log);
        }
    }

    function accept($data){
        return $data['type'] == 'log' ;
    }
    function work($data)
    {
            $this->monolog->{$data['level']}($data['message'], $data['context']);
    }

    protected function registerLogger(Application $app)
    {
//        $app->instance('log', $log = new Writer(
//            new Monolog($app->environment()), $app['events'])
//        );
        $this->monolog = $monolog =  new Monolog($app->environment());
        $log = new Writer( $monolog, null);

        return $log;
    }

}