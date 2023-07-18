<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Phalcon\Mvc\Model\Query\Builder;

class FilterConfig extends \Reloday\Application\Models\FilterConfigExt
{

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

	public function initialize(){
		parent::initialize();
	}

    /**
     * List by user and type of module
     * @param  array $options
     * @return array
     */
    public static function listByCriteria($options = array())
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\FilterConfig', 'FilterConfig');
        $queryBuilder->columns([
            'FilterConfig.id',
            'FilterConfig.name',
            'FilterConfig.user_profile_id',
            'FilterConfig.target_active',
            'FilterConfig.creator_user_profile_id',
        ]);
        $queryBuilder->distinct(true);

        $queryBuilder->where("FilterConfig.company_id IS NULL OR FilterConfig.company_id = '" . $options["company_id"] . "'");

        // If admin no filter
        if ( ! isset($options["is_admin"]) || ! $options["is_admin"] ) {
            if (isset($options["user_profile_id"])) {
                if (isset($options['is_manager']) && $options['is_manager'] == true){
                    $queryBuilder->andWhere("FilterConfig.creator_user_profile_id = '" . $options["user_profile_id"] . "'");
                }else{
                    $queryBuilder->andWhere("FilterConfig.user_profile_id IS NULL OR FilterConfig.creator_user_profile_id = '" . $options["user_profile_id"] . "'");
                }

            } else {
                return [
                    'success' => false,
                    'detail' => 'LIST_FILTER_CONFIG_FAIL_USER_NOT_FOUND_TEXT'
                ];
            }
        }

        if (isset($options["target"])) {
            $queryBuilder->andWhere("FilterConfig.target = '" . $options["target"] . "'");
        }

        if (isset($options["target_active"])) {
            $queryBuilder->andWhere("FilterConfig.target_active = '" . $options["target_active"] . "'");
        }

        $queryBuilder->orderBy('FilterConfig.creator_user_profile_id ASC, FilterConfig.id ASC');

        try {
            $users = ($di->get('modelsManager')->executeQuery($queryBuilder->getPhql()));
            return [
                'success' => true,
                'data' => $users
            ];
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

    public static function filterItemByCriteria($options = array())
    {
        $queryBuilder = new Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\FilterConfig', 'FilterConfig');
        $queryBuilder->columns([
            'FilterConfig.id',
            'FilterConfig.name',
            'FilterConfig.user_profile_id',
            'FilterConfig.target_active',
            'FilterConfig.creator_user_profile_id',
            'item_id' => 'FilterConfigItem.id',
            'value_id' => 'FilterConfigItemValue.id',
            'field_id' => 'FilterField.id',
            'field_name' => 'FilterField.name',
            'operator' =>'FilterConfigItemValue.operator',
            'value' => 'FilterConfigItemValue.value',

        ]);
        $queryBuilder->distinct(true);

        $queryBuilder->leftJoin('\Reloday\Gms\Models\FilterConfigItem', 'FilterConfigItem.filter_config_id = FilterConfig.id', 'FilterConfigItem');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\FilterConfigItemValue', 'FilterConfigItemValue.filter_config_item_id = FilterConfigItem.id', 'FilterConfigItemValue');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\FilterField', 'FilterField.id = FilterConfigItem.filter_field_id', 'FilterField');

        $queryBuilder->where("FilterConfig.company_id IS NULL OR FilterConfig.company_id = '" . $options["company_id"] . "'");

        // If admin no filter
        if ( ! isset($options["is_admin"]) || ! $options["is_admin"] ) {
            if (isset($options["user_profile_id"])) {
                if (isset($options['is_manager']) && $options['is_manager'] == true){
                    $queryBuilder->andWhere("FilterConfig.creator_user_profile_id = '" . $options["user_profile_id"] . "'");
                }else{
                    $queryBuilder->andWhere("FilterConfig.user_profile_id IS NULL OR FilterConfig.creator_user_profile_id = '" . $options["user_profile_id"] . "'");
                }

            } else {
                return [
                    'success' => false,
                    'detail' => 'FILTER_CONFIG_FAIL_USER_NOT_FOUND_TEXT'
                ];
            }
        }

        if (isset($options["target"])) {
            $queryBuilder->andWhere("FilterConfig.target = '" . $options["target"] . "'");
        }

        if (isset($options["target_active"])) {
            $queryBuilder->andWhere("FilterConfig.target_active = '" . $options["target_active"] . "'");
        }

        if (isset($options["filter"])) {
            $queryBuilder->andWhere("FilterConfig.name = '" . $options["filter"] . "'");
        }

        $queryBuilder->offset(0);
        $queryBuilder->limit(1);

        try {
            $items = $queryBuilder->getQuery()->execute()->toArray();

            return [
                'success' => true,
                'data' => $items && count($items) ? $items[0] : []
            ];
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

    /**
     * @param $filter_config_id
     * @return bool
     */
    public static function __checkFilterCached($filter_config_id, $checkItems = []){
        $di = \Phalcon\DI::getDefault();
        $cacheManager = $di->get('cache');
        $aFilterConfigItem = $cacheManager->get('TMP_ITEMS_FILTER_' . $filter_config_id);

        return !($aFilterConfigItem === $checkItems);


//        $aItemData = [];
//        $filterConfig = self::findFirstById($filter_config_id);
//        if($filterConfig){
//            $filterConfigItem = new FilterConfigItem();
//            $aOptions = [
//                'target' => $filterConfig->getTarget(),
//                'filter_config_id' => $filterConfig->getId(),
//            ];
//            $aItem = $filterConfigItem::listJoinByCriteria($aOptions, false);
//
//            if($aItem['success']){
//                $aItemData = $aItem['data'];
//            }
//        }
//
//        return ($aFilterConfigItem !== $aItemData);
    }
}
