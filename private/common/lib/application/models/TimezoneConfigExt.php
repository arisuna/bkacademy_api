<?php

namespace SMXD\Application\Models;


class TimezoneConfigExt extends TimezoneConfig
{

    const DEFAULT_TIME_ZONE = 'Asia/Singapore';

    /**
     * [initialize description]
     * @return [type] [description]
     */
    public function initialize()
    {
        parent::initialize();
        $this->setReadConnectionService('dbRead');
        $this->setWriteConnectionService('db');
    }

}
