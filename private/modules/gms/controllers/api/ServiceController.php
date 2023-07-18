<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Db\Adapter\Pdo\Mysql;
use Phalcon\Http\Client\Provider\Exception;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\ConstantHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\ReminderHelper;
use Reloday\Application\Lib\UserActionHelpers;

use Reloday\Application\Models\MediaAttachmentExt;
use Reloday\Application\Models\ReportExt;
use Reloday\Application\Models\ServiceFieldExt;
use Reloday\Application\Models\TaskTemplateExt;
use Reloday\Gms\Models\Attributes;
use Reloday\Gms\Models\AttributesValue;
use Reloday\Gms\Models\Country;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\NeedFormGabaritItem;
use Reloday\Gms\Models\NeedFormGabaritItemSystemField;
use Reloday\Gms\Models\Report;
use Reloday\Gms\Models\Service;
use Reloday\Gms\Models\ServiceCompanyHasServiceProvider;
use Reloday\Gms\Models\ServiceCompany;
use Reloday\Gms\Models\ServiceEvent;
use Reloday\Gms\Models\ServiceField;
use Reloday\Gms\Models\ServiceProviderCompany;
use Reloday\Gms\Models\ServiceProviderCompanyHasServiceProviderType;
use Reloday\Gms\Models\ServiceProviderType;
use Reloday\Gms\Models\TaskFile;
use Reloday\Gms\Models\TaskTemplateChecklist;
use Reloday\Gms\Models\TaskTemplateCompany;
use Reloday\Gms\Models\TaskTemplate;
use Reloday\Gms\Models\TaskTemplateReminder;
use Reloday\Gms\Models\TaskTemplateDefault;
use Reloday\Gms\Models\MediaAttachment;
use Reloday\Gms\Models\TaskWorkflow;
use Reloday\Gms\Models\Workflow;
use Reloday\Application\Lib\CacheHelper;


/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class ServiceController extends BaseController
{
    /**
     * @Route("/service", paths={module="gms"}, methods={"GET"}, name="gms-service-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_RELOCATION, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_RELOCATION_SERVICE, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE, 'action' => AclHelper::ACTION_INDEX]
        ]);

        $result = [];
        // Load list service
        $company_id = ModuleModel::$user_profile->getCompanyId();
        $services = ServiceCompany::getListOfMyCompany();


        // Load service provider name for each services
        if (count($services)) {

            $service_ids = [];
            $providers_in_service_arr = [];
            foreach ($services as $item) {
                $service_ids[] = $item->getId();
            }

            // Find service provider has found in services
            $providers_in_service = ServiceCompanyHasServiceProvider::find([
                'service_company_id IN (' . implode(',', $service_ids) . ')'
            ]);

            $provider_ids = [];
            $providers_arr = [];
            if (count($providers_in_service)) {
                foreach ($providers_in_service as $provider) {
                    $provider_ids[$provider->getServiceProviderCompanyId()] = $provider->getServiceProviderCompanyId();
                }
                if (count($providers_in_service)) {
                    $providers = ServiceProviderCompany::find([
                        'id IN (' . implode(',', $provider_ids) . ')'
                    ]);
                    // Convert providers list
                    if ($providers) {
                        foreach ($providers as $provider) {
                            $providers_arr[$provider->getId()] = $provider;
                        }
                    }
                }
                // Add service name
                foreach ($providers_in_service as $provider) {
                    if (!isset($providers_in_service_arr[$provider->getServiceOfCompanyId()])) {
                        $providers_in_service_arr[$provider->getServiceOfCompanyId()] = [];
                    }
                    $service_name = isset($providers_arr[$provider->getServiceProviderCompanyId()])
                        ? $providers_arr[$provider->getServiceProviderCompanyId()]->getName() : '';

                    $providers_in_service_arr[$provider->getServiceOfCompanyId()][] = $service_name;
                }
            }

            foreach ($services as $service) {
                $result[$service->getId()] = $service->toArray();
                if (count($providers_in_service_arr)) {
                    if (isset($providers_in_service_arr[$service->getId()])) {
                        $result[$service->getId()]['providers'] = [];
                        $result[$service->getId()]['providers'] = $providers_in_service_arr[$service->getId()];
                    }

                }
            }
        }
        $this->response->setJsonContent([
            'success' => true,
            'data' => array_values($result),
        ]);
        $this->response->send();

    }

    /**
     * @Route("/service", paths={module="gms"}, methods={"GET"}, name="gms-service-index")
     */
    public function getServicesActiveAction()
    {
        $this->view->disable();
        $this->checkAjax(['GET', 'POST']);

        $params = [];
        $params['limit'] = Helpers::__getRequestValue('pageSize');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['query'] = Helpers::__getRequestValue('query');

        $return = ServiceCompany::__findActiveServices($params);
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     *
     */
    public function initAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex();

        $user_company = ModuleModel::$user_profile->getCompanyId();

       
        $cacheName = "SERVICE_TEMPLATE_INIT_" .$user_company;
        $cachedObject = CacheHelper::__getCacheValue($cacheName, CacheHelper::__TIME_24H);
        if ($cachedObject == null) {
            $templates = [];
            $company = ModuleModel::$company;
            $companyServiceTemplates = $company->getCompanyServiceTemplates();
            foreach ($companyServiceTemplates as $companyServiceTemplate) {
                $service = $companyServiceTemplate->getService();
                if ($service) {
                    $templates[] = $service->toArray();
                }
            }
    
            $attribute_template = Attributes::__findWithCache(['conditions' => 'name IN ("PHASES")'], CacheHelper::__TIME_24H); //, "TIME", "TIME_DEPENDENCY"
            if (count($attribute_template) == 0) {
                exit(json_encode([
                    'success' => false,
                    'message' => 'ATTRIBUTE_NOT_FOUND_TEXT'
                ]));
            }
    
            $phases = [];
            foreach ($attribute_template as $item) {
                $attributes = AttributesValue::__findWithCache([
                    'company_id=' . $user_company . ' AND attributes_id=' . $item->getId()
                ], CacheHelper::__TIME_24H);
                if (count($attributes) == 0) {
                    $attributes = AttributesValue::__findWithCache([
                        'attributes_id=' . $item->getId()
                    ], CacheHelper::__TIME_24H);
                }
                // Find PHASES attributes
                if ($item->getName() == 'PHASES' & count($attributes) > 0) {
                    $phases = $attributes->toArray();
                }
            }
            $service_provider_types = ServiceProviderType::__findWithCache(['order' => 'name'], CacheHelper::__TIME_24H);
            $service_provider_types_arr = [];
            if (count($service_provider_types)) {
                foreach ($service_provider_types as $item) {
                    $service_provider_types_arr[$item->getId()] = $item->toArray();
                }
            }
            $cachedObject = [
                'success' => true,
                'service_templates' => count($templates) ? $templates : [],
                'phase_attributes' => $phases,
                'provider_types' => array_values($service_provider_types_arr)
            ];
            if ($cachedObject) {
                CacheHelper::__updateCacheValue($cacheName, $cachedObject, CacheHelper::__TIME_24H);
            }
        }
        echo json_encode($cachedObject);
    }

    /**
     * @Route("/service", paths={module="gms"}, methods={"GET"}, name="gms-service-index")
     */
    public function simpleAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');

        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_RELOCATION, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_RELOCATION_SERVICE, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE, 'action' => AclHelper::ACTION_INDEX]
        ]);

        $serviceArray = [];
        // Load list service
        $services = ServiceCompany::getSimpleListOfMyCompany();
        /*
        foreach ($services as $service) {
            $serviceArray[$service->getId()] = $service->toArray();
            $serviceArray[$service->getId()]['number_providers'] = $service->countActiveServiceProviderCompany();
        }
        */
        $this->response->setJsonContent([
            'success' => true,
            'data' => ($services),
        ]);
        $this->response->send();
    }

    /**
     * Load content of service template by service id
     * @param int $service_id
     */
    public function loadServiceTemplateContentAction($service_id = 0)
    {
        $this->view->disable();
        $this->checkAjaxGet();


        $tasks = TaskTemplateDefault::find([
            'service_id=' . $service_id
        ]);

        $tasks_arr = [];
        if (count($tasks)) {
            $event_ids = [];
            foreach ($tasks as $task) {
                $tasks_arr[$task->getId()] = $task->toArray();
                if ($task->getServiceEventId())
                    $event_ids[] = $task->getServiceEventId();
            }
            // Load list event by event ids
            if (count($event_ids)) {
                $events = ServiceEvent::find('id IN (' . implode(',', $event_ids) . ') AND base = 0');
                $events_arr = [];
                if (count($events)) {
                    foreach ($events as $event) {
                        $events_arr[$event->getId()] = $event->getName();
                    }
                }

                foreach ($tasks_arr as $k => $task) {
                    if (isset($task['service_event_id'], $events_arr)) {
                        $tasks_arr[$k]['reminder_event'] = $events_arr[$task['reminder_service_event_id']];
                        $tasks_arr[$k]['tmp_id'] = $task['id'];
                    }
                }
            }
        }

        $base_events = ServiceEvent::find('service_id = ' . $service_id . ' AND base = 1');
        $base_events_arr = [];
        if (count($base_events)) {
            foreach ($base_events as $event) {
                $base_events_arr[] = ['id' => $event->getId(), 'value' => $event->getName()];
            }
        }

        echo json_encode([
            'success' => true,
            'tasks' => $tasks_arr,
            'base_events' => $base_events_arr,
        ]);
    }

    /**
     * Load all provider has relationship with current company (through the user)
     */
    public function loadProvidersAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $providers = ServiceProviderCompany::getListOfMyCompany();
        $providers_arr = [];
        if (count($providers) > 0) {
            foreach ($providers as $item) {
                $providers_arr[$item->getId()] = $item->toArray();
                $providers_arr[$item->getId()]['country'] = $item->getCountry() ? $item->getCountry()->getName() : "";
                $providers_arr[$item->getId()]['type_ids'] = $item->getTypeIdList();
            }
        }
        $this->response->setJsonContent([
            'success' => true,
            'data' => ($providers_arr)
        ]);
        return $this->response->send();
    }

    /**
     * Load list provider by provider type
     */
    public function loadProviderRelationByTypeAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $type_id = Helpers::__getRequestValue('type_id');
        $providers = Helpers::__getRequestValueAsArray('providers');

        if (empty($providers) || count($providers) == 0) {
            $this->response->setJsonContent([
                'success' => true,
                'data' => []
            ]);
            return $this->response->send();
        }

        // Sure that get provider with wright format
        $providers = implode(',', array_map('intval', $providers));
        // Find list provider has relation with type
        $types = ServiceProviderCompanyHasServiceProviderType::find([
            'service_provider_type_id=' . $type_id . ' AND service_provider_company_id IN (' . $providers . ')'
        ]);

        $types_arr = [];
        if (count($types)) {
            foreach ($types as $item) {
                $types_arr[] = intval($item->getServiceProviderCompanyId());
            }
        }

        $this->response->setJsonContent([
            'success' => true,
            'data' => $types_arr
        ]);
        return $this->response->send();
    }

    /**
     * Load list event by service id
     * @param int $service_id
     */
    public function loadEventsAction($service_id = 0)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $events = ServiceEvent::findByServiceId((int)$service_id);
        $this->response->setJsonContent([
            'success' => true,
            'events' => $events
        ]);
        return $this->response->send();
    }

    /**
     * Load list event by service id
     * @param int $service_id
     */
    public function eventsAction($service_id = 0)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $events = ServiceEvent::findByServiceId((int)$service_id);
        $service_array = [];
        foreach ($events as $event) {
            $service_array[$event->getId()] = $event;
        }
        $this->response->setJsonContent([
            'success' => true,
            'data' => $service_array
        ]);
        return $this->response->send();
    }

    /**
     * Load list event by service id
     * @param int $service_id
     */
    public function getEventsOfServiceCompanyAction($service_id = 0)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $serviceCompany = [];
        if (Helpers::__isValidId($service_id)) {
            $serviceCompany = ServiceCompany::findFirstById($service_id);
        }

        if (Helpers::__isValidUuid($service_id)) {
            $serviceCompany = ServiceCompany::findFirstByUuid($service_id);
        }

        if ($serviceCompany && $serviceCompany->belongsToGms()) {

            $events = ServiceEvent::findByServiceId((int)$serviceCompany->getServiceId());
            $service_array = [];
            foreach ($events as $event) {
                $service_array[$event->getId()] = $event;
            }
            $this->response->setJsonContent([
                'success' => true,
                'data' => $service_array
            ]);
            return $this->response->send();
        }
    }

    /**
     * @param int $id
     */
    public function detailAction($id = 0)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_RELOCATION, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_RELOCATION_SERVICE, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE, 'action' => AclHelper::ACTION_INDEX]
        ]);
        // Load service
        $service = ServiceCompany::findFirst($id);
        if (!$service instanceof ServiceCompany) {
            exit(json_encode([
                'success' => false,
                'message' => 'SERVICE_INFO_NOT_FOUND'
            ]));
        }

        // Load task list
        $tasks = TaskTemplateCompany::find([
            'service_company_id=' . $service->getId()
        ]);
        $tasks_arr = [];
        if (count($tasks)) {
            foreach ($tasks as $task) {
                $tasks_arr[$task->getId()] = $task->toArray();
                $tasks_arr[$task->getId()]['tmp_id'] = $task->getId();
                $serviceEvent = $task->getServiceEvent();
                $tasks_arr[$task->getId()]['reminder_event'] = $serviceEvent ? $serviceEvent->getName() : "";
            }
        }

        // load provider list
        $providers = ServiceCompanyHasServiceProvider::find([
            'service_company_id=' . $service->getId()
        ]);
        $providers_arr = [];
        if (count($providers)) {
            foreach ($providers as $provider) {
                // Load provider info
                $model = ServiceProviderCompany::findFirst($provider->getServiceProviderCompanyId());
                if (!$model instanceof ServiceProviderCompany) {
                    continue;
                }
                // Load country info
                $country = Country::findFirst($model->getCountryId());
                // Load type info
                $type = ServiceProviderType::findFirst($provider->getServiceProviderTypeId());

                $data = $model->toArray();
                $data['country'] = $country instanceof Country ? $country->getName() : '';
                $data['type'] = $type instanceof ServiceProviderType ? $type->getName() : '';
                $data['type_id'] = $provider->getServiceProviderTypeId();
                $providers_arr[] = $data;
            }
        }

        $base_events = ServiceEvent::find('service_id = ' . $service->getServiceId() . ' AND base = 1');
        $base_events_arr = [];
        if (count($base_events)) {
            foreach ($base_events as $event) {
                $base_events_arr[] = ['id' => $event->getId(), 'value' => $event->getName()];
            }
        }

        $data_service = $service->toArray();
        $data_service['attachments'] = MediaAttachment::__get_attachments_from_uuid($service->getUuid());

        echo json_encode([
            'success' => true,
            'detail' => $data_service,
            'task_list' => $tasks_arr,
            'provider_list' => $providers_arr,
            'base_events' => $base_events_arr
        ]);
    }

    /**
     * @param $service_uuid
     */
    public function get_svpAction($service_uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();

        // Load service
        $service = ServiceCompany::findFirstByUuid($service_uuid);
        if (!$service instanceof ServiceCompany || $service->belongsToGms() == false) {
            $this->response->setJsonContent([
                'success' => false,
                'message' => 'SERVICE_INFO_NOT_FOUND_TEXT'
            ]);
            return $this->response->send();
        }

        // load provider list
        $providers = ServiceCompanyHasServiceProvider::find([
            'service_company_id=' . $service->getId()
        ]);
        $return = ['success' => true, 'message' => 'SERVICE_PROVIDER_NOT_FOUND_TEXT', 'data' => []];

        $providers_arr = [];
        if (count($providers)) {
            foreach ($providers as $provider) {
                // Load provider info
                $model = ServiceProviderCompany::findFirst($provider->getServiceProviderCompanyId());
                if (!$model instanceof ServiceProviderCompany) {
                    continue;
                }
                // Load country info
                $country = Country::findFirst($model->getCountryId());
                // Load type info
                $type = ServiceProviderType::findFirst($provider->getServiceProviderTypeId());

                $data = $model->toArray();
                $data['country'] = $country instanceof Country ? $country->getName() : '';
                $data['type'] = $type instanceof ServiceProviderType ? $type->getName() : '';
                $data['type_id'] = $provider->getServiceProviderTypeId();
                $providers_arr[] = $data;
            }
            $return = [
                'success' => true,
                'data' => $providers_arr
            ];
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @Route("/service", paths={module="gms"}, methods={"GET"}, name="gms-service-index")
     */
    public function getSimpleListActiveAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $serviceArray = [];
        // Load list service
        $services = ServiceCompany::getListActiveOfMyCompany();
        foreach ($services as $service) {
            $serviceArray[$service->getId()] = $service->toArray();
            $serviceArray[$service->getId()]['number_providers'] = $service->countActiveServiceProviderCompany();
            $serviceArray[$service->getId()]['need_spouse'] = false;
            $serviceArray[$service->getId()]['need_child'] = false;
            $serviceArray[$service->getId()]['service_name'] = $service->getService() ? $service->getService()->getName() : false;
            $serviceArray[$service->getId()]['need_dependant'] = false;
            if ($service->getServiceId() == Service::PARTNER_SUPPORT_SERVICE) {
                $serviceArray[$service->getId()]['need_spouse'] = true;
            }
            if ($service->getServiceId() == Service::SCHOOL_SEARCH_SERVICE) {
                $serviceArray[$service->getId()]['need_child'] = true;
            }
            if ($service->getServiceId() == Service::IMMIGRATION_VISA_SERVICE) {
                $serviceArray[$service->getId()]['need_dependant'] = true;
            }

        }
        $this->response->setJsonContent([
            'success' => true,
            'data' => array_values($serviceArray),
        ]);
        $this->response->send();
    }

    /**
     * @Route("/service", paths={module="gms"}, methods={"GET"}, name="gms-service-index")
     */
    public function getSimpleListDesactiveAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $serviceArray = [];
        // Load list service
        $services = ServiceCompany::getListDesactiveOfMyCompany();
        foreach ($services as $service) {
            $serviceArray[$service->getId()] = $service->toArray();
            $serviceArray[$service->getId()]['number_providers'] = $service->countActiveServiceProviderCompany();
            $serviceArray[$service->getId()]['service_name'] = $service->getService() ? $service->getService()->getName() : false;
        }
        $this->response->setJsonContent([
            'success' => true,
            'data' => array_values($serviceArray),
        ]);
        $this->response->send();
    }

    /**
     * get all provider types
     */
    public function provider_typesAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $service_provider_types = ServiceProviderType::find(['order' => 'name']);
        $this->response->setJsonContent([
            'success' => true,
            'data' => $service_provider_types
        ]);
        return $this->response->send();
    }

    /**
     * get all provider types
     */
    public function getServiceTemplatesAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $company = ModuleModel::$company;

        $companyServiceTemplates = $company->getCompanyServiceTemplates();

        if (count($companyServiceTemplates) > 0) {

            $listServiceIds = [];
            foreach ($companyServiceTemplates as $companyServiceTemplate) {
                $listServiceIds[] = $companyServiceTemplate->getServiceId();
            }

            $services = Service::find([
                'conditions' => 'id IN ({service_ids:array}) and is_deleted <> :is_deleted:',
                'bind' => [
                    'is_deleted' => ModelHelper::YES,
                    'service_ids' => $listServiceIds
                ]
            ]);
            $this->response->setJsonContent([
                'success' => true,
                'data' => $services
            ]);
        } else {
            $this->response->setJsonContent([
                'success' => true,
                'data' => []
            ]);
        }

        return $this->response->send();
    }


    /**
     * @param $uuid
     */
    public function removeServiceAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclDelete();

        $return = [
            'success' => false,
            'message' => 'SERVICE_INFO_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $serviceCompany = ServiceCompany::findFirstByUuid($uuid);
            if ($serviceCompany instanceof ServiceCompany && $serviceCompany->belongsToGms()) {
                //Find all match field to questionnaire to delete
                $needFormGabaritItemSystemFields = $serviceCompany->getNeedFormGabaritItemSystemFields();
                if (count($needFormGabaritItemSystemFields) > 0) {
                    $needFormGabaritItemSystemFields->delete();
                }

                //Find all service has relation with questionnaire
                $needFormGabaritServiceCompanies = $serviceCompany->getNeedFormGabaritServiceCompanies();
                if (count($needFormGabaritServiceCompanies) > 0) {
                    $needFormGabaritItemSystemFields->delete();
                }

                $return = $serviceCompany->__quickRemove();
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Save service action
     */
    public function editServiceCompanyAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclCreate();

        $return = [
            'success' => false,
            'message' => 'SERVICE_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $service = ServiceCompany::findFirstByUuid($uuid);
            if ($service instanceof ServiceCompany && $service->belongsToGms()) {
                $service->setData(Helpers::__getRequestValuesArray());
                $serviceReturn = $service->__quickUpdate();
                if ($serviceReturn['success'] == true) {
                    $return = [
                        'success' => true,
                        'data' => $service,
                        'message' => 'SAVE_SERVICE_SUCCESS_TEXT',
                    ];
                } else {
                    $return = [
                        'success' => false,
                        'data' => $service,
                        'message' => isset($serviceReturn['errorMessage']) && is_array($serviceReturn['errorMessage']) ? end($serviceReturn['errorMessage']) : 'SAVE_SERVICE_FAIL_TEXT'
                    ];
                }
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function saveTaskTemplateListAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclCreate();

        $return = ['success' => false, 'message' => 'SERVICE_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $service = ServiceCompany::findFirstByUuid($uuid);

            if ($service instanceof ServiceCompany && $service->belongsToGms()) {
                $taskList = Helpers::__getRequestValue('tasks');
                $taskListIds = [];
                if (is_array($taskList) && count($taskList)) {
                    $this->db->begin();
                    foreach ($taskList as $item) {
                        $item = (array)$item;
                        if (is_numeric($item['id']) > 0 && $item['id'] > 0) {
                            $taskListIds[] = $item['id'];
                        }
                    }

                    /*** save task **/
                    foreach ($taskList as $taskItem) {
                        $taskItem = (array)$taskItem;
                        $task = new TaskTemplateCompany();
                        $taskItem['service_company_id'] = $service->getId();
                        if (!isset($taskItem['service_id'])) {
                            $taskItem['service_id'] = $service->getServiceId();
                        }
                        $result = $task->__save($taskItem);
                        if (!$result instanceof TaskTemplateCompany) {
                            $this->db->rollback();
                            $return = $result;
                            goto end_of_function;
                        }
                    }
                    $this->db->commit();
                }
                $return = [
                    'success' => true,
                    'message' => 'TASK_TEMPLATE_COMPANY_SUCCESS_TEXT',
                ];

            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @param $uuid
     */
    public function deleteTaskTemplateListAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclCreate();

        $return = ['success' => false, 'message' => 'SERVICE_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $service = ServiceCompany::findFirstByUuid($uuid);

            if ($service instanceof ServiceCompany && $service->belongsToGms()) {
                $taskList = Helpers::__getRequestValue('tasks');
                $taskListIds = [];
                if (is_array($taskList) && count($taskList)) {
                    $this->db->begin();


                    foreach ($taskList as $item) {
                        $item = (array)$item;
                        if (is_numeric($item['id']) > 0 && $item['id'] > 0) {
                            $taskListIds[] = $item['id'];
                        }
                    }

                    $taskListToRemove = $service->getTasksTemplate([
                        'conditions' => 'id IN ({ids:array})',
                        'bind' => [
                            'ids' => $taskListIds,
                        ]
                    ]);

                    if ($taskListToRemove && $taskListToRemove->count() > 0) {
                        try {
                            if ($taskListToRemove->count() > 0) {
                                $taskListToRemove->delete();
                            }
                        } catch (\PDOException $e) {
                            $this->db->rollback();
                            $return = [
                                'success' => false,
                                'message' => 'TASK_TEMPLATE_COMPANY_FAILED_TEXT',
                                'detail' => $e->getMessage()
                            ];
                            goto end_of_function;
                        } catch (Exception $e) {
                            $this->db->rollback();
                            $return = [
                                'success' => false,
                                'message' => 'TASK_TEMPLATE_COMPANY_FAILED_TEXT',
                                'detail' => $e->getMessage()
                            ];
                            goto end_of_function;
                        }
                    }
                    $this->db->commit();
                }
                $return = [
                    'success' => true,
                    'message' => 'TASK_TEMPLATE_COMPANY_SUCCESS_TEXT',
                ];

            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function saveProviderListAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclCreate();

        $return = ['success' => false, 'message' => 'SERVICE_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $service = ServiceCompany::findFirstByUuid($uuid);
            if ($service instanceof ServiceCompany && $service->belongsToGms()) {
                $providers = Helpers::__getRequestValue('providers');
                $return = ['success' => true];
                if (is_array($providers) && count($providers)) {
                    $this->db->begin();

                    $providerIds = [];
                    foreach ($providers as $providerItem) {
                        $providerItem = (array)$providerItem;
                        if (isset($providerItem['id']) && is_numeric($providerItem['id']) > 0 && $providerItem['id'] > 0) {
                            $providerIds[] = $providerItem['id'];
                        }
                    }

                    if (count($providerIds) == 0) {
                        $providerRelsToRemove = $service->getServiceCompanyHasServiceProvider();
                    } else {
                        $providerRelsToRemove = $service->getServiceCompanyHasServiceProvider([
                            'conditions' => 'service_provider_company_id NOT IN ({ids:array})',
                            'bind' => [
                                'ids' => $providerIds
                            ]
                        ]);
                    }
                    /**** delete **/
                    if ($providerRelsToRemove->count()) {
                        try {
                            if ($providerRelsToRemove->count() > 0) {
                                $providerRelsToRemove->delete();
                            }
                        } catch (\PDOException $e) {
                            $this->db->rollback();
                            $return = [
                                'success' => false,
                                'message' => 'PROVIDER_COMPANY_FAILED_TEXT',
                                'detail' => $e->getMessage()
                            ];
                            goto end_of_function;
                        } catch (Exception $e) {
                            $this->db->rollback();
                            $return = [
                                'success' => false,
                                'message' => 'PROVIDER_COMPANY_FAILED_TEXT',
                                'detail' => $e->getMessage()
                            ];
                            goto end_of_function;
                        }
                    }

                    /**** add new **/
                    foreach ($providers as $key => $providerItem) {

                        $providerItem = (array)$providerItem;

                        $providerRelation = ServiceCompanyHasServiceProvider::findFirst([
                            'conditions' => 'service_company_id = :service_company_id: AND service_provider_company_id = :service_provider_company_id:',
                            'bind' => [
                                'service_company_id' => $service->getId(),
                                'service_provider_company_id' => $providerItem['id'],
                            ]
                        ]);


                        if (!$providerRelation) {

                            $providerRelation = new ServiceCompanyHasServiceProvider();
                            $result = $providerRelation->__create([
                                'service_company_id' => $service->getId(),
                                'service_provider_company_id' => $providerItem['id'],
                                'service_provider_type_id' => $providerItem['type_id']
                            ]);

                            if ($result instanceof ServiceCompanyHasServiceProvider) {
                                //OK
                            } else {
                                $this->db->rollback();
                                $return = $result;
                                goto end_of_function;
                            }
                        }
                    }
                    $this->db->commit();
                }

                $return = [
                    'success' => true,
                    'message' => 'PROVIDER_COMPANY_SUCCESS_TEXT',
                ];
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();

    }


    /**
     * @param $uuid
     */
    public function getServiceCompanyDetailAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $return = [
            'success' => false,
            'message' => 'SERVICE_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $service = ServiceCompany::findFirstByUuid($uuid);
            if ($service instanceof ServiceCompany && $service->belongsToGms()) {
                $data = $service->toArray();
                $data['service_template_name'] = $service->getServiceTemplateName();
                $data['id'] = intval($service->getId());
                $data['service_id'] = intval($service->getServiceId());
                $data['status'] = intval($service->getStatus());
                $return = [
                    'success' => true,
                    'data' => $data
                ];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();

    }

    /**
     * @param $uuid
     */
    public function getServiceCompanyEventsListAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $return = [
            'success' => false,
            'message' => 'SERVICE_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $service = ServiceCompany::findFirstByUuid($uuid);
            if ($service instanceof ServiceCompany && $service->belongsToGms()) {
                $events = ServiceEvent::findByServiceId((int)$service->getServiceId());
                $serviceEventsArray = [];
                foreach ($events as $event) {
                    $serviceEventsArray[$event->getId()] = $event;
                }
                $return = [
                    'success' => true,
                    'data' => $serviceEventsArray,
                ];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();

    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function getServiceCompanyTaskListAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax(['GET', 'POST']);

        $return = ['success' => false, 'message' => 'SERVICE_NOT_FOUND_TEXT'];

        $params = [];
        $params['object_uuid'] = $uuid;
        $params['has_file'] = Helpers::__getRequestValue('has_file');
        $params['task_type'] = Helpers::__getRequestValue('task_type');
        $params['query'] = Helpers::__getRequestValue('query');

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $service = ServiceCompany::findFirstByUuid($uuid);
            if ($service instanceof ServiceCompany && $service->belongsToGms()) {
                $tasks = TaskTemplate::__findWithFilters($params);
                $tasks_arr = [];
                if ($tasks['success'] && count($tasks['data']) > 0) {
                    foreach ($tasks['data'] as $task) {
//                        $taskItem = $task->toArray();
                        $taskItem = $task;
                        $taskItem['tmp_id'] = $task['id'];

                        $reminderTemplate = TaskTemplateReminder::find([
                            "conditions" => "object_uuid = :task_uuid:",
                            'bind' => [
                                'task_uuid' => $task['uuid'] ?: '',
                            ]
                        ]);

                        if ($reminderTemplate) {
                            $taskItem['reminders'] = $reminderTemplate->toArray();

                            $i = 0;
                            foreach ($taskItem['reminders'] as $reminder) {
                                if ($reminder['service_event_id'] > 0) {
                                    $serviceEvent = ServiceEvent::findFirstById($reminder['service_event_id']);
                                    $taskItem['reminders'][$i]['event_name'] = $serviceEvent->getLabel();
                                }
                                $i++;
                            }
                        }

                        $tasks_arr[] = $taskItem;
                    }
                }
                $return = [
                    'success' => true,
                    'data' => ($tasks_arr)
                ];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();

    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function getServiceCompanyProviderListAction($uuid)
    {

        $this->view->disable();
        $this->checkAjaxGet();

        $return = [
            'success' => false,
            'message' => 'SERVICE_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $service = ServiceCompany::findFirstByUuid($uuid);
            if ($service instanceof ServiceCompany && $service->belongsToGms()) {
                // Load task list
                $providerRelations = $service->getServiceCompanyHasServiceProvider();
                $providersArray = [];
                if (count($providerRelations)) {
                    foreach ($providerRelations as $providerRelation) {
                        $serviceProvider = $providerRelation->getServiceProviderCompany();
                        if ($serviceProvider) {
                            $dataProvider = $serviceProvider->toArray();
                            $dataProvider['country'] = $serviceProvider->getCountry() ? $serviceProvider->getCountry()->getName() : '';
                            $dataProvider['type'] = $providerRelation->getServiceProviderType() ? $providerRelation->getServiceProviderType()->getLabel() : '';
                            $providersArray[] = $dataProvider;
                        }
                    }
                }
                $return = [
                    'success' => true,
                    'data' => $providersArray
                ];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();

    }

    /**
     * @return mixed
     */
    public function searchServiceAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $serviceArray = [];
        // Load list service
        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');
        $services = ServiceCompany::getListActiveOfMyCompany($params);
        foreach ($services as $service) {
            $serviceArray[$service->getId()] = [
                'id' => (int)$service->getId(),
                'uuid' => $service->getUuid(),
                'name' => $service->getName(),
            ];
        }
        $this->response->setJsonContent([
            'success' => true,
            'data' => array_values($serviceArray),
            'total_rest_items' => 0
        ]);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function createServiceCompanyAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();

        $result = ['message' => 'SAVE_SERVICE_COMPANY_FAIL_TEXT', 'success' => false];
        $data = Helpers::__getRequestValuesArray();
        $task_list = Helpers::__getRequestValue('task_list');
        $provider_list = Helpers::__getRequestValue('provider_list');
        $attachments = Helpers::__getRequestValue('attachments');

        $data['company_id'] = ModuleModel::$company->getId();

        //save service
        $service = new ServiceCompany();
        $service->setData($data);
        $service->setStatus(ServiceCompany::STATUS_ACTIVATED);
        $result = $service->__quickCreate();
        if ($result['success'] == false) {
            if (isset($result['errorMessage']) && is_array($result['errorMessage'])) {
                $result['message'] = reset($result['errorMessage']);
            } else {
                $result['message'] = 'SAVE_SERVICE_COMPANY_FAIL_TEXT';
            }
        } else {
            $result['message'] = 'SAVE_SERVICE_COMPANY_SUCCESS_TEXT';
        }

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * @param $uuid
     */
    public function saveAttachementsAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclCreate();

        $return = ['success' => false, 'message' => 'SERVICE_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $service = ServiceCompany::findFirstByUuid($uuid);
            if ($service instanceof ServiceCompany && $service->belongsToGms()) {
                $attachments = Helpers::__getRequestValue('attachments');
                $return = ['success' => true];
                if (is_array($attachments) && count($attachments)) {

                    $return = MediaAttachment::__createAttachments([
                        'objectUuid' => $uuid,
                        'fileList' => $attachments,
                    ]);

                    if ($return['success'] == true) {
                        $return['message'] = 'SAVE_SERVICE_SUCCESS_TEXT';
                    }
                }
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();

    }

    /**
     * @return mixed
     */
    public function createTaskTemplateAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();

        $return = ['success' => false, 'message' => 'SERVICE_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $service = ServiceCompany::findFirstByUuid($uuid);

            if ($service instanceof ServiceCompany && $service->belongsToGms()) {

                $taskItem = Helpers::__getRequestValuesArray();

                if(isset($taskItem['description']) && $taskItem['description'] != null && $taskItem['description'] != ''){
                    $taskItem['description'] = rawurldecode(base64_decode($taskItem['description']));
                }

                $tasks = TaskTemplate::__findWithFilters([
                    'object_uuid' => $uuid,
                    'task_type' => $taskItem['task_type'] ?: 1
                ]);

                $sequence = 1;
                if ($tasks['success'] !== false) {
                    $sequence = count($tasks['data']) + 1;
                }

                $task = new TaskTemplate();
                $taskItem['service_company_id'] = $service->getId();
                if (!isset($taskItem['service_id'])) {
                    $taskItem['service_id'] = $service->getServiceId();
                }
                $task->setData($taskItem);
                $task->setUuid($taskItem['uuid']);
                $task->setSequence($sequence);
                $resultTask = $task->__quickCreate();

                if ($resultTask['success'] == false) {
                    $return = $resultTask;
                    $return['message'] = 'TASK_TEMPLATE_COMPANY_FAILED_TEXT';
                    goto end_of_function;
                }
                if (isset($taskItem['reminders']) && count($taskItem['reminders'])) {
                    foreach ($taskItem['reminders'] as $reminder) {
                        $task_template_reminder = new TaskTemplateReminder();
                        $task_template_reminder->setObjectUuid($task->getUuid());
                        $task_template_reminder->setData($reminder);
                        $resultTaskTemplateReminder = $task_template_reminder->__quickCreate();

                        if ($resultTaskTemplateReminder['success'] == false) {
                            $return = $resultTaskTemplateReminder;
                            $return['message'] = 'TASK_TEMPLATE_COMPANY_FAILED_TEXT';
                            goto end_of_function;
                        }
                    }
                }
                $return = [
                    'success' => true,
                    'data' => $task,
                    'message' => 'TASK_TEMPLATE_COMPANY_SUCCESS_TEXT',
                ];
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function editTaskTemplateAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclCreate();

        $return = ['success' => false, 'message' => 'SERVICE_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $service = ServiceCompany::findFirstByUuid($uuid);

            if ($service instanceof ServiceCompany && $service->belongsToGms()) {

                $taskItem = Helpers::__getRequestValuesArray();
                $task = TaskTemplate::findFirstById($taskItem['id']);

                if (!$task && $task->getServiceCompanyId() != $service->getId()) {
                    $return = [
                        'success' => false,
                        'message' => 'TASK_TEMPLATE_COMPANY_FAIL_TEXT',
                    ];
                    goto end_of_function;
                }

                $taskItem['service_company_id'] = $service->getId();
                if (!isset($taskItem['service_id'])) {
                    $taskItem['service_id'] = $service->getServiceId();
                }
                if(isset($taskItem['description']) && $taskItem['description'] != null && $taskItem['description'] != ''){
                    $taskItem['description'] = rawurldecode(base64_decode($taskItem['description']));
                }
                $task->setData($taskItem);

                $resultTask = $task->__quickUpdate();

                if ($resultTask['success'] == false) {
                    $return = $resultTask;
                    $return['message'] = 'TASK_TEMPLATE_COMPANY_FAIL_TEXT';
                    goto end_of_function;
                }

                $return = [
                    'success' => true,
                    'data' => $task,
                    'message' => 'TASK_TEMPLATE_COMPANY_SUCCESS_TEXT',
                ];
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param string $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function removeTaskTemplateAction($uuid = '')
    {

        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclCreate();

        $return = ['success' => false, 'message' => 'SERVICE_NOT_FOUND_TEXT'];


        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $task = TaskTemplate::findFirstByUuid($uuid);

            if ($task instanceof TaskTemplate) {
                $service_company = $task->getServiceCompany();
                if ($service_company && $service_company->belongsToGms()) {
                    $resultDelete = $task->__quickRemove();
                    if ($resultDelete['success']) {
                        $return = [
                            'success' => true,
                            'message' => 'TASK_DELETE_SUCCESS_TEXT',
                        ];
                    } else {
                        $return = [
                            'detail' => $resultDelete,
                            'success' => true,
                            'message' => 'TASK_DELETE_FAIL_TEXT',
                        ];
                    }
                }
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @param $uuid
     */
    public function addProviderToServiceAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();

        $return = ['success' => false, 'message' => 'SERVICE_NOT_FOUND_TEXT'];

        $providerData = Helpers::__getRequestValuesArray();
        $id = Helpers::__getRequestValue('id');

        if (!($uuid != '' && Helpers::__isValidUuid($uuid))) {
            goto end_of_function;
        }
        if (!($id != '' && Helpers::__checkId($id))) {
            goto end_of_function;
        }
        $service = ServiceCompany::findFirstByUuid($uuid);
        if (!($service instanceof ServiceCompany && $service->belongsToGms())) {
            goto end_of_function;
        }

        $provider = ServiceProviderCompany::findFirstById($id);
        if (!($provider instanceof ServiceProviderCompany && $provider->belongsToGms())) {
            goto end_of_function;
        }

        $providerRelation = ServiceCompanyHasServiceProvider::findFirst([
            'conditions' => 'service_company_id = :service_company_id: AND service_provider_company_id = :service_provider_company_id:',
            'bind' => [
                'service_company_id' => $service->getId(),
                'service_provider_company_id' => $provider->getId(),
            ]
        ]);

        if (!$providerRelation) {
            $providerRelation = new ServiceCompanyHasServiceProvider();
            $providerRelation->setData([
                'service_company_id' => $service->getId(),
                'service_provider_company_id' => $provider->getId(),
                'service_provider_type_id' => $providerData['type_id']
            ]);

            $return = $providerRelation->__quickCreate();
        } else {
            $return = ['success' => true];
        }

        if ($return['success'] == true) {
            $return['message'] = 'PROVIDER_COMPANY_SUCCESS_TEXT';
        } else {
            $return['message'] = 'PROVIDER_COMPANY_FAILED_TEXT';
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function removeProviderFromServiceAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclCreate();

        $return = ['success' => false, 'message' => 'SERVICE_NOT_FOUND_TEXT'];


        $id = Helpers::__getRequestValue('id');

        if (!($uuid != '' && Helpers::__isValidUuid($uuid))) {
            goto end_of_function;
        }
        if (!($id != '' && Helpers::__checkId($id))) {
            goto end_of_function;
        }
        $service = ServiceCompany::findFirstByUuid($uuid);
        if (!($service instanceof ServiceCompany && $service->belongsToGms())) {
            goto end_of_function;
        }

        $provider = ServiceProviderCompany::findFirstById($id);
        if (!($provider instanceof ServiceProviderCompany && $provider->belongsToGms())) {
            goto end_of_function;
        }

        $providerRelation = ServiceCompanyHasServiceProvider::findFirst([
            'conditions' => 'service_company_id = :service_company_id: AND service_provider_company_id = :service_provider_company_id:',
            'bind' => [
                'service_company_id' => $service->getId(),
                'service_provider_company_id' => $provider->getId(),
            ]
        ]);

        if ($providerRelation) {
            $return = $providerRelation->__quickRemove();
        } else {
            $return = ['success' => false];
        }

        if ($return['success'] == true) {
            $return['message'] = 'DELETE_PROVIDER_COMPANY_SUCCESS_TEXT';
        } else {
            $return['message'] = 'DELETE_PROVIDER_COMPANY_FAILED_TEXT';
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $id (service_company id)
     * @return mixed
     * Get service fields
     */
    public function getServiceFieldsFromServiceIdAction($id)
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $return = ['success' => false, 'message' => 'SERVICE_FIELDS_NOT_FOUND_TEXT'];

        if (!$id) {
            goto end_of_function;
        }

        $serviceCompany = ServiceCompany::findFirstById($id);

        if (!$serviceCompany) {
            goto end_of_function;
        }

        $fields = ServiceFieldExt::find([
            'conditions' => 'service_id = :service_id:',
            'bind' => [
                'service_id' => $serviceCompany->getServiceId()
            ],
            'columns' => ['code', 'label_constant']
        ]);

        $return = [
            'success' => true,
            'data' => $fields
        ];

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid (service_company uuid)
     * @return mixed
     * Get service fields
     */
    public function getServiceFieldsFromServiceCompanyUuidAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $return = ['success' => false, 'message' => 'SERVICE_FIELDS_NOT_FOUND_TEXT'];

        if (!$uuid) {
            goto end_of_function;
        }

        $serviceCompany = ServiceCompany::findFirstByUuid($uuid);

        if (!$serviceCompany) {
            goto end_of_function;
        }

        $need_form_gabarit_item_id = Helpers::__getRequestValue('need_form_gabarit_item_id');

        if (isset($need_form_gabarit_item_id) && $need_form_gabarit_item_id > 0) {
            $needFormGabaritItem = NeedFormGabaritItem::findFirstById($need_form_gabarit_item_id);

            $returnFields = ServiceField::__findServiceFields([
                'service_company_uuid' => $serviceCompany->getUuid(),
                'answer_format' => $needFormGabaritItem->getAnswerFormat(),
                'company_id' => ModuleModel::$company->getId(),
                'need_form_gabarit_id' => $needFormGabaritItem->getNeedFormGabaritId(),
            ]);

            if (!$returnFields['success']) {
                $return = $returnFields;
                goto end_of_function;
            }

            $fields = $returnFields['data'];
        } else {
            $fields = ServiceFieldExt::find([
                'conditions' => 'service_id = :service_id:',
                'bind' => [
                    'service_id' => $serviceCompany->getServiceId()
                ],
            ]);
        }


        $arrFields = [];
        if (count($fields) > 0) {
            foreach ($fields as $field) {
                if ($field) {
                    if (!is_array($field)) {
                        $item = $field->toArray();
                        $item['type_name'] = $field->getServiceFieldType() ? strtolower($field->getServiceFieldType()->getName()) : '';
                        $arrFields[] = $item;
                    } else {
                        $arrFields[] = $field;

                    }
                }

            }
        }


        $return = [
            'success' => true,
            'data' => $arrFields
        ];
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Find service company
     */
    public function getServiceCompanyByIdsAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $ids = Helpers::__getRequestValue('ids');
        if ($ids && !is_array($ids)) {
            $ids = explode(',', $ids);
        }
        $params = [];

        if ($ids) {
            $params['ids'] = $ids;
        }

        $return = ServiceCompany::__findWithFilter($params);

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function cloneServiceCompanyAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();

        $result = ['message' => 'SAVE_SERVICE_COMPANY_FAIL_TEXT', 'success' => false];
        $data = Helpers::__getRequestValuesArray();

        $old_service = ServiceCompany::findFirst($data['id']);

        $params = [
            'detail' => $data,
            'task_list' => $old_service->getTasksTemplate(),
            'provider_list' => $old_service->getServiceCompanyHasServiceProvider(),
        ];

        // Start transaction
        $this->db->begin();

        // 1. Save base information (service_company)
        $service_data = $params['detail'];
        unset($service_data['id']);
        unset($service_data['uuid']);

        $uuid = Helpers::__uuid();
        $service = new ServiceCompany();
        $service->setData($service_data);
        $service->setUuid($uuid);
        $service->setStatus(ServiceCompany::STATUS_ACTIVATED);
        $result = $service->__quickCreate();

        if (!$result['data'] instanceof ServiceCompany) {
            $this->db->rollback();
            goto end_of_function;
        }


        // 2. Save list provider relate with this service (service_company_has_service_provider)
        $provider_list = $params['provider_list'];


        if (count($provider_list) > 0) {

            foreach ($provider_list as $item) {
                $item = $item->toArray();
                $providerRelation = new ServiceCompanyHasServiceProvider();
                $providerRelation->setData([
                    'service_company_id' => $result['data']->getId(),
                    'service_provider_company_id' => $item['service_provider_company_id'],
                    'service_provider_type_id' => $item['service_provider_type_id']
                ]);
                $resultProvider = $providerRelation->__quickCreate();

                if (!$resultProvider['data'] instanceof ServiceCompanyHasServiceProvider) {
                    $this->db->rollback();
                    $result = $resultProvider;
                    goto end_of_function;
                }
            }
        }

        // 3. Save list task relate with this service (task_template_company)
        $task_list = $params['task_list'];
        if (count($task_list) > 0) {
            foreach ($task_list as $item) {
                $itemArr = $item->toArray();
                unset($itemArr['id']);
                unset($itemArr['uuid']);
                unset($itemArr['object_uuid']);
                $taskTemplateUuid = Helpers::__uuid();
                $task = new TaskTemplate();
                $itemArr['service_company_id'] = $result['data']->getId();
                if (!isset($itemArr['service_id'])) {
                    $itemArr['service_id'] = $service->getServiceId();
                }
                $task->setObjectUuid($uuid);
                $task->setUuid($taskTemplateUuid);
                $task->setData($itemArr);
                $resultTask = $task->__quickCreate();

                if (!$resultTask['data'] instanceof TaskTemplate) {
                    $this->db->rollback();
                    $result = $resultTask;
                    goto end_of_function;
                }

                //Reminder
                $reminders = $item->getTaskTemplateReminders();
                foreach ($reminders as $reminder){
                    $reminderArr = $reminder->toArray();
                    unset($reminderArr['id']);
                    unset($reminderArr['uuid']);
                    unset($reminderArr['object_uuid']);
                    $newRm = new TaskTemplateReminder();
                    $newRm->setData($reminderArr);
                    $newRm->setObjectUuid($taskTemplateUuid);
                    $resultTaskRm = $newRm->__quickCreate();
                    if (!$resultTaskRm['data']) {
                        $this->db->rollback();
                        $result = $resultTaskRm;
                        goto end_of_function;
                    }

                }

                //checklist
                $checklists = $item->getTaskTemplateChecklists();
                foreach ($checklists as $checklist){
                    $checklistArr = $checklist->toArray();
                    unset($checklistArr['id']);
                    unset($checklistArr['uuid']);
                    unset($checklistArr['object_uuid']);
                    $newCl = new TaskTemplateChecklist();
                    $newCl->setData($checklistArr);
                    $newCl->setObjectUuid($taskTemplateUuid);
                    $resultTaskCl = $newCl->__quickCreate();
                    if (!$resultTaskCl['data']) {
                        $this->db->rollback();
                        $result = $resultTaskCl;
                        goto end_of_function;
                    }

                }

                //Task file
                $taskFiles = $item->getFiles();
                foreach ($taskFiles as $taskFile){
                    $taskFileArr = $taskFile->toArray();
                    unset($taskFileArr['id']);
                    unset($taskFileArr['uuid']);
                    unset($taskFileArr['object_uuid']);

                    $newTaskFile = new TaskFile();
                    $newTaskFile->setData($taskFileArr);
                    $newTaskFile->setObjectUuid($taskTemplateUuid);
                    $resultTaskFile = $newTaskFile->__quickCreate();
                    if (!$resultTaskFile['data']) {
                        $this->db->rollback();
                        $result = $resultTaskFile;
                        goto end_of_function;
                    }
                }
            }
        }

        // 4. Save list attachment relate with this service (media_attachment)
        $attachments = $old_service->getMediaAttachments();
        $news = [];
        foreach ($attachments as $attachment){
            $attachmentArr = $attachment->toArray();
            unset($attachmentArr['id']);
            unset($attachmentArr['uuid']);
            unset($attachmentArr['object_uuid']);

            $resultAtt = MediaAttachmentExt::__createAttachment([
                'objectUuid' =>$uuid,
                'ownerCompany' => ModuleModel::$company,
                'file' => $attachment->getMedia(),
                'userProfile' => ModuleModel::$user_profile,
            ]);

            if (!$resultAtt['success']) {
                $this->db->rollback();
                $result = $resultAtt;
                goto end_of_function;
            }
        }

        // DB save
        $this->db->commit();

        $result['oldAttachments'] =$attachments;
        $result['$news'] =$news;

        if ($result['success'] == false) $result['message'] = 'SAVE_SERVICE_COMPANY_FAIL_TEXT';
        else $result['message'] = 'SAVE_SERVICE_COMPANY_SUCCESS_TEXT';

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function createSubTaskAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $uuid = Helpers::__getRequestValue("object_uuid");
        $name = Helpers::__getRequestValue("name");
        if (!$name || $name == "") {
            $return = ['success' => false, 'message' => 'NAME_REQUIRED_TEXT'];
            goto end_of_function;
        }
        $return = ['success' => false, 'message' => 'TASK_WORKFLOW_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $task_template = TaskTemplate::findFirstByUuid($uuid);
            if ($task_template instanceof TaskTemplate && $task_template->belongsToGms()) {
                $this->checkMyPermissionEditTaskTemplate($task_template);
                $taskItem = Helpers::__getRequestValuesArray();
                $task = new TaskTemplateChecklist();
                $taskItem['object_uuid'] = $uuid;
                $task->setData($taskItem);
                $resultTask = $task->__quickCreate();

                if ($resultTask['success'] == false) {
                    $return = $resultTask;
                    $return['message'] = 'TASK_WORKFLOW_FAIL_TEXT';
                    goto end_of_function;
                }
                $return = [
                    'success' => true,
                    'data' => $task,
                    'message' => 'TASK_WORKFLOW_SUCCESS_TEXT',
                ];
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function editSubTaskAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();

        $parent_uuid = Helpers::__getRequestValue("object_uuid");
        $name = Helpers::__getRequestValue("name");
        if (!$name || $name == "") {
            $return = ['success' => false, 'message' => 'NAME_REQUIRED_TEXT'];
            goto end_of_function;
        }
        $return = ['success' => false, 'message' => 'TASK_WORKFLOW_NOT_FOUND_TEXT'];

        if ($parent_uuid != '' && Helpers::__isValidUuid($parent_uuid)) {
            $task_template = TaskTemplate::findFirstByUuid($parent_uuid);

            if ($task_template instanceof TaskTemplate && $task_template->belongsToGms()) {

                $taskItem = Helpers::__getRequestValuesArray();

                $task = TaskTemplateChecklist::findFirstById($taskItem['id']);

                if ($task && $task->getObjectUuid() != $parent_uuid) {
                    $return = [
                        'success' => false,
                        'message' => 'TASK_TEMPLATE_FAIL_TEXT',
                    ];
                    goto end_of_function;
                }

                $task->setData($taskItem);
                $resultTask = $task->__quickUpdate();

                if ($resultTask['success'] == true) {
                    $return = $resultTask;
                    $return['message'] = 'TASK_WORKFLOW_FAIL_TEXT';
                    goto end_of_function;
                }

                $return = [
                    'success' => true,
                    'data' => $task,
                    'message' => 'TASK_WORKFLOW_SUCCESS_TEXT',
                ];
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function removeSubTaskAction($uuid)
    {

        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclCreate();

        $return = ['success' => false, 'message' => 'TASK_WORKFLOW_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $checklist = TaskTemplateChecklist::findFirstByUuid($uuid);
            if ($checklist instanceof TaskTemplateChecklist) {
                $parentTask = TaskTemplate::findFirstByUuid($checklist->getObjectUuid());
                if ($parentTask instanceof TaskTemplate && $parentTask->belongsToGms()) {
                    $resultDelete = $checklist->__quickRemove();
                    if ($resultDelete['success']) {
                        $return = [
                            'success' => true,
                            'message' => 'TASK_WORKFLOW_DELETE_SUCCESS_TEXT',
                        ];
                    } else {
                        $return = [
                            'detail' => $resultDelete,
                            'success' => true,
                            'message' => 'TASK_WORKFLOW_DELETE_FAIL_TEXT',
                        ];
                    }

                }
            }

        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * load detail of task template company
     * @method GET
     */
    public function getSubTaskListAction($uuid = 0)
    {
        $this->view->disable();
        $this->checkAjax('GET');

        $taskTemplate = TaskTemplate::findFirstByUuid($uuid);

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT',
        ];

        if ($taskTemplate instanceof TaskTemplate && $taskTemplate->belongsToGms()) {
            $data = $taskTemplate->getTaskTemplateChecklists();
            $return = [
                'success' => true,
                'message' => 'LOAD_DETAIL_SUCCESS_TEXT',
                'data' => $data
            ];
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $id
     */
    public function getServiceFieldDetailAction($id)
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $return = [
            'success' => false,
            'message' => 'SERVICE_FIELD_NOT_FOUND_TEXT'
        ];

        if ($id != '' && Helpers::__isValidId($id)) {
            $serviceField = ServiceField::findFirstById($id);
            if ($serviceField instanceof ServiceField) {
                $data = $serviceField->toArray();
                $return = [
                    'success' => true,
                    'data' => $data
                ];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();

    }

    /**
     * @Route("/service", paths={module="gms"}, methods={"GET"}, name="gms-service-index")
     */
    public function listAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $serviceArray = [];

        $params = [];
        $params['status'] = Helpers::__getRequestValue('status');
        $params['service_id'] = Helpers::__getRequestValue('service_id');
        $params['query'] = Helpers::__getRequestValue('query');
        $params['limit'] = Helpers::__getRequestValue('limit');
         
        if(Helpers::__getRequestValue('pageSize')){
            $params['limit'] = Helpers::__getRequestValue('pageSize');
        }
        $params['page'] = Helpers::__getRequestValue('page');
        $params['start'] = Helpers::__getRequestValue('start');

        $order = Helpers::__getRequestValueAsArray('sort');
        $ordersConfig = Helpers::__getApiOrderConfig([$order]);
        $orders = Helpers::__getRequestValue('orders');
        if ($orders && is_array($orders)) {
            $orders = reset($orders);
            if (is_object($orders)) {
                $ordersConfig = [];
                $ordersConfig[] = [
                    "field" => strtolower($orders->column),
                    "order" => isset($orders->descending) && $orders->descending ? 'desc' : 'asc'
                ];
            }
        }

        $servicesRes = ServiceCompany::__findWithFilter($params, $ordersConfig);

        $totalPages = 0;
        $services = [];
        if ($servicesRes['success']) {
            $services = $servicesRes['data'];
            if (isset($servicesRes['total_pages'])) {
                $totalPages = $servicesRes['total_pages'];
            }
        }

        foreach ($services as $service) {
            $item = $service->toArray();
            $item["service_id"] = intval($item["service_id"]);
            $item['need_spouse'] = false;
            $item['need_child'] = false;
            $item['need_dependant'] = false;
            if ( $item["service_id"] == Service::PARTNER_SUPPORT_SERVICE) {
                $item['need_spouse'] = true;
            }
            if ( $item["service_id"] == Service::SCHOOL_SEARCH_SERVICE) {
                $item['need_child'] = true;
            }
            if ( $item["service_id"] == Service::IMMIGRATION_VISA_SERVICE) {
                $item['need_dependant'] = true;
            }
            $serviceArray[] = $item;

        }
        $this->response->setJsonContent([
            'success' => true,
            'data' => array_values($serviceArray),
            'total_pages' => $totalPages,
        ]);
        $this->response->send();
    }

    public function checkMyPermissionEditTaskTemplate(TaskTemplate $task): bool
    {
        if ($task->getObjectType() == TaskTemplateExt::SERVICE_TYPE) {
            $this->checkAclCreate(AclHelper::CONTROLLER_SERVICE);
            $service = ServiceCompany::findFirstByUuid($task->getObjectUuid());
            if (!$service instanceof ServiceCompany || !$service->belongsToGms()) {
                return false;
            }

            return true;
        }

        $this->checkAclUpdate(AclHelper::CONTROLLER_WORKFLOW);
        $workflow = Workflow::findFirstByUuid($task->getObjectUuid());
        if (!$workflow instanceof Workflow || !$workflow->belongsToGms()) {
            return false;
        }

        return true;
    }
}
