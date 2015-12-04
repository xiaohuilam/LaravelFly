<?php
/**
 * Created by PhpStorm.
 * User: ivy
 * Date: 2015/11/28
 * Time: 1:28
 */

namespace LaravelFly\Task;



interface Task
{

    public function accept($data);
    public function work($data);

}