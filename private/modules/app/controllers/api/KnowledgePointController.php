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

class KnowledgePointController extends BaseController
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
        $params['chapter_id'] = Helpers::__getRequestValue('chapter_id');
        $params['topic_id'] = Helpers::__getRequestValue('topic_id');
        $params['level'] = Helpers::__getRequestValue('level');
        
        $orders = Helpers::__getRequestValue('orders');
        $ordersConfig = Helpers::__getApiOrderConfig($orders);
        $result = KnowledgePoint::__findWithFilters($params, $ordersConfig);
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
            $knowledgePoint = KnowledgePoint::findFirstByUuid($uuid);
        } else {
            $knowledgePoint = KnowledgePoint::findFirstById($uuid);
        }
        $data = $knowledgePoint instanceof KnowledgePoint ? $knowledgePoint->toArray() : [];

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
        $model = new KnowledgePoint();
        $isNew = false;
        $uuid = Helpers::__getRequestValue('uuid');
        if (Helpers::__isValidUuid($uuid)) {
            $model = KnowledgePoint::findFirstByUuid($uuid);
            if (!$model instanceof KnowledgePoint) {
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
        $chapterId = Helpers::__getRequestValue('chapter_id');
        $chapter = Chapter::findFirstById($chapterId);
        if($chapter){
            $model->setGrade($chapter->getGrade());
            $model->setSubject($chapter->getSubject());
        }
        $topicId = Helpers::__getRequestValue('topic_id');
        $topic = Topic::findFirstById($topicId);
        if($topic){
            $model->setTopicId($topic->getId());
        }
        $model->setName(Helpers::__getRequestValue('name'));
        $model->setCode(Helpers::__getRequestValue('code'));
        $model->setLevel(Helpers::__getRequestValue('level'));
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
            $model = KnowledgePoint::findFirstByUuid($uuid);
            if ($model instanceof KnowledgePoint) {
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