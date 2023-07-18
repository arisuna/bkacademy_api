<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class AssignmentCompanyData extends \Reloday\Application\Models\AssignmentCompanyDataExt
{

    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('hr_company_id', '\Reloday\Gms\Models\Company', 'id', [
            'alias' => 'HrCompany'
        ]);

        $this->belongsTo('gms_company_id', '\Reloday\Gms\Models\Company', 'id', [
            'alias' => 'GmsCompany'
        ]);

        $this->belongsTo('assignment_id', '\Reloday\Gms\Models\Assignment', 'id', [
            'alias' => 'Assignment'
        ]);
    }

    /**
     * @param $assignment
     * @param $customFieldName
     * @param $customFieldValue
     */
    public static function __addNew($assignment, $customFieldName, $customFieldValue)
    {
        $model = self::findFirst([
            'conditions' => 'assignment_id = :assignment_id: AND company_id = :company_id: AND field_name = :field_name:',
            'bind' => [
                'assignment_id' => $assignment->getId(),
                'company_id' => ModuleModel::$company->getId(),
                'field_name' => $customFieldName
            ]
        ]);
        
        if (!$model) {
            $model = new self();
        }

        $model->setAssignmentId($assignment->getId());
        $model->setCompanyId(ModuleModel::$company->getId());
        $model->setFieldName($customFieldName);
        $model->setFieldValue($customFieldValue);
        if ($model->getId() > 0) {
            return $model->__quickUpdate();
        } else {
            return $model->__quickCreate();
        }
    }
}
