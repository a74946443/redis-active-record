<?php
/**
 * Created by PhpStorm.
 * User: gordon
 * Date: 2017/9/30
 * Time: 上午11:20
 */

namespace RedisActiveRecord;


class StreamConnection implements Connection
{
    public $host = 'localhost';
    public $port = 6379;
    public $database = 0;
    public $auth = null;
    public $connectionTimeout = 60;
    public $dataTimeout = 60;
    public $socketClientFlags = STREAM_CLIENT_CONNECT;

    private $stream = null;

    public function connection()
    {
        if ($this->isActive()) {
            return true;
        }
        $this->stream = stream_socket_client(
            'tcp://' . $this->host . ':' . $this->port,
            $error_no,
            $err_msg,
            $this->connectionTimeout,
            $this->socketClientFlags
        );

        if ($this->isActive()) {
            return true;
        }
        throw new \Exception('Connection Failed! MSG:'.$error_no.';'.$err_msg, 500);
    }

    public function close()
    {
        if (!$this->isActive()) {
            //TODO QUIT
            stream_socket_shutdown($this->stream, STREAM_SHUT_RDWR);
            $this->stream = null;
        }
    }

    public function isActive()
    {
        return $this->stream !== null;
    }

    public function send(Command $command)
    {
        $stream_contents = $command->getCommands();
        fwrite($this->stream, $stream_contents);
        return $this;
    }

    public function accept(Command $command)
    {
        $stream_contents = $command->getCommands();
        return $this->response($stream_contents);
    }

    private function response($commands)
    {
        if (($line = fgets($this->stream)) === false) {
            throw new \Exception('读取数据失败，Command:'.$commands, 500);
        }

        $type = $line{0};
        $line = mb_substr($line, 1, -2, '8bit');
        switch ($type) {
            case '+': // Status reply
                if ($line === 'OK' || $line === 'PONG') {
                    return true;
                } else {
                    return $line;
                }
            case '-': // Error reply
                throw new \Exception("Redis error: " . $line . "\nRedis command was: " . $commands, 500);
            case ':': // Integer reply
                return $line;
            case '$': // Bulk replies
                if ($line == '-1') {
                    return null;
                }

                $length = (int)$line + 2;
                $data = '';
                while ($length > 0) {
                    if (($block = fread($this->stream, $length)) === false) {
                        throw new \Exception("Failed to read from socket.\nRedis command was: " . $commands, 500);
                    }
                    $data .= $block;
                    $length -= mb_strlen($block, '8bit');
                }
                return mb_substr($data, 0, -2, '8bit');
            case '*': // Multi-bulk replies
                $count = (int)$line;
                $data = [];
                for ($i = 0; $i < $count; $i++) {
                    $data[] = $this->response($commands);
                }

                return $data;
            default:
                throw new \Exception('Received illegal data from redis: ' . $line . "\nRedis command was: " . $commands, 500);
        }
    }
}
