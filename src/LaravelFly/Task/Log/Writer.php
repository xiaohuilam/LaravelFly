<?php

namespace LaravelFly\Task\Log;

use MySQLHandler\MySQLHandler;

class Writer extends \Illuminate\Log\Writer 
{


    public function useMySql($pdo, $table='log', $level = 'debug')
    {
    	
        $this->monolog->pushHandler(
        	$handler = new MySQLHandler($pdo, $table, [], $this->parseLevel($level), false) 
        );

        $handler->setFormatter($this->getDefaultFormatter());
    }

}
