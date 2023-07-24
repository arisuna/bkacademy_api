<?php

class MainTask extends \Phalcon\Cli\Task
{
    use TaskTrait;

    /**
     * flush
     */
    public function flushcacheAction()
    {

        $redis = new Redis();
        echo "".getenv('REDIS_HOST').getenv('REDIS_PORT')." \r\n";
        $redis->connect(getenv('REDIS_HOST'), getenv('REDIS_PORT'));
        $res = $redis->flushDb();
        $res = $redis->flushAll();
        if ($res == true) {
            echo "FLUSH CACHE OK \r\n";
        } else {
            echo "FLUSH CACHE NOT OK \r\n";
        }
    }
}
