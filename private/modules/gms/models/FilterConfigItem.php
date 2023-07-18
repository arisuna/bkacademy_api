<?php

namespace Reloday\Gms\Models;

use Phalcon\Di;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Phalcon\Mvc\Model\Query\Builder;

class FilterConfigItem extends \Reloday\Application\Models\FilterConfigItemExt
{

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

	public function initialize(){
		parent::initialize();
	}

    /**
     * List filter config items
     * @param  array $aCriteria
     * @return array
     */
    public static function listJoinByCriteria($aCriteria, $isFullData = true)
    {
        if ( ! $aCriteria['filter_config_id'] ) {
            return [
                'success' => false,
                'detail' => 'LIST_FILTER_CONFIG_ITEM_FILTER_ID_MANDATORY_TEXT'
            ];
        }

        $di = DI::getDefault();
        $queryBuilder = new Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\FilterConfigItem', 'FilterConfigItem');
        $queryBuilder->columns([
            'item_id' => 'FilterConfigItem.id',
            'value_id' => 'FilterConfigItemValue.id',
            'field_id' => 'FilterField.id',
            'field_name' => 'FilterField.name',
            'FilterConfigItemValue.operator',
            'FilterConfigItemValue.value'
        ]);
        $queryBuilder->leftJoin('\Reloday\Gms\Models\FilterConfigItemValue', 'FilterConfigItemValue.filter_config_item_id = FilterConfigItem.id', 'FilterConfigItemValue');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\FilterField', 'FilterField.id = FilterConfigItem.filter_field_id', 'FilterField');

        $queryBuilder->where("FilterConfigItem.filter_config_id = '" . $aCriteria["filter_config_id"] . "'");


        if ( isset($aCriteria['target']) ) {
            $queryBuilder->andWhere("FilterField.target = '" . $aCriteria['target'] . "'");
        }

        try {
//            $items = ($di->get('modelsManager')->executeQuery($queryBuilder->getPhql()));
            $items = $queryBuilder->getQuery()->execute()->toArray();
            if(!$isFullData){
                $data = [];
                foreach ($items as $item){
                    $arr['field_name'] = $item['field_name'];
                    $arr['operator'] = $item['operator'];
                    $arr['value'] = $item['value'];
                    $data[] = $arr;
                }

                return [
                    'success' => true,
                    'data' => $data
                ];
            }else{
                return [
                    'success' => true,
                    'data' => $items
                ];
            }

        } catch (\PDOException $e) {
            return [
                'success' => false,
                'detail' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'detail' => $e->getMessage()
            ];
        }
    }
}
