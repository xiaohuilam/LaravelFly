<?php

use Illuminate\Database\Connectors\ConnectionFactory;
use  Illuminate\Database\DatabaseManager;

class LaravelFlyServer
{
    protected static $instance;
    protected $laravelDir;
    protected $compiledPath;
    public $swoole_http_server;
    protected $app;
    protected $kernelClass;
    protected $kernel;

    public $taskApp;
    protected $taskObjs=[];

    public function __construct($laravelDir, $options, $kernelClass = '\App\Http\Kernel')
    {

        $this->laravelDir = realpath($laravelDir);
        $this->compiledPath = $this->laravelDir . 'bootstrap/cache/compiled.php';

        if (LOAD_COMPILED_BEFORE_WORKER) {
            $this->loadCompiledAndInitSth();
        }

        $this->swoole_http_server = $server = new \swoole_http_server($options['listen_ip'], $options['listen_port']);

        $this->kernelClass = $kernelClass;

        if (LARAVEL_TASK ) {
            $server->on('Task', [$this, 'onTask']);
            $server->on('Finish', [$this, 'onFinish']);
            
            $this->taskApp= new \LaravelFly\Task\Application($this->laravelDir);
            $tasks=[
                //'LaravelFly\Task\Log\LogTask', // this task is not allowed here, because it uses db; Swoole Rule: one process, on db connection
            ];
            foreach($tasks as $task){
                $this->taskObjs[]= new $task($this->taskApp);
            }
        }else{
            unset($options['task_worker_num']);
        }

        $server->set($options);

        $server->on('WorkerStart', array($this, 'onWorkerStart'));

        $server->on('request', array($this, 'onRequest'));

        return $this;
    }

    public function start()
    {
        $this->swoole_http_server->start();
    }

    protected function loadCompiledAndInitSth()
    {

        if (file_exists($this->compiledPath)) {
            require $this->compiledPath;
        }

        // removed from Illuminate\Foundation\Http\Kernel::handle
        \Illuminate\Http\Request::enableHttpMethodParameterOverride();

    }

    public function onWorkerStart($server, $worker_id)
    {
        if ($worker_id >= $server->setting['worker_num']) {
            echo 'task worker start',"\n";
            
            $tasksInWorker=[
            	// this task uses db, so it's necessary to make it in worker;  
            	// db connection would be created in this task creation, because this task is the first to call $app->make('db')
                'LaravelFly\Task\Log\LogTask',
            ];
            foreach($tasksInWorker as $task){
                $this->taskObjs[]= new $task($this->taskApp);
            }


                

//		  每个task worker都有一个app
//            $taskApp= new \LaravelFly\Task\Application($this->laravelDir);
//            $tasks=[
//                'LaravelFly\Task\Log\LogTask',
//            ];
//            foreach($tasks as $task){
//                $this->taskObjs[]= new $task($taskApp);
//            }

        } else {

            if (!LOAD_COMPILED_BEFORE_WORKER) {
                $this->loadCompiledAndInitSth();
            }

            $this->app = $app = LARAVELFLY_GREEDY ?
                new \LaravelFly\Greedy\Application($this->laravelDir) :
                new \LaravelFly\Application($this->laravelDir);

           if( LARAVEL_TASK ) {
               $app->server= $server;
           }

            $app->singleton(
                \Illuminate\Contracts\Http\Kernel::class,
                LARAVELFLY_KERNEL
            );
            $app->singleton(
                \Illuminate\Contracts\Console\Kernel::class,
                \App\Console\Kernel::class
            );
            $app->singleton(
                \Illuminate\Contracts\Debug\ExceptionHandler::class,
                \App\Exceptions\Handler::class
            );

            $this->kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

            $this->bootstrap();
        }
    }

    protected function bootstrap()
    {


        // App\Providers\RouteServiceProvider.boot app['url'] which need app['request']
        // app['url']->request will update when app['request'] changes, becuase
        // there is "$app->rebinding( 'request',...)" at Illuminate\Routing\RoutingServiceProvider
        if (LARAVELFLY_GREEDY) {
            $this->app->instance('request', $this->getFakeRequest());
        }

        $this->kernel->bootstrap();


    }

    protected function getFakeRequest()
    {
        static $request = null;
        if (is_null($request)) {
            $request = \Illuminate\Http\Request::createFromBase(new \Symfony\Component\HttpFoundation\Request());
        }
        return $request;

    }


    public function onRequest($request, $response)
    {

        // global vars used by: Symfony\Component\HttpFoundation\Request::createFromGlobals()
        // this static method is alse used by Illuminate\Auth\Guard
        $this->setGlobal($request);

        // according to : Illuminate\Http\Request::capture
        // static::enableHttpMethodParameterOverride(); // this line moved to $this->bootstrap() :
        $laravel_request = \Illuminate\Http\Request::createFromBase(\Symfony\Component\HttpFoundation\Request::createFromGlobals());

        // see: Illuminate\Foundation\Http\Kernel::handle($request)
        $laravel_response = $this->kernel->handle($laravel_request);


        //  sometimes there are errors saying 'http_onReceive: connection[...] is closed' and this type of error make worker restart
        if (!$this->swoole_http_server->exist($response->fd)) {
            return;
        }

        foreach ($laravel_response->headers->allPreserveCase() as $name => $values) {
            foreach ($values as $value) {
                $response->header($name, $value);
            }
        }

        foreach ($laravel_response->headers->getCookies() as $cookie) {
            $response->cookie($cookie->getName(), $cookie->getValue(), $cookie->getExpiresTime(), $cookie->getPath(), $cookie->getDomain(), $cookie->isSecure(), $cookie->isHttpOnly());
        }

        // I think " $l_response->send()" is enough
        // $response->status($l_response->getStatusCode());

        // gzip use nginx
        // $response->gzip(1);

        ob_start();
        // $laravel_response->send() contains setting header and cookie ,and $response->header and $response->cookie do same jobs.
        // They are all necessary , according by my test
        $laravel_response->send();
        $response->end(ob_get_clean());


        $this->kernel->terminate($laravel_request, $laravel_response);


        $this->app->restoreAfterRequest();

    }

    // copied from Swoole Framework
    // https://github.com/swoole/framework/blob/master/libs/Swoole/Http/ExtServer.php
    // Swoole Framework is a web framework like Laravel
    protected function setGlobal($request)
    {
        if (isset($request->get)) {
            $_GET = $request->get;
        } else {
            $_GET = array();
        }
        if (isset($request->post)) {
            $_POST = $request->post;
        } else {
            $_POST = array();
        }
        if (isset($request->files)) {
            $_FILES = $request->files;
        } else {
            $_FILES = array();
        }
        if (isset($request->cookie)) {
            $_COOKIE = $request->cookie;
        } else {
            $_COOKIE = array();
        }
        if (isset($request->server)) {
            $_SERVER = $request->server;
        } else {
            $_SERVER = array();
        }
        //todo: necessary?
        foreach ($_SERVER as $key => $value) {
            $_SERVER[strtoupper($key)] = $value;
            unset($_SERVER[$key]);
        }
        $_REQUEST = array_merge($_GET, $_POST, $_COOKIE);
        $_SERVER['REQUEST_URI'] = $request->server['request_uri'];

        foreach ($request->header as $key => $value) {
            $_key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
            $_SERVER[$_key] = $value;
        }
        $_SERVER['REMOTE_ADDR'] = $request->server['remote_addr'];
    }

    public function onTask($server, $task_id, $from_id, $data)
    {

        foreach($this->taskObjs as $obj){
            if($obj->accept($data)){
               $obj->work($data);
                return;
            }
        }
        return;

        echo "This Task {$task_id} from Worker {$from_id}\n";
        var_dump($data);
        for ($i = 0; $i < 30; $i++) {
            sleep(1);
            echo "Taks {$task_id} Handle {$i} times...\n";
        }
//        $fd=$data['fd'];
//        if($server->exist($fd)){
//            $server->send($fd, "Data in Task {$task_id}");
//        }
        return "Task {$task_id}'s result";
    }

    public function onFinish($server, $task_id, $data)
    {
        echo "Task {$task_id} finish\n";
        echo "Result: {$data}\n";
    }

    public static function getInstance($laravelDir, $options)
    {
        if (!self::$instance) {
            self::$instance = new static($laravelDir, $options);
        }
        return self::$instance;
    }
}

