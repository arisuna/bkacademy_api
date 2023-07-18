<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class RelocationGuide extends \Reloday\Application\Models\RelocationGuideExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    public function initialize(){
        parent::initialize();

        $this->belongsTo('relocation_id', 'Reloday\Gms\Models\Relocation', 'id', [
            'alias' => 'Relocation'
        ]);


        $this->belongsTo('employee_id', 'Reloday\Gms\Models\Employee', 'id', [
            'alias' => 'Employee'
        ]);

        $this->belongsTo('guide_id', 'Reloday\Gms\Models\Guide', 'id', [
            'alias' => 'Guide'
        ]);
    }

    /**
     * @param $options
     * @return mixed
     */

    public static function __findWithFilter($options = []){

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\RelocationGuide', 'RelocationGuide');
        $queryBuilder->distinct(true);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Guide', 'RelocationGuide.id = Guide.id', 'Guide');
        $queryBuilder->where('RelocationGuide.relocation_id = :relocation_id:', ['relocation_id' => $options['relocation_id']]);

        try{
            $data = $queryBuilder->getQuery()->execute();

            return [
                'success' => true,
                'data' => $data
            ];
        } catch (\Phalcon\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()], 'data' => []];
        } catch (\PDOException $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()], 'data' => []];
        } catch (\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()], 'data' => []];
        }
    }

    /**
     * @param $relocation_id
     * @param $guide_id
     * @param $employee_id
     * @return mixed
     */
    public static function __addRelocationGuide($relocation_id, $guide_id, $employee_id){
        $relocationGuide = new self();
        $random = new Random();

        $relocationGuide->setUuid($random->uuid());
        $relocationGuide->setRelocationId($relocation_id);
        $relocationGuide->setGuideId($guide_id);
        $relocationGuide->setEmployeeId($employee_id);
        $relocationGuide->setCreatorUserProfileUuid(ModuleModel::$user_profile->getUuid());
        $return = $relocationGuide->__quickCreate();

        return $return;
    }


    /**
     * @param $relocation_id
     * @param $employee_id
     * @return mixed
     */
    public static function __removeAllRelocationGuide($relocation_id){
        $relocationGuides = self::find([
            'conditions' => 'relocation_id = :relocation_id:',
            'bind' => [
                'relocation_id' => $relocation_id,
            ]
        ]);

        $return = ['success' => true];
        if (count($relocationGuides) > 0)
            foreach ($relocationGuides as $relocationGuide){
                $return = $relocationGuide->__quickRemove();
            }

        return $return;
    }

    /**
     * @return mixed
     */
    public function getParsedData(){
        $item = $this->toArray();
        return $item;
    }
}
