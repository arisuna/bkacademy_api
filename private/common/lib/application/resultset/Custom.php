<?php

namespace SMXD\Application\Resultset;

use \Phalcon\Mvc\Model\Resultset\Simple;
use SMXD\Application\Lib\ModelHelper;

class Custom extends Simple
{
    public function toArray()
    {
        return ModelHelper::__toArray($this);
    }
}