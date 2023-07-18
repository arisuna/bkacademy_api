<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Http\Client\Provider\Exception;
use Reloday\Application\Lib\HistoryModel;
use Reloday\Application\Lib\RelodayDynamoORM;
use Reloday\Application\Models\EmployeeExt;
use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Help\NotificationServiceHelper;
use Reloday\Gms\Models\History;
use Reloday\Gms\Models\HistoryOld;
use Reloday\Gms\Models\HistoryAction;
use Reloday\Gms\Models\ModuleModel;
use \Reloday\Gms\Models\NeedFormGabaritSection;
use \Reloday\Gms\Models\NeedFormGabaritItem;
use \Reloday\Gms\Models\NeedFormRequest;
use \Reloday\Gms\Models\NeedFormRequestAnswer;
use \Reloday\Application\Lib\Helpers;
use \Reloday\Gms\Models\EmailTemplateDefault;
use \Reloday\Gms\Models\SupportedLanguage;
use \Reloday\Application\Lib\RelodayQueue;
use Reloday\Needform\Models\NeedFormGabaritRequestAnswer;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class NeedformController extends ModuleApiController
{
    /**
     * @Route("/needform", paths={module="gms"}, methods={"GET"}, name="gms-needform-index")
     */
    public function itemAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $result = [
            'success' => false,
            'message' => 'NEED_ASSESSMENT_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $need_assessment = NeedFormRequest::findFirstByUuid($uuid);

            if ($need_assessment instanceof NeedFormRequest) {
                // Load need assessment items
                $need_form_gabarit = $need_assessment->getNeedFormGabarit();

                if ($need_form_gabarit) {

                    // Load need assessment sections
                    $sections = NeedFormGabaritSection::find([
                        'conditions' => 'need_form_gabarit_id=:id:',
                        'bind' => [
                            'id' => $need_form_gabarit->getId()
                        ],
                        'order' => 'position ASC']);
                    $formBuilder = [];
                    foreach ($sections as $key => $section) {
                        $formBuilder[$key] = $section->toArray();
                        $formBuilder[$key]['index'] = (int)$key;
                        $formBuilder[$key]['items'] = [];
//                        $items = NeedFormGabaritItem::find(['conditions' => 'need_form_gabarit_section_id=:id:',
//                            'bind' => [
//                                'id' => $section->getId()
//                            ],
//                            'order' => 'position ASC']);

                        $items = $section->getDetailSectionContentMappingWithRequest($need_assessment);

                        $need_assessment_array['formBuilder'][$key]['items'] = $items;
                        $formBuilder[$key]['items'] = $items;
//                        $i = 0;
//                        foreach ($items as $item) {
//                            $formBuilder[$key]['items'][$i] = $item;
//                            $i++;
//                        }
                    }
                    $request = $need_assessment->toArray();
                    $request['editable'] = $need_assessment->getStatus() == NeedFormRequest::STATUS_SENT ||
                    $need_assessment->getStatus() == NeedFormRequest::STATUS_NOT_SENT ||
                    $need_assessment->getStatus() == NeedFormRequest::STATUS_ACTIVE ||
                    $need_assessment->getStatus() == NeedFormRequest::STATUS_READ ? true : false;

                    $profile = $need_assessment->getEmployee();
                    $profileArray = $profile->toArray();
                    $profileArray['avatar_url'] = $profile->getAvatarUrl();
                    $profileArray['company_name'] = $profile->getCompany() ? $profile->getCompany()->getName() : '';


                    $need_form_gabarit_array = $need_form_gabarit->toArray();
                    $need_form_gabarit_array['company_name'] = $need_form_gabarit->getCompany() ? $need_form_gabarit->getCompany()->getName() : '';

                    $result = [
                        'success' => true,
                        'data' => $request,
                        'questions' => isset($items) ? $items : [],
                        'profile' => $profileArray,
                        'formBuilder' => $formBuilder,
                        'needForm' => $need_form_gabarit_array,
                    ];
                }
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
     * @param $uuid
     */
    public function saveItemAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $dataArray = $this->request->getJsonRawBody(true);
        $return = [
            'success' => false,
            'message' => 'NEED_ASSESSMENT_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $need_assessment = NeedFormRequest::findFirstByUuid($uuid);
            if ($need_assessment instanceof NeedFormRequest) {
                // Load need assessment items
                $need_form_gabarit = $need_assessment->getNeedFormGabarit();

                ModuleModel::$company = $need_assessment->getOwnerCompany();

                if ($need_form_gabarit) {
                    //$request = $need_assessment->toArray();
                    $sections = Helpers::__getRequestValue("questions");
                    if ($need_assessment->isEditable() == true) {
                        $need_assessment->setStatus(NeedFormRequest::STATUS_ANSWERED);
                        $resultAssessement = $need_assessment->__quickUpdate();
                        if ($resultAssessement['success'] == true) {

                            $request = $need_assessment->toArray();
//                            $resultNotification = $this->sendHistoryReplyItemsToOwner($need_assessment);
//                            $resultNotificationReporter = $this->sendHistoryReplyItemsToReporter($need_assessment);
                            $employeeProfile = $need_assessment->getEmployee();
                            $resultNotification = $this->assigneeAddNotification([
                                'uuid' => $need_assessment->getUuid(),
                                'object_uuid' => $need_assessment->getUuid(),
                                'type' => HistoryModel::TYPE_NEEDS_ASSESSMENT,
                                'action' => HistoryModel::QUESTIONNAIRE_ANSWERED,
                                'need_assessment_name' => $need_assessment ? $need_assessment->getFormName() : '',
                                'creator_user_profile_uuid' => $employeeProfile->getUuid(),
                                'creator_company_id' => $employeeProfile->getCompanyId(),
                                'language' => SupportedLanguage::LANG_EN
                            ]);



                            $resultHistoryOfObject = $this->sendHistoryOfObject($need_assessment);
                            $request['editable'] = false;
                            $return = [
                                'success' => true,
                                'data' => $request,
                                'message' => 'NEED_ASSESSMENT_SAVE_SUCCESS_TEXT',
                                'notification' => $resultNotification,
                            ];

                            if($need_assessment->getStatus() == NeedFormRequest::STATUS_ANSWERED){
                                $resultQueue = $need_assessment->sendToQuestionnaireServiceFieldQueue();
                                $return['resultQueue'] = $resultQueue;
                            }


                        } else {
                            $this->db->rollback();
                            $return = [
                                'success' => false,
                                'message' => 'NEED_ASSESSMENT_FAIL_TEXT',
                                'detail' => $resultAssessement
                            ];
                        }
                    } else {
                        $return = [
                            'success' => false,
                            'message' => 'NEED_ASSESSMENT_ALREADY_ANSWERED_TEXT',
                        ];
                    }
                }
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function saveItemOldAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $dataArray = $this->request->getJsonRawBody(true);
        $return = [
            'success' => false,
            'message' => 'NEED_ASSESSMENT_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $need_assessment = NeedFormRequest::findFirstByUuid($uuid);
            if ($need_assessment instanceof NeedFormRequest) {
                // Load need assessment items
                $need_form_gabarit = $need_assessment->getNeedFormGabarit();

                ModuleModel::$company = $need_assessment->getOwnerCompany();

                if ($need_form_gabarit) {
                    //$request = $need_assessment->toArray();
                    $sections = Helpers::__getRequestValue("questions");
                    if ($need_assessment->isEditable() == true) {
                        if (count($sections) > 0) {
                            $this->db->begin();
                            foreach ($sections as $section) {
                                $questions = $section->items;
                                if (count($questions) > 0) {

                                    foreach ($questions as $question) {
                                        $needFormGabaritItem = NeedFormGabaritItem::findFirstById($question->id);
                                        if ($needFormGabaritItem) {
                                            $needFormGabaritAnswer = NeedFormRequestAnswer::findFirst([
                                                'conditions' => 'need_form_gabarit_item_id = :need_form_gabarit_item_id: AND need_form_request_id = :need_form_request_id:',
                                                'bind' => [
                                                    'need_form_gabarit_item_id' => $needFormGabaritItem->getId(),
                                                    'need_form_request_id' => $need_assessment->getId(),
                                                ]
                                            ]);

                                            if (!$needFormGabaritAnswer) {
                                                $needFormGabaritAnswer = new NeedFormRequestAnswer();
                                            }

                                            $value = "";
                                            if (isset($question->value)) {
                                                if (is_array($question->value)) {
                                                    $value = json_encode($question->value);
                                                } else {
                                                    $value = $question->value;

                                                    if (is_string($question->value)) {
                                                        if ($needFormGabaritItem->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_DATE) {
                                                            $value = strtotime($question->value);
                                                            //take only date
                                                        }

                                                        if ($needFormGabaritItem->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_TIME) {
                                                            $value = strtotime($question->value);
                                                            //take only hour
                                                        }
                                                    }
                                                }
                                            } else if ($needFormGabaritItem->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_MULTIPLE_OPTION ||
                                                $needFormGabaritItem->getAnswerFormat() == NeedFormGabaritItem::ANSWER_FORMAT_MATRIX) {
                                                $value = json_encode($question->options);
                                            }
                                            if (isset($question->content)) {
                                                $needFormGabaritAnswer->setContent($question->content);
                                            }

                                            if (is_array($value) && count($value) == 0) {
                                                $value = json_encode([]);
                                            }
                                            $resultAnswer = $needFormGabaritAnswer->__save([
                                                'need_form_gabarit_item_id' => $needFormGabaritItem->getId(),
                                                'need_form_request_id' => $need_assessment->getId(),
                                                'need_form_gabarit_id' => $need_assessment->getNeedFormGabaritId(),
                                                'user_profile_uuid' => $need_assessment->getUserProfileUuid(),
                                                'answer' => $value
                                            ]);

                                            if (!$resultAnswer instanceof NeedFormRequestAnswer) {
                                                $this->db->rollback;
                                                $return = $resultAnswer;
                                                goto end_of_function;
                                            }
                                        }
                                    }

                                }
                            }
                            $need_assessment->setStatus(NeedFormRequest::STATUS_ANSWERED);
                            $resultAssessement = $need_assessment->__quickUpdate();
                            if ($resultAssessement['success'] == true) {
                                $this->db->commit();
                                $request = $need_assessment->toArray();
//                                $resultNotification = $this->sendHistoryReplyItemsToOwner($need_assessment);
//                                $resultNotificationReporter = $this->sendHistoryReplyItemsToReporter($need_assessment);
                                $employeeProfile = $need_assessment->getEmployee();
                                $resultNotification = $this->assigneeAddNotification([
                                    'uuid' => $need_assessment->getUuid(),
                                    'object_uuid' => $need_assessment->getUuid(),
                                    'type' => HistoryModel::TYPE_NEEDS_ASSESSMENT,
                                    'action' => HistoryModel::QUESTIONNAIRE_ANSWERED,
                                    'need_assessment_name' => $need_assessment ? $need_assessment->getFormName() : '',
                                    'creator_user_profile_uuid' => $employeeProfile->getUuid(),
                                    'creator_company_id' => $employeeProfile->getCompanyId(),
                                    'language' => SupportedLanguage::LANG_EN
                                ]);
                                $resultHistoryOfObject = $this->sendHistoryOfObject($need_assessment);
                                $request['editable'] = false;
                                $return = [
                                    'success' => true,
                                    'data' => $request,
                                    'message' => 'NEED_ASSESSMENT_SAVE_SUCCESS_TEXT',
                                    'notification' => $resultNotification,
                                ];
                            } else {
                                $this->db->rollback();
                                $return = [
                                    'success' => false,
                                    'message' => 'NEED_ASSESSMENT_FAIL_TEXT',
                                    'detail' => $resultAssessement
                                ];
                            }
                        }
                    } else {
                        $return = [
                            'success' => false,
                            'message' => 'NEED_ASSESSMENT_ALREADY_ANSWERED_TEXT',
                        ];
                    }
                }
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     *
     */
    public function sendHistoryReplyItems($needFormRequest)
    {
        $employeeProfile = $needFormRequest->getEmployee();
        $relocation_service_company = $needFormRequest->getRelocationServiceCompany();
        if ($relocation_service_company) {
            $viewers = $relocation_service_company->getMembers();

            //var_dump( $viewers ); die();

            if (is_object($viewers) && $viewers->count()) {
                $beanQueue = new RelodayQueue(getenv('QUEUE_SEND_NOTIFICATION'));
                foreach ($viewers as $viewer) {
                    $beanQueue->addQueue([
                        'action' => "sendNotificationMail",
                        'to' => $viewer->getWorkemail(),
                        'templateName' => EmailTemplateDefault::REPLY_NEEDS_ASSESSMENT,
                        'language' => ModuleModel::$system_language,
                        'params' => [
                            'assignee_name' => $employeeProfile->getFirstname() . " " . $employeeProfile->getLastname(),
                            'email' => $viewer->getWorkemail(),
                            'date' => date('Y-m-d H:i:s'),
                            //'url' => $needFormRequest->getFrontendUrl()
                            'need_assessment_name' => $needFormRequest ? $needFormRequest->getFormName() : ''
                        ],
                    ]);
                }
            }
        }
    }

    /**
     * @param $needFormRequest
     * @return array|bool
     */
    public function sendHistoryReplyItemsToOwner($needFormRequest)
    {
        $employeeProfile = $needFormRequest->getEmployee();
        $relocation_service_company = $needFormRequest->getRelocationServiceCompany();
        $owner = $relocation_service_company ? $relocation_service_company->getOwner() : false;
        if (!$owner) {
            $owner = $needFormRequest->getRelocation() ? $needFormRequest->getRelocation()->getOwner() : false;
        }
        if ($owner) {
            $relodayQueue = RelodayQueue::__getQueueSendMail();
            $return = $relodayQueue->addQueue([
                'action' => RelodayQueue::ACTION_SEND_MAIL,
                'to' => $owner->getWorkemail(),
                'templateName' => EmailTemplateDefault::REPLY_NEEDS_ASSESSMENT,
                'language' => ModuleModel::$language,
                'params' => [
                    'assignee_name' => $employeeProfile->getFirstname() . " " . $employeeProfile->getLastname(),
                    'email' => $owner->getWorkemail(),
                    'date' => date('d M Y - H:i:s'),
                    'url' => $needFormRequest->getFrontendUrl(),
                    'need_assessment_name' => $needFormRequest ? $needFormRequest->getFormName() : ''
                ],
            ]);
            return $return;
        } else {
            return false;
        }
    }

    /**
     * @param $needFormRequest
     * @return array|bool
     */
    public function sendHistoryReplyItemsToReporter($needFormRequest)
    {
        $employeeProfile = $needFormRequest->getEmployee();
        $relocation_service_company = $needFormRequest->getRelocationServiceCompany();
        $owner = $relocation_service_company ? $relocation_service_company->getDataReporter() : false;
        if (!$owner) {
            $owner = $needFormRequest->getRelocation() ? $needFormRequest->getRelocation()->getDataReporter() : false;
        }
        if ($owner) {
            $relodayQueue = RelodayQueue::__getQueueSendMail();
            $return = $relodayQueue->addQueue([
                'action' => RelodayQueue::ACTION_SEND_MAIL,
                'to' => $owner->getWorkemail(),
                'templateName' => EmailTemplateDefault::REPLY_NEEDS_ASSESSMENT,
                'language' => ModuleModel::$language,
                'params' => [
                    'assignee_name' => $employeeProfile->getFirstname() . " " . $employeeProfile->getLastname(),
                    'email' => $owner->getWorkemail(),
                    'date' => date('d M Y - H:i:s'),
                    'url' => $needFormRequest->getFrontendUrl(),
                    'need_assessment_name' => $needFormRequest ? $needFormRequest->getFormName() : ''
                ],
            ]);
            return $return;
        } else {
            return false;
        }
    }

    /**
     * @param $needFormRequest
     * @return array|bool
     */
    private function sendHistoryOfObject($needFormRequest)
    {
        $employeeProfile = $needFormRequest->getEmployee();
        $relocation_service_company = $needFormRequest->getRelocationServiceCompany();

        if ($relocation_service_company){
            $params = [];
            $params['questionnaire_name'] = $needFormRequest->getFormName();

            $objectArray = [];
            $objectArray['uuid'] = $relocation_service_company->getUuid();
            $objectArray['frontend_url'] = $relocation_service_company->getSimpleFrontendUrl();
            $objectArray['number'] = $relocation_service_company->getNumber();
            $objectArray['name'] = $relocation_service_company->getServiceCompany()->getName();
            $objectArray['frontend_state'] = $relocation_service_company->getFrontendState();
            $objectArray['object_label'] = "SERVICE_TEXT";

            $employeeArray = $employeeProfile->toArray();
            $employeeArray['fullname'] = $employeeProfile->getFullname();
            $employeeArray['avatarUrl'] = $employeeProfile->getAvatarUrl();

            $actionObject = HistoryAction::__getActionObject('QUESTIONNAIRE_ANSWERED', History::TYPE_SERVICE);

//            RelodayDynamoORM::__init();
//            $historyObject = RelodayDynamoORM::factory('\Reloday\Application\Models\RelodayHistory')->create();
//            $historyObject->id = Helpers::__uuid();
//            $historyObject->type = HistoryOld::TYPE_SERVICE;
//            $historyObject->user_action = $actionObject->getMessage();
//            $historyObject->user_profile_uuid = $employeeProfile->getUuid();
//            $historyObject->user_name = $employeeProfile->getFirstname() . " " . $employeeProfile->getLastname();
//            $historyObject->message = $actionObject->getMessage();
//            $historyObject->object_uuid = $relocation_service_company->getUuid();
//            $historyObject->company_uuid = $needFormRequest->getOwnerCompany() ? $needFormRequest->getOwnerCompany()->getUuid() : null;
//            $historyObject->ip = $this->request->getClientAddress();
//            $historyObject->created_at = time();
//            $historyObject->object = HistoryOld::__parseDynamoParams($objectArray);
//            $historyObject->user_profile = HistoryOld::__parseDynamoParams($employeeArray);
//            $historyObject->params = HistoryOld::__parseDynamoParams($params);


            $historyObject = new History();
            $historyObject->setUuid(Helpers::__uuid());
            $historyObject->setType(History::TYPE_SERVICE);
            $historyObject->setUserAction($actionObject->getMessage());
            $historyObject->setUserProfileUuid($employeeProfile->getUuid());
            $historyObject->setMessage($actionObject->getMessage());
            $historyObject->setObjectUuid($relocation_service_company->getUuid());
            $historyObject->setCompanyUuid($needFormRequest->getOwnerCompany() ? $needFormRequest->getOwnerCompany()->getUuid() : null);
            $historyObject->setIp($this->request->getClientAddress());
            $historyObject->setObject(json_encode($objectArray));
            $historyObject->setParams(json_encode($params));

            $return = $historyObject->__quickCreate();
            return $return;
        }


        return false;
    }

    /**
     * Update Value
     */
    public function updateValueAction(){
        $this->view->disable();
        $this->checkAjaxPost();

        $need_form_request_uuid = Helpers::__getRequestValue('need_form_request_uuid');
        $question = Helpers::__getRequestValuesArray();

        $request_answer_id = Helpers::__getRequestValue('need_form_request_answer_id');
        $value = Helpers::__getRequestValue('value');

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];


        if ($need_form_request_uuid != '' && Helpers::__isValidUuid($need_form_request_uuid)) {

            $needFormRequest = NeedFormRequest::findFirstByUuid($need_form_request_uuid);

            if ($needFormRequest instanceof NeedFormRequest) {
                // Load need assessment items
                $needFormGabarit = $needFormRequest->getNeedFormGabarit();

                ModuleModel::$company = $needFormRequest->getOwnerCompany();
                $needFormGabaritItem = NeedFormGabaritItem::findFirstById($question['id']);

                if ($needFormGabarit) {
                    //$request = $need_assessment->toArray();

                    switch ($needFormGabaritItem->getAnswerFormat()){
                        case NeedFormGabaritItem::ANSWER_FORMAT_MULTIPLE_OPTION:
                            $options = $question['options'];
                            $this->db->begin();
                            $newOptions = [];
                            foreach ($options as $option){

                                if(isset($option['need_form_request_answer_id'])){
                                    $requestAnswer = NeedFormRequestAnswer::findFirstById($option['need_form_request_answer_id']);
                                }else{
                                    $requestAnswer = false;
                                }

                                if($option['selected']){

                                    if(!$requestAnswer){
                                        $item = NeedFormGabaritItem::findFirstById($option['id']);
                                        if(!$item){
                                            $this->db->rollback();
                                            goto end_of_function;
                                        }

                                        $requestAnswer = new NeedFormRequestAnswer();
                                        $requestAnswer->setNeedFormGabaritId($needFormRequest->getNeedFormGabaritId());
                                        $requestAnswer->setNeedFormRequestId($needFormRequest->getId());
                                        $requestAnswer->setUserProfileUuid($needFormRequest->getUserProfileUuid());
                                        $requestAnswer->setNeedFormGabaritItemId($option['id']);

                                        $requestAnswer->setAnswer($option['value']);

                                        if($value === NeedFormGabaritItem::OTHER_TYPE){
                                            $requestAnswer->setContent(isset($option['content']) ? $option['content'] : '');
                                        }

                                        $return = $requestAnswer->__quickCreate();


                                        if(!$return['success']){
                                            $this->db->rollback();
                                            goto end_of_function;
                                        }

                                        $option['need_form_request_answer_id'] = $requestAnswer->getId();
                                    }else{
                                        if($requestAnswer->getAnswer() == NeedFormGabaritItem::OTHER_TYPE){

                                            $requestAnswer->setContent(isset($option['content']) ? $option['content'] : '');
                                            $return = $requestAnswer->__quickSave();
                                            if(!$return['success']){
                                                $this->db->rollback();
                                                goto end_of_function;
                                            }
                                        }
                                    }

                                }else{
                                    if($requestAnswer){
                                        $return = $requestAnswer->__quickRemove();
                                        if(!$return['success']){
                                            $this->db->rollback();
                                            goto end_of_function;
                                        }
                                        unset($option['need_form_request_answer_id']);

                                    }
                                }

                                $newOptions[] = $option;
                            }
                            $return['data'] = $newOptions;
                            $this->db->commit();

                            break;
                        case NeedFormGabaritItem::ANSWER_FORMAT_SINGLE_OPTION:
                        case NeedFormGabaritItem::ANSWER_FORMAT_DROPDOWN_LIST:
                            $isNew = false;
                            if($request_answer_id){
                                $requestAnswer = NeedFormRequestAnswer::findFirstById($request_answer_id);
                            }else{
                                $requestAnswer = new NeedFormRequestAnswer();
                                $isNew = true;
                            }

                            $requestAnswer->setNeedFormGabaritItemId($question['id']);
//                            $requestAnswer->setChildrenNeedFormGabaritItemId($question['sub_item_id']);
                            $requestAnswer->setAnswer($value);

                            if($value === NeedFormGabaritItem::OTHER_TYPE){
                                $requestAnswer->setContent(isset($question['content']) ? $question['content'] : '');
                            }

                            $requestAnswer->setNeedFormGabaritId($needFormRequest->getNeedFormGabaritId());
                            $requestAnswer->setNeedFormRequestId($needFormRequest->getId());
                            $requestAnswer->setUserProfileUuid($needFormRequest->getUserProfileUuid());

                            if($isNew){
                                //Recheck
                                if($requestAnswer->isAlreadyCreated()){
                                    $return = $requestAnswer->__quickUpdate();
                                }else{
                                    $return = $requestAnswer->__quickCreate();
                                }
                            }else{
                                $return = $requestAnswer->__quickSave();
                            }

                            if(!$return['success']){
                                goto end_of_function;
                            }
                            break;
                        default:
                            $isNew = false;
                            if($request_answer_id){
                                $requestAnswer = NeedFormRequestAnswer::findFirstById($request_answer_id);
                            }else{
                                $requestAnswer = new NeedFormRequestAnswer();
                                $isNew = true;
                            }
                            $requestAnswer->setNeedFormGabaritItemId($question['id']);
                            $requestAnswer->setAnswer($value);

                            $requestAnswer->setNeedFormGabaritId($needFormRequest->getNeedFormGabaritId());
                            $requestAnswer->setNeedFormRequestId($needFormRequest->getId());
                            $requestAnswer->setUserProfileUuid($needFormRequest->getUserProfileUuid());

                            if($isNew){
                                //Recheck
                                if($requestAnswer->isAlreadyCreated()){
                                    $return = $requestAnswer->__quickUpdate();
                                }else{
                                    $return = $requestAnswer->__quickCreate();
                                }
                            }else{
                                $return = $requestAnswer->__quickSave();
                            }

                            if(!$return['success']){
                                goto end_of_function;
                            }

                            break;
                    }


                }
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * @param $data
     * @return mixed
     */
    private function assigneeAddNotification($data){

        $queueSendMail = RelodayQueue::__getQueueSendNotification();
        $dataParams = [
            'sender_name' => 'Notification',
            'root_company_id' => isset($data['creator_company_id']) ? $data['creator_company_id'] : null,
            'action' => RelodayQueue::ACTION_ASSIGNEE_SEND_NOTIFICATION_TO_ACCOUNT,
            'language' => $data['language'],
            'params' => $data,
        ];
        $resultQueue = $queueSendMail->addQueue($dataParams);
        return ['success' => true, '$resultQueue' => $resultQueue];
    }
}
