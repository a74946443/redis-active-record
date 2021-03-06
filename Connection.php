<?php
/**
 * Created by PhpStorm.
 * User: gordon
 * Date: 2017/9/30
 * Time: 下午2:36
 */

namespace RedisActiveRecord;


interface Connection
{
    public function connection();

    public function close();

    public function isActive();

    public function send(Command $command);

    public function accept(Command $command);
}
