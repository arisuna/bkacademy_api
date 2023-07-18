<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Module;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Paginator\Factory;

class NeedFormGabarit extends \Reloday\Application\Models\NeedFormGabaritExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    const TYPE_SURVEY = 0;
    const TYPE_NEED_FORM = 1;
    const LIMIT_PER_PAGE = 10;

    /**
     *
     */
    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('need_form_category_id', 'Reloday\Gms\Models\NeedFormCategory', 'id', [
            'alias' => 'NeedFormCategory'
        ]);

        $this->belongsTo('company_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'Company'
        ]);
        $this->hasMany('id', 'Reloday\Gms\Models\NeedFormGabaritSection', 'need_form_gabarit_id', [
            'alias' => 'NeedFormGabaritSection',
        ]);

        $this->hasManyToMany('id', 'Reloday\Gms\Models\NeedFormGabaritSection', 'need_form_gabarit_id', 'id', 'Reloday\Gms\Models\NeedFormGabaritItem', 'need_form_gabarit_section_id', [
            'alias' => 'NeedFormGabaritItems'
        ]);

        $this->hasManyToMany('id', 'Reloday\Gms\Models\NeedFormGabaritServiceCompany', 'need_form_gabarit_id', 'service_company_id', 'Reloday\Gms\Models\ServiceCompany', 'id', [
            'alias' => 'ServiceCompanies'
        ]);

        $this->hasMany('id', 'Reloday\Gms\Models\NeedFormRequest', 'need_form_gabarit_id', [
            'alias' => 'NeedFormRequest'
        ]);
    }

    /**
     * [get description]
     * @param  {[type]} $name [description]
     * @return {[type]}       [description]
     */
    public function get($name)
    {
        return $this->$name;
    }

    /**
     * [set description]
     * @param {[type]} $name     [description]
     * @param {[type]} $variable [description]
     */
    public function set($name, $value)
    {
        $this->$name = $value;
    }

    /**
     * [__save description]
     * @param  {Array}  $custom [description]
     * @return {[type]}         [description]
     */
    public function __save($custom = [])
    {
        $this->setData($custom);
        /****** YOUR CODE ***/
        if ($this->getStatus() == '' || $this->getStatus() == null) {
            $this->setStatus(self::STATUS_ACTIVE);
        }

        /** type need form defaut */
        if ($this->getType() == '' || $this->getType() == null) {
            $this->getType(self::TYPE_NEED_FORM);
        }
        return $this->__quickUpdate();
    }

    /**
     * [__save description]
     * @param  {Array}  $custom [description]
     * @return {[type]}         [description]
     */
    public function __create($custom = [])
    {
        $this->setData($custom);
        /****** YOUR CODE ***/
        if ($this->getStatus() == '' || $this->getStatus() == null) {
            $this->setStatus(self::STATUS_ACTIVE);
        }

        /** type need form defaut */
        if ($this->getType() == '' || $this->getType() == null) {
            $this->getType(self::TYPE_NEED_FORM);
        }
        return $this->__quickCreate();
    }

    /**
     * @return bool
     */
    public function belongsToGms()
    {
        if (ModuleModel::$company->getId() == $this->getCompanyId()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $relocation_id
     * @return mixed
     */
    public function getNeedFormRequestOfRelocation($relocation_id)
    {
        return $this->getNeedFormRequest([
            'conditions' => 'relocation_id = :relocation_id:',
            'bind' => [
                'relocation_id' => $relocation_id
            ]
        ])->getFirst();
    }

    /**
     * @param $relocation_id
     * @return mixed
     */
    public function getNeedFormRequestOfServiceCompany($service_company_id)
    {
        return $this->getNeedFormRequest([
            'conditions' => 'service_company_id = :service_company_id:',
            'bind' => [
                'service_company_id' => $service_company_id
            ]
        ])->getFirst();
    }

    /**
     * @param $relocation_service_company_id
     * @return mixed
     */
    public function getNeedFormRequestOfRelocationServiceCompany($relocation_service_company_id)
    {
        return $this->getNeedFormRequest([
            'conditions' => 'relocation_service_company_id = :relocation_service_company_id: and status != :status:',
            'bind' => [
                'relocation_service_company_id' => $relocation_service_company_id,
                'status' => NeedFormRequest::STATUS_ARCHIVED
            ],
            'order' => 'created_at DESC, id DESC'
        ])->getFirst();
    }


    /**
     * @param $relocation_service_company_id
     * @return mixed
     */

    public function getNeedFormRequestOfObject($object_uuid)
    {
        return $this->getNeedFormRequest([
            'conditions' => 'object_uuid = :object_uuid:',
            'bind' => [
                'object_uuid' => $object_uuid
            ]
        ])->getFirst();
    }

    /**
     * @return array
     */
    public static function __findWithFilter($options = [], $orders = [])
    {
        if (isset($options['mode']) && is_string($options['mode'])) {
            $mode = $options['mode'];
        } else {
            $mode = "large";
        }
        $bindArray = [];
        $attribute = Attributes::findFirstByCode(self::ATTRIBUTE_CATEGORY);
        if(!$attribute){
            return ['success' => false, 'detail' => 'attribute not found'];
        }
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->distinct(true);
        $queryBuilder->addFrom('\Reloday\Gms\Models\NeedFormGabarit', 'NeedFormGabarit');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\AttributesValue',"AttributeCategory.id = (SUBSTRING_INDEX(NeedFormGabarit.need_form_category, '_', -1))", 'AttributeCategory');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\AttributesValueTranslation',"TranslationCategory.attributes_value_id = AttributeCategory.id AND TranslationCategory.language = '". ModuleModel::$language . "'", 'TranslationCategory');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\NeedFormGabaritServiceCompany', 'NeedFormGabarit.id = NeedFormGabaritServiceCompany.need_form_gabarit_id', 'NeedFormGabaritServiceCompany');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\ServiceCompany', 'ServiceCompany.id = NeedFormGabaritServiceCompany.service_company_id', 'ServiceCompany');

        $queryBuilder->groupBy('NeedFormGabarit.id');

        $queryBuilder->columns([
            'NeedFormGabarit.id',
            'NeedFormGabarit.number',
            'NeedFormGabarit.name',
            'NeedFormGabarit.description',
            'NeedFormGabarit.need_form_category',
            'NeedFormGabarit.status',
            'NeedFormGabarit.type',
            'NeedFormGabarit.updated_at',
            'NeedFormGabarit.created_at',
            'category' => 'TranslationCategory.value'
        ]);

        $queryBuilder->andwhere("NeedFormGabarit.company_id = :company_id: and NeedFormGabarit.status <> :status_archived:", [
            'company_id' => ModuleModel::$company->getId(),
            'status_archived' =>  NeedFormGabarit::STATUS_ARCHIVED
        ]);

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("NeedFormGabarit.name LIKE :query: OR NeedFormGabarit.number LIKE :query:
            OR TranslationCategory.value LIKE :query: OR ServiceCompany.name LIKE :query:", [
                'query' => '%' . $options['query'] . '%',
            ]);
        } 
        
        if (isset($options['categories']) && is_array($options['categories']) && count($options['categories']) > 0) {
            $queryBuilder->andwhere("NeedFormGabarit.need_form_category IN ({categories:array})", ["categories" => $options['categories']]);
        }

        if (isset($options['service_ids']) && is_array($options['service_ids']) && count($options['service_ids']) > 0) {
            $queryBuilder->andwhere("ServiceCompany.id IN ({service_ids:array})", [
                'service_ids' => $options['service_ids']
            ]);
        }


        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;

        if (!isset($options['page'])) {
            $page = intval($start / self::LIMIT_PER_PAGE) + 1;
        } else {
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;
        }

        /** process order */
        if (count($orders)) {
            $order = reset($orders);
            $queryBuilder->orderBy('NeedFormGabarit.created_at DESC');
            if ($order['field'] == "name") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['NeedFormGabarit.name ASC']);
                } else {
                    $queryBuilder->orderBy(['NeedFormGabarit.name DESC']);
                }
            }

            if ($order['field'] == "updated_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['NeedFormGabarit.updated_at ASC']);
                } else {
                    $queryBuilder->orderBy(['NeedFormGabarit.updated_at DESC']);
                }
            }

            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['NeedFormGabarit.created_at ASC']);
                } else {
                    $queryBuilder->orderBy(['NeedFormGabarit.created_at DESC']);
                }
            }

        }else{
            $queryBuilder->orderBy('NeedFormGabarit.created_at DESC');
        }

        try {

            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();

            $itemsArray = [];

            foreach ($pagination->items as $item) {
                $itemArray = $item->toArray();
                $itemArray['id'] = intval($itemArray['id']);
                $itemArray['status'] = intval($itemArray['status']);
                $need_form_gabarit = self::findFirstById($itemArray['id']);
                $itemArray['services'] = [];
                $itemArray['services_tooltip'] = "";
                $services = $need_form_gabarit->getServiceCompanies([
                    'conditions' => 'status != -1',
                ]);
                if(count($services) > 0){
                    $i = 0;
                    foreach($services as $service){
                         
                        $itemArray['services'][] = $service->toArray();
                        $itemArray['services_tooltip'] .=  ($i < count($services) - 1) ? $service->getName() . "; " : $service->getName();
                        $i ++;
                    }
                }
                $itemsArray[] = $itemArray;
            }

            return [
                'success' => true,
                'page' => $page,
                // 'order' => $order,
                // 'sql' => $queryBuilder->getQuery()->getSql(),
                // 'params' => $options,
                'data' => $itemsArray,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_rest_items' => $pagination->total_items - $limit * $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages
            ];

        } catch (\Phalcon\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }


}
