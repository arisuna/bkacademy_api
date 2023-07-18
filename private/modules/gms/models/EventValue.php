<?php
/**
 * Created by PhpStorm.
 * User: macbook
 * Date: 2/12/20
 * Time: 2:37 PM
 */

namespace Reloday\Gms\Models;


use Reloday\Application\Models\EventValueExt;

class EventValue extends EventValueExt
{
    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    public function initialize(){
        parent::initialize();
        $this->belongsTo(
            'event_id',
            'Reloday\Gms\Models\Event',
            'id',
            ['alias' => 'Event']
        );
    }
}
