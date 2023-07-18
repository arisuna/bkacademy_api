<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class FinancialAccount extends \Reloday\Application\Models\FinancialAccountExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    public function initialize()
    {
        parent::initialize();
    }

    /**
     * check is belong to GMS
     * @return [type] [description]
     */
    public function belongsToGms()
    {
        if (!ModuleModel::$company) return false;
        if ($this->getCompanyId() == ModuleModel::$company->getId() && $this->getIsDeleted() == Helpers::NO) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $currency
     * @return \Phalcon\Mvc\Model\ResultsetInterface
     */
    public static function __findFirstByCurrency($currency)
    {
        return self::findFirst([
            'conditions' => 'company_id = :company_id: AND currency = :currency: and is_deleted = 0',
            'bind' => [
                'company_id' => ModuleModel::$company->getId(),
                'currency' => $currency
            ]
        ]);
    }
}
