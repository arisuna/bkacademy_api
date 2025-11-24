<?php

namespace SMXD\App\Controllers\API;

use SMXD\App\Models\Attributes;
use SMXD\App\Models\AttributesValue;
use SMXD\App\Models\AttributesValueTranslation;
use SMXD\App\Models\Chapter;
use SMXD\App\Models\Company;
use SMXD\App\Models\Classroom;
use SMXD\App\Models\SupportedLanguage;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\ModelHelper;

class TopicController extends BaseController
{

    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function searchAction()
    {
        $this->view->disable();
        // $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPutGet();
        $params = [];
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['order'] = Helpers::__getRequestValue('order');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['search'] = Helpers::__getRequestValue('query');
        $params['grade'] = Helpers::__getRequestValue('grade');
        $params['subject'] = Helpers::__getRequestValue('subject');
        
        $orders = Helpers::__getRequestValue('orders');
        $ordersConfig = Helpers::__getApiOrderConfig($orders);
        $result = Topic::__findWithFilters($params, $ordersConfig);
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function detailAction($uuid)
    {
        $this->view->disable();
        // $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxGet();
        if(Helpers::__isValidUuid($uuid)){
            $topic = Topic::findFirstByUuid($uuid);
        } else {
            $topic = Topic::findFirstById($uuid);
        }
        $data = $topic instanceof Topic ? $topic->toArray() : [];

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
        $model = new Topic();
        $isNew = false;
        $uuid = Helpers::__getRequestValue('uuid');
        if (Helpers::__isValidUuid($uuid)) {
            $model = Topic::findFirstByUuid($uuid);
            if (!$model instanceof Topic) {
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
        $model->setCode(Helpers::__getRequestValue('code'));
        $model->setGrade(Helpers::__getRequestValue('grade'));
        $model->setSubject(Helpers::__getRequestValue('subject'));

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
            $model = Topic::findFirstByUuid($uuid);
            if ($model instanceof Topic) {
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