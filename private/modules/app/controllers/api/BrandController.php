<?php

namespace SMXD\app\controllers\api;

use SMXD\App\Models\Attributes;
use SMXD\App\Models\AttributesValue;
use SMXD\App\Models\AttributesValueTranslation;
use SMXD\App\Models\Brand;
use SMXD\App\Models\Company;
use SMXD\App\Models\ProductModel;
use SMXD\App\Models\SupportedLanguage;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;

class BrandController extends BaseController
{

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function preCreateAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $result = [
            'success' => true, 'data' => ['uuid' => Helpers::__uuid()]
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
        $result = Brand::__findWithFilters($params);
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function detailAction($uuid)
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxGet();

        $data = Brand::findFirstByUuid($uuid);
        $data = $data instanceof Brand ? $data->toArray() : [];

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
        $isNew = true;
        $this->response->setJsonContent($this->__save($isNew));
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
    private function __save($isNew = false)
    {
        $model = new Brand();
        $uuid = Helpers::__getRequestValue('uuid');
        if (!$isNew) {
            $model = Brand::findFirstByUuid($uuid);
            if (!$model instanceof Brand) {
                $result = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
                goto end;
            }
        }else{
            $model->setUuid($uuid ?: Helpers::__uuid());
        }
        $model->setName(Helpers::__getRequestValue('name'));
        $model->setStatus(Helpers::__getRequestValue('status') == 0 ? 0 : 1);
        $model->setDescription(Helpers::__getRequestValue('description'));
        $model->setRectangularLogoUuid(Helpers::__getRequestValue('rectangular_logo_uuid'));
        $model->setSquaredLogoUuid(Helpers::__getRequestValue('squared_logo_uuid'));

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
    public function saveAction()
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPost();

        $uuid = Helpers::__getRequestValue('uuid');
        $model = new Brand();
        if (Helpers::__isValidUuid($uuid)) {
            $model = Brand::findFirstByUuid($uuid);
            if (!$model instanceof Brand) {
                $result = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
                goto end;
            }
        }else{
            $model->setUuid(Helpers::__uuid());
        }


        $model->setName(Helpers::__getRequestValue('name'));
        $model->setStatus(Helpers::__getRequestValue('status') == 0 ? 0 : 1);
        $model->setDescription(Helpers::__getRequestValue('description'));

        $this->db->begin();
        if ($model->save()) {

            $this->db->commit();
            $this->response->setJsonContent([
                'success' => true,
                'data' => $model,
                'message' => 'DATA_SAVE_SUCCESS_TEXT',
            ]);
        } else {
            $this->db->rollback();
            $msg = [];
            foreach ($model->getMessages() as $message) {
                $msg[] = $message->getMessage();
            }
            $this->response->setJsonContent([
                'success' => false,
                'message' => 'DATA_SAVE_FAIL_TEXT',
                'detail' => $msg
            ]);
        }

        end:
        $this->response->send();
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
        $model = Brand::findFirstByUuid($uuid);

        if (!$model instanceof Brand) {
            $result = [
                'success' => false,
                'message' => 'DATA_NOT_FOUND_TEXT'
            ];
            goto end;
        }

        $newModel = new Brand();
        $newModel->setUuid(Helpers::__uuid());
        $newModel->setName(Helpers::__getRequestValue('name'));
        $newModel->setStatus(Helpers::__getRequestValue('status') == 0 ? 0 : 1);
        $newModel->setDescription(Helpers::__getRequestValue('description'));
        $newModel->setRectangularLogoUuid(Helpers::__getRequestValue('rectangular_logo_uuid'));
        $newModel->setSquaredLogoUuid(Helpers::__getRequestValue('squared_logo_uuid'));

        $this->db->begin();
        $result = $newModel->__quickSave();

        if (!$result) {
            $this->db->rollback();
            goto end;
        }

        $productModels = $model->getProductModels();
        if(count($productModels) > 0){
            foreach ($productModels as $item){
                $newProductModel = new ProductModel();
                $newProductModel->setUuid(Helpers::__uuid());
                $newProductModel->setName($item->getName());
                $newProductModel->setSeries($item->getSeries());
                $newProductModel->setStatus($item->getStatus());
                $newProductModel->setDescription($item->getDescription());
                $newProductModel->setBrandId($newModel->getId());

                $resultProductModel = $newProductModel->__quickSave();

                if (!$resultProductModel) {
                    $result = $resultProductModel;
                    $this->db->rollback();
                    goto end;
                }
            }
        }

        $this->db->commit();

        end:
        $this->response->setJsonContent($result);
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
            $model = Brand::findFirstByUuid($uuid);
            if ($model instanceof Brand) {
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