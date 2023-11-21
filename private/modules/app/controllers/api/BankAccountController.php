<?php

namespace SMXD\App\Controllers\API;

use Phalcon\Config;
use Phalcon\Http\ResponseInterface;
use SMXD\App\Models\Acl;
use SMXD\App\Models\BankAccount;
use SMXD\App\Models\ModuleModel;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Models\ApplicationModel;

/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/app/api")
 */
class BankAccountController extends BaseController
{
    /**
     * @param string $uuid
     * @return ResponseInterface
     */
    public function detailAction(string $uuid = ''): ResponseInterface
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $data = BankAccount::findFirstByUuid($uuid);

        $result = [
            'success' => true,
            'data' => $data
        ];

        end:

        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
