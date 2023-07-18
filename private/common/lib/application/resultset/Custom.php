<?php

namespace Reloday\Application\Resultset;

use \Phalcon\Mvc\Model\Resultset\Simple;
use Reloday\Application\Lib\ModelHelper;

class Custom extends Simple
{
    public function toArray()
    {
        return ModelHelper::__toArray($this);
    }
}