<?php
/**
 * Created by PhpStorm.
 * User: anmeo
 * Date: 10/11/16
 * Time: 4:54 PM
 */

use \Phalcon\Cli\Task;

class TestTask extends Task
{


    /**
     * @throws Exception
     */
    public function test_timezone1Action()
    {
        $timezone = new DateTimeZone("Asia/Singapore");
        $offset = $timezone->getOffset(new DateTime);
        var_dump($offset);
        die();
    }

    /**
     * @throws Exception
     */
    public function test_timezone2Action()
    {
        $timezone = 'Pacific/Nauru';
        $time = new \DateTime('now', new DateTimeZone($timezone));
        $timezoneOffset = $time->format('P');
        var_dump($timezoneOffset);
        die();
    }
}