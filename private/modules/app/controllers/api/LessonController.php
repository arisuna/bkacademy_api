<?php

namespace SMXD\App\Controllers\API;

use Phalcon\Config;
use SMXD\App\Models\Acl;
use SMXD\App\Models\Company;
use SMXD\App\Models\Student;
use SMXD\App\Models\Classroom;
use SMXD\App\Models\LessonClass;
use SMXD\App\Models\Lesson;
use SMXD\App\Models\LessonType;
use SMXD\App\Models\ExamType;
use SMXD\App\Models\ModuleModel;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Models\ApplicationModel;

/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/app/api")
 */
class LessonController extends BaseController
{

    /**
     * Get detail of object
     * @param int $id
     */
    public function detailAction($id = 0)
    {
    	$this->view->disable();
        $this->checkAjaxGet();
        $lesson = Lesson::findFirst((int)$id);
        $data = $lesson instanceof Lesson ? $lesson->toArray() : [];
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
        $this->checkAcl(AclHelper::ACTION_CREATE, AclHelper::CONTROLLER_LESSON);
        $this->checkAjaxPost();

        $model = new Lesson();
        $data = Helpers::__getRequestValuesArray();
        $model->setData($data);
        $model->setDate(strtotime($data['date']));
        $start_date_of_year = strtotime('first day of january this year');
        $datediff = ($model->getDate() - $start_date_of_year) / (60 * 60 * 24);
        $model->setWeek(round($datediff / 7));
        $classroom = Classroom::findFirstById($data['class_id']);
        if(!$classroom || $classroom->getIsDeleted() == Helpers::YES){
            $result = [
                'success' => false,
                'message' => 'CLASSROOM_NOT_FOUND_TEXT'
            ];
        }  
        $lessonType = LessonType::findFirstById($data['lesson_type_id']);
        if(!$lessonType){
            $result = [
                'success' => false,
                'message' => 'LESSON_TYPE_NOT_FOUND_TEXT'
            ];
        }        
        $date_name = Lesson::DATE_NAME[date('D', $model->getDate())];
        $model->setCode($classroom->getName().'.'.$lessonType->getCode(). '.'. $date_name.'.'.date('d', $model->getDate()).date('m', $model->getDate()));

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
        $this->checkAcl(AclHelper::ACTION_EDIT, AclHelper::CONTROLLER_LESSON);
        $this->checkAjaxPut();

        $result = [
            'success' => false,
            'message' => 'Data not found'
        ];

        if (Helpers::__isValidId($id)) {

            $model = Lesson::findFirstById($id);
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
        $this->checkAcl(AclHelper::ACTION_DELETE, AclHelper::CONTROLLER_LESSON);
        $this->checkAjaxDelete();
        $lesson = Lesson::findFirstById($id);

        if (!$lesson) {
            $return = [
                'success' => false,
                'message' => 'STUDENT_NOT_FOUND_TEXT',
            ];
            goto end;
        }
        $this->db->begin();
        $deleteLesson = $lesson->__quickRemove();
            if ($deleteLesson['success'] == true) {
                $this->db->commit();
                $result = $deleteLesson;
            } else {
                $this->db->rollback();
                $result = ([
                    'success' => false,
                    'message' => 'DATA_SAVE_FAIL_TEXT',
                    'detail' => $deleteLesson
                ]);
            }
        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @Route("/constant", paths={module="backend"}, methods={"GET"}, name="backend-constant-index")
     */
    public function searchAction()
    {
    	$this->view->disable();
        $this->checkAcl(AclHelper::ACTION_INDEX, AclHelper::CONTROLLER_LESSON);
        $this->checkAjaxPutGet();
        $params = [];
        $params['limit'] = Helpers::__getRequestValue('limit');
        $orders = Helpers::__getRequestValue('orders');
        $ordersConfig = Helpers::__getApiOrderConfig($orders);
        $params['page'] = Helpers::__getRequestValue('page');
        $params['search'] = Helpers::__getRequestValue('query');
        $params['lesson_type_id'] = Helpers::__getRequestValue('lesson_type_id');
        $params['week'] = Helpers::__getRequestValue('week');
        $params['date'] = Helpers::__getRequestValue('date');
        $grades = Helpers::__getRequestValue('grades');
        if (is_array($grades) && count($grades) > 0) {
            foreach ($grades as $grade) {
                $params['grades'][] = $grade->id;
            }
        }
        $weeks = Helpers::__getRequestValue('weeks');
        if (is_array($weeks) && count($weeks) > 0) {
            foreach ($weeks as $week) {
                $params['weeks'][] = $week->id;
            }
        }
        $classrooms = Helpers::__getRequestValue('classrooms');
        if (is_array($classrooms) && count($classrooms) > 0) {
            foreach ($classrooms as $classroom) {
                $params['classrooms'][] = $classroom->id;
            }
        }
        $result = Lesson::__findWithFilters($params, $ordersConfig);
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
