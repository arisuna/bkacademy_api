<?php

class MainTask extends \Phalcon\Cli\Task
{

    /**
     * flush
     */
    public function flushcacheAction()
    {

        $redis = new Redis();
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
