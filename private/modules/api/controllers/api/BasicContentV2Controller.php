<?php

namespace SMXD\api\controllers\api;

use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Api\Models\BasicContent;
use SMXD\Api\Models\Product;
use SMXD\Application\Lib\Helpers;

class BasicContentV2Controller extends ModuleApiController
{

    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function detailAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        if (Helpers::__isValidUuid($uuid)) {
            $data = BasicContent::findFirstByUuid($uuid);

        } else {
            $data = BasicContent::findFirstById($uuid);

        }

        $result = [
            'success' => true,
            'data' => $data
        ];
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function createAction()
    {
        $this->view->disable();
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
        $description = Helpers::__getRequestValue('encodeDescription');
        $description = $description ? rawurldecode(base64_decode($description)) : null;

        $model = new BasicContent();
        $isNew = false;
        $uuid = Helpers::__getRequestValue('uuid');
        if (Helpers::__isValidUuid($uuid)) {
            $model = BasicContent::findFirstByUuid($uuid);
            if (!$model instanceof BasicContent) {
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
//        $product = Product::findFirstByDescriptionId($model->getId());
//        if($product){
//            $this->checkAclEdit(AclHelper::CONTROLLER_PRODUCT);
//        }
        $model->setDescription($description);

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
     * @params $uuid
     */
    public function deleteAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();

        $result = [
            'success' => false,
            'message' => 'YOU_DO_NOT_HAVE_PERMISSION_TEXT'
        ];


        if (Helpers::__isValidUuid($uuid)) {
            $model = BasicContent::findFirstByUuid($uuid);
            if ($model instanceof BasicContent) {
//                $product = Product::findFirstByDescriptionId($model->getId());
//                if($product){
//                    $this->checkAclEdit(AclHelper::CONTROLLER_PRODUCT);
//                }
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