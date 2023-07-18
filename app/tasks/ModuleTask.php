<?php
/**
 * Created by PhpStorm.
 * User: anmeo
 * Date: 10/11/16
 * Time: 4:54 PM
 */

use \Phalcon\Cli\Task;
use \Email\Parse;

class ModuleTask extends Task
{

    /**
     * @param $params
     */
    public function parseParams($params)
    {
        $return = [];
        foreach ($params as $item) {
            if (preg_match('#^--([a-z0-9\-]+)=([a-z0-9,_]+)?$#', $item, $matches)) {
                if (isset($matches[1]) && isset($matches[2])) {
                    $return[$matches[1]] = $matches[2];
                } elseif (isset($matches[1])) {
                    $return[$matches[1]] = true;
                }
            } elseif (preg_match('#^--([a-z0-9\-]+)$#', $item, $matches)) {
                if (isset($matches[1])) {
                    $return[$matches[1]] = true;
                }
            }
        }
        return $return;
    }

}