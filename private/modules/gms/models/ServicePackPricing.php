<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;


class ServicePackPricing extends \Reloday\Application\Models\ServicePackPricingExt {

    /**
     * @return bool
     */
    public function belongsToGms(){
        return $this->getCompanyId() == ModuleModel::$company->getId();
    }
}