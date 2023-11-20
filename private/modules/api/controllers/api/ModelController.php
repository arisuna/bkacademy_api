<?php

namespace SMXD\api\controllers\api;

use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Api\Models\Brand;
use SMXD\Api\Models\Model;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Lib\Helpers;

class ModelController extends ModuleApiController
{
    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function searchAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $params = [];
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['order'] = Helpers::__getRequestValue('order');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['search'] = Helpers::__getRequestValue('query');
        $result = Model::__findWithFilters($params);
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function getListAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();

        $brand_id = Helpers::__getRequestValue('brand_id');
        $query = Helpers::__getRequestValue('query');

        if($brand_id && $brand_id > 0){
            $conditions = ' brand_id = :brand_id: and status >= 0 ';
            $bind =  [
                'brand_id' => $brand_id
            ];
            if($query){
                $conditions .= ' and name LIKE :search:';
                $bind['search'] = '%'. $query . '%';
            }
            $data = Model::find([
                'conditions' => $conditions,
                'bind' => $bind,
                'order' => 'created_at desc'
            ]);
        }else{
            $conditions = ' status >= 0 ';
            $bind =  [];
            if($query){
                $conditions .= ' and name LIKE :search:';
                $bind['search'] = '%'. $query . '%';
            }

            $data = Model::find([
                'conditions' => $conditions,
                'bind' => $bind,
                'order' => 'created_at desc'
            ]);
        }

        $result = [];
        foreach ($data as $item){
            $result[] = $item->parsedDataToArray();
        }

        $result = [
            'success' => true,
            'data' => $data
        ];
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function detailAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $data = Brand::__findFirstByUuidCache($uuid, CacheHelper::__TIME_7_DAYS);
        
        $data = $data instanceof Brand ? $data->toArray() : [];

        $this->response->setJsonContent([
            'success' => true,
            'data' => $data
        ]);

        end:
        $this->response->send();
    }

}