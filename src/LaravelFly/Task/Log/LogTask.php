<?php


namespace LaravelFly\Task\Log;

use Monolog\Logger as Monolog;
use Illuminate\Foundation\Application;

class LogTask extends  \Illuminate\Foundation\Bootstrap\ConfigureLogging implements \LaravelFly\Task\Task
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
    protected function configureHandlers(Application $app, Writer $log)
    {
    	
    	$log->useMySql($app->make('db')->connection()->getPdo());
    	return;
    	
    	// if use file log, when many Log task, some did not finish, for example:  400 Log:info, only 300 records added to log file
        $method = 'configure'.ucfirst($app['config']['app.log']).'Handler';
        $this->{$method}($app, $log);
    }

}