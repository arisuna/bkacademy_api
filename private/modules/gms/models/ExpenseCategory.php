<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class ExpenseCategory extends \Reloday\Application\Models\ExpenseCategoryExt
{
    public function initialize()
    {
        parent::initialize();
    }

    /**
     * @return \Reloday\Gms\Models\Workflow[]
     */
    public static function getListOfMyCompany($options = array())
    {
        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $categories = ExpenseCategory::find([
                'conditions' => 'company_id = :company_id: AND is_deleted = 0 AND name like :query:',
                'bind' => [
                    'company_id' => ModuleModel::$company->getId(),
                     'query' => '%' . $options['query'] . '%',
                ]
            ]);
        } else {
            $categories = ExpenseCategory::find([
                'conditions' => 'company_id = :company_id: AND is_deleted = 0',
                'bind' => [
                    'company_id' => ModuleModel::$company->getId()
                ]
            ]);
        }
        $category_array = [];
        if ($categories->count() > 0) {
            foreach ($categories as $category) {
                $item = $category->toArray();
                $item['unit_name'] = isset(self::UNIT_NAME[$category->getUnit()]) ? self::UNIT_NAME[$category->getUnit()] : "";
                $category_array[] = $item;
            }
        }
        return ($category_array);
    }

    /**
     * @return \Reloday\Gms\Models\Workflow[]
     */
    public static function getListActiveOfMyCompany()
    {
        $categories = ExpenseCategory::find([
            'conditions' => 'company_id = :company_id: AND is_deleted = 0 AND is_enabled = 1',
            'bind' => [
                'company_id' => ModuleModel::$company->getId()
            ]
        ]);
        $category_array = [];
        if ($categories->count() > 0) {
            foreach ($categories as $category) {
                $item = $category->toArray();
                $item['unit_name'] = ($category->getUnit() > 0 && isset(self::UNIT_NAME[$category->getUnit()])) ? self::UNIT_NAME[$category->getUnit()] : "";
                $category_array[] = $item;
            }
        }
        return ($category_array);
    }

    /**
     * @return \Reloday\Gms\Models\Workflow[]
     */
    public static function getListTimelogCategoryOfMyCompany()
    {
        $categories = ExpenseCategory::find([
            'conditions' => 'company_id = :company_id: AND is_deleted = 0 AND is_enabled = 1 AND unit IN ({units:array})',
            'bind' => [
                'company_id' => ModuleModel::$company->getId(),
                'units' => ExpenseCategory::TIMELOG_CATEGORY_UNIT
            ]
        ]);
        $category_array = [];
        if ($categories->count() > 0) {
            foreach ($categories as $category) {
                $item = $category->toArray();
                $item['unit_name'] = self::UNIT_NAME[$category->getUnit()];
                $category_array[] = $item;
            }
        }
        return ($category_array);
    }

    /**
     * check is belong to GMS
     * @return [type] [description]
     */
    public function belongsToGms()
    {
        if (!ModuleModel::$company) return false;
        if ($this->getCompanyId() == ModuleModel::$company->getId()) {
            return true;
        } else {
            return false;
        }
    }

    public function isEditable(){
        $checkExpenseIfExist = Expense::findFirstByExpenseCategoryId($this->getId());
        if($checkExpenseIfExist instanceof Expense){
            return false;
        }
        return true;
    }
}
