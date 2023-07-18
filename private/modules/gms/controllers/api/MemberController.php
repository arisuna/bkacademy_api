<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Http\Client\Provider\Exception;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\DynamoHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Models\UserProfileExt;
use Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Controllers\API\BaseController as BaseController;
use Reloday\Gms\Models\Assignment;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\Dependant;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\RelocationServiceCompany;
use Reloday\Gms\Models\Task;
use \Reloday\Gms\Models\HistoryOld;
use \Reloday\Gms\Models\HistoryNotification;
use Reloday\Gms\Models\Timelog;
use Reloday\Gms\Models\UserProfile;
use Reloday\Gms\Models\DataUserMember;
use Reloday\Gms\Models\ObjectMap;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Module;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class MemberController extends BaseController
{
    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getMembersByCompanyAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();

        $objectUuid = Helpers::__getRequestValue('uuid');
        $companyId = Helpers::__getRequestValue('companyId');
        $isOwner = Helpers::__getRequestValue('isOwner');
        $isReporter = Helpers::__getRequestValue('isReporter');
        $isViewer = Helpers::__getRequestValue('isViewer');
        $getAll = Helpers::__getRequestValue('getAll');
        $return = ['success' => true, 'data' => []];

        if ($companyId > 0) {
            $company = Company::findFirstById($companyId);
            if ($company && $company->hasActiveRelation(ModuleModel::$company->getId())) {
                //continue
            } else {
                $return = ['success' => false, 'message' => 'COMPANY_NOT_FOUND_TEXT'];
                goto end_of_function;
            }
        } else {
            $return = ['success' => false, 'message' => 'COMPANY_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        if ($objectUuid != '' && Helpers::__isValidUuid($objectUuid)) {
            if ($isOwner) {
                $profile = DataUserMember::getDataOwner($objectUuid, $companyId);
            } elseif ($isReporter) {
                $profile = DataUserMember::getDataReporter($objectUuid, $companyId);
            } elseif ($isViewer) {
                $profiles = DataUserMember::__getDataViewers($objectUuid, $companyId);
            } elseif ($getAll) {
                $profiles = DataUserMember::getMembersObject($objectUuid, $companyId);
            }
            if (isset($profile) && $profile) {
                $item = $profile->toArray();
                $item['company_name'] = ($profile->getCompany()) ? $profile->getCompany()->getName() : null;
                $return = [
                    'success' => true,
                    'data' => [$item]
                ];
            } elseif (isset($profiles) && $profiles) {
                $return = [
                    'success' => true,
                    'data' => $profiles
                ];
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $user_profile_uuid
     */
    public function checkApplicationAction($user_profile_uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $return = [
            'success' => false,
            'hasApplication' => false
        ];

        if ($user_profile_uuid && Helpers::__isValidUuid($user_profile_uuid)) {
            $userProfile = UserProfile::findFirstByUuidCache($user_profile_uuid);
            if (!$userProfile) {
                $userProfile = Employee::findFirstByUuidCache($user_profile_uuid);
            }
        }

        if (isset($userProfile) && $userProfile && ($userProfile->belongsToGms() || $userProfile->manageByGms())) {
            $return = [
                'success' => true,
                'hasApplication' => $userProfile->getCompany() && $userProfile->getCompany()->hasApplication() ? true : ($userProfile instanceof Employee ? true : false),
                'company' => $userProfile->getCompany()
            ];
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getProfileAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $return = ['success' => false];

        $userProfileId = Helpers::__getRequestValue('profileId');
        $userProfileUuid = Helpers::__getRequestValue('profileUuid');
        $userProfileType = Helpers::__getRequestValue('profileType');
        $userProfile = false;

        if ($userProfileId > 0) {
            if ($userProfileType == "member") {
                $userProfile = UserProfile::findFirstByIdCache($userProfileId);
            } elseif ($userProfileType == "employee") {
                $userProfile = Employee::findFirstByIdCache($userProfileId);
            } elseif ($userProfileType == "dependant") {
                $userProfile = Dependant::findFirstByIdCache($userProfileId);
            } else {
                $userProfile = UserProfile::findFirstByIdCache($userProfileId);
            }
        } elseif ($userProfileUuid != '' && Helpers::__isValidUuid($userProfileUuid)) {
            if ($userProfileType == "member") {
                $userProfile = UserProfile::findFirstByUuidCache($userProfileUuid);
            } elseif ($userProfileType == "employee") {
                $userProfile = Employee::findFirstByUuidCache($userProfileId);
            } elseif ($userProfileType == "dependant") {
                $userProfile = Dependant::findFirstByUuidCache($userProfileId);
            } else {
                $userProfile = UserProfile::findFirstByUuidCache($userProfileUuid);
            }
        }

        $return = [
            'success' => true,
            'data' => [
                'id' => $userProfile ? $userProfile->getId() : '',
                'firstname' => $userProfile ? $userProfile->getFirstname() : '',
                'lastname' => $userProfile ? $userProfile->getLastname() : '',
                'uuid' => $userProfile ? $userProfile->getUuid() : '',
                'email' => $userProfile ? (method_exists($userProfile, 'getEmail') ? $userProfile->getEmail() : (method_exists($userProfile, 'getWorkemail') ? $userProfile->getWorkemail() : (
    method_exists($userProfile, 'getPrincipalEmail') ? $userProfile->getPrincipalEmail() : ''))) : ''
            ]
        ];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * HR MEMBERS ONLY
     * [managerAction description]
     * @param  [type] $company_id [description]
     * @return [type]             [description]
     */
    public function getHrMembersAction($company_id = '')
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $contacts = UserProfile::getHrMembers($company_id);
        $this->response->setJsonContent(['success' => true, 'message' => '', 'data' => $contacts['data']]);
        return $this->response->send();
    }


    /**
     * get GMS contact Only
     * @return mixed
     */
    public function getGmsMembersAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $contacts = UserProfile::getGmsWorkers();
        $this->response->setJsonContent(['success' => true, 'message' => '', 'data' => $contacts]);
        return $this->response->send();
    }


    /**
     * get GMS contact Only
     * @return mixed
     */
    public function getGmsContactsForTimelogAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $contacts = UserProfile::getGmsWorkersForTimelog();
        $this->response->setJsonContent(['success' => true, 'message' => '', 'data' => $contacts]);
        return $this->response->send();
    }


    /**
     * HR MANAGER + GMS MANAGER
     * [managerAction description]
     * @param  [type] $company_id [description]
     * @return [type]             [description]
     */
    public function getManagersAction($company_id = '')
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $contacts = UserProfile::getManagers($company_id);
        $this->response->setJsonContent(['success' => true, 'message' => '', 'data' => $contacts]);
        return $this->response->send();
    }


}
