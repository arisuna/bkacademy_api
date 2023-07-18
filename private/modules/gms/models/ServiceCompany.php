<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;

use Phalcon\Db\Adapter\Pdo\Mysql;
use Phalcon\Http\Client\Provider\Exception;
use Phalcon\Http\Request;
use Reloday\Application\Lib\AthenaHelper;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Models\FilterConfigExt;
use Reloday\Application\Models\ServiceExt;
use Reloday\Gms\Models\ModuleModel as ModuleModel;
use Reloday\Gms\Models\ServiceProviderCompany;
use Phalcon\Security\Random;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;

class ServiceCompany extends \Reloday\Application\Models\ServiceCompanyExt
{
    /** initialize model */
    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('service_id', 'Reloday\Gms\Models\Service', 'id', [
            'alias' => 'Service',
            'reusable' => true,
            'cache' => [
                'key' => 'SERVICE_' . $this->getServiceId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
        ]);

        $this->hasMany('service_id', 'Reloday\Gms\Models\ServiceEvent', 'service_id', [
            'alias' => 'ServiceEvents',
        ]);

        $this->hasMany('service_id', 'Reloday\Gms\Models\ServiceField', 'service_id', [
            'alias' => 'ServiceFields'
        ]);

        $this->hasMany('uuid', 'Reloday\Gms\Models\TaskTemplate', 'object_uuid', [
            'alias' => 'TasksTemplate',
            'params' => [
                'order' => 'sequence ASC'
            ]
        ]);

        $this->hasMany('id', 'Reloday\Gms\Models\ServiceCompanyHasServiceProvider', 'service_company_id', [
            'alias' => 'ServiceCompanyHasServiceProvider'
        ]);

        $this->hasManyToMany(
            'id', 'Reloday\Gms\Models\ServiceCompanyHasServiceProvider',
            'service_company_id', 'service_provider_company_id',
            'Reloday\Gms\Models\ServiceProviderCompany', 'id',
            [
                'alias' => 'ServiceProviderCompany'
            ]
        );

        $this->hasManyToMany(
            'id', 'Reloday\Gms\Models\ServiceCompanyHasServiceProvider',
            'service_company_id', 'service_provider_company_id',
            'Reloday\Gms\Models\ServiceProviderCompany', 'id',
            [
                'alias' => 'service_provider_companies'
            ]
        );
    }

    /**
     * Save service and model related
     * @param Request $req
     * @param Mysql $db
     * @return array|\Reloday\Application\Models\ServiceCompany|\Reloday\Application\Models\ServiceCompanyExt
     */
    public function saveService(Request $req, Mysql $db)
    {
        $method = 'get' . ucfirst(strtolower($req->getMethod()));
        // Start transaction
        $db->begin();

        // 1. Save base information (service_company)
        $service_data = $req->$method('detail');
        $service_data['company_id'] = ModuleModel::$user_profile->getCompanyId();
        $service = $this->__save($service_data);

        if (!$service instanceof ServiceCompany) {
            $db->rollback();
            return $service;
        }

        // 2. Save list provider relate with this service (service_company_has_service_provider)
        $provider_list = $req->$method('provider_list');
        // Load current provider list
        $current_list = $service->getServiceCompanyHasServiceProvider();

        $provider_list_ids = [];

        $provider_to_remove_ids = [];

        if (count($current_list) > 0 && count($provider_list) > 0) {

            foreach ($provider_list as $k => $provider) {
                $provider_list_ids[] = $provider['id'];
            }

            foreach ($current_list as $item) {
                if (!in_array($item->getServiceProviderCompanyId(), array_values($provider_list_ids))) {
                    try {
                        $item->delete();
                    } catch (\PDOException $e) {
                        $db->rollback();
                        return ['success' => false, 'details' => $e->getMessage()];
                    } catch (Exception $e) {
                        $db->rollback();
                        return ['success' => false, 'details' => $e->getMessage()];
                    }
                }
                foreach ($provider_list as $k => $provider) {
                    if (intval($item->getServiceProviderCompanyId()) === intval($provider['id'])) {
                        unset($provider_list[$k]);
                    }
                }
            }
        } else {

            try {
                $service->getServiceCompanyHasServiceProvider()->delete();
            } catch (\PDOException $e) {
                $db->rollback();
                return ['success' => false, 'details' => $e->getMessage()];
            } catch (Exception $e) {
                $db->rollback();
                return ['success' => false, 'details' => $e->getMessage()];
            }
        }


        if (count($provider_list) > 0) {
            foreach ($provider_list as $item) {
                $provider = new ServiceCompanyHasServiceProvider();
                $data = [
                    'service_company_id' => $service->getId(),
                    'provider_id' => $item['id'],
                    'provider_type_id' => $item['type_id'],
                ];

                $result = $provider->__save($data);
                if (!$result instanceof ServiceCompanyHasServiceProvider) {
                    $db->rollback();
                    return $result;
                }
            }
        }


        // 3. Save list task relate with this service (task_template_company)
        $task_list = $req->$method('task_list');
        if (is_array($task_list) && count($task_list)) {
            //var_dump( $task_list ); die();
            foreach ($task_list as $item) {
                $task = new TaskTemplateCompany();
                $item['service_company_id'] = $service->getId();
                if (!isset($item['service_id'])) {
                    $item['service_id'] = $service->getServiceId();
                }

                $result = $task->__save($item);
                if (!$result instanceof TaskTemplateCompany) {
                    $db->rollback();
                    return $result;
                }
            }
        }

        $task_list_remove = $req->$method('task_list_remove');
        if (is_array($task_list_remove) && count($task_list_remove) > 0) {
            foreach ($task_list_remove as $item) {
                if (isset($item['id'])) {
                    $task = TaskTemplateCompany::findFirstById($item['id']);
                    if (!$task->delete()) {
                        $error_messages = $task->getMessages();
                        $db->rollback();
                        return [
                            'success' => true,
                            'message' => 'TASK_TEMPLATE_COMPANY_FAILED_TEXT',
                            'detail' => implode(',', $error_messages)
                        ];
                    }
                }
            }
        }
        $db->commit();
        return [
            'data' => $service,
            'success' => true,
            'message' => 'SAVE_SERVICE_SUCCESS_TEXT'
        ];
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

    /**
     * @return \Reloday\Application\Models\ServiceCompany[]
     */
    public static function getListOfMyCompany()
    {
        return ServiceCompany::find([
            'conditions' => 'company_id = :company_id: AND status != :status_active:',
            'bind' => [
                'company_id' => ModuleModel::$company->getId(),
                'status_active' => ServiceCompany::STATUS_ACTIVATED
            ]
        ]);
    }

    /**
     *
     */
    public static function getFullListOfMyCompany()
    {
        return ServiceCompany::find([
            'conditions' => 'company_id = :company_id: AND status != :status_draft:',
            'bind' => [
                'company_id' => ModuleModel::$company->getId(),
                'status_draft' => ServiceCompany::STATUS_DRAFT
            ]
        ]);
    }

    /**
     * @return \Reloday\Application\Models\ServiceCompany[]
     */
    public static function getListActiveOfMyCompany($options = array())
    {
        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            return ServiceCompany::find([
                'conditions' => 'company_id = :company_id: AND status != :status_deleted: AND name LIKE :query:',
                'bind' => [
                    'company_id' => ModuleModel::$company->getId(),
                    'status_deleted' => ServiceCompany::STATUS_DELETED,
                    'query' => '%' . $options['query'] . '%',
                ]
            ]);
        }
        return ServiceCompany::find([
            'conditions' => 'company_id = :company_id: AND status != :status_deleted:',
            'bind' => [
                'company_id' => ModuleModel::$company->getId(),
                'status_deleted' => ServiceCompany::STATUS_DELETED
            ]
        ]);
    }

    /**
     * @return \Reloday\Application\Models\ServiceCompany[]
     */
    public static function getListDesactiveOfMyCompany()
    {
        return ServiceCompany::find([
            'conditions' => 'company_id = :company_id: AND status = :status_deleted:',
            'bind' => [
                'company_id' => ModuleModel::$company->getId(),
                'status_deleted' => ServiceCompany::STATUS_DELETED
            ]
        ]);
    }

    /**
     * @return \Reloday\Application\Models\ServiceCompany[]
     */
    public static function getSimpleListOfMyCompany()
    {
        $collection = ServiceCompany::find([
            'columns' => 'id, uuid, code, name, company_id, service_id, status, created_at',
            'conditions' => 'company_id = :company_id: AND status != :status_deleted:',
            'bind' => [
                'company_id' => ModuleModel::$company->getId(),
                'status_deleted' => ServiceCompany::STATUS_DELETED
            ],
            'hydration' => \Phalcon\Mvc\Model\Resultset::HYDRATE_ARRAYS
        ]);

        if ($collection) {
            $items = $collection->toArray();
        }

        foreach ($items as $key => $item) {
            $items[$key] = self::__toArray($item);
        }
        return $items;
    }

    /**
     * @return mixed
     */
    public function getActiveServiceProviderCompany()
    {
        return $this->getServiceProviderCompany(['conditions' => 'status = :status_active:', 'bind' => [
            'status_active' => ServiceProviderCompany::STATUS_ACTIVATED
        ]]);
    }

    /**
     * @return mixed
     */
    public function countActiveServiceProviderCompany()
    {
        return $this->getServiceProviderCompany(['conditions' => 'status = :status_active:', 'bind' => [
            'status_active' => ServiceProviderCompany::STATUS_ACTIVATED
        ]])->count();
    }


    /**
     * Save service and model related
     * @param Request $req
     * @param Mysql $db
     * @return array|\Reloday\Application\Models\ServiceCompany|\Reloday\Application\Models\ServiceCompanyExt
     */
    public function updateServiceCompany($dataServiceCompany)
    {
        $db->begin();
        // 1. Save base information (service_company)
        $service_data = $dataServiceCompany['detail'];
        $service_data['company_id'] = ModuleModel::$user_profile->getCompanyId();
        $service = $this->__update($service_data);
        return $service;
    }


    /**
     * [__save description]
     * @param  {Array}  $custom [description]
     * @return {[type]}         [description]
     */
    public function __update($custom = [])
    {

        $req = new Request();
        $model = $this;

        if (!($model->getId() > 0)) {
            if ($req->isPut()) {
                $data_id = isset($custom['{tableName}_id']) && $custom['{tableName}_id'] > 0 ? $custom['{tableName}_id'] : $req->getPut('{tableName}_id');
                if ($data_id > 0) {
                    $model = $this->findFirstById($data_id);
                    if (!$model instanceof $this) {
                        return [
                            'success' => false,
                            'message' => 'DATA_NOT_FOUND_TEXT',
                        ];
                    }
                }
            }
        }

        /** @var [varchar] [set uunique id] */
        if (property_exists($model, 'uuid') && method_exists($model, 'getUuid') && method_exists($model, 'setUuid')) {
            if (property_exists($model, 'uuid') && method_exists($model, 'getUuid') && method_exists($model, 'setUuid')) {
                if ($model->getUuid() == '') {
                    $random = new Random;
                    $uuid = $random->uuid();
                    $model->setUuid($uuid);
                }
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
                && $field_name != "password"
            ) {

                if (!isset($fields_numeric[$field_name])) {
                    $field_name_value = Helpers::__getRequestValueWithCustom($field_name, $custom);
                    $field_name_value = Helpers::__coalesce($field_name_value, $model->__get($field_name));
                    $field_name_value = $field_name_value != '' ? $field_name_value : $model->__get($field_name);
                    $model->__set($field_name, $field_name_value);

                } else {

                    $field_name_value = Helpers::__getRequestValueWithCustom($field_name, $custom);
                    $field_name_value = Helpers::__coalesce($field_name_value, $model->__get($field_name));
                    if ($field_name_value != '' && !is_null($field_name_value)) {
                        $model->__set($field_name, $field_name_value);
                    }
                }
            }
        }
        /****** YOUR CODE ***/

        /****** END YOUR CODE **/
        return $model->__quickUpdate();
    }

    /**
     * @return bool
     */
    public function isArchived()
    {
        return $this->getStatus() == self::STATUS_DELETED;
    }

    /**
     * @param $item
     * @return mixed
     */
    public static function __toArray($item)
    {
        return ModelHelper::__toArray(new self(), $item);
    }

    /**
     * @param $params
     * @param $orders
     * @return array
     */
    public static function __executeReport($params, $orders = [])
    {
        $queryString = " Select ass.reference, r.identify, rsc.name as relo_service_name, r.start_date as relo_start_date, rsc.number as service_number, sc.name as service_company_name, ";
        $queryString .= " hrcompany.name as hr_company, ";
        $queryString .= " bcompany.name as booker, ";
        $queryString .= " spc.name as service_provider_name, ";
        $queryString .= " concat(ee.firstname, ' ', ee.lastname) as assignee_name, ";
        $queryString .= " concat(owner.firstname, ' ', owner.lastname) as owner,";
        $queryString .= " concat(creator.firstname, ' ', creator.lastname) as reporter,";
        $queryString .= " cast(viewer.fullname as json) AS viewers, ";
        $queryString .= " case when rsc.progress = 0 then '" . Constant::__translateConstant('NOT_STARTED_TEXT', ModuleModel::$language) .
            "' when rsc.progress = 3 then '" . Constant::__translateConstant('COMPLETED_TEXT', ModuleModel::$language) .
            "' else '" . Constant::__translateConstant('ONGOING_TEXT', ModuleModel::$language) . "' end as status,";
        $queryString .= " case when rsc.progress = 0 then 0 when rsc.progress = 3 then 3 else 1 end as progress,";
        $queryString .= " rsc.created_at as rsc_creation_date,";
        $queryString .= " sev_start_date.value as service_start_date,";
        $queryString .= " sev_end_date.value as service_end_date,";
        $queryString .= " sev_authorised_date.value as service_authorisation_date,";
        $queryString .= " sev_completion_date.value as service_completion_date,";
        $queryString .= " sev_expiry_date.value as service_expiry_date,";
        $queryString .= " st.name as service_template, ";
        $queryString .= " sc.service_id as service_id, ";
        $queryString .= " cast(field.detail as json) as fields, ";
        $queryString .= " concat(relo_owner.firstname, ' ', relo_owner.lastname) as relo_owner_name, ";
        $queryString .= " ee.reference as ee_reference ";

        $queryString .= " ,CASE WHEN ab.departure_hr_office_id > 0 THEN (select office.name from office where id = ab.departure_hr_office_id) ELSE '' END as origin_office  ";
        $queryString .= " ,CASE WHEN ad.destination_hr_office_id > 0 THEN (select office.name from office where id = ad.destination_hr_office_id) ELSE '' END as destination_office  ";

        $queryString .= " from relocation_service_company as rsc";
        $queryString .= " join relocation as r on r.id = rsc.relocation_id";
        $queryString .= " join assignment as ass on ass.id = r.assignment_id";

        $queryString .= " LEFT JOIN assignment_basic AS ab ON ab.id = ass.id ";
        $queryString .= " LEFT JOIN assignment_destination AS ad ON ad.id = ass.id ";

        $queryString .= " join service_company as sc on sc.id = rsc.service_company_id";
        $queryString .= " join service as st on st.id = sc.service_id";

        // Specific field
        $queryString .= " left join (";
        $queryString .= " select service_field_value.relocation_service_company_id,   ";
        $queryString .= " map_agg(service_field.code, case when service_field_value.value is not null then service_field_value.value else '' end) as detail ";
        $queryString .= " from service_field_value ";
        $queryString .= " join service_field on service_field.id = service_field_value.service_field_id group by service_field_value.relocation_service_company_id)";
        $queryString .= " as field on field.relocation_service_company_id = rsc.id ";
        $queryString .= " left join service_provider_company as spc on spc.id = rsc.service_provider_company_id ";

        $queryString .= " JOIN company AS hrcompany ON ass.company_id = hrcompany.id ";
        $queryString .= " LEFT JOIN company AS bcompany ON ass.booker_company_id = bcompany.id ";
        $queryString .= " JOIN employee AS ee ON ass.employee_id = ee.id ";
        //Owner 2
        $queryString .= " join data_user_member as data_owner on data_owner.member_type_id = 2 and data_owner.object_uuid = rsc.uuid ";
        $queryString .= " join user_profile as owner on owner.uuid = data_owner.user_profile_uuid ";
        //Creator 5
        $queryString .= " join data_user_member as data_creator on data_creator.member_type_id = 5 and data_creator.object_uuid = rsc.uuid ";
        $queryString .= " join user_profile as creator on creator.uuid = data_creator.user_profile_uuid ";
        //Viewer 6
        $queryString .= " LEFT JOIN ( ";
        $queryString .= " select array_agg(concat(user_profile.firstname, ' ',user_profile.lastname)) as fullname, ";
        $queryString .= " data_user_member.object_uuid, user_profile.company_id ";
        $queryString .= " from user_profile join data_user_member on data_user_member.user_profile_uuid = user_profile.uuid";
        $queryString .= " where data_user_member.member_type_id = 6 group by data_user_member.object_uuid, user_profile.company_id)";
        $queryString .= " as viewer on viewer.object_uuid = rsc.uuid and viewer.company_id = sc.company_id ";

        //Relocation Owner 2
        $queryString .= " left join data_user_member as data_relo_owner on data_relo_owner.member_type_id = 2 and data_relo_owner.object_uuid = r.uuid ";
        $queryString .= " LEFT join user_profile as relo_owner on relo_owner.uuid = data_relo_owner.user_profile_uuid ";

        //Date
        $queryString .= " JOIN service_event as sv_start_date on sv_start_date.base = true and sv_start_date.service_id = sc.service_id and sv_start_date.code = 'SERVICE_START' ";
        $queryString .= " LEFT JOIN service_event_value as sev_start_date on sev_start_date.relocation_service_company_id = rsc.id and sv_start_date.id = sev_start_date.service_event_id ";
        $queryString .= " JOIN service_event as sv_end_date on  sv_end_date.base = true and sv_end_date.service_id = sc.service_id and sv_end_date.code = 'SERVICE_END' ";
        $queryString .= " LEFT JOIN service_event_value as sev_end_date on sev_end_date.relocation_service_company_id = rsc.id and sv_end_date.id = sev_end_date.service_event_id ";
        $queryString .= " JOIN service_event as sv_authorised_date on  sv_authorised_date.base = true and sv_authorised_date.service_id = sc.service_id and sv_authorised_date.code = 'AUTHORISED' ";
        $queryString .= " LEFT JOIN service_event_value as sev_authorised_date on sev_authorised_date.relocation_service_company_id = rsc.id and sv_authorised_date.id = sev_authorised_date.service_event_id ";
        $queryString .= " JOIN service_event as sv_completion_date on  sv_completion_date.base = true and sv_completion_date.service_id = sc.service_id and sv_completion_date.code = 'COMPLETION' ";
        $queryString .= " LEFT JOIN service_event_value as sev_completion_date on sev_completion_date.relocation_service_company_id = rsc.id and sv_completion_date.id = sev_completion_date.service_event_id ";
        $queryString .= " JOIN service_event as sv_expiry_date on  sv_expiry_date.base = true and sv_expiry_date.service_id = sc.service_id and sv_expiry_date.code = 'EXPIRY' ";
        $queryString .= " LEFT JOIN service_event_value as sev_expiry_date on sev_expiry_date.relocation_service_company_id = rsc.id and sv_expiry_date.id = sev_expiry_date.service_event_id ";

        $queryString .= " left join (select entity_progress.value, entity_progress.status, entity_progress.object_uuid from entity_progress "; // change from join to left join 7621 create rsc withouth entity_progress
        $queryString .= " join (select count(entity_progress.id), max(entity_progress.id) as id from entity_progress group by entity_progress.object_uuid) ";
        $queryString .= " as eg on eg.id = entity_progress.id) as progress on progress.object_uuid = rsc.uuid ";
        $queryString .= " where rsc.status = 1 and r.active = 1 ";
        $queryString .= " and rsc.service_company_id = " . $params['service_company_id'] . "";

        if (isset($params['filter_config_id'])) {

            $tableField = [
                'ACCOUNT_ARRAY_TEXT' => 'hrcompany.id',
                'BOOKER_ARRAY_TEXT' => 'bcompany.id',
                'OWNER_ARRAY_TEXT' => 'owner.id',
                'REPORTER_ARRAY_TEXT' => 'creator.id',
                'ASSIGNEE_ARRAY_TEXT' => 'ee.id',
                'STATUS_TEXT' => '(case when rsc.progress = 0 then 0 when rsc.progress = 3 then 3 else 1 end)',
                'SERVICE_CREATION_DATE_TEXT' => 'rsc.created_at',
                'SERVICE_START_DATE_TEXT' => 'sev_start_date.value',
                'SERVICE_END_DATE_TEXT' => 'sev_end_date.value',
                'SERVICE_AUTHORISED_DATE_TEXT' => 'sev_authorised_date.value',
                'SERVICE_COMPLETION_DATE_TEXT' => 'sev_completion_date.value',
                'SERVICE_EXPIRY_DATE_TEXT' => 'sev_expiry_date.value',
            ];
            $dataType = [
                'SERVICE_CREATION_DATE_TEXT' => 'date',
                'SERVICE_START_DATE_TEXT' => 'int',
                'SERVICE_END_DATE_TEXT' => 'int',
                'SERVICE_AUTHORISED_DATE_TEXT' => 'int',
                'SERVICE_COMPLETION_DATE_TEXT' => 'int',
                'SERVICE_EXPIRY_DATE_TEXT' => 'int',
            ];
            Helpers::__addFilterConfigConditionsQueryString($queryString, $params['filter_config_id'], $params['is_tmp'], FilterConfigExt::SERVICE_EXTRACT_FILTER_TARGET, $tableField, $dataType);
        }

        if (count($orders)) {
            $order = reset($orders);
            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryString .= " ORDER BY rsc_creation_date ASC ";
                } else {
                    $queryString .= " ORDER BY rsc_creation_date DESC ";
                }
            } else if ($order['field'] == "employee_name") {
                if ($order['order'] == "asc") {
                    $queryString .= " ORDER BY assignee_name ASC ";
                } else {
                    $queryString .= " ORDER BY assignee_name DESC ";
                }
            } else {
                $queryString .= " ORDER BY rsc_creation_date DESC ";
            }

        } else {
            $queryString .= " ORDER BY rsc_creation_date DESC ";
        }

        AthenaHelper::__setCompanyFolder(ModuleModel::$company->getUuid());
        $execution = AthenaHelper::executionQuery($queryString);
        if (!$execution["success"]) {
            return $execution;
        }
        $executionInfo = AthenaHelper::getExecutionInfo($execution["executionId"]);
        return $executionInfo;
    }

    /**
     * @param $options
     * @param $orders
     * @return mixed
     */
    public static function __findWithFilter($options = [], $orders = [])
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\ServiceCompany', 'ServiceCompany');
        $queryBuilder->innerJoin('\Reloday\Gms\Models\Service', 'ServiceCompany.service_id = Service.id', 'Service');
        

        $queryBuilder->leftJoin('\Reloday\Gms\Models\ServiceCompanyHasServiceProvider', 'ServiceCompanyHasServiceProvider.service_company_id = ServiceCompany.id', 'ServiceCompanyHasServiceProvider');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\ServiceProviderCompany', 'ServiceProviderCompany.id = ServiceCompanyHasServiceProvider.service_provider_company_id AND ServiceProviderCompany.status = '.ServiceProviderCompany::STATUS_ACTIVATED, 'ServiceProviderCompany');
        $queryBuilder->distinct(true);
        $queryBuilder->columns([
            "ServiceCompany.id",
            "ServiceCompany.uuid",
            "ServiceCompany.service_id",
            "ServiceCompany.name",
            "ServiceCompany.code",
            "ServiceCompany.company_code",
            "ServiceCompany.shortname",
            "ServiceCompany.phase",
            "ServiceCompany.valid_for",
            "ServiceCompany.description",
            "ServiceCompany.includes",
            "ServiceCompany.excludes",
            "ServiceCompany.notes",
            "ServiceCompany.status",
            "ServiceCompany.office_id",
            "ServiceCompany.company_id",
            "ServiceCompany.created_at",
            "ServiceCompany.updated_at",
            "number_providers" => "COUNT(ServiceProviderCompany.id)",
            "service_name" => "Service.name"
        ]);
        $queryBuilder->where('ServiceCompany.company_id = :company_id:', [
            'company_id' => ModuleModel::$company->getId()
        ]);
        if (isset($options['ids']) && is_array($options['ids']) && count($options['ids']) > 0) {
            $queryBuilder->andWhere('ServiceCompany.id IN ({service_company_ids:array})', [
                'service_company_ids' => $options['ids']
            ]);
        }

        if (isset($options['status']) && $options['status']) {
            $queryBuilder->andWhere('ServiceCompany.status = :status:', [
                'status' => $options['status']
            ]);
        } else {
            $queryBuilder->andWhere('ServiceCompany.status = 1');
        }

        if (isset($options['service_id']) && $options['service_id'] > 0) {
            $queryBuilder->andWhere('ServiceCompany.service_id = :service_id:', [
                'service_id' => $options['service_id']
            ]);
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("ServiceCompany.name LIKE :query: OR ServiceCompany.code LIKE :query:  OR Service.name LIKE :query:", [
                'query' => '%' . $options['query'] . '%',
            ]);
        }

        if (count($orders)) {
            $order = reset($orders);
            if ($order['field'] == "updated_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['ServiceCompany.updated_at ASC']);
                } else {
                    $queryBuilder->orderBy(['ServiceCompany.updated_at DESC']);
                }
            } else {
                $queryBuilder->orderBy(['ServiceCompany.updated_at DESC']);
            }

            if ($order['field'] == "name") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['ServiceCompany.name ASC']);
                } else {
                    $queryBuilder->orderBy(['ServiceCompany.name DESC']);
                }
            }

            if ($order['field'] == "template_name") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Service.name ASC']);
                } else {
                    $queryBuilder->orderBy(['Service.name DESC']);
                }
            }
        } else {
            $queryBuilder->orderBy(["ServiceCompany.id DESC"]);
        }

        $queryBuilder->groupBy('ServiceCompany.id');
        try {
            if (isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0) {
                $limit = $options['limit'];
                $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
                $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;

                if ($page == 0) {
                    $page = intval($start / $limit) + 1;
                }

                $paginator = new PaginatorQueryBuilder([
                    "builder" => $queryBuilder,
                    "limit" => $limit,
                    "page" => $page
                ]);

                $pagination = $paginator->getPaginate();

                return [
                    'success' => true,
                    'options' => $options,
                    'limit_per_page' => $limit,
                    'page' => $page,
                    'data' => $pagination->items,
                    'before' => $pagination->before,
                    'next' => $pagination->next,
                    'last' => $pagination->last,
                    'current' => $pagination->current,
                    'total_items' => $pagination->total_items,
                    'total_pages' => $pagination->total_pages,
                    'total_rest_items' => $pagination->total_items - $limit * $pagination->current,
                ];
            }

            $data = $queryBuilder->getQuery()->execute();

            return [
                'success' => true,
                'data' => $data,
                'total_pages' => 0
            ];

        } catch (\Phalcon\Exception $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (Exception $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }

    /**
     * @return mixed
     */
    public function getServiceTemplateName()
    {
        return parent::getServiceTemplateName(); // TODO: Change the autogenerated stub
    }

    /**
     * @return \Reloday\Application\Models\ServiceCompany[]
     */
    public static function getListActiveOfMyCompanyWithMapField($options = array())
    {

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\ServiceCompany', 'ServiceCompany');
        $queryBuilder->innerJoin('\Reloday\Gms\Models\Service', 'Service.id = ServiceCompany.service_id', 'Service');
        $queryBuilder->innerJoin('\Reloday\Gms\Models\ServiceField', 'ServiceField.service_id = Service.id', 'ServiceField');
        $queryBuilder->innerJoin('\Reloday\Gms\Models\MapField', 'MapField.service_field_id = ServiceField.id', 'MapField');
        $queryBuilder->distinct(true);
        $queryBuilder->where('ServiceCompany.status = 1');
        $queryBuilder->andWhere('MapField.is_active = :is_active:', [
            'is_active' => ModelHelper::YES
        ]);
        $queryBuilder->andWhere('ServiceCompany.company_id = :company_id:', [
            'company_id' => ModuleModel::$company->getId()
        ]);
        if (isset($options['ids']) && is_array($options['ids']) && count($options['ids']) > 0) {
            $queryBuilder->andWhere('ServiceCompany.id IN ({service_company_ids:array})', [
                'service_company_ids' => $options['ids']
            ]);
        }

        $queryBuilder->groupBy('ServiceCompany.id');


        try {

            $data = $queryBuilder->getQuery()->execute();

            return [
                'success' => true,
                'data' => $data,
            ];

        } catch (\Phalcon\Exception $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (Exception $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }

    /**
     * @param $options
     * @param $orders
     * @return mixed
     */
    public static function __findActiveServices($options = [], $orders = [])
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\ServiceCompany', 'ServiceCompany');
        $queryBuilder->innerJoin('\Reloday\Gms\Models\Service', 'ServiceCompany.service_id = Service.id', 'Service');

        $queryBuilder->distinct(true);
        $queryBuilder->columns([
            "ServiceCompany.id",
            "ServiceCompany.uuid",
            "ServiceCompany.service_id",
            "ServiceCompany.name",
            "ServiceCompany.code",
            "ServiceCompany.created_at",
            "ServiceCompany.updated_at",
            "service_name" => "Service.name"
        ]);
        $queryBuilder->where('ServiceCompany.company_id = :company_id:', [
            'company_id' => ModuleModel::$company->getId()
        ]);
        if (isset($options['ids']) && is_array($options['ids']) && count($options['ids']) > 0) {
            $queryBuilder->andWhere('ServiceCompany.id IN ({service_company_ids:array})', [
                'service_company_ids' => $options['ids']
            ]);
        }

        $queryBuilder->andWhere('ServiceCompany.status = 1');


        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("ServiceCompany.name LIKE :query:", [
                'query' => '%' . $options['query'] . '%',
            ]);
        }

        $queryBuilder->orderBy(['ServiceCompany.name ASC']);

        $queryBuilder->groupBy('ServiceCompany.id');
        try {
            if (!isset($options['limit'])){
                $options['limit'] = 20;
            }
            $limit = $options['limit'];
            $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;

            if ($page == 0) {
                $page = intval($start / $limit) + 1;
            }

            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page
            ]);

            $pagination = $paginator->getPaginate();

            return [
                'success' => true,
                'options' => $options,
                'limit_per_page' => $limit,
                'page' => $page,
                'data' => $pagination->items,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages,
                'total_rest_items' => $pagination->total_items - $limit * $pagination->current,
            ];

        } catch (\Phalcon\Exception $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (Exception $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }
}
