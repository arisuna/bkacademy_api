<?php

namespace SMXD\App\Controllers\API;

use Phalcon\Config;
use SMXD\App\Models\Acl;
use SMXD\App\Models\Company;
use SMXD\App\Models\LessonType;
use SMXD\App\Models\ModuleModel;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Models\ApplicationModel;

/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/app/api")
 */
class LessonTypeController extends BaseController
{

    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function getListAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $data = LessonType::find();
        $result = [
            'success' => true,
            'data' => $data
        ];
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Get detail of object
     * @param int $id
     */
    public function detailAction($id = 0)
    {
    	$this->view->disable();
        $this->checkAjaxGet();
        $lesson_type = LessonType::findFirstById((int)$id);
        $data = $lesson_type instanceof LessonType ? $lesson_type->toArray() : [];
        $this->response->setJsonContent([
            'success' => true,
            'data' => $data
        ]);

        end:
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function createAction()
    {
    	$this->view->disable();
        $this->checkAcl(AclHelper::ACTION_INDEX, AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPost();

        $model = new LessonType();
        $data = Helpers::__getRequestValuesArray();
        $model->setData($data);

        $this->db->begin();
        $resultCreate = $model->__quickCreate();

        if ($resultCreate['success'] == true) {
            $this->db->commit();
            $result = [
                'success' => true,
                'message' => 'DATA_SAVE_SUCCESS_TEXT'
            ];
        } else {
            $this->db->rollback();
            $result = ([
                'success' => false,
                'detail' => $resultCreate,
                'message' => 'DATA_SAVE_FAIL_TEXT',
            ]);
        }
        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function updateAction($id)
    {

    	$this->view->disable();
        $this->checkAcl(AclHelper::ACTION_INDEX, AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPut();

        $result = [
            'success' => false,
            'message' => 'Data not found'
        ];

        if (Helpers::__isValidId($id)) {

            $model = LessonType::findFirstById($id);
            if ($model) {
                $data = Helpers::__getRequestValuesArray();
                $model->setData($data);
                

                $this->db->begin();
                $resultCreate = $model->__quickUpdate();

                if ($resultCreate['success'] == true) {
                    $this->db->commit();
                    $result = $resultCreate;
                } else {
                    $this->db->rollback();
                    $result = ([
                        'success' => false,
                        'message' => 'DATA_SAVE_FAIL_TEXT',
                        'detail' => $resultCreate
                    ]);
                }
            }
        }
        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Delete data
     */
    public function deleteAction($id)
    {

    	$this->view->disable();
        $this->checkAcl(AclHelper::ACTION_INDEX, AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxDelete();
        $lesson_type = LessonType::findFirstById($id);

        if (!$lesson_type) {
            $return = [
                'success' => false,
                'message' => 'STUDENT_NOT_FOUND_TEXT',
            ];
            goto end;
        }
        $this->db->begin();
        $deleteLessonType = $lesson_type->__quickRemove();
            if ($deleteLessonType['success'] == true) {
                $this->db->commit();
                $result = $deleteLessonType;
            } else {
                $this->db->rollback();
                $result = ([
                    'success' => false,
                    'message' => 'DATA_SAVE_FAIL_TEXT',
                    'detail' => $deleteLessonType
                ]);
            }
        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
