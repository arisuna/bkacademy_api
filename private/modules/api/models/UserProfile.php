<?php

namespace SMXD\Api\Models;

use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Models\UserExt;
use SMXD\Api\Models\ModuleModel;
use SMXD\Api\Module;

class User extends UserExt
{
    /**
     * [initialize description]
     * @return [type] [description]
     */
    public function initialize()
    {
        parent::initialize();
    }

    public function getParsedArray($columns = NULL)
    {
        $array = $this->toArray();
        $array['isAdmin'] = $this->isAdmin();

        unset($array['id']);
        unset($array['aws_cognito_uuid']);

        return $array;
    }
}