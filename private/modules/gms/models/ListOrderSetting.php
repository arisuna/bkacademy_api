<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class ListOrderSetting extends \Reloday\Application\Models\ListOrderSettingExt
{	

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

	public function initialize(){
		parent::initialize(); 
	}

    /**
     * @param $options
     */
	public static function checkOrderSetting($options = []){
	    $listOrderSetting = self::findFirst([
            'conditions' => 'object_type = :object_type: AND object_uuid = :object_uuid: AND list_type = :list_type:',
            'bind' => [
                'object_type' => $options['object_type'],
                'object_uuid' => $options['uuid'],
                'list_type' => $options['list_type']
            ]
        ]);
	    if (!$listOrderSetting){

            $newData = new ListOrderSetting();
            $newData->setCompanyId(ModuleModel::$company->getId());
            $newData->setObjectType($options['object_type']);
            $newData->setObjectUuid($options['uuid']);
            $newData->setListType($options['list_type']);
            $newData->setOrderSetting(isset($options['order_setting']) && is_array($options['order_setting']) ? json_encode($options['order_setting']) : null);
            $newData->setCreatedAt(date('Y-m-d H:i:s'));

            $result = $newData->__quickCreate();
        }else{
	        if (isset($options['id']) && intval($options['id'] > 0)){
	            $orderSetting = json_decode($listOrderSetting->getOrderSetting());
	            if (is_array($orderSetting) && !in_array(intval($options['id']), $orderSetting)){
                    $orderSetting[] = intval($options['id']);
                    $listOrderSetting->setOrderSetting(json_encode($orderSetting, true));
                    $result = $listOrderSetting->__quickSave();
                }

            }
        }

        return true;
    }
}
