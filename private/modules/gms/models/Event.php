<?php
/**
 * Created by PhpStorm.
 * User: macbook
 * Date: 2/12/20
 * Time: 2:46 PM
 */

namespace Reloday\Gms\Models;


use Reloday\Application\Models\EventExt;

class Event extends EventExt
{
    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    public function initialize(){
        parent::initialize();
    }
}
