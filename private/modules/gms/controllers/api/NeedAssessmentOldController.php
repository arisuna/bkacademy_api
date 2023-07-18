<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Models\NeedAssessmentsExt;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\NeedAssessments;
use Reloday\Gms\Models\NeedFormGabaritItem;
use Reloday\Gms\Models\NeedFormGabarit;

use Reloday\Gms\Models\NeedFormGabaritSection;
use Reloday\Gms\Models\NeedFormGabaritServiceCompany;
use Reloday\Gms\Models\NeedFormRequest;
use Reloday\Gms\Models\NeedFormRequestAnswer;
use Reloday\Gms\Models\ServiceCompany;

use Reloday\Application\Lib\Helpers;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class NeedAssessmentOldController extends BaseController
{
    /**
     * @Route("/needassessment", paths={module="gms"}, methods={"GET"}, name="gms-needaccessment-index")
     */
    public function indexAction()
    {
        //get service company
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
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
                $results[$item->getId()]['services'] = NeedFormGabaritServiceCompany::getByNeedFormGabarit($item->getId());
            }
            $this->response->setJsonContent([
                'success' => true,
                'data' => $results
            ]);
        }
        $this->response->send();
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
                'company_id=' . ModuleModel::$company->getId() . ' AND id=' . (int)$id
            ]);

            if ($need_assessment instanceof NeedFormGabarit) {
                $need_assessment_array = $need_assessment->toArray();
                // Load need assessment services
                $need_assessment_array['service_company_id'] = [];
                $service_companies = NeedFormGabaritServiceCompany::getByNeedFormGabarit((int)$id);
                $i = 0;
                foreach ($service_companies as $service_company) {
                    $need_assessment_array['service_company_id'][$i] = (int)$service_company->getId();
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
                foreach ($sections as $section) {
                    $need_assessment_array['formBuilder'][$section->getPosition()] = $section->toArray();
                    $need_assessment_array['formBuilder'][$section->getPosition()]['index'] = (int)$section->getPosition();
                    $need_assessment_array['formBuilder'][$section->getPosition()]['goToSection'] = (int)$section->getNextSectionId();
                    $need_assessment_array['formBuilder'][$section->getPosition()]['items'] = [];
                    $items = NeedFormGabaritItem::find(['conditions' => 'need_form_gabarit_section_id=:id:',
                        'bind' => [
                            'id' => $section->getId()
                        ],
                        'order' => 'position ASC'
                    ]);
                    $i = 0;
                    foreach ($items as $keyPosition => $item) {
                        $need_assessment_array['formBuilder'][$section->getPosition()]['items'][$keyPosition] = $item->getContent();
                        $i++;
                    }
                }
                $result = [
                    'success' => true,
                    'formBuilder' => $need_assessment_array['formBuilder'],
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
            $service_companies = Helpers::__getRequestValue("service_company_id");
            if (count($service_companies) > 0) {
                foreach ($service_companies as $service_company) {
                    $need_form_gabarit_service_company = new NeedFormGabaritServiceCompany();
                    $need_form_gabarit_service_company->setNeedFormGabaritId($needForm->getId());
                    $need_form_gabarit_service_company->setServiceCompanyId($service_company);
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
                $service_company_ids = Helpers::__getRequestValue("service_company_id");


                if (count($service_company_ids) > 0) {

                    $itemsToDelete = NeedFormGabaritServiceCompany::find([
                        "conditions" => "need_form_gabarit_id  = :need_form_gabarit_id: AND service_company_id NOT IN ({service_company_ids:array})",
                        "bind" => [
                            'need_form_gabarit_id' => $id,
                            'service_company_ids' => $service_company_ids
                        ]
                    ]);
                    if ($itemsToDelete->count()) {
                        $resultDelete = ModelHelper::__quickRemoveCollection($itemsToDelete);
                    }


                    foreach ($service_company_ids as $service_company_id) {


                        $need_form_gabarit_service_company = NeedFormGabaritServiceCompany::findFirst([
                            "conditions" => "need_form_gabarit_id  = :need_form_gabarit_id: AND service_company_id = :service_company_id:",
                            "bind" => [
                                'need_form_gabarit_id' => $id,
                                'service_company_id' => $service_company_id
                            ]
                        ]);

                        if (!$need_form_gabarit_service_company instanceof NeedFormGabaritServiceCompany) {
                            $need_form_gabarit_service_company = new NeedFormGabaritServiceCompany();
                            $need_form_gabarit_service_company->setNeedFormGabaritId($need_assessment->getId());
                            $need_form_gabarit_service_company->setServiceCompanyId($service_company_id);
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
                        }
                    }
                }

                //save section
                $sections = Helpers::__getRequestValue("formBuilder");
                $existed_sections = $need_assessment->getNeedFormGabaritSection();
                if (count($existed_sections) > 0) {
                    foreach ($existed_sections as $existed_section) {
                        $found = false;
                        if (count($sections) > 0) {
                            foreach ($sections as $section) {
                                if (isset($section->id) && $section->id == $existed_section->getId()) {
                                    $found = true;
                                }
                            }
                        }
                        if (!$found) {
                            $delete_section = $existed_section->__quickRemove();
                            if (!$delete_section['success']) {
                                $this->db->rollback();
                                $return = [
                                    'success' => false,
                                    'msg' => 'DATA_CREATE_FAIL_TEXT',
                                    'data' => $delete_section
                                ];
                                goto end_of_function;
                            }
                        }
                    }
                }
                if (count($sections) > 0) {
                    foreach ($sections as $section) {
                        if (!isset($section->id)) {
                            $need_form_gabarit_section = new NeedFormGabaritSection();
                        } else {
                            $need_form_gabarit_section = NeedFormGabaritSection::findFirstById($section->id);
                        }
                        $need_form_gabarit_section->setNeedFormGabaritId($need_assessment->getId());

                        if (property_exists($section, 'name') || isset($section->name)) {
                            $need_form_gabarit_section->setName($section->name);
                        }
                        if (property_exists($section, 'description') || isset($section->description)) {
                            $need_form_gabarit_section->setDescription($section->description);
                        }
                        if (property_exists($section, 'index') || isset($section->index)) {
                            $need_form_gabarit_section->setPosition($section->index);
                        }

                        if (isset($section->next_section_id) && is_numeric($section->next_section_id) && $section->next_section_id > 0) {
                            $need_form_gabarit_section->setNextSectionId($section->next_section_id);
                        }
                        $section_result = $need_form_gabarit_section->__quickSave();
                        $result['formBuilder'][] = $need_form_gabarit_section;
                        if (!$section_result['success']) {
                            $this->db->rollback();
                            $return = [
                                'success' => false,
                                'msg' => 'DATA_CREATE_FAIL_TEXT',
                                'data' => $section_result
                            ];
                            goto end_of_function;
                        }
                        $items = $section->items;
                        if (count($items) > 0) {
                            $i = 0;
                            $applyItemIds = [];
                            foreach ($items as $applyItem) {
                                if (isset($applyItem->id)) {
                                    $applyItemIds[] = (int)$applyItem->id;
                                }
                            }
                            //delete Items not exist in Section
                            //@delete question fixed
                            $currentNeedGabaritItems = $need_form_gabarit_section->getNeedFormGabaritItems();

                            foreach ($currentNeedGabaritItems as $currentItem) {
                                if (!in_array($currentItem->getId(), $applyItemIds)) {
                                    $item_result = $currentItem->__quickRemove();
                                    if ($item_result['success'] == false) {
                                        $this->db->rollback();
                                        $return = [
                                            'success' => false,
                                            'msg' => 'DATA_CREATE_FAIL_TEXT',
                                            'data' => $item_result,
                                        ];
                                        goto end_of_function;
                                    }
                                }
                            }


                            foreach ($items as $keyPosition => $item) {
                                //Create Item in SECTION
                                if (!isset($item->id) || !is_numeric($item->id) || !(int)($item->id) > 0) {
                                    $need_form_gabarit_item = new NeedFormGabaritItem();
                                } else {
                                    $need_form_gabarit_item = NeedFormGabaritItem::findFirstById((int)($item->id));
                                    if (!$need_form_gabarit_item) {
                                        $need_form_gabarit_item = new NeedFormGabaritItem();
                                    }
                                }

                                $need_form_gabarit_item->setNeedFormGabaritId($need_form_gabarit_section->getNeedFormGabaritId());
                                $need_form_gabarit_item->setNeedFormGabaritSectionId($need_form_gabarit_section->getId());
                                if (isset($item->props->title)) {
                                    $need_form_gabarit_item->setQuestion($item->props->title);
                                }
                                $need_form_gabarit_item->setPosition($keyPosition);
                                if (isset($item->config->required)) {
                                    $need_form_gabarit_item->setIsMandatory($item->config->required ? NeedFormGabaritItem::REQUIRED : NeedFormGabaritItem::NOT_REQUIRED);
                                }
                                $need_form_gabarit_item->setType($item);
                                if (isset($item->config->maxSelections)) {
                                    $need_form_gabarit_item->setLimit($item->config->maxSelections);
                                } else if ($need_form_gabarit_item->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_MULTIPLE_OPTION) {
                                    $need_form_gabarit_item->setLimit(count($item->options));
                                }
                                if (isset($item->options)) {
                                    if (is_array($item->options) && count($item->options) > 0) {
                                        $item->options = (array)$item->options;
                                        foreach ($item->options as $key => $content) {
                                            if (isset($content->goToSection)) {
                                                $item->options[$key]->goToSection = intval($content->goToSection);
                                            }
                                        }
                                    }
                                }
                                if (isset($item->options)) {
                                    $need_form_gabarit_item->setAnswerContent(json_encode($item->options, true));
                                }
                                if (isset($item->config->direction)) {
                                    $need_form_gabarit_item->setDirection($item->config->direction == "horizontal" ? NeedFormGabaritItem::DIRECTION_HORIZONTAL : NeedFormGabaritItem::DIRECTION_VERTICAL);
                                }
                                $item_result = $need_form_gabarit_item->__quickSave();
                                if (!$item_result['success']) {
                                    $this->db->rollback();
                                    $return = [
                                        'success' => false,
                                        'msg' => 'DATA_CREATE_FAIL_TEXT',
                                        'data' => $item_result,
                                        'raw' => $need_form_gabarit_item
                                    ];
                                    goto end_of_function;
                                }
                                $i++;
                            }
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


                //save to table need_form_gabarit_service_company
                $service_companies = Helpers::__getRequestValue("service_company_id");
                if (count($service_companies) > 0) {
                    foreach ($service_companies as $service_company) {
                        $need_form_gabarit_service_company = new NeedFormGabaritServiceCompany();
                        $need_form_gabarit_service_company->setNeedFormGabaritId($need_assessment->getId());
                        $need_form_gabarit_service_company->setServiceCompanyId($service_company);
                        $need_form_gabarit_service_company_save = $need_form_gabarit_service_company->__quickSave();
                        if (!$need_form_gabarit_service_company_save['success']) {
                            $this->db->rollback();
                            $return = [
                                'success' => false,
                                'message' => 'DATA_CREATE_FAIL_TEXT',
                                'data' => $need_form_gabarit_service_company_save
                            ];
                            goto end_of_function;
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
                        $resultSave = $newSectionItem->__quickCreate();
                        if ($resultSave['success'] == false) {
                            $this->db->rollback();
                            $return = $resultSave;
                            goto end_of_function;
                        }
                        $items = NeedFormGabaritItem::find(['conditions' => 'need_form_gabarit_section_id=:id:',
                            'bind' => [
                                'id' => $section->getId()
                            ],
                            'order' => 'position ASC']);
                        if (count($items) > 0) {
                            foreach ($items as $item) {
                                $itemArray = $item->toArray();
                                unset($itemArray['id']);
                                $newItem = \Phalcon\Mvc\Model::cloneResult(new NeedFormGabaritItem(), $itemArray);
                                $newItem->setNeedFormGabaritSectionId($newSectionItem->getId());
                                $newItemSave = $newItem->__quickCreate();
                                if ($newItemSave['success'] == false) {
                                    $this->db->rollback();
                                    $return = $newItemSave;
                                    goto end_of_function;
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
                $needFormGabarit = $needFormRequest->getNeedFormGabarit();
                if ($needFormGabarit) {
                    $formBuilder = $needFormRequest->getFormBuilderStructure();
                    $result = [
                        'success' => true,
                        'data' => $needFormRequest,
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

    //test new
}
