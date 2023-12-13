<?php

namespace SMXD\Api\Controllers\API;

use Phalcon\Config;
use Phalcon\Http\ResponseInterface;
use SMXD\Api\Models\Acl;
use SMXD\Api\Models\Address;
use SMXD\Api\Models\AdministrativeRegion;
use SMXD\Api\Models\District;
use SMXD\Api\Models\Province;
use SMXD\Api\Models\Ward;
use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Application\Lib\AclHelper;
use SMXD\Api\Models\ModuleModel;
use SMXD\Api\Controllers\api\BaseController;
use SMXD\Application\Lib\Helpers;

/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/api")
 */
class AddressController extends ModuleApiController
{
    /**
     * @return \Phalcon\Http\Response|ResponseInterface
     */
    public function detailAction(string $uuid = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $return = ['success'=> false, 'DATA_NOT_FOUND_TEXT'];
        $data = Address::findFirstByUuid($uuid);

        if(!$data){
            goto end;
        }

        $return = [
            'success' => true,
            'data' => $data
        ];


        end:
         $this->response->setJsonContent($return);
        return $this->response->send();
    }

    public function searchProvincesAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $result = [
            'success' => true,
            'data' => []
        ];

        $query = Helpers::__getRequestValue('query');
        if ($query) {
            $results = Province::find([
                'conditions' => 'name LIKE :query: OR name_en LIKE :query: OR fullname LIKE :query: OR fullname_en LIKE :query:',
                'bind' => [
                    'query' => "%" . $query . "%",
                ]
            ]);
        } else {
            $results = Province::find();
        }

        if ($results && count($results) > 0) {
            $result['data'] = $results;
        }

        end:

        $this->response->setJsonContent($result);
        $this->response->send();
    }

    public function searchRegionsProvincesAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $result = [
            'success' => true,
            'data' => []
        ];

        $query = Helpers::__getRequestValue('query');

        $regions = AdministrativeRegion::findWithCache([
            'conditions' => 'name LIKE :query: OR code LIKE :query:',
            'bind' => [
                'query' => "%" . $query . "%",
            ]
        ]);

        if ($regions && count($regions) > 0) {
            $provincesArr = [];

            $provinces = Province::find();

            if ($provinces && count($provinces) > 0) {
                foreach ($provinces as $item) {
                    $provincesArr[$item->getAdministrativeRegionId()][] = $item->toArray();
                }
            }

            foreach ($regions as $item) {
                $itemArr = $item->toArray();
                $itemArr['items'] = $provincesArr[$item->getId()] ? $provincesArr[$item->getId()] : [];
                $result['data'][] = $itemArr;
            }
        }

        end:

        $this->response->setJsonContent($result);
        $this->response->send();
    }

    public function searchDistrictsAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $result = [
            'success' => true,
            'data' => []
        ];

        $provinceId = Helpers::__getRequestValue('province_id');
        $query = Helpers::__getRequestValue('query');
        if ($provinceId && $provinceId > 0) {
            if ($query) {
                $results = District::find([
                    'conditions' => 'province_id = :province_id: AND name LIKE :query: OR name_en LIKE :query: OR fullname LIKE :query: OR fullname_en LIKE :query:',
                    'bind' => [
                        'province_id' => $provinceId,
                        'query' => "%" . $query . "%"
                    ]
                ]);
            } else {
                $results = District::find([
                    'conditions' => 'province_id = :province_id:',
                    'bind' => [
                        'province_id' => $provinceId
                    ]
                ]);
            }

            if ($results && count($results) > 0) {
                $result['data'] = $results;
            }
        }

        end:

        $this->response->setJsonContent($result);
        $this->response->send();
    }

    public function searchWardsAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $result = [
            'success' => true,
            'data' => []
        ];

        $districtId = Helpers::__getRequestValue('district_id');
        $query = Helpers::__getRequestValue('query');
        if ($districtId && $districtId > 0) {
            if ($query) {
                $results = Ward::find([
                    'conditions' => 'district_id = :district_id: AND name LIKE :query: OR name_en LIKE :query: OR fullname LIKE :query: OR fullname_en LIKE :query:',
                    'bind' => [
                        'district_id' => $districtId,
                        'query' => "%" . $query . "%"
                    ]
                ]);
            } else {
                $results = Ward::find([
                    'conditions' => 'district_id = :district_id:',
                    'bind' => [
                        'district_id' => $districtId
                    ]
                ]);
            }

            if ($results && count($results) > 0) {
                $result['data'] = $results;
            }
        }

        end:

        $this->response->setJsonContent($result);
        $this->response->send();
    }

}