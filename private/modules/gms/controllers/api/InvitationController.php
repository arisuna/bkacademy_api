<?php

namespace Reloday\Gms\Controllers\API;

use Elasticsearch\Endpoints\Cat\Help;
use Phalcon\Test\Mvc\Model\Behavior\Helper;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Application\Lib\RelodayUrlHelper;
use Reloday\Application\Models\ApplicationModel;
use Reloday\Application\Models\CompanyTypeExt;
use \Reloday\Gms\Controllers\ModuleApiController;

use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Help\InvitationHelper;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\CompanyType;
use Reloday\Gms\Models\Contract;
use Reloday\Gms\Models\ContractPermission;
use Reloday\Gms\Models\ContractPermissionItem;
use Reloday\Gms\Models\EmailTemplateDefault;
use Reloday\Gms\Models\HrCompany;
use Reloday\Gms\Models\InvitationRequest;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\UserProfile;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class InvitationController extends BaseController
{
    /**
     * @Route("/guide", paths={module="gms"}, methods={"GET"}, name="gms-guide-index")
     */
    public function getMyInvitationsAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAcl('index', 'invitation');
        $params = [];
        $params["from_company_id"] = ModuleModel::$company->getId();
        $params["direction"] = InvitationRequest::DIRECTION_FROM_DSP_TO_HR;
        $invitations = InvitationRequest::__findWithFilter($params);
        $received_invitations = InvitationRequest::__getReceivedInvitations();
        $return = [
            "success" => true,
            "data" => $invitations["data"],
            "received_invitations" => $received_invitations["data"]
        ];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', 'invitation');

        $data = Helpers::__getRequestValuesArray();
        $toCompanyId = Helpers::__getRequestValue('to_company_id');
        $email = Helpers::__getRequestValue('email');

        if (!Helpers::__isEmail($email)) {
            $result = ['success' => false, 'message' => 'EMAIL_NOT_VALID_TEXT'];
            goto end_of_function;
        }

        if (InvitationHelper::__canInviteHrToCreateFromEmail($email) == false) {
            $result = ['success' => false, 'message' => 'INVITE_FAIL_HR_ACCOUNT_EXISTED_TEXT'];
            goto end_of_function;
        }
        //Check email hr company created by dsp
        $email = Helpers::__getRequestValue('email');
        $existedCompany = Company::findFirst([
            'conditions' => 'created_by_company_id = :created_by_company_id: and company_type_id = :hr_type: and email = :email: and status = :status:',
            'bind' => [
                'created_by_company_id' => ModuleModel::$company->getId(),
                'hr_type' => Company::TYPE_HR,
                'email' => $email,
                'status' => Company::STATUS_ACTIVATED
            ]
        ]);

        if($existedCompany){
            $result = ['success' => false, 'message' => 'INVITATION_EMAIL_UNIQUE_TEXT'];
            goto end_of_function;
        }

        $invitation = new InvitationRequest();
        $invitation->setUuid(ApplicationModel::uuid());
        $invitation->setData($data);
        $invitation->setFromCompanyId(ModuleModel::$company->getId());
        $invitation->setDirection(InvitationRequest::DIRECTION_FROM_DSP_TO_HR);
        $invitation->setCompanyName(Helpers::__getRequestValue('company_name'));
        $invitation->setFirstname(Helpers::__getRequestValue('firstname'));
        $invitation->setLastname(Helpers::__getRequestValue('lastname'));
        $invitation->setEmail(Helpers::__getRequestValue('email'));
        $invitation->setInviterUserProfileId(ModuleModel::$user_profile->getId());
        if (Helpers::__isValidId(Helpers::__getRequestValue('to_company_id'))) {
            $invitation->setToCompanyId(Helpers::__getRequestValue('to_company_id'));
        }
        $invitation->setWebsite(Helpers::__getRequestValue('website'));
        $invitation->setEmail(Helpers::__getRequestValue('email'));

        $invitation->setStatus(InvitationRequest::STATUS_PENDING);
        $invitation->setIsActive(1);
        $invitation->setIsExecuted(1);
        $invitation->setToken($this->security->hash($invitation->getUuid()));
        $result = $invitation->__quickCreate();

        if ($result['success'] == true) {
            $beanQueue = RelodayQueue::__getQueueSendMail();
            $dataArray = [
                'action' => "sendMail",
                'to' => $invitation->getEmail(),
                'email' => $invitation->getEmail(),
                'language' => ModuleModel::$system_language,
                'templateName' => EmailTemplateDefault::DSP_INVITE_HR_TO_CREATE,
                'params' => [
                    'invitee_name' => $invitation->getFirstname() . " " . $invitation->getLastname(),
                    'inviter_name' => ModuleModel::$user_profile->getFirstname() . " " . ModuleModel::$user_profile->getLastname(),
                    'inviter_company' => ModuleModel::$company->getName(),
                    'url' => RelodayUrlHelper::__getMainUrl() . '/register/#/invitation-request/' . base64_encode($invitation->getToken()),
                    'url_login' => RelodayUrlHelper::__getMainUrl() . '/register/#/invitation-request/' . base64_encode($invitation->getToken()),
                ]
            ];
            $resultCheck = $beanQueue->addQueue($dataArray);
            $result['resultCheck'] = $resultCheck;
        }

        if ($result['success'] == false) {
            $result['message'] = 'SENT_INVITATION_FAIL_TEXT';
        } else {
            $result['message'] = 'SENT_INVITATION_SUCCESS_TEXT';
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function createForExistedHrAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', 'invitation');

        $hr_company_id = Helpers::__getRequestValue('to_company_id');
        $firstname = Helpers::__getRequestValue('firstname');
        $lastname = Helpers::__getRequestValue('lastname');
        $email = Helpers::__getRequestValue('email');
        $hr_company = Company::findFirstById($hr_company_id);

        if (!Helpers::__isEmail($email)) {
            $result = ['success' => false, 'message' => 'EMAIL_NOT_VALID_TEXT'];
            goto end_of_function;
        }

        if (InvitationHelper::__canInviteHrToCreateFromEmail($email) == false) {
            $result = ['success' => false, 'message' => 'INVITE_FAIL_HR_ACCOUNT_EXISTED_TEXT'];
            goto end_of_function;
        }


        if (!$hr_company instanceof Company && $hr_company->getCompanyTypeId() != CompanyType::TYPE_HR) {
            $result = [
                "success" => false,
                "message" => "DATA_INVALID_TEXT",
                "detail" => "company do not exist or not a hr company"
            ];
            goto end_of_function;
        }
        $invitation = new InvitationRequest();
        $invitation->setData();
        $invitation->setToCompanyId($hr_company_id);
        $invitation->setFromCompanyId(ModuleModel::$company->getId());
        $invitation->setCompanyName($hr_company->getName());
        $invitation->setEmail($email);
        $invitation->setWebsite($hr_company->getWebsite());
        $invitation->setFirstname($firstname);
        $invitation->setLastname($lastname);
        $invitation->setDirection(InvitationRequest::DIRECTION_FROM_DSP_TO_HR);
        $invitation->setStatus(InvitationRequest::STATUS_PENDING);
        $invitation->setIsActive(1);
        $invitation->setIsExecuted(1);
        $invitation->setToken($this->security->hash($invitation->getUuid()));
        $invitation->setInviterUserProfileId(ModuleModel::$user_profile->getId());
        $beanQueue = RelodayQueue::__getQueueSendMail();
        $dataArray = [
            'action' => "sendMail",
            'to' => $email,
            'email' => $email,
            'language' => ModuleModel::$system_language,
            'templateName' => EmailTemplateDefault::DSP_INVITE_HR_TO_CREATE,
            'params' => [
                'invitee_name' => $firstname . " " . $lastname,
                'inviter_name' => ModuleModel::$user_profile->getFirstname() . " " . ModuleModel::$user_profile->getLastname(),
                'inviter_company' => ModuleModel::$company->getName(),
                'url' => RelodayUrlHelper::__getMainUrl() . '/register/#/invitation-request/' . base64_encode($invitation->getToken()),
            ]
        ];
        $resultCheck = $beanQueue->addQueue($dataArray);
        $result['resultCheck'] = $resultCheck;

        $result = $invitation->__quickCreate();
        if ($result['success'] == false) {
            $result['message'] = 'SENT_INVITATION_FAIL_TEXT';
        } else {
            $result['message'] = 'SENT_INVITATION_SUCCESS_TEXT';
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function resendAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', 'invitation');

        $id = Helpers::__getRequestValue("invitation_request") ?? Helpers::__getRequestValue("invitation_request_id");

        $invitation = InvitationRequest::findFirstById($id);
        if (!$invitation instanceof InvitationRequest) {
            $result = [
                "success" => false,
                "message" => "INVITATION_NOT_FOUND_TEXT",
                "detail" => "invitation request not exist"
            ];
            goto end_of_function;
        }
        if ($invitation->getStatus() == InvitationRequest::STATUS_SUCCESS) {
            $result = [
                "success" => false,
                "message" => "INVITATION_EXECUTED_TEXT",
                "detail" => "invitation request successed"
            ];
            goto end_of_function;
        }

        if (InvitationHelper::__canInviteHrToCreateFromEmail($invitation->getEmail()) == false) {
            $result = [
                "success" => false,
                "message" => "INVITE_FAIL_HR_ACCOUNT_EXISTED_TEXT",
                "detail" => "invitation request successed"
            ];
            goto end_of_function;
        }

//        $to_company = $invitation->getToCompany();
//        if ($to_company instanceof Company) {
//            $invitation->setCompanyName($to_company->getName());
//            $invitation->setEmail($to_company->getEmail());
//            $invitation->setWebsite($to_company->getWebsite());
//            $result = $invitation->__quickUpdate();
//            if ($result['success'] == false) {
//                $result['message'] = 'SENT_INVITATION_FAIL_TEXT';
//                goto end_of_function;
//            }
//        }

        $beanQueue = RelodayQueue::__getQueueSendMail();
        $dataArray = [
            'action' => "sendMail",
            'to' => $invitation->getEmail(),
            'email' => $invitation->getEmail(),
            'language' => ModuleModel::$system_language,
            'templateName' => EmailTemplateDefault::DSP_INVITE_HR_TO_CREATE,
            'params' => [
                'invitee_name' => $invitation->getFirstname() . " " . $invitation->getLastname(),
                'inviter_name' => ModuleModel::$user_profile->getFirstname() . " " . ModuleModel::$user_profile->getLastname(),
                'inviter_company' => ModuleModel::$company->getName(),
                'message' => "",
                'url_login' => RelodayUrlHelper::__getMainUrl() . '/register/#/invitation-request/' . base64_encode($invitation->getToken()),
                'url' => RelodayUrlHelper::__getMainUrl() . '/register/#/invitation-request/' . base64_encode($invitation->getToken()),
            ]
        ];
        $resultCheck = $beanQueue->addQueue($dataArray);
        $result['resultCheck'] = $resultCheck;
        $result['success'] = true;
        $result['message'] = 'SENT_INVITATION_SUCCESS_TEXT';

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function detailAction($id)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAcl('index', 'invitation');

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if (Helpers::__isValidId($id) == false) {
            goto end_of_function;
        }

        $guide = InvitationRequest::findFirstById($id);
        if ($guide && $guide->belongsToCompany() && $guide->isArchived() == false) {
            $guideArray = $guide->toArray();
            $guideArray['tagsList'] = $guide->getTagsObjectList();
            $result = ['success' => true, 'data' => $guideArray];
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function deleteAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAcl('index', 'invitation');
        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if (Helpers::__isValidUuid($uuid) == false) {
            goto end_of_function;
        }
        $request = InvitationRequest::findFirstByUuid($uuid);
        if ($request && $request->belongsToCompany() && $request->canDelete() == true) {
            $result = $request->__quickRemove();
            if ($result['success'] == false) {
                $result['message'] = 'REMOVE_INVITATION_REQUEST_FAIL_TEXT';
            } else {
                $result['message'] = 'REMOVE_INVITATION_REQUEST_SUCCESS_TEXT';
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $id
     */
    public function editAction($id)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAcl('index', 'invitation');

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if (Helpers::__isValidId($id) == false) {
            goto end_of_function;
        }
        $guide = InvitationRequest::findFirstById($id);
        if ($guide && $guide->belongsToCompany()) {

            $data = Helpers::__getRequestValuesArray();
            $data['company_id'] = ModuleModel::$company->getId();
            $guide->setData($data);

            $result = $guide->__quickUpdate();
            if ($result['success'] == false) {
                $result['message'] = 'UPDATE_INVITATION_FAIL_TEXT';
            } else {
                $result['message'] = 'UPDATE_INVITATION_SUCCESS_TEXT';
            }
        }

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function acceptAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', 'invitation');

        $id = Helpers::__getRequestValue("invitation_request") ?? Helpers::__getRequestValue("invitation_request_id");
        $invitation = InvitationRequest::findFirstById($id);
        if (!$invitation instanceof InvitationRequest) {
            $result = [
                "success" => false,
                "message" => "DATA_INVALID_TEXT",
                "detail" => "invitation request not exist"
            ];
            goto end_of_function;
        }
        if ($invitation->getStatus() == InvitationRequest::STATUS_SUCCESS) {
            $result = [
                "success" => false,
                "message" => "DATA_INVALID_TEXT",
                "detail" => "invitation request accepted"
            ];
            goto end_of_function;
        }

        if ($invitation->getToCompanyId() != ModuleModel::$company->getId()) {
            $result = [
                "success" => false,
                "message" => "DATA_INVALID_TEXT",
                "detail" => "you can not accept an invitation request which is not belonged to you"
            ];
            goto end_of_function;
        }

        $this->db->begin();
        $contract = new Contract();
        $contract->setFromCompanyId($invitation->getFromCompanyId());
        $contract->setStartDate(time());
        $contract->setToCompanyId(ModuleModel::$company->getId());
        $contract->setName($invitation->getCompanyName() . " - " . ModuleModel::$company->getName() . " - " . date('Ymd'));
        $contract->setStatus(Contract::STATUS_ACTIVATED);
        $rest = $contract->__quickCreate();
        if ($rest['success'] == false) {
            $this->db->rollback();
            $result = [
                'success' => false,
                'message' => "ERROR_CONTRACT_TEXT",
                'detail' => $rest,
            ];
            goto end_of_function;

        }

        // ModuleModel::$contract = $contract;
        // ModuleModel::$hrCompany = $invitation->getFromCompany();

        $invitation->setStatus(InvitationRequest::STATUS_SUCCESS);
        $invitation->setIsExecuted(1);

        $resultInvitationRequestUpdate = $invitation->__quickUpdate();

        if ($resultInvitationRequestUpdate['success'] == false) {
            $this->db->rollback(); //rollback
            $result = [
                'success' => false,
                'message' => "ERROR_UPDATE_DATA_TEXT",
                'detail' => $resultInvitationRequestUpdate['details'],
            ];
            goto end_of_function;
        }
        //init contract permission

        $contractPermissionItems = ContractPermissionItem::find();

        if(count($contractPermissionItems) > 0){
            foreach ($contractPermissionItems as $permission){
                $newLinked = new ContractPermission();
                $newLinked->setContractId($contract->getId());
                $newLinked->setContractPermissionItemId($permission->getId());
                $newLinked->setController($permission->getController());
                $newLinked->setAction($permission->getAction());
                $newLinked->setCreatorUserProfileId(ModuleModel::$user_profile->getId());
                $creat_permission =  $newLinked->__quickCreate();
                if ($creat_permission['success'] == false) {
                    $this->db->rollback(); //rollback
                    $result = [
                        'success' => false,
                        'message' => "ERROR_CONTRACT_TEXT",
                        'detail' => $creat_permission,
                    ];
                    goto end_of_function;
                }
            }
        }

        $result['success'] = true;
        $result['message'] = 'ACCEPT_INVITATION_SUCCESS_TEXT';
        //--------------------------send mail about invitation ------------------------
        $beanQueue = new RelodayQueue(getenv('QUEUE_SEND_MAIL'));
        $inviter = UserProfile::findFirstByIdCache($invitation->getInviterUserProfileId());
        $resultCheck1 = "";
        if ($inviter instanceof UserProfile) {
            $dataArray1 = [
                'action' => "sendMail",
                'to' => $inviter->getWorkemail(),
                'email' => $inviter->getWorkemail(),
                'language' => $inviter->getLanguage(),
                'templateName' => EmailTemplateDefault::INVITATION_CONNECTION_ACCEPTED,
                'params' => [
                    'inviter_name' => $inviter->getFirstname() . " " . $inviter->getLastname(),
                    'inviter_company' => $inviter->getCompanyName(),
                    'company_name' => ModuleModel::$company->getName(),
                    'url' => $invitation->getDirection() == RelodayUrlHelper::__getMainUrl() . 'hr/#/app/providers/invitations',
                ]
            ];
            $resultCheck1 = $beanQueue->addQueue($dataArray1);
        }
        $result["resultCheck1"] = $resultCheck1;
        $this->dispatcher->setParam('return', $return);

        $this->db->commit();
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function denyAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', 'invitation');

        $id = Helpers::__getRequestValue("invitation_request") ?? Helpers::__getRequestValue("invitation_request_id");
        $invitation = InvitationRequest::findFirstById($id);
        if (!$invitation instanceof InvitationRequest) {
            $result = [
                "success" => false,
                "message" => "DATA_INVALID_TEXT",
                "detail" => "invitation request not exist"
            ];
            goto end_of_function;
        }
        if ($invitation->getStatus() == InvitationRequest::STATUS_SUCCESS) {
            $result = [
                "success" => false,
                "message" => "DATA_INVALID_TEXT",
                "detail" => "invitation request accepted"
            ];
            goto end_of_function;
        }

        if ($invitation->getToCompanyId() != ModuleModel::$company->getId()) {
            $result = [
                "success" => false,
                "message" => "DATA_INVALID_TEXT",
                "detail" => "you can not accept an invitation request which is not belonged to you"
            ];
            goto end_of_function;
        }

        $invitation->setStatus(InvitationRequest::STATUS_DENIED);
        $invitation->setIsExecuted(1);

        $resultInvitationRequestUpdate = $invitation->__quickUpdate();

        if ($resultInvitationRequestUpdate['success'] == false) {
            $result = [
                'success' => false,
                'message' => "ERROR_UPDATE_DATA_TEXT",
                'detail' => $resultInvitationRequestUpdate['details'],
            ];
        }
        $result['success'] = true;
        $result['message'] = 'DENIED_INVITATION_SUCCESS_TEXT';
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     *
     */
    public function verifyAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAcl('index', 'invitation');

        $return = ['success' => true];
        $toCompanyId = Helpers::__getRequestValue('to_company_id');
        $email = Helpers::__getRequestValue('email');

        if (Helpers::__isValidId($toCompanyId)) {
            $company = HrCompany::findFirstById($toCompanyId);
        }
        if (Helpers::__isEmail($email)) {
            $userProfile = UserProfile::findFirstByWorkemailCache($email);
            if ($userProfile instanceof UserProfile) {
                $return['user_profile'] = $userProfile;


                $targetCompany = $userProfile->getCompany();
                if ($targetCompany && $targetCompany->isHr() && $targetCompany->hasSubscription() == false) {
                    $return['company'] = $targetCompany;
                }
            }
        }

        $invitation = InvitationRequest::__findActiveInvitationSentByEmail($email);
        if ($invitation) {
            $return['invitation'] = $invitation;
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

}
