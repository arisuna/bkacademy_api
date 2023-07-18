<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\Helpers;
use Reloday\Application\Models\UserGroupExt;
use Reloday\Gms\Models\Contract;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Office;
use Reloday\Gms\Models\UserGroup;
use Reloday\Gms\Models\UserInContract;
use Reloday\Gms\Models\UserLogin;
use Reloday\Gms\Models\UserProfile;
use Reloday\Application\Models\UserProfileExt;
use Reloday\Gms\Module;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class HrMemberController extends BaseController
{
    /**
     * @Route("/member", paths={module="gms"}, methods={"GET"}, name="gms-member-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        $result = UserProfile::getHrMembersFullInfo([
            'status' => UserProfile::STATUS_ACTIVE
        ]);

        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'data' => $result['data']
            ]);
        } else {
            echo json_encode($result);
        }
    }

    /**
     *
     */
    public function get_rolesAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        // Load list roles
        $roles = UserGroup::find([
            'conditions' => 'type="' . UserGroup::TYPE_HR . '" AND status=' . UserGroup::STATUS_ACTIVATED,
            'order' => 'name'
        ]);
        $this->response->setJsonContent([
            'success' => true,
            'data' => $roles
        ]);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function detailAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
        $result = [
            'success' => false, 'message' => 'USER_PROFILE_NOT_FOUND_TEXT'
        ];
        if ($uuid && Helpers::__isValidUuid($uuid)) {
            $profile = UserProfile::findFirstByUuid($uuid);
        } else if ($uuid > 0) {
            $profile = UserProfile::findFirstById($uuid);
        }
        if ($profile instanceof UserProfile && $profile->manageByGms()) {
            $profileArray = $profile->toArray();
            $profileArray['company_name'] = $profile->getCompanyName();
            $profileArray['role_name'] = $profile->getUserGroup()->getLabel();
            $profileArray['office_name'] = $profile->getOffice() ? $profile->getOffice()->getName() : '';
            $profileArray['country_name'] = $profile->getCountry() ? $profile->getCountry()->getName() : '';
            $result = [
                'success' => true,
                'data' => $profileArray,
                'fields' => $profile->getFieldsDataStructure()
            ];
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @Route("/member", paths={module="gms"}, methods={"GET"}, name="gms-member-index")
     */
    public function searchAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl('index', $this->router->getControllerName());
        $params = [];
        $companies = Helpers::__getRequestValue('companies');
        if (is_array($companies) && count($companies) > 0) {
            $company_ids = [];
            foreach ($companies as $company) {
                $company_ids[] = $company['id'];
            }
            $params['company_ids'] = $company_ids;
        }

        $query = Helpers::__getRequestValue('query');
        if (is_string($query) && $query != '') {
            $params['query'] = $query;
        }
        $result = UserProfile::__findHrContactWithFilter($params);
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getAdminProfilesAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $this->checkAcl('index', $this->router->getControllerName());
        $company_id = Helpers::__getRequestValue('company_id');
        $params = [];
        $query = Helpers::__getRequestValue('query');

        if (is_string($query) && $query != '') {
            $params['query'] = $query;
        }
        if (Helpers::__isValidId($company_id)) {
            $params['company_id'] = $company_id;
            $params['role_ids'] = [UserGroup::GROUP_HR_ADMIN, UserGroup::GROUP_HR_MANAGER];
            $params['limit'] = 1000;
            $result = UserProfile::__findHrContactWithFilter($params);
        } else {
            $result = ['success' => true, 'data' => []];
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

}
