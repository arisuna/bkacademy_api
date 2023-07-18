<?php

trait TaskTrait
{
    /**
     * @param $params
     */
    public function parseParams($params)
    {
        $return = [];
        foreach ($params as $item) {
            if (preg_match('#^--([a-z\-]+)=([a-z0-9\_\-\/]+)?$#', $item, $matches)) {
                if (isset($matches[1]) && isset($matches[2])) {
                    $return[$matches[1]] = $matches[2];
                } elseif (isset($matches[1])) {
                    $return[$matches[1]] = true;
                }
            } elseif (preg_match('#^--([a-z\-]+)$#', $item, $matches)) {
                if (isset($matches[1])) {
                    $return[$matches[1]] = true;
                }
            }
        }
        return $return;
    }
}