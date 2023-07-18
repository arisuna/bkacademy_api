<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Help\AutofillEmailTemplateHelper;
use Reloday\Gms\Models\ModuleModel;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;

class CommentsTemplate extends \Reloday\Application\Models\CommentsTemplateExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;
    const LIMIT_PER_PAGE = 20;

    public function initialize()
    {
        parent::initialize();

        /** get media */
        $this->hasManyToMany(
            'uuid', 'Reloday\Gms\Models\MediaAttachment',
            'object_uuid', 'media_uuid',
            'Reloday\Gms\Models\Media', 'uuid', [
                'alias' => 'MediaList',
                'params' => [
                    'conditions' => 'Reloday\Gms\Models\Media.is_deleted = ' . Helpers::NO,
                ]
            ]
        );
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

        $req = new Request();
        $model = $this;
        $data = $req->getPost();

        if ($req->isPut()) {
            // Request update
            //
            $data_id = isset($custom['data_id']) && $custom['data_id'] > 0 ? $custom['data_id'] : $req->getPut('id');

            if ($data_id > 0) {
                $model = $this->findFirstById($data_id);
                if (!$model instanceof $this) {
                    return [
                        'success' => false,
                        'message' => 'DATA_NOT_FOUND_TEXT',
                    ];
                }
            }
            $data = $req->getPut();
        }


        /** @var [varchar] [set uunique id] */
        if (property_exists($model, 'uuid') && method_exists($model, 'getUuid') && method_exists($model, 'setUuid')) {
            $uuid = isset($data['uuid']) ? $data['uuid'] : (isset($custom['uuid']) ? $custom['uuid'] : $model->getUuid());
            if ($model->getUuid() == '') {
                $random = new Random;
                $uuid = $random->uuid();
            }
            if ($uuid != '') {
                $model->setUuid($uuid);
            }
        }
        /****** ALL ATTRIBUTES ***/
        $metadataManager = $model->getModelsMetaData(); //get Meta Data Manager
        $fields = $metadataManager->getAttributes($model);  //get Attributes from MetaData Manager
        $fields_numeric = $metadataManager->getDataTypesNumeric($model);

        foreach ($fields as $key => $field_name) {
            if ($field_name != 'id'
                && $field_name != 'uuid'
                && $field_name != 'created_at'
                && $field_name != 'updated_at'
                && $field_name != "password") {

                if (!isset($fields_numeric[$field_name])) {
                    $model->set(
                        $field_name,
                        isset($custom[$field_name]) ? $custom[$field_name] :
                            (isset($data[$field_name]) ? $data[$field_name] : $model->get($field_name))
                    );
                } else {
                    $field_name_value = isset($custom[$field_name]) ? $custom[$field_name] :
                        (isset($data[$field_name]) ? $data[$field_name] : $model->get($field_name));
                    if (is_numeric($field_name_value) && $field_name_value != '' && !is_null($field_name_value) && !empty($field_name_value)) {
                        $model->set($field_name, $field_name_value);
                    }
                }
            }
        }
        /****** YOUR CODE ***/

        /****** END YOUR CODE **/
        try {
            if ($model->getId() == null) {
                if ($req->isPost()) {
                    $reference = $this->getNewReference();
                    $model->set("reference", $reference);
                }
            }
            if ($model->save()) {
                return $model;
            } else {
                $msg = [];
                foreach ($model->getMessages() as $message) {
                    $msg[$message->getField()] = $message->getMessage();
                }
                $result = [
                    'success' => false,
                    'message' => 'DATA_SAVE_FAIL_TEXT',
                    'detail' => $msg,
                    'data' => $model
                ];
                return $result;
            }
        } catch (\PDOException $e) {
            $result = [
                'success' => false,
                'data' => $model,
                'message' => 'DATA_SAVE_FAIL_TEXT',
                'detail' => $e->getMessage(),
            ];
            return $result;
        }
    }

    /**
     * @return string
     */
    public function getNewReference()
    {
        $count = self::count([
            "conditions" => 'company_id = :company_id:',
            "bind" => [
                'company_id' => ModuleModel::$company->getId()
            ]
        ]);

        return self::PREFIX . "-" . ($count + 1);
    }


    /**
     * load all allowance type associated with an GMS with contract
     * @return [type] [description]
     */
    public static function __loadByCompany($query = '')
    {
        if($query != null && $query != '') {
            $templates = self::find([
                "conditions" => "company_id = :company_id: AND status <> :status_archived: and subject LIKE :query:",
                "bind" => [
                    'company_id' => ModuleModel::$company->getId(),
                    'status_archived' => self::STATUS_ARCHIVED,
                    "query" => '%'.$query.'%'
                ],
                "order" => "subject ASC"
            ]);

        } else {
            $templates = self::find([
                "conditions" => "company_id = :company_id: AND status <> :status_archived:",
                "bind" => [
                    'company_id' => ModuleModel::$company->getId(),
                    'status_archived' => self::STATUS_ARCHIVED,
                ],
                "order" => "subject ASC"
            ]);
        }
        $returnArray = [];
        if (count($templates)) {
            foreach ($templates as $template) {
                $returnArray[$template->getId()] = $template->toArray();
                //$mediaList = $template->getMediaList();
                //$returnArray[$template->getId()]['items'] = $mediaList;
                $returnArray[$template->getId()]['number_items'] = $template->countMediaList();
            }
        }
        return array_values($returnArray);
    }

    /**
     * check is belong to GMS
     * @return [type] [description]
     */
    public function belongsToGms()
    {
        $company = ModuleModel::$company;
        if ($company) {
            if ($this->getCompanyId() == $company->getId()) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public static function __findWithFilter($options = [], $orders = []){
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\CommentsTemplate', 'CommentsTemplate');
        $queryBuilder->distinct(true);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Company', 'Company.id = CommentsTemplate.company_id', 'Company');

        $queryBuilder->where("CommentsTemplate.company_id = :gms_company_id:", [
            'gms_company_id' => ModuleModel::$company->getId()
        ]);

        $queryBuilder->andwhere("CommentsTemplate.status <> :status_archived:", [
            'status_archived' => self::STATUS_ARCHIVED
        ]);

        if(isset($options['is_active']) && is_numeric($options['is_active'])){
            $queryBuilder->andwhere("CommentsTemplate.is_active = :is_active:", [
                'is_active' => $options['is_active']
            ]);
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("CommentsTemplate.subject LIKE :query:", [
                'query' => '%' . $options['query'] . '%',
            ]);
        }

        if (isset($options['supported_language_id']) && is_numeric($options['supported_language_id']) && $options['supported_language_id'] > 0) {
            $queryBuilder->andWhere('CommentsTemplate.supported_language_id = :supported_language_id:', [
                'supported_language_id' => $options['supported_language_id']
            ]);

        }

        if (isset($options['has_autofill']) && is_bool($options['has_autofill']) && $options['has_autofill']) {
            $queryBuilder->andWhere('CommentsTemplate.is_map_field = :is_map_field_yes:', [
                'is_map_field_yes' => ModelHelper::YES
            ]);

        }
        if (isset($options['has_files'])  && is_bool($options['has_files']) && $options['has_files']) {
            $queryBuilder->innerjoin('\Reloday\Gms\Models\MediaAttachment', 'MediaAttachment.object_uuid = CommentsTemplate.uuid', 'MediaAttachment');
        }

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        if (!isset($options['page'])) {
            $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
            $page = intval($start / $limit) + 1;
        } else {
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 1;
        }

        if (count($orders)) {
            $order = reset($orders);

            if (!$order['field']) {
                $queryBuilder->orderBy(['CommentsTemplate.created_at DESC']);
            }

            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['CommentsTemplate.created_at ASC']);
                } else {
                    $queryBuilder->orderBy(['CommentsTemplate.created_at DESC']);
                }
            }

            if ($order['field'] == "updated_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['CommentsTemplate.updated_at ASC']);
                } else {
                    $queryBuilder->orderBy(['CommentsTemplate.updated_at DESC']);
                }
            }

            if ($order['field'] == "subject") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['CommentsTemplate.subject ASC']);
                } else {
                    $queryBuilder->orderBy(['CommentsTemplate.subject DESC']);
                }
            }

        }else{
            $queryBuilder->orderBy('CommentsTemplate.subject ASC');

        }


        try {
            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();

            $items = [];

            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $item) {
                    $data = $item->toArray();
                    $data['number_items'] = $item->countMediaList();
                    $items[] = $data;
                }
            }

            return [
                'success' => true,
                'page' => $page,
                'data' => $items,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages,
                'total_rest_items' => $pagination->total_items - $limit * $pagination->current,

            ];

        } catch (\Phalcon\Exception $e) {
            \Sentry\captureException($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            \Sentry\captureException($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }
}
