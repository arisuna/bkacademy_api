<?php

namespace SMXD\app\controllers\api;

use Phalcon\Config;
use Phalcon\Http\ResponseInterface;
use SMXD\App\Models\Acl;
use SMXD\App\Models\Address;
use SMXD\App\Models\Company;
use SMXD\App\Models\StaffUserGroup;
use SMXD\App\Models\ModuleModel;
use SMXD\App\Models\User;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Models\CompanyExt;

/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/app/api")
 */
class AddressController extends BaseController
{
    public function searchAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $params = [];
        $params['limit'] = Helpers::__getRequestValue('limit');
        $orders = Helpers::__getRequestValue('orders');
        $ordersConfig = Helpers::__getApiOrderConfig($orders);
        $params['page'] = Helpers::__getRequestValue('page');
        $params['search'] = Helpers::__getRequestValue('query');
        $params['is_end_user'] = true;
        $params['statuses'] = Helpers::__getRequestValue('statuses');

        $result = Address::__findWithFilters($params, $ordersConfig);
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}