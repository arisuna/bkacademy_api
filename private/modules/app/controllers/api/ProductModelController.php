<?php

namespace SMXD\app\controllers\api;

use SMXD\App\Models\Attributes;
use SMXD\App\Models\AttributesValue;
use SMXD\App\Models\AttributesValueTranslation;
use SMXD\App\Models\ProductModel;
use SMXD\App\Models\Company;
use SMXD\App\Models\SupportedLanguage;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;

class ProductModelController extends BaseController
{

    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function getListAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();

        $brand_id = Helpers::__getRequestValue('brand_id');
        if($brand_id && $brand_id > 0){
            $data = ProductModel::find([
                'conditions' => 'brand_id = :brand_id: and status >= 0',
                'bind' => [
                    'brand_id' => $brand_id
                ]
            ]);
        }else{
            $data = ProductModel::find([
                'conditions' => 'status >= 0',
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

    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function searchAction()
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPutGet();
        $params = [];
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['order'] = Helpers::__getRequestValue('order');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['search'] = Helpers::__getRequestValue('query');
        $result = ProductModel::__findWithFilters($params);
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function detailAction($uuid)
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxGet();

        $data = ProductModel::findFirstByUuid($uuid);
        $data = $data instanceof ProductModel ? $data->toArray() : [];

        $this->response->setJsonContent([
            'success' => true,
            'data' => $data
        ]);

        end:
        $this->response->send();
    }

    /**
     *
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPost();
        $this->response->setJsonContent($this->__save());
        end:
        $this->response->send();
    }

    /**
     *
     */
    public function updateAction()
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPut();

        //Change Archived status of attribute value
        $this->response->setJsonContent($this->__save());
        end:
        $this->response->send();
    }

    /**
     * @return array
     */
    private function __save()
    {
        $model = new ProductModel();
        $isNew = false;
        $uuid = Helpers::__getRequestValue('uuid');
        if (Helpers::__isValidUuid($uuid)) {
            $model = ProductModel::findFirstByUuid($uuid);
            if (!$model instanceof ProductModel) {
                $result = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
                goto end;
            }
        }else{
            $isNew = true;
            $model->setUuid(Helpers::__uuid());
        }
        $model->setName(Helpers::__getRequestValue('name'));
        $model->setSeries(Helpers::__getRequestValue('series'));
        $model->setStatus(Helpers::__getRequestValue('status') == 0 ? 0 : 1);
        $model->setDescription(Helpers::__getRequestValue('description'));
        $model->setBrandId(Helpers::__getRequestValue('brand_id'));

        $this->db->begin();
        if($isNew){
            $result = $model->__quickCreate();
        }else{
            $result = $model->__quickSave();
        }
        if ($result['success']) {
            $this->db->commit();
        } else {
            $this->db->rollback();
        }

        end:
        return $result;
    }

    /**
     * Old function
     */
    public function cloneAction()
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPost();

        $uuid = Helpers::__getRequestValue('uuid');

        $model = ProductModel::findFirstByUuid($uuid);
        if (!$model instanceof ProductModel) {
            $return = [
                'success' => false,
                'message' => 'DATA_NOT_FOUND_TEXT'
            ];
            goto end;
        }

        $newModel = new ProductModel();
        $newModel->setUuid(Helpers::__uuid());
        $newModel->setName(Helpers::__getRequestValue('name'));
        $newModel->setSeries(Helpers::__getRequestValue('series'));
        $newModel->setStatus(Helpers::__getRequestValue('status') == 0 ? 0 : 1);
        $newModel->setDescription(Helpers::__getRequestValue('description'));
        $newModel->setBrandId(Helpers::__getRequestValue('brand_id'));

        $this->db->begin();

        $return = $newModel->__quickSave();
        if(!$return['success']){
            $this->db->rollback();
            goto end;
        }

        end:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * @param $id
     */
    public function deleteAction($uuid)
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxDelete();

        $result = [
            'success' => false,
            'message' => 'YOU_DO_NOT_HAVE_PERMISSION_TEXT'
        ];


        if (Helpers::__isValidUuid($uuid)) {
            $model = ProductModel::findFirstByUuid($uuid);
            if ($model instanceof ProductModel) {
                $result = $model->__quickRemove();
                if ($result['success'] == false) {
                    $result = [
                        'success' => false,
                        'message' => 'DATA_DELETE_FAIL_TEXT'
                    ];
                }
            } else {
                $result = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
            }
        }

        $this->response->setJsonContent($result);
        end:
        return $this->response->send();
    }


}