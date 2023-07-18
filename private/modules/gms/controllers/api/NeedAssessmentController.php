<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Models\NeedAssessmentsExt;
use Reloday\Gms\Models\MapField;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\NeedAssessments;
use Reloday\Gms\Models\NeedFormGabaritItem;
use Reloday\Gms\Models\NeedFormGabarit;

use Reloday\Gms\Models\NeedFormGabaritItemSystemField;
use Reloday\Gms\Models\NeedFormGabaritSection;
use Reloday\Gms\Models\NeedFormGabaritServiceCompany;
use Reloday\Gms\Models\NeedFormRequest;
use Reloday\Gms\Models\NeedFormRequestAnswer;
use Reloday\Gms\Models\ServiceCompany;

use Reloday\Application\Lib\Helpers;
use Reloday\Application\Models\ConstantExt;
use Reloday\Gms\Models\Attributes;
use Reloday\Gms\Models\ServiceField;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class NeedAssessmentController extends BaseController
{
    /**
     * @Route("/needassessment", paths={module="gms"}, methods={"GET"}, name="gms-needaccessment-index")
     */
    public function indexAction()
    {
        //get service company
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex();
        $options = [];
        $options['query'] = Helpers::__getRequestValue('query');
        $options['page'] = Helpers::__getRequestValue('page');
        $options['limit'] = Helpers::__getRequestValue('limit');
        $options['service_ids'] = Helpers::__getRequestValue('service_ids');

        /*** type ****/

        $categories = Helpers::__getRequestValueAsArray('categories');
        $category_codes = [];
        if (is_array($categories) && count($categories) > 0) {
            $attribute = Attributes::findFirstByCode(NeedFormGabarit::ATTRIBUTE_CATEGORY);
            if(!$attribute){
                $return = ['success' => false, 'detail' => 'attribute not found'];
                goto end_of_function;
            }
            foreach ($categories as $item) {
                $category_codes[] = $attribute->getId() .'_' .$item;
            }
        }
        $options['categories'] = $category_codes;

        $order = Helpers::__getRequestValueAsArray('sort');
        $ordersConfig = Helpers::__getApiOrderConfig([$order]);
        $orders = Helpers::__getRequestValue('orders');
        if ($orders && is_array($orders)) {
            $orders = reset($orders);
            if (is_object($orders)) {
                $ordersConfig = [];
                $ordersConfig[] = [
                    "field" => strtolower($orders->column),
                    "order" => isset($orders->descending) && $orders->descending == true ? 'desc' : 'asc'
                ];
            }
        }
        $return = NeedFormGabarit::__findWithFilter($options, $ordersConfig);
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     *
     */
    public function simpleAction()
    {
        //get service company
        $this->view->disable();
        $this->checkAjaxGet();
        // Load list
        $need_assessment_list = NeedFormGabarit::find([
            'conditions' => "company_id = :company_id: AND status <> :status_archived:",
            'bind' => [
                'company_id' => (int)ModuleModel::$company->getId(),
                'status_archived' => NeedFormGabarit::STATUS_ARCHIVED
            ]
        ]);
        if ($need_assessment_list->count() > 0) {
            $results = array();

            foreach ($need_assessment_list as $item) {
                $results[$item->getId()] = $item->toArray();
                $results[$item->getId()]['category_name'] = "";
                $results[$item->getId()]['service_is_archived'] = $item->getServiceCompanyId() > 0 && $item->getServiceCompany() ? $item->getServiceCompany()->isArchived() : false;
                $results[$item->getId()]['service_name'] = $item->getServiceCompanyId() > 0 && $item->getServiceCompany() ? $item->getServiceCompany()->getName() : '';
            }
            $this->response->setJsonContent([
                'success' => true,
                'data' => $results
            ]);
        }
        $this->response->send();
    }

    /**
     * init function
     */
    public function initAction()
    {
        $this->view->disable();
        $company = ModuleModel::$company;
        $services = ServiceCompany::find([
            'conditions' => 'company_id=:company_id: AND status = :status_active:',
            'bind' => [
                'company_id' => ModuleModel::$company->getId(),
                'status_active' => ServiceCompany::STATUS_ACTIVATED
            ]
        ]);

        $list_services = [];
        if (count($services) > 0) {
            foreach ($services as $service) {
                $list_services[$service->getId()] = $service->toArray();
            }
        }
        $this->response->setJsonContent([
            'success' => true,
            'services' => $list_services
        ]);
        $this->response->send();
    }

    /**
     * Load detail of need assessment
     * @param int $id
     */
    public function detailAction($id = 0)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        $result = [
            'success' => false,
            'message' => 'NEED_ASSESSMENT_NOT_FOUND_TEXT'
        ];

        if (Helpers::__checkId($id) && $id > 0) {

            $need_assessment = NeedFormGabarit::findFirst([
                'conditions' => 'status != :status_del: AND company_id = :company_id: AND id = :id:',
                'bind' => [
                    'status_del' => NeedFormGabarit::STATUS_ARCHIVED,
                    'company_id'=> ModuleModel::$company->getId(),
                    'id' => (int)$id
                ]
            ]);

            if ($need_assessment instanceof NeedFormGabarit) {
                $need_assessment_array = $need_assessment->toArray();
                // Load need assessment services
                $need_assessment_array['service_companies'] = [];
                $service_companies = NeedFormGabaritServiceCompany::getByNeedFormGabarit((int)$id);
                $i = 0;
                foreach ($service_companies as $service_company) {
                    $arrService = $service_company->toArray();
                    $arrService['selected'] = NeedFormGabaritServiceCompany::__isDeleted($need_assessment->getId(), $service_company->getId()) ? false : true;
                    $need_assessment_array['service_companies'][$i] = $arrService;
                    $i++;
                }

                // Load need assessment sections
                $sections = NeedFormGabaritSection::find([
                    'conditions' => 'need_form_gabarit_id=:id:',
                    'bind' => [
                        'id' => $id
                    ],
                    'order' => 'position ASC']);
                $need_assessment_array['formBuilder'] = [];

                $dataSections = [];

                foreach ($sections as $section) {
                    $item = $section->toArray();
                    if($section->getNextSectionId() == -1){
                        $item['section_name'] = 'SUBMIT_FORM_TEXT';
                    }else{
                        $item['section_name'] = $need_assessment ? $need_assessment->getSectionNameBasedOnIndex($section->getNextSectionId()) : '';
                    }
                    $dataSections[] = $item;

                    $need_assessment_array['formBuilder'][$section->getPosition()] = $section->toArray();
                    $need_assessment_array['formBuilder'][$section->getPosition()]['index'] = (int)$section->getPosition();
                    $need_assessment_array['formBuilder'][$section->getPosition()]['goToSection'] = (int)$section->getNextSectionId();
                    $need_assessment_array['formBuilder'][$section->getPosition()]['items'] = [];
//                    $items = NeedFormGabaritItem::find(['conditions' => 'need_form_gabarit_section_id=:id:',
//                        'bind' => [
//                            'id' => $section->getId(),
//                        ],
//                        'order' => 'position ASC'
//                    ]);
                    $items = $section->getDetailSectionContent();

                    $need_assessment_array['formBuilder'][$section->getPosition()]['items'] = $items;
//                    $i = 0;
//                    foreach ($items as $keyPosition => $item) {
//                        $need_assessment_array['formBuilder'][$section->getPosition()]['items'][$keyPosition] = $item->getContent();
//                        $i++;
//                    }
                }
                $result = [
                    'success' => true,
                    'formBuilder' => $need_assessment_array['formBuilder'],
                    'sections' => $dataSections,
                    'data' => $need_assessment_array
                ];
            } else {
                $result = [
                    'success' => false,
                    'message' => 'NEED_ASSESSMENT_NOT_FOUND_TEXT'
                ];
            }
        }

        $this->response->setJsonContent($result);
        $this->response->send();

    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function createFormAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclUpdate();
        $this->db->begin();


        $check_name_unique = NeedFormGabarit::findFirst([
            "conditions" => "name = :name: and company_id=:company: and status !=:status:",
            "bind" => [
                "name" => Helpers::__getRequestValue("name"),
                "company" => ModuleModel::$company->getId(),
                "status" => NeedFormGabarit::STATUS_ARCHIVED
            ]
        ]);
        if ($check_name_unique instanceof NeedFormGabarit) {
            $this->db->rollback();
            $return = [
                "success" => false,
                "message" => "NEED_ASSESSMENT_NAME_MUST_BE_UNIQUE_TEXT"
            ];
            goto end_of_function;
        }
        $needForm = new NeedFormGabarit();
        if ($needForm) {
            $data = [
                'company_id' => ModuleModel::$company->getId(),
                'description' => Helpers::__getRequestValue('description'),
                'name' => Helpers::__getRequestValue('name'),
                'need_form_category' => Helpers::__getRequestValue('need_form_category'),
            ];
            $result = $needForm->__create($data);
            if ($result['success'] == false) {
                $this->db->rollback();
                $return = $result;
                goto end_of_function;
            }

            //save to table need_form_gabarit_service_company
            $service_companies = Helpers::__getRequestValue("service_companies");
            if (count($service_companies) > 0) {
                foreach ($service_companies as $service_company) {
                    if(!is_array($service_company)){
                        $service_company = (array)$service_company;
                    }
                    if($service_company['selected']){
                        $need_form_gabarit_service_company = new NeedFormGabaritServiceCompany();
                        $need_form_gabarit_service_company->setNeedFormGabaritId($needForm->getId());
                        $need_form_gabarit_service_company->setServiceCompanyId($service_company['id']);
                        $resultSave = $need_form_gabarit_service_company->__quickSave();
                        if (!$resultSave['success']) {
                            $this->db->rollback();
                            $return = [
                                'success' => false,
                                'msg' => 'DATA_CREATE_FAIL_TEXT',
                                'data' => $need_form_gabarit_service_company
                            ];
                            goto end_of_function;
                        }
                    }

                }
            }
            $this->db->commit();
            $return = [
                'success' => true,
                'data' => $needForm
            ];
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * Save need assessment
     */
    public function saveFormAction($id)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclUpdate();
        $this->db->begin();

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($id > 0 && Helpers::__checkId($id)) {
            $need_assessment = NeedFormGabarit::findFirstById($id);
            $check_name_unique = NeedFormGabarit::findFirst([
                "conditions" => "name = :name: and company_id =:company: and status !=:status: and id != :id:",
                "bind" => [
                    "name" => Helpers::__getRequestValue("name"),
                    "company" => ModuleModel::$company->getId(),
                    "status" => NeedFormGabarit::STATUS_ARCHIVED,
                    "id" => $id
                ]
            ]);
            if ($check_name_unique instanceof NeedFormGabarit) {
                $this->db->rollback();
                $return = [
                    "success" => false,
                    "message" => "NEED_ASSESSMENT_NAME_MUST_BE_UNIQUE_TEXT"
                ];
                goto end_of_function;
            }
            if ($need_assessment && $need_assessment->belongsToGms()) {
                $data = [
                    'company_id' => ModuleModel::$company->getId(),
                    'description' => Helpers::__getRequestValue('description'),
                    'name' => Helpers::__getRequestValue('name'),
                    'need_form_category' => Helpers::__getRequestValue('need_form_category'),
                ];
                $result = $need_assessment->__save($data);
                if ($result['success'] == false) {
                    $this->db->rollback();
                    $return = $result;
                    goto end_of_function;
                }
                //save to table need_form_gabarit_service_company
//                $service_company_ids = Helpers::__getRequestValue("service_company_id");
                $service_companies = Helpers::__getRequestValue("service_companies");


                if (count($service_companies) > 0) {

//                    $itemsToDelete = NeedFormGabaritServiceCompany::find([
//                        "conditions" => "need_form_gabarit_id  = :need_form_gabarit_id: AND service_company_id NOT IN ({service_company_ids:array})",
//                        "bind" => [
//                            'need_form_gabarit_id' => $id,
//                            'service_company_ids' => $service_company_ids
//                        ]
//                    ]);
//                    if ($itemsToDelete->count()) {
//                        $resultDelete = ModelHelper::__quickRemoveCollection($itemsToDelete);
//                        if (!$resultDelete['success']) {
//                            $this->db->rollback();
//                            $return = [
//                                'success' => false,
//                                'msg' => 'DATA_CREATE_FAIL_TEXT',
//                                'data' => $resultDelete
//                            ];
//                            goto end_of_function;
//                        }
//                    }


                    foreach ($service_companies as $service_company) {
                        if(!is_array($service_company)){
                            $service_company = (array)$service_company;
                        }

                        $need_form_gabarit_service_company = NeedFormGabaritServiceCompany::findFirst([
                            "conditions" => "need_form_gabarit_id  = :need_form_gabarit_id: AND service_company_id = :service_company_id:",
                            "bind" => [
                                'need_form_gabarit_id' => $id,
                                'service_company_id' => $service_company['id']
                            ]
                        ]);

                        if (!$need_form_gabarit_service_company instanceof NeedFormGabaritServiceCompany) {
                            $need_form_gabarit_service_company = new NeedFormGabaritServiceCompany();
                            $need_form_gabarit_service_company->setNeedFormGabaritId($need_assessment->getId());
                            $need_form_gabarit_service_company->setServiceCompanyId($service_company['id']);

                            if(!$service_company['selected']){
                                $need_form_gabarit_service_company->setIsDeleted(ModelHelper::YES);
                            }

                            $need_form_gabarit_service_company_save = $need_form_gabarit_service_company->__quickCreate();
                            if (!$need_form_gabarit_service_company_save['success']) {
                                $this->db->rollback();
                                $return = [
                                    'success' => false,
                                    'msg' => 'DATA_CREATE_FAIL_TEXT',
                                    'data' => $need_form_gabarit_service_company_save
                                ];
                                goto end_of_function;
                            }
                        }else{
                            if(!$service_company['selected']){
                                $resultDelete = $need_form_gabarit_service_company->__quickRemove();
                                if (!$resultDelete['success']) {
                                    $this->db->rollback();
                                    $return = [
                                        'success' => false,
                                        'msg' => 'DATA_CREATE_FAIL_TEXT',
                                        'data' => $resultDelete
                                    ];
                                    goto end_of_function;
                                }
                            }else{
                                $need_form_gabarit_service_company->setIsDeleted(ModelHelper::NO);
                                $resultSave = $need_form_gabarit_service_company->__quickUpdate();
                                if (!$resultSave['success']) {
                                    $this->db->rollback();
                                    $return = [
                                        'success' => false,
                                        'msg' => 'DATA_CREATE_FAIL_TEXT',
                                        'data' => $resultSave
                                    ];
                                    goto end_of_function;
                                }
                            }
                        }
                    }
                } else {
                    $itemsToDelete = NeedFormGabaritServiceCompany::find([
                        "conditions" => "need_form_gabarit_id  = :need_form_gabarit_id:",
                        "bind" => [
                            'need_form_gabarit_id' => $id
                        ]
                    ]);
                    if ($itemsToDelete->count()) {
                        $resultDelete = ModelHelper::__quickRemoveCollection($itemsToDelete);
                        if (!$resultDelete['success']) {
                            $this->db->rollback();
                            $return = [
                                'success' => false,
                                'msg' => 'DATA_CREATE_FAIL_TEXT',
                                'data' => $resultDelete
                            ];
                            goto end_of_function;
                        }
                    }
                }

                $this->db->commit();
                $return = $result;
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }


    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function cloneFormAction()
    {

        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAclUpdate();

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        $id = Helpers::__getRequestValue("old_need_assessment");

        if ($id > 0 && Helpers::__checkId($id)) {
            $need_assessment_old = NeedFormGabarit::findFirstById($id);
            if ($need_assessment_old && $need_assessment_old->belongsToGms()) {

                $this->db->begin();
                $need_assessment = new NeedFormGabarit();
                $data['company_id'] = ModuleModel::$company->getId();
                $resultCreate = $need_assessment->__create($data);
                if ($resultCreate['success'] == false) {
                    $this->db->rollback();
                    $return = $need_assessment;
                    goto end_of_function;
                }


//                //save to table need_form_gabarit_service_company
//                $service_companies = Helpers::__getRequestValue("service_company_id");
//                if (count($service_companies) > 0) {
//                    foreach ($service_companies as $service_company) {
//                        $need_form_gabarit_service_company = new NeedFormGabaritServiceCompany();
//                        $need_form_gabarit_service_company->setNeedFormGabaritId($need_assessment->getId());
//                        $need_form_gabarit_service_company->setServiceCompanyId($service_company);
//                        $need_form_gabarit_service_company_save = $need_form_gabarit_service_company->__quickSave();
//                        if (!$need_form_gabarit_service_company_save['success']) {
//                            $this->db->rollback();
//                            $return = [
//                                'success' => false,
//                                'message' => 'DATA_CREATE_FAIL_TEXT',
//                                'data' => $need_form_gabarit_service_company_save
//                            ];
//                            goto end_of_function;
//                        }
//                    }
//                }

                //save to table need_form_gabarit_service_company
                $service_companies = Helpers::__getRequestValue("service_companies");
                if (count($service_companies) > 0) {
                    foreach ($service_companies as $service_company) {
                        if(!is_array($service_company)){
                            $service_company = (array)$service_company;
                        }
                        if($service_company['selected']){
                            $need_form_gabarit_service_company = new NeedFormGabaritServiceCompany();
                            $need_form_gabarit_service_company->setNeedFormGabaritId($need_assessment->getId());
                            $need_form_gabarit_service_company->setServiceCompanyId($service_company['id']);
                            $resultSave = $need_form_gabarit_service_company->__quickSave();
                            if (!$resultSave['success']) {
                                $this->db->rollback();
                                $return = [
                                    'success' => false,
                                    'msg' => 'DATA_CREATE_FAIL_TEXT',
                                    'data' => $need_form_gabarit_service_company
                                ];
                                goto end_of_function;
                            }
                        }

                    }
                }


                //save section
                $sections = $need_assessment_old->getNeedFormGabaritSection();
                if (count($sections) > 0) {
                    foreach ($sections as $section) {
                        $itemArray = $section->toArray();
                        unset($itemArray['id']);
                        $newSectionItem = \Phalcon\Mvc\Model::cloneResult(new NeedFormGabaritSection(), $itemArray);
                        $newSectionItem->setNeedFormGabaritId($need_assessment->getId());
//                        $newSectionItem->setNextSectionId(null);
                        $resultSave = $newSectionItem->__quickCreate();
                        if ($resultSave['success'] == false) {
                            $this->db->rollback();
                            $return = $resultSave;
                            goto end_of_function;
                        }
                        $items = NeedFormGabaritItem::find(['conditions' => 'need_form_gabarit_section_id=:id: and need_form_gabarit_item_id is null',
                            'bind' => [
                                'id' => $section->getId(),
                            ],
                            'order' => 'position ASC']);
                        if (count($items) > 0) {
                            foreach ($items as $item) {
                                $itemArray = $item->toArray();
                                unset($itemArray['id']);
                                $newItem = \Phalcon\Mvc\Model::cloneResult(new NeedFormGabaritItem(), $itemArray);
                                $newItem->setNeedFormGabaritId($need_assessment->getId());
                                $newItem->setNeedFormGabaritSectionId($newSectionItem->getId());
                                $newItemSave = $newItem->__quickCreate();
                                if ($newItemSave['success'] == false) {
                                    $this->db->rollback();
                                    $return = $newItemSave;
                                    goto end_of_function;
                                }

                                //Sub items
                                $subItems = $item->getSubItems();
                                foreach ($subItems as $subItem){
                                    $subItemArray = $subItem->toArray();
                                    unset($subItemArray['id']);
                                    unset($subItemArray['need_form_gabarit_item_id']);
                                    $newSubItem = \Phalcon\Mvc\Model::cloneResult(new NeedFormGabaritItem(), $subItemArray);
                                    $newSubItem->setNeedFormGabaritId($need_assessment->getId());
                                    $newSubItem->setNeedFormGabaritSectionId($newSectionItem->getId());
                                    $newSubItem->setNeedFormGabaritItemId($newItem->getId());
                                    $newSubItemSave = $newSubItem->__quickCreate();
                                    if ($newSubItemSave['success'] == false) {
                                        $this->db->rollback();
                                        $return = $newSubItemSave;
                                        goto end_of_function;
                                    }
                                }
                            }
                        }
                    }
                }

                /**** delete ****/
                $this->db->commit();
                $return = [
                    'success' => true,
                    'message' => 'NEED_ASSESSMENT_CREATE_SUCCESS_TEXT',
                    'data' => $need_assessment
                ];
                goto end_of_function;

            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function cloneSectionAction()
    {

        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAclUpdate();

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        $id = Helpers::__getRequestValue("id");

        if ($id > 0 && Helpers::__checkId($id)) {
            $oldSection = NeedFormGabaritSection::findFirstById($id);
            if ($oldSection) {

                $company = ModuleModel::$company;
                $language = $company->getLanguage();

                $cloneText = ConstantExt::__translateConstant('CLONE_BTN_TEXT', $language);

                $this->db->begin();
                $oldSectionArr = $oldSection->toArray();
                unset($oldSectionArr['id']);
                unset($oldSectionArr['created_at']);
                unset($oldSectionArr['updated_at']);
                unset($oldSectionArr['position']);

                $needFormGabarit = $oldSection->getNeedFormGabarit();
                $countSections = $needFormGabarit->getNeedFormGabaritSection();

                $newSectionItem = \Phalcon\Mvc\Model::cloneResult(new NeedFormGabaritSection(), $oldSectionArr);
                $newSectionItem->setName($cloneText . ' ' . $oldSection->getName());
                $newSectionItem->setNeedFormGabaritId($oldSection->getNeedFormGabaritId());
                $newSectionItem->setNextSectionId(null);
                $newSectionItem->setPosition(count($countSections) > 0 ? count($countSections) : 0);

                $resultSave = $newSectionItem->__quickCreate();

                if ($resultSave['success'] == false) {
                    $this->db->rollback();
                    $return = $resultSave;
                    goto end_of_function;
                }
                //save item

                $items = NeedFormGabaritItem::find(['conditions' => 'need_form_gabarit_section_id=:id: and need_form_gabarit_item_id is null',
                    'bind' => [
                        'id' => $oldSection->getId(),
                    ],
                    'order' => 'position ASC']);

                if (count($items) > 0) {
                    foreach ($items as $item) {
                        $itemArray = $item->toArray();
                        unset($itemArray['id']);
                        $newItem = \Phalcon\Mvc\Model::cloneResult(new NeedFormGabaritItem(), $itemArray);
                        $newItem->setNeedFormGabaritId($newSectionItem->getNeedFormGabaritId());
                        $newItem->setNeedFormGabaritSectionId($newSectionItem->getId());
                        $newItem->reupdateYesnoQuestion();
                        $newItemSave = $newItem->__quickCreate();
                        if ($newItemSave['success'] == false) {
                            $this->db->rollback();
                            $return = $newItemSave;
                            goto end_of_function;
                        }

                        //Sub items
                        $subItems = $item->getSubItems();
                        foreach ($subItems as $subItem){
                            $subItemArray = $subItem->toArray();
                            unset($subItemArray['id']);
                            unset($subItemArray['need_form_gabarit_item_id']);
                            $newSubItem = \Phalcon\Mvc\Model::cloneResult(new NeedFormGabaritItem(), $subItemArray);
                            $newSubItem->setNeedFormGabaritId($newSectionItem->getNeedFormGabaritId());
                            $newSubItem->setNeedFormGabaritSectionId($newSectionItem->getId());
                            $newSubItem->setNeedFormGabaritItemId($newItem->getId());
                            $newSubItem->setNextSectionId(null);
                            $newSubItemSave = $newSubItem->__quickCreate();
                            if ($newSubItemSave['success'] == false) {
                                $this->db->rollback();
                                $return = $newSubItemSave;
                                goto end_of_function;
                            }
                        }
                    }
                }

                /**** delete ****/
                $this->db->commit();
                $return = [
                    'success' => true,
                    'message' => 'NEED_ASSESSMENT_SECTION_CLONE_SUCCESS_TEXT',
                    'data' => $newSectionItem
                ];
                goto end_of_function;

            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function cloneQuestionAction()
    {

        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAclUpdate();

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        $id = Helpers::__getRequestValue("id");

        if ($id > 0 && Helpers::__checkId($id)) {
            $oldItem = NeedFormGabaritItem::findFirstById($id);
            if ($oldItem) {

                $company = ModuleModel::$company;
                $language = $company->getLanguage();

                $cloneText = ConstantExt::__translateConstant('CLONE_BTN_TEXT', $language);

                $oldItemArr = $oldItem->toArray();
                unset($oldItemArr['id']);
                unset($oldItemArr['created_at']);
                unset($oldItemArr['updated_at']);

                // $needFormGabaritSection = $oldItem->getNeedFormGabaritSection();
                // $countItems = $needFormGabaritSection->getNeedFormGabaritItems();


                $this->db->begin();
                //save item

                $newItem = \Phalcon\Mvc\Model::cloneResult(new NeedFormGabaritItem(), $oldItemArr);
                $newItem->setQuestion($cloneText . ' ' . $oldItem->getQuestion());
                $newItem->setNeedFormGabaritId($oldItem->getNeedFormGabaritId());
                $newItem->setNeedFormGabaritSectionId($oldItem->getNeedFormGabaritSectionId());

                $newItem->reupdateYesnoQuestion();
         
                $resultSave = $newItem->__quickCreate();
                if ($resultSave['success'] == false) {
                    $this->db->rollback();
                    $return = $resultSave;
                    goto end_of_function;
                }

                //Sub items
                $subItems = $oldItem->getSubItems();
                foreach ($subItems as $subItem){
                    $subItemArray = $subItem->toArray();
                    unset($subItemArray['id']);
                    unset($subItemArray['need_form_gabarit_item_id']);
                    $newSubItem = \Phalcon\Mvc\Model::cloneResult(new NeedFormGabaritItem(), $subItemArray);
                    $newSubItem->setNeedFormGabaritId($oldItem->getNeedFormGabaritId());
                    $newSubItem->setNeedFormGabaritSectionId($oldItem->getNeedFormGabaritSectionId());
                    $newSubItem->setNeedFormGabaritItemId($newItem->getId());
                    $newSubItem->setNextSectionId(null);
                    $newSubItemSave = $newSubItem->__quickCreate();
                    if ($newSubItemSave['success'] == false) {
                        $this->db->rollback();
                        $return = $newSubItemSave;
                        goto end_of_function;
                    }
                }

                /**** delete ****/
                $this->db->commit();
                $return = [
                    'success' => true,
                    'message' => 'NEED_ASSESSMENT_QUESTION_CLONE_SUCCESS_TEXT',
                    'data' => $newItem
                ];
                goto end_of_function;

            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     *
     */
    public function deleteNeedFormAction($id)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclDelete();

        $return = ['success' => false];
        if ($id > 0) {
            $need_form_gabarit = NeedFormGabarit::findFirstById($id);

            if ($need_form_gabarit && $need_form_gabarit->belongsToGms()) {

                if ($need_form_gabarit->getNeedFormCategory() == '' || $need_form_gabarit->getNeedFormCategory() == '') {

                }

                $return = $need_form_gabarit->__quickRemove();

                if ($return['success'] == true) {
                    $return['message'] = 'SAVE_NEED_ASSESSMENT_SUCCESS_TEXT';
                } else {
                    $return['message'] = 'SAVE_NEED_ASSESSMENT_FAILT_TEXT';
                }
            }
        }
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * Load list answer option in need assessment
     * @param int $need_assessment_id
     */
    public function needFormRequestDetail($uuid = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        $needAssignmentRelocation = NeedAssessmentsRelocation::findFirstByUuid($uuid);

        if (!$needAssignmentRelocation instanceof NeedAssessmentsRelocation) {
            $this->response->setJsonContent([
                'success' => false,
                'message' => 'NEED_ASSESSMENT_NOT_FOUND_TEXT'
            ]);
            $this->response->send();
            return;
        }

        // Find relocation
        $serviceCompany = $needAssignmentRelocation->getActiveRelocationServiceCompany();
        $relocation = $needAssignmentRelocation->getRelocation();
        $rootNeedAssesment = $needAssignmentRelocation->getNeedAssessments();

        // Load list question
        $need_assessment_items = $rootNeedAssesment ? $rootNeedAssesment->getNeedAssessmentItems() : [];
        $needAssessmentItemsArray = [];

        if ($needAssignmentRelocation->getStatus() == NeedAssessmentsRelocation::STATUS_ANSWERED) {

            foreach ($need_assessment_items as $needAssessmentItem) {
                $answer = $needAssessmentItem->getNeedAssessmentAnswer()->getFirst();
                $optionsArray = [];
                if ($needAssessmentItem->getAnswerFormat() == NeedAssessmentItems::QUESTIONNAIRE_TYPE_MULTI ||
                    $needAssessmentItem->getAnswerFormat() == NeedAssessmentItems::QUESTIONNAIRE_TYPE_OPTION
                ) {

                    $responseObject = json_decode($answer->getAnswer());
                    foreach (json_decode($needAssessmentItem->getAnswerContent()) as $key => $value) {
                        $optionsArray[$key] = [
                            'value' => $value,
                            'selected' => (is_numeric($responseObject) && $key == $responseObject) ? true : (
                            is_object($responseObject) && isset($responseObject->$key) && $responseObject->$key == true ? true : false
                            ),
                        ];
                    }
                }

                $needAssessmentItemsArray[] = [
                    'id' => $needAssessmentItem->getId(),
                    'uid' => $needAssessmentItem->getUid(),
                    'questionnaire' => $needAssessmentItem->getQuestionnaire(),
                    'answer_format' => $needAssessmentItem->getAnswerFormat(),
                    'answer_content' => $needAssessmentItem->getAnswerContent(),
                    'answer_options' => $optionsArray,
                    'answer' => $answer ? $answer->getAnswer() : '',
                    'answer_data' => $answer ? json_decode($answer->getAnswer()) : '',
                ];
            }
        }

        $this->response->setJsonContent([
            'success' => true,
            'data' => count($needAssessmentItemsArray) ? $needAssessmentItemsArray : [],
            'service_uuid' => ($serviceCompany ? $serviceCompany->getUuid() : ''),
            'relocation_uuid' => $relocation->getUuid(),
            'need_assignment' => $needAssignmentRelocation
        ]);
        return $this->response->send();
    }

    /**
     * Load detail of need assessment
     * @param int $id
     */
    public function requestDetailAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
        $result = [
            'success' => false,
            'message' => 'NEED_ASSESSMENT_NOT_FOUND_TEXT'
        ];

        if ($uuid !== '' && Helpers::__isValidUuid($uuid)) {
            $needFormRequest = NeedFormRequest::findFirstByUuid($uuid);
            if ($needFormRequest instanceof NeedFormRequest && $needFormRequest->belongsToGms()) {
                $data = $needFormRequest->toArray();
                $needFormGabarit = $needFormRequest->getNeedFormGabarit();
                if ($needFormGabarit) {
                    if($needFormRequest->getStatus() == NeedFormRequest::STATUS_ANSWERED){
                        $data['answered_on'] = $needFormRequest->getUpdatedAt();

                        $formBuilder = $needFormRequest->getFormBuilderStructureAnswered($needFormRequest);
                    }else{
                        $data['answered_on'] = null;
                        $formBuilder = $needFormRequest->getFormBuilderStructure();
                    }
                    $result = [
                        'success' => true,
                        'data' => $data,
                        'formBuilder' => $formBuilder,
                        'questions' => $formBuilder
                    ];
                }
            } else {
                $result = [
                    'success' => false,
                    'message' => 'NEED_ASSESSMENT_NOT_FOUND_TEXT'
                ];
            }
        } else {
            $result = [
                'success' => false,
                'message' => 'NEED_ASSESSMENT_NOT_FOUND_TEXT',
                'detail' => 'UUID NOT VALID'
            ];
        }

        $this->response->setJsonContent($result);
        $this->response->send();

    }

    /**
     * Save Section task
     */
    public function saveSectionAction(){
        $this->view->disable();
        $this->checkAjaxPost();
//        $this->checkAclUpdate();
        $this->db->begin();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $need_form_gabarit_id = Helpers::__getRequestValue("need_form_gabarit_id");

        $needFormGabarit = NeedFormGabarit::findFirstById($need_form_gabarit_id);

        if(!$needFormGabarit){
           goto end_of_function;
        }

        $id = Helpers::__getRequestValue("id");
        $next_section_id = Helpers::__getRequestValue("next_section_id");

        $isNew = false;
        if($id){
            $section = NeedFormGabaritSection::findFirstById($id);
        }else{
            $isNew = true;
            $section = new NeedFormGabaritSection();
        }

        $countSections = $needFormGabarit->getNeedFormGabaritSection();

        $section->setNeedFormGabaritId($need_form_gabarit_id);
        $section->setName(Helpers::__getRequestValue('name'));
        $section->setDescription(Helpers::__getRequestValue('description'));
        if(!$section->getId() > 0){
            $section->setPosition(count($countSections) > 0 ? count($countSections) : 0);

        }
        if(isset($next_section_id)){
            $section->setNextSectionId(intval($next_section_id));

        }else{
            $section->setNextSectionId(null);

        }

        if($isNew){
            $return = $section->__quickCreate();
        }else{
            $return = $section->__quickSave();

        }
        if(!$return['success']){
            $this->db->rollback();
            goto end_of_function;
        }

        $this->db->commit();

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * detail
     */
    public function detailSectionAction($id){
        $this->view->disable();
        $this->checkAjaxGet();
//        $this->checkAclUpdate();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];


        $section = NeedFormGabaritSection::findFirstById($id);

        if(!$section){
            goto end_of_function;
        }

//        $items = $section->getNeedFormGabaritItems();

        $arrSection = $section->parsedDataToArray();
//        $arrSection['items'] = $items;

        $return = [
            'success' => true,
            'data' => $arrSection
        ];

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $need_form_gabarit_id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function listSectionsAction($need_form_gabarit_id){
        $this->view->disable();
        $this->checkAjaxPutGet();
//        $this->checkAclUpdate();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];


        $needFormGabarit = NeedFormGabarit::findFirstById($need_form_gabarit_id);
        $exceptIds = Helpers::__getRequestValue('exceptIds');
        $isSubmit = Helpers::__getRequestValue('isSubmit');

        $sections = $needFormGabarit->getNeedFormGabaritSection([
            'order' => 'position ASC'
        ]);

        $data = [];
//        $data = $sections->toArray();
        foreach ($sections as $key => $section){
            if($exceptIds){
                if(!in_array($section->getId(), $exceptIds)){
                    $item = $section->parsedDataToArray();
                    $item['index'] = $key;
                    $data[] = $item;
                }
            }else{
                $item = $section->parsedDataToArray();
                $item['index'] = $key;
                $data[] = $item;
            }
        }
        if($isSubmit){
            $data[] = ['id' => -1, 'index' => -1, 'name' => 'SUBMIT_FORM_TEXT'];
        }

        $return = [
            'success' => true,
            'data' => $data
        ];

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * remove section
     */
    public function removeNeedFormGabaritSectionAction($id){
        $this->view->disable();
        $this->checkAjaxDelete();
//        $this->checkAclUpdate();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];


        $section = NeedFormGabaritSection::findFirstById($id);

        if(!$section){
            goto end_of_function;
        }

        $needFormGabarit = $section->getNeedFormGabarit();


        $this->db->begin();

        $items = NeedFormGabaritItem::find([
            'conditions' => 'need_form_gabarit_section_id = :need_form_gabarit_section_id:',
            'bind' => [
                'need_form_gabarit_section_id' => $section->getId()
            ]
        ]);

        foreach ($items as $item){
            $matchFields = $item->getNeedFormGabaritItemSystemFields();
            if(count($matchFields) > 0){
                foreach ($matchFields as $matchField){
                    $resultDelete = $matchField->__quickRemove();

                    if(!$resultDelete['success']){
                        $this->db->rollback();
                        $return = $resultDelete;
                        goto end_of_function;
                    }
                }
            }
        }

        $itemsDelete = $items->delete();

        $return = $section->__quickRemove();

        if(!$return['success']){
            $this->db->rollback();
            goto end_of_function;
        }

        //Reorder Section
        $listSections = $needFormGabarit->getNeedFormGabaritSection([
            'order' => 'position ASC'
        ]);

        if(count($listSections) > 0){
            foreach ($listSections as $i => $section){
                $section->setPosition($i);
                $resultUpdate = $section->__quickSave();

                if(!$resultUpdate['success']){
                    $return = $resultUpdate;
                    $this->db->rollback();
                    goto  end_of_function;
                }
            }

        }


        $this->db->commit();

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }



    /**
     * Save item
     */
    public function saveItemAction(){
        $this->view->disable();
        $this->checkAjaxPost();
//        $this->checkAclUpdate();
        $this->db->begin();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $arrayData = Helpers::__getRequestValuesArray();

        $need_form_gabarit_id = Helpers::__getRequestValue("need_form_gabarit_id");
        $needFormGabarit = NeedFormGabarit::findFirstById($need_form_gabarit_id);
        if(!$needFormGabarit){
            goto end_of_function;
        }

        $need_form_gabarit_section_id = Helpers::__getRequestValue("need_form_gabarit_section_id");
        $needFormGabaritSection = NeedFormGabaritSection::findFirstById($need_form_gabarit_section_id);
        if(!$needFormGabaritSection){
            goto end_of_function;
        }

        $id = Helpers::__getRequestValue("id");

        $isNew = false;
        if($id){
            $item = NeedFormGabaritItem::findFirstById($id);

            //Check type
            $oldType = $item->getType();
            if($oldType != $arrayData['type']){
                $matchAnswers =  $item->getNeedFormGabaritItemSystemFields();
                if(count($matchAnswers) > 0){
                    $resultDelete = $matchAnswers->delete();
                }

                $subItems =  $item->getSubItems();
                if(count($subItems) > 0){
                    $resultDelete = $subItems->delete();
                }
            }
        }else{
            $isNew = true;
            $item = new NeedFormGabaritItem();
        }

        $countItems = $needFormGabaritSection->getNeedFormGabaritItems();

        $item->setNeedFormGabaritId($need_form_gabarit_id);
        $item->setNeedFormGabaritSectionId($need_form_gabarit_section_id);
        $item->setQuestion(Helpers::__getRequestValue('question'));
        $item->setTypeV2($arrayData);

        if(isset($arrayData['limit']) && $item->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_MULTIPLE_OPTION){
            $item->setLimit((int)$arrayData['limit']);
        }

        if(!$item->getId() > 0){
            $item->setPosition(count($countItems) > 0 ? count($countItems) : 0);
        }

        if($item->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_LINEAR || $item->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_YESNO){
            if($arrayData['options']){
                $item->setAnswerContent(json_encode($arrayData['options'], true));
            }
        }

        $item->setIsMandatory((int)Helpers::__getRequestValue('is_mandatory'));


        if($isNew){
            $return = $item->__quickCreate();
        }else{
            $return = $item->__quickSave();
        }

        if(!$return['success']){
            $this->db->rollback();
            goto end_of_function;
        }

        if($item->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_MULTIPLE_OPTION ||
            $item->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_DROPDOWN_LIST ||
            $item->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_SINGLE_OPTION){
            //Todo list (After finish template directive)
            $options = $arrayData['options'];
            foreach ($options as $key => $option){
                if(is_object($option)){
                    $option = (array) $option;
                }
                $isSubNew = false;
                if($option['id'] && $option['id'] > 0){
                    $subItem = NeedFormGabaritItem::findFirstById($option['id']);
                }else{
                    $subItem = new NeedFormGabaritItem();
                    $isSubNew = true;
                }

                $value = $option['value'];

                $subItem->setNeedFormGabaritId($need_form_gabarit_id);
                $subItem->setNeedFormGabaritSectionId($need_form_gabarit_section_id);
                $subItem->setQuestion($value);
                $subItem->setAnswerFormat($item->getAnswerFormat());
                $subItem->setPosition($key);
                $subItem->setNeedFormGabaritItemId($item->getId());

                if(isset($option['next_section_id'])){
                    $subItem->setNextSectionId((int)$option['next_section_id']);
                }else{
                    $subItem->setNextSectionId(null);
                }

                if(isset($option['goToSection'])){
                    $subItem->setNextSectionId((int)$option['goToSection']);
                }

                if($isNew){
                    $returnSubItem = $subItem->__quickCreate();
                }else{
                    $returnSubItem = $subItem->__quickSave();
                }

                if(!$returnSubItem['success']){
                    $this->db->rollback();
                    $return = $returnSubItem;
                    goto end_of_function;
                }
            }
        }



        $this->db->commit();

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $id
     */
    public function detailNeedFormGabaritItemAction($id){
        $this->view->disable();
        $this->checkAjaxGet();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $item = NeedFormGabaritItem::findFirstById($id);
        if(!$item){
            goto end_of_function;
        }

        $data = $item->toArray();
        $data['is_mandatory'] = (int)$item->getIsMandatory();
        $data['limit'] = (int)$item->getLimit();
        $data['id'] = (int)$item->getId();

        $data['type'] = $item->getType();

        if($item->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_LINEAR || $item->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_YESNO){
            if (Helpers::__isJsonValid($item->getAnswerContent())) {
                $options = json_decode($item->getAnswerContent(), true);
                $data['options'] = $options;
                $isChecked = false;
                foreach ($options as $option){
                    if(isset($option['next_section_id']) && $option['next_section_id'] !== null){
                        $isChecked = true;
                    }
                }
                if($isChecked){
                    $data['config']['checked'] = true;
                }
            }

        }


        if($item->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_MULTIPLE_OPTION ||
            $item->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_DROPDOWN_LIST ||
            $item->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_SINGLE_OPTION){
            $subItems = $item->getSubItems();
            $options = [];
            $isChecked = false;
            foreach ($subItems as $subItem){
                $option = [];
                $option['id'] = $subItem->getId();
                $option['value'] = $subItem->getQuestion();
                if($subItem->getNextSectionId() !== null){
                    $option['next_section_id'] = (int)$subItem->getNextSectionId();
                    $isChecked = true;
                }
                $options[] = $option;
            }
            $data['options'] = $options;
            if($isChecked){
                $data['config']['checked'] = $isChecked;
            }
        }

        //Check match field
        $data['hasMatchField'] = $item->hasMatchField();

        $return = ['success' => true, 'data' => $data, 'options' => isset($data['options']) ? $data['options'] : []];

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Delete Item
     */
    public function removeNeedFormGabaritItemAction($id){
        $this->view->disable();
        $this->checkAjaxDelete();

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $item = NeedFormGabaritItem::findFirstById($id);
        $section = $item->getNeedFormGabaritSection();
        if(!$item){
            goto end_of_function;
        }
        $this->db->begin();
        $subItems = $item->getSubItems();
        if(count($subItems) > 0){
            foreach ($subItems as $subItem){
                $resultDelete = $subItem->__quickRemove();

                if(!$resultDelete['success']){
                    $this->db->rollback();
                    $return = $resultDelete;
                    goto end_of_function;
                }
            }
        }

        $matchFields = $item->getNeedFormGabaritItemSystemFields();
        if(count($matchFields) > 0){
            foreach ($matchFields as $matchField){
                $resultDelete = $matchField->__quickRemove();

                if(!$resultDelete['success']){
                    $this->db->rollback();
                    $return = $resultDelete;
                    goto end_of_function;
                }
            }
        }

        $return = $item->__quickRemove();

        if(!$return['success']){
            $this->db->rollback();
            goto end_of_function;
        }

        //Reorder Items
        $listItems = $section->getNeedFormGabaritItems();

        if(count($listItems) > 0){
            foreach ($listItems as $i => $item){
                $item->setPosition($i);
                $resultUpdate = $item->__quickSave();

                if(!$resultUpdate['success']){
                    $return = $resultUpdate;
                    $this->db->rollback();
                    goto  end_of_function;
                }
            }

        }

        $this->db->commit();
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     *  Update position of NeedFormGabaritSection
     */
    public function setPositionOfSectionsAction(){
        $this->view->disable();
        $this->checkAjaxPost();

        $sectionIds = Helpers::__getRequestValue('sectionIds');

        $i = 0;

        $this->db->begin();
        foreach($sectionIds as $id){
            $needFormGabaritSection = NeedFormGabaritSection::findFirstById($id);
            if($needFormGabaritSection){
                $needFormGabaritSection->setPosition($i);
                $resultUpdate = $needFormGabaritSection->__quickSave();

                if(!$resultUpdate['success']){
                    $return = $resultUpdate;
                    $this->db->rollback();
                    goto  end_of_function;
                }
                $i++;
            }
        }

        $return['success'] = true;
        $return['message'] = 'ORDER_SUCCESSFULLY_TEXT';
        $this->db->commit();


        end_of_function:

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Update position of NeedFormGabaritItem
     */
    public function setPositionOfItemsAction(){
        $this->view->disable();
        $this->checkAjaxPost();

        $itemIds = Helpers::__getRequestValue('itemIds');

        $i = 0;
        $this->db->begin();
        foreach($itemIds as $id){
            $needFormGabaritItem = NeedFormGabaritItem::findFirstById($id);
            if($needFormGabaritItem){
                $needFormGabaritItem->setPosition($i);
                $resultUpdate = $needFormGabaritItem->__quickUpdate();

                if(!$resultUpdate['success']){
                    $return = $resultUpdate;
                    $this->db->rollback();
                    goto  end_of_function;
                }
                $i++;
            }
        }

        $return['success'] = true;
        $return['message'] = 'ORDER_SUCCESSFULLY_TEXT';
        $this->db->commit();


        end_of_function:

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * List associate services
     * @param $id (need_form_gabarit_id)
     * @return \Phalcon\Http\ResponseInterface
     */
    public function getListAssociateServicesAction($id){
        $this->view->disable();
        $this->checkAjaxPost();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if(!$id > 0){
            goto end_of_function;
        }


        $serviceCompanies = ServiceCompany::getListActiveOfMyCompany([]);
        $needFormGabarit = NeedFormGabarit::findFirstById($id);

        $need_form_gabarit_item_id = Helpers::__getRequestValue('need_form_gabarit_item_id');
        $needFormGabaritItem = NeedFormGabaritItem::findFirstById($need_form_gabarit_item_id);

        if($needFormGabarit){
            $arrObjectUuids = [];

//            $services = $needFormGabarit->getServiceCompanies();
            if($needFormGabaritItem){
                $NeedFormGabaritItemSystemFields = $needFormGabaritItem->getNeedFormGabaritItemSystemFields([
                    'order' => 'created_at DESC',
                    'columns' => 'object_uuid'
                ])->toArray();

                foreach ($NeedFormGabaritItemSystemFields as $NeedFormGabaritItemSystemField){
                    $arrObjectUuids[] = $NeedFormGabaritItemSystemField['object_uuid'];
                }
            }

            $arrItem = [];

            if(Helpers::__getRequestValue('isMapField')){
                $arrItem[] = [
                    'uuid' => NeedFormGabaritItemSystemField::OBJECT_ASSIGNMENT,
                    'name' => 'ASSIGNMENT_TEXT',
                    'is_selected' => count($arrObjectUuids) > 0 && in_array(NeedFormGabaritItemSystemField::OBJECT_ASSIGNMENT, $arrObjectUuids) ? true : false
                ];

                $arrItem[] = [
                    'uuid' => NeedFormGabaritItemSystemField::OBJECT_RELOCATION,
                    'name' => 'RELOCATION_TEXT',
                    'is_selected' => count($arrObjectUuids) > 0 && in_array(NeedFormGabaritItemSystemField::OBJECT_RELOCATION, $arrObjectUuids) ? true : false
                ];

                $arrItem[] = [
                    'uuid' => NeedFormGabaritItemSystemField::OBJECT_EMPLOYEE,
                    'name' => 'EMPLOYEE_TEXT',
                    'is_selected' => count($arrObjectUuids) > 0 && in_array(NeedFormGabaritItemSystemField::OBJECT_EMPLOYEE, $arrObjectUuids) ? true : false
                ];

                // $arrItem[] = [
                //     'uuid' => NeedFormGabaritItemSystemField::OBJECT_ACCOUNT,
                //     'name' => 'ACCOUNT_TEXT',
                //     'is_selected' => count($arrObjectUuids) > 0 && in_array(NeedFormGabaritItemSystemField::OBJECT_ACCOUNT, $arrObjectUuids) ? true : false
                // ];
            }

            foreach ($serviceCompanies as $service){
                $item = $service->toArray();
                if (count($arrObjectUuids) > 0 && in_array($service->getUuid(), $arrObjectUuids)){
                    $item['is_selected'] = true;
                }else{
                    $item['is_selected'] = false;
                }

                $arrItem[] = $item;
            }

            $return = [
                'success' => true,
                'data' => $arrItem
            ];
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Matching Field
     */
    public function matchServiceFieldAction(){
        $this->view->disable();
        $this->checkAjaxPost();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $needFormGabaritItemId = Helpers::__getRequestValue('need_form_gabarit_item_id');
        $objectUuid = Helpers::__getRequestValue('object_uuid');
        $serviceFieldId = Helpers::__getRequestValue('service_field_id');
        $id = Helpers::__getRequestValue('id');

        $needFormGabaritItem = NeedFormGabaritItem::findFirstById($needFormGabaritItemId);

        if(!$needFormGabaritItem){
            $return['message'] = 'NO_NEED_FORM_GABARIT_ITEM_TEXT';
            goto end_of_function;
        }


        if($objectUuid == NeedFormGabaritItemSystemField::OBJECT_RELOCATION || $objectUuid == NeedFormGabaritItemSystemField::OBJECT_ASSIGNMENT || $objectUuid == NeedFormGabaritItemSystemField::OBJECT_EMPLOYEE || $objectUuid == NeedFormGabaritItemSystemField::OBJECT_ACCOUNT){
            $mapField = MapField::findFirstByUuid(Helpers::__getRequestValue('map_field_uuid'));
            if(!$mapField){
                $return['message'] = 'MAP_FIELD_NOT_FOUND_TEXT';
                goto end_of_function;
            }
        }else{
            $object = ServiceCompany::findFirstByUuid($objectUuid);
            if(!$object){
                $return['message'] = 'SERVICE_COMPANY_NOT_FOUND_TEXT';
                goto end_of_function;
            }

            $serviceField = ServiceField::findFirstById($serviceFieldId);
            if(!$serviceField){
                $return['message'] = 'SERVICE_FIELD_NOT_FOUND_TEXT';
                goto end_of_function;
            }
        }


        $isNew = false;
        if($id){
            $NeedFormGabaritItemSystemField = NeedFormGabaritItemSystemField::findFirstById($id);
        }else{
            $NeedFormGabaritItemSystemField = new NeedFormGabaritItemSystemField();
            $isNew = true;
        }

        $NeedFormGabaritItemSystemField->setNeedFormGabaritItemId($needFormGabaritItem->getId());
        $NeedFormGabaritItemSystemField->setNeedFormGabaritId($needFormGabaritItem->getNeedFormGabaritId());
        $NeedFormGabaritItemSystemField->setObjectUuid($objectUuid);
        $NeedFormGabaritItemSystemField->setServiceFieldId(isset($serviceField) && $serviceField ? $serviceField->getId() : null);

        if(isset($object) && $object instanceof ServiceCompany){
            $NeedFormGabaritItemSystemField->setServiceCompanyId($object->getId());
            $NeedFormGabaritItemSystemField->setMapFieldUuid(null);
        }

        if(isset($mapField) && $mapField instanceof MapField){
            $NeedFormGabaritItemSystemField->setMapFieldUuid($mapField->getUuid());
            $NeedFormGabaritItemSystemField->setServiceCompanyId(null);
            $NeedFormGabaritItemSystemField->setServiceFieldId(null);
        }


        if($isNew){
            $NeedFormGabaritItemSystemField->setUuid(Helpers::__uuid());
            $return = $NeedFormGabaritItemSystemField->__quickCreate();
        }else{
            $return = $NeedFormGabaritItemSystemField->__quickUpdate();
        }

        if($return['success']){
            $return['data'] = $NeedFormGabaritItemSystemField->parsedDataToArray();
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Load list field matching
     * @param $id //Need form gabarit item id
     */
    public function loadListFieldsMatchingAction($id){
        $this->view->disable();
        $this->checkAjaxGet();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if(!$id > 0){
            goto end_of_function;
        }

        $needFormGabaritItem = NeedFormGabaritItem::findFirstById($id);

        if($needFormGabaritItem){
            $NeedFormGabaritItemSystemFields = $needFormGabaritItem->getNeedFormGabaritItemSystemFields([
                'order' => 'created_at DESC'
            ]);

            $data = [];

            foreach ($NeedFormGabaritItemSystemFields as $NeedFormGabaritItemSystemField){
                $item = $NeedFormGabaritItemSystemField->parsedDataToArray();
                $data[] = $item;
            }

            $return = [
                'success' => true,
                'data' => $data
            ];
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Remove field matching
     */
    public function removeFieldMatchingAction($id){
        $this->view->disable();
        $this->checkAjaxDelete();

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $NeedFormGabaritItemSystemField = NeedFormGabaritItemSystemField::findFirstById($id);

        if(!$NeedFormGabaritItemSystemField){
            goto end_of_function;
        }
        $return = $NeedFormGabaritItemSystemField->__quickRemove();

        if(!$return['success']){
            goto end_of_function;
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Get match field based on object name
     */
    public function getMapFieldsDataAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $return = [
            'success' => false,
            'message' => 'FIELD_NOT_FOUND_TEXT'
        ];

        $object = Helpers::__getRequestValue('objectName');

        if ($object == NeedFormGabaritItemSystemField::OBJECT_ASSIGNMENT || $object == NeedFormGabaritItemSystemField::OBJECT_RELOCATION || $object == NeedFormGabaritItemSystemField::OBJECT_EMPLOYEE  || $object == NeedFormGabaritItemSystemField::OBJECT_ACCOUNT) {
            $listFieldCodes = NeedFormGabaritItemSystemField::MAP_OBJECT_TYPE_TO_MAP_FIELDS_CODE[$object];

            $needFormItem = NeedFormGabaritItem::findFirstById(Helpers::__getRequestValue('need_form_gabarit_item_id'));
            $typeNames = $needFormItem ? NeedFormGabaritItemSystemField::MAP_QUESTIONNAIRE_TYPE_TO_SERVICE_FIELDS[$needFormItem->getAnswerFormat()] : [];
            $mapFields = MapField::__findWithFilter([
                'codes' => $listFieldCodes,
                'typeNames' => $typeNames,
                'company_id' => ModuleModel::$company->getId(),
                'need_form_gabarit_id' => $needFormItem->getNeedFormGabaritId(),
            ]);

//            $mapFields = MapField::find([
//                'conditions' => 'code IN ({codes:array})',
//                'bind' => [
//                    'codes' => $listFieldCodes
//                ]
//            ]);

            $return = [
                'success' => true,
                'data' => $mapFields
            ];
        }
        $this->response->setJsonContent($return);
        return $this->response->send();

    }
}
