<?php

namespace SMXD\App\Controllers\API;

use SMXD\App\Models\Attributes;
use SMXD\App\Models\AttributesValue;
use SMXD\App\Models\AttributesValueTranslation;
use SMXD\App\Models\Chapter;
use SMXD\App\Models\Topic;
use SMXD\App\Models\KnowledgePoint;
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
        $params['query'] = Helpers::__getRequestValue('query');
        $grades = Helpers::__getRequestValueAsArray('grades');
        $params['subject'] = Helpers::__getRequestValue('subject');
        $chapters = Helpers::__getRequestValueAsArray('chapters');
        $levels = Helpers::__getRequestValueAsArray('levels');
        $topics = Helpers::__getRequestValueAsArray('topics');
        $types = Helpers::__getRequestValueAsArray('chapter_types');
        $params['grade_ids'] = [];
        $params['chapters'] = [];
        $params['levels'] = [];
        $params['topics'] = [];
        $params['types'] = [];
        if(count($types) > 0){
            foreach($types as $type){     
                $params['types'][]= $type['value'];
            }
        }
        if(count($grades) > 0){
            foreach($grades as $grade){     
                $params['grades'][]= $grade['id'];
            }
        }
        if(count($chapters) > 0){
            foreach($chapters as $knowledge_point){     
                $params['chapters'][]= $knowledge_point['id'];
            }
        }
        if(count($levels) > 0){
            foreach($levels as $level){     
                $params['levels'][]= $level['value'];
            }
        }
        if(count($topics) > 0){
            foreach($topics as $topic){     
                $params['topics'][]= $topic['id'];
            }
        }
        
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
            $knowledge_point = KnowledgePoint::findFirstByUuid($uuid);
        } else {
            $knowledge_point = KnowledgePoint::findFirstById($uuid);
        }
        $data = $knowledge_point instanceof KnowledgePoint ? $knowledge_point->toArray() : [];

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
        $model->setName(Helpers::__getRequestValue('name'));
        $model->setCode(Helpers::__getRequestValue('code'));
        $model->setLevel(Helpers::__getRequestValueAsArray('level')['value'] ?? '');
        $model->setGrade(Helpers::__getRequestValue('grade'));
        $model->setSubject(Chapter::SUBJECT_MATH);
        $chapter_id = Helpers::__getRequestValue('chapter_id');
        if($chapter_id > 0){
            $chapter = Chapter::findFirstById($chapter_id);
            if(!$chapter instanceof Chapter){
                $result = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
                goto end;
            }
        }
        $topic_id = Helpers::__getRequestValue('topic_id');
        if($topic_id > 0){
            $topic = Topic::findFirstById($topic_id);
            if(!$topic instanceof Topic){
                $result = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
                goto end;
            }
        }

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