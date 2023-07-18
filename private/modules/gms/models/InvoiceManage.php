<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;

use Reloday\Application\Lib\FPDF_Common;
use Reloday\Application\Lib\Utils;
use Reloday\Gms\Models\MediaAttachment;
use Phalcon\Http\Request;

class InvoiceManage extends \Reloday\Application\Models\InvoiceManageExt
{
    /**
     *
     */
    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('company_id', 'Reloday\Gms\Models\Company', 'id', ['alias' => 'Company']);
        $this->belongsTo('owner_id', 'Reloday\Gms\Models\Company', 'id', ['alias' => 'OwnerCompany']);
        $this->belongsTo('country_id', 'Reloday\Gms\Models\Country', 'id', ['alias' => 'Country']);
        $this->belongsTo('tax_id', 'Reloday\Gms\Models\TaxRule', 'id', ['alias' => 'Tax']);
    }

    /**
     *
     */
    public function belongsToGms()
    {
        if ($this->getOwnerId() == ModuleModel::$company->getId()) {
            return true;
        } else {
            return false;
        }
    }
}