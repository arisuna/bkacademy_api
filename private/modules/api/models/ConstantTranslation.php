<?php

namespace SMXD\Api\Models;

class ConstantTranslation extends \SMXD\Application\Models\ConstantTranslationExt {
    /**
     *
     */
    public function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub

        $this->belongsTo('constant_id', 'SMXD\Api\Models\Constant', 'id', [
            'alias' => 'Constant'
        ]);
    }

}