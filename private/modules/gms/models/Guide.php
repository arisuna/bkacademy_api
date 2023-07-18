<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Models\FilterConfigExt;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Paginator\Factory;

class Guide extends \Reloday\Application\Models\GuideExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;
    const LIMIT_PER_PAGE = 10;

    public function initialize()
    {
        parent::initialize();
        $this->hasMany('id', 'Reloday\Gms\Models\GuideContent', 'guide_id', [
            'alias' => 'Contents',
            'params' => [
                'order' => 'created_at DESC',
            ]
        ]);
    }

    /**
     * @return bool
     */
    public function belongsToCompany()
    {
        return $this->getCompanyId() == ModuleModel::$company->getId();
    }

    /**
     * @param array $options
     * @param array $orders
     * @param bool $fullinfo
     * @return array
     */
    public static function __findWithFilter($options = array(), $orders = array(), $fullinfo = false)
    {

        $bindArray = [];

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Guide', 'Guide');
        $queryBuilder->distinct(true);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Company', 'Guide.company_id = Company.id', 'Company');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Country', 'Country.id = Guide.country_id', 'Country');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Company', 'TargetCompany.id = Guide.target_company_id', 'TargetCompany');
        if ($fullinfo == false) {
            $queryBuilder->columns([
                'Guide.uuid',
                'Guide.id',
                'Guide.title',
                'Guide.city',
                'company_name' => 'TargetCompany.name',
                'country_iso_code_flag' => 'Country.cio_flag',
                'country' => 'Country.name',
                'Guide.created_at',
                'Guide.language']);
        }

        $queryBuilder->where('Guide.company_id = :root_company_id:', [
            'root_company_id' => intval(ModuleModel::$company->getId())
        ]);

        $queryBuilder->andwhere('Guide.status = :status_active:', [
            'status_active' => self::STATUS_ACTIVE
        ]);

        if (isset($options['languagues']) && is_array($options['languagues']) && count($options['languagues']) > 0) {
            $queryBuilder->andwhere("Guide.language IN ({languagues:array})", [
                'languagues' => $options['languagues'],
            ]);
        }

        if (isset($options['country_ids']) && is_array($options['country_ids']) && count($options['country_ids']) > 0) {
            $queryBuilder->andwhere("Guide.country_id IN ({country_ids:array})", [
                'country_ids' => $options['country_ids'],
            ]);
        }

        if (isset($options['company_ids']) && is_array($options['company_ids']) && count($options['company_ids']) > 0) {
            $queryBuilder->andwhere("Guide.target_company_id IN ({company_ids:array})", [
                'company_ids' => $options['company_ids'],
            ]);
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("(Guide.title LIKE :query: OR Guide.summary LIKE :query:)", [
                'query' => '%' . $options['query'] . '%',
            ]);
        }

        if (isset($options['filter_config_id'])) {

            $tableField = [
                'COUNTRY_ARRAY_TEXT' => 'Guide.country_id',
                'ACCOUNT_ARRAY_TEXT' => 'Guide.target_company_id',
                'LANGUAGE_TEXT' => 'Guide.language',
            ];

            $dataType = [
            ];

            Helpers::__addFilterConfigConditions($queryBuilder, $options['filter_config_id'], $options['is_tmp'], FilterConfigExt::GUIDE_FILTER_TARGET, $tableField, $dataType, ModuleModel::$user_profile->getUuid());
        }

        /** process order */
        if (count($orders)) {
            $order = reset($orders);
            $queryBuilder->orderBy('Guide.created_at DESC');

            if ($order['field'] == "name" || $order['field'] == "title") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Guide.title ASC']);
                } else {
                    $queryBuilder->orderBy(['Guide.title DESC']);
                }
            }

            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Guide.created_at ASC']);
                } else {
                    $queryBuilder->orderBy(['Guide.created_at DESC']);
                }
            }

            if ($order['field'] == "country") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Country.name ASC']);
                } else {
                    $queryBuilder->orderBy(['Country.name DESC']);
                }
            }

            if ($order['field'] == "target_company") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['TargetCompany.name ASC']);
                } else {
                    $queryBuilder->orderBy(['TargetCompany.name DESC']);
                }
            }

            if ($order['field'] == "language") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Guide.language ASC']);
                } else {
                    $queryBuilder->orderBy(['Guide.language DESC']);
                }
            }

            if ($order['field'] == "city") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Guide.city ASC']);
                } else {
                    $queryBuilder->orderBy(['Guide.city DESC']);
                }
            }
        }else{
            $queryBuilder->orderBy("Guide.created_at DESC");
        }

        $queryBuilder->groupBy('Guide.id');

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : (
        isset($options['length']) && is_numeric($options['length']) && $options['length'] > 0 ? $options['length'] : self::LIMIT_PER_PAGE);

        if (!isset($options['page'])) {
            $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
            $page = intval($start / $limit) + 1;
        } else {
            $start = 0;
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 1;
        }

        try {
            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();
            $guide_array = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $guide) {
                    if ($fullinfo == false) {
                        $guide = $guide->toArray();
                        $guide_array[$guide['uuid']] = $guide;
                    }
                }
            }

            return [
                'success' => true,
                '$start' => $start,
                '$limit' => $limit,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages,
                'page' => $page,
                'order' => $orders,
                'data' => array_values($guide_array),
            ];

        } catch (\Phalcon\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }

    /**
     * @param $content
     */
    public function updateContent($content)
    {
        $newContent = new GuideContent();
        $newContent->setGuideId($this->getId());
        $newContent->setContent($content);
        $newContent->setVersionId(1);
        return $newContent->__quickCreate();
    }

    /**
     * @param $options
     * @return array
     */
    public static function __findGuideForRelocationWithFilters($options)
    {
        $destination_country_id = isset($options['destination_country_id']) ? $options['destination_country_id'] : null;
        $destination_city = isset($options['destination_city']) ? $options['destination_city'] : null;

        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Guide', 'Guide');
        $queryBuilder->distinct(true);
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Company', 'Company.id = Guide.company_id', 'Company');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Country', 'Country.id = Guide.country_id', 'Country');
        $queryBuilder->where('Guide.status = :status_active:', [
            'status_active' => Guide::STATUS_ACTIVE,
        ]);

        $queryBuilder->andWhere('(Guide.company_id  = :gms_company_id:)',
            [
                'gms_company_id' => isset($options['gms_company_id']) ? $options['gms_company_id'] : null,
            ]
        );

//        $queryBuilder->andWhere('Country.id  = :destination_country_id:',
//            [
//                'destination_country_id' => $destination_country_id
//            ]
//        );


        $suggested = true;
        if (isset($options['excepted_guide_ids']) && is_array($options['excepted_guide_ids']) && count($options['excepted_guide_ids']) > 0) {
            $suggested = false;
            $queryBuilder->andwhere("Guide.id NOT IN ({excepted_guide_ids:array})", [
                'excepted_guide_ids' => $options['excepted_guide_ids'],
            ]);
        }


        try {
            $guides = $queryBuilder->getQuery()->execute();
            $data = [];
            foreach ($guides as $guide){
                $item = $guide->toArray();
                $item['company_name'] = $guide->getCompany()->getName();
                $item['content'] = $guide->getContent();
                if ($suggested){
                    if (($item['country_id'] == $destination_country_id &&
                        ($item['city'] == null || $item['city'] == $destination_city) &&
                        $item['target_company_id'] == $options['hr_company_id']) ||
                        ($item['country_id'] == $destination_country_id &&
                            $item['target_company_id'] == null &&
                            ($item['city'] == null || $item['city'] == $destination_city))){
                        $item['is_selected'] = true;
                    }else{
                        $item['is_selected'] = false;
                    }
                }else{
                    $item['is_selected'] = false;
                }
                $logo = ObjectAvatar::__getLogo($guide->getUuid());
                $item['logo'] = $logo ? $logo['image_data']['url_thumb'] : null;
                $item['country_name'] = $guide->getCountry() ? $guide->getCountry()->getName() : null;
                $item['hr_company_name'] = $guide->getHrCompany() ? $guide->getHrCompany()->getName() : null;



                $data[] = $item;
            }

            usort($data, function($left, $right){
                return $right['is_selected'] - $left['is_selected'];
            });

            return [
                'success' => true,
                'data' => $data,
                'params' => $options
            ];


        } catch (\Phalcon\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()], 'data' => []];
        } catch (\PDOException $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()], 'data' => []];
        } catch (\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()], 'data' => []];
        }
    }
}
