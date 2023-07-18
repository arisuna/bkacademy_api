<?php

namespace Reloday\Gms\Models;

use Phalcon\Di;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Mvc\Model\Query\Builder;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class FilterField extends \Reloday\Application\Models\FilterFieldExt
{

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

	public function initialize(){
		parent::initialize();
	}

    /**
     * Allow retrieving rows from filter_field table
     * @param array $aCriteria
     * @return array
     */
    public static function listByCriteria($aCriteria)
    {
        $di = DI::getDefault();
        $queryBuilder = new Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\FilterField', 'FilterField');
        $queryBuilder->columns([
            'FilterField.id',
            'FilterField.name',
            'FilterField.target'
        ]);

        if ( isset($aCriteria['target']) ) {
            $queryBuilder->andwhere("FilterField.target = '" . $aCriteria["target"] . "'");
        }

        try {
            $fields = ($di->get('modelsManager')->executeQuery($queryBuilder->getPhql()));
            return [
                'success' => true,
                'data' => $fields
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
}
