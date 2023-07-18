<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;

/**
 * Class ServicePricing
 * @package Reloday\Gms\Models
 */
class ServicePricing extends \Reloday\Application\Models\ServicePricingExt {

    /**
     * check if the Service Pricing belongs to the current gms company
     * current gms company loaded in ModuleModel
     * @return bool
     */
    public function belongsToGms(){
        return $this->getCompanyId() == ModuleModel::$company->getId();
    }
}