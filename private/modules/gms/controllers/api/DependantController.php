<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\Helpers;
use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Models\Dependant;
use Reloday\Gms\Models\EntityDocument;
use Reloday\Gms\Models\ModuleModel;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class DependantController extends BaseController
{
    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getExpirePassportListAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $return = ['success' => false, 'data' => [], 'message' => 'EMPLOYEE_NOT_FOUND_TEXT'];
        /****** query ****/
        $params = [];
        $params['limit'] = 100;
        /****** start ****/
        $params['page'] = 0;
        $params['passport_is_unusable'] = true;
        /*** search **/
        $return = EntityDocument::__getExpiredPassportOfDependant($params);
        if ($return['success'] == true) {
            $return['recordsFiltered'] = ($return['total_items']);
            $return['recordsTotal'] = ($return['total_items']);
            $return['length'] = ($return['total_items']);
            $return['draw'] = Helpers::__getRequestValue('draw');
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * [detailAction description]
     * @param string $id [description]ee
     * @return [type]     [description]ee
     */
    public function getAction(String $uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex(AclHelper::CONTROLLER_EMPLOYEE);
        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];
        if ($uuid && Helpers::__isValidUuid($uuid)) {
            $dependant = Dependant::findFirstByUuid($uuid);
            if ($dependant instanceof Dependant && $dependant->belongsToGms()) {
                $dependantArray = $dependant->toArray();
                $dependantArray['birth_country_name'] = $dependant->getBirthCountry() ? $dependant->getBirthCountry()->getName() : '';
                $dependantArray['spoken_languages'] = $dependant->parseSpokenLanguages();
                $dependantArray['citizenships'] = $dependant->parseCitizenships();
                $result = [
                    'success' => true,
                    'employee' => $dependant->getEmployee(),
                    'data' => $dependantArray,
                ];
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * [detailAction description]
     * @param string $id [description]ee
     * @return [type]     [description]ee
     */
    public function deleteAction(String $uuid)
    {
        $this->view->disable();
        $this->checkAjax(['PUT', 'DELETE']);
        $this->checkAcl("manage_dependant", AclHelper::CONTROLLER_EMPLOYEE);

        $dependantInput = Helpers::__getRequestValuesArray();
        if ($uuid && Helpers::__isValidUuid($uuid)) {
            $dependant = Dependant::findFirstByUuid($uuid);
            if ($dependant instanceof Dependant && $dependant->belongsToGms()) {
                $employee = $dependant->getEmployee();
                $dependantInput['employee_id'] = $dependant->getEmployeeId();
                ModuleModel::$dependant = $dependant;
                $dependant->setData($dependantInput);
                $resultSave = $dependant->__quickRemove();
                if ($resultSave['success'] == true) {
                    $result = [
                        'success' => true,
                        'message' => 'DELETE_DEPENDANT_SUCCESS_TEXT',
                        'data' => $dependant,
                    ];
                    ModuleModel::$employee = $employee;
                    $this->dispatcher->setParam('return', $result);
                } else {
                    $result = $resultSave;
                }
            } else {
                $result = [
                    'success' => false, 'message' => 'EMPLOYEE_NOT_FOUND_TEXT'
                ];
            }
        } else {
            $result = [
                'success' => false, 'message' => 'PARAMETER_INVALID_TEXT'
            ];
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
