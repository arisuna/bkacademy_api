<?php

namespace SMXD\app\controllers\api;

use Phalcon\Config;
use Phalcon\Http\ResponseInterface;
use SMXD\App\Models\Acl;
use SMXD\App\Models\BankAccount;
use SMXD\App\Models\Classroom;
use SMXD\App\Models\ClassroomSchedule;
use SMXD\App\Models\KnowledgePoint;
use SMXD\App\Models\StudentClass;
use SMXD\App\Models\Student;
use SMXD\App\Models\StudentCategoryScore;
use SMXD\App\Models\StaffUserGroup;
use SMXD\App\Models\ModuleModel;
use SMXD\App\Models\User;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\ModelHelper;

/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/app/api")
 */
class ClassroomController extends BaseController
{

    public function searchAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $params = [];
        $params['limit'] = Helpers::__getRequestValue('limit');
        $orders = Helpers::__getRequestValue('orders');
        $ordersConfig = Helpers::__getApiOrderConfig($orders);
        $params['page'] = Helpers::__getRequestValue('page');
        $params['search'] = Helpers::__getRequestValue('query');
        $params['is_end_user'] = true;
        $statuses = Helpers::__getRequestValue('statuses');
        $params['statuses'] = $statuses;
//        if ($statuses && count($statuses) > 0) {
//            if (in_array(Classroom::STATUS_ARCHIVED, $statuses)) {
//                $params['is_deleted'] = Helpers::YES;
//            } else {
//                $params['is_deleted'] = Helpers::NO;
//            }
//
//            foreach ($statuses as $item) {
//                if ($item != -1) {
//                    $params['statuses'][] = $item;
//                }
//            }
//        }

        $result = Classroom::__findWithFilters($params, $ordersConfig);
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
        $data = Classroom::find([
            'conditions' => 'is_deleted <> 1'
        ]);
        $result = [
            'success' => true,
            'data' => $data
        ];
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Get detail of object
     * @param string $uuid
     * @return \Phalcon\Http\Response|ResponseInterface
     */
    public function detailAction(string $uuid = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();
        if (Helpers::__isValidUuid($uuid)) {
            $classroom = Classroom::findFirstByUuid($uuid);
        } else {
            $classroom = Classroom::findFirstById($uuid);
        }
        $data = $classroom instanceof Classroom ? $classroom->toArray() : [];
        $data['student_ids'] = [];
        $data['students'] = [];
        $data['knowledge_points'] = [];


        $studentClasses = StudentClass::getAllStudentOfClass($classroom->getId());

        $knowledgePoints = KnowledgePoint::getAllKnowledgePointOfGrade($classroom->getGrade());
        foreach($knowledgePoints as $knowledgePoint){
            $data['knowledge_points'][$knowledgePoint->getId()] = $knowledgePoint->toArray();
        }

        foreach ($studentClasses as $studentClass) {
            $student = $studentClass->getStudent();
            if($student && $student->getStatus() != Student::STATUS_DELETED){
                $dataArray = $studentClass->toArray();
                $dataArray['student'] = $student->toArray();
                $dataArray['student']['knowledge_points'] = [];
                foreach($knowledgePoints as $knowledgePoint){
                    $student_scores = StudentCategoryScore::find([
                        'conditions' => 'student_id = :student_id: and category_id = :category_id:',
                        'bind'=> [
                            'student_id' => $student->getId(),
                            "category_id" => $knowledgePoint->getId()
                        ],
                        'order' => 'date DESC',
                        'limit' => 5
                    ]);
                    $result = null;
                    $dataArray['student']['knowledge_points'][$knowledgePoint->getId()]['result'] = $result;
                }
                $data['student_ids'][] = $student->getId();
                $data['students'][] = $dataArray;
            }
        }

        $data['schedules'] = [];
        $schedules = ClassroomSchedule::getAllScheduleOfClass($classroom->getId());
        foreach ($schedules as $schedule) {
            $data['schedules'][] = $schedule->toArray();
        }

        $this->response->setJsonContent([
            'success' => true,
            'data' => $data
        ]);

        end:
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|ResponseInterface
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $name = Helpers::__getRequestValue('name');
        $checkIfExist = Classroom::findFirst([
            'conditions' => 'name = :name: and is_deleted <> 1',
            'bind' => [
                'name' => $name
            ]
        ]);

        if ($checkIfExist) {
            $result = [
                'success' => false,
                'message' => 'NAMEL_MUST_UNIQUE_TEXT'
            ];
            goto end;
        }

        $model = new Classroom();
        $data = Helpers::__getRequestValuesArray();
        $model->setData($data);
        $model->setStatus(Classroom::STATUS_ACTIVATED);

        $this->db->begin();

        $resultCreate = $model->__quickCreate();
        if ($resultCreate['success']) {
            $this->db->commit();
            $result = [
                'success' => true,
                'message' => 'DATA_SAVE_SUCCESS_TEXT'
            ];
        } else {
            $this->db->rollback();
            $result = [
                'success' => false,
                'detail' => is_array($resultCreate['detail']) ? implode(". ", $resultCreate['detail']) : $resultCreate,
                'message' => 'DATA_SAVE_FAIL_TEXT',
            ];
        }

        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|ResponseInterface
     */
    public function updateAction($id)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $data = Helpers::__getRequestValuesArray();

        $result = [
            'success' => false,
            'message' => 'CLASSROOM_NOT_FOUND_TEXT'
        ];

        if ($id == null || !Helpers::__isValidId($id)) {
            goto end;
        }

        $model = Classroom::findFirstById($id);
        if (!$model instanceof Classroom) {
            goto end;
        }

        $model->setData($data);

        $this->db->begin();

        $result = $model->__quickUpdate();
        if (!$result['success']) {
            $this->db->rollback();
            $result['message'] = 'DATA_SAVE_FAIL_TEXT';
        }

        $student_ids = Helpers::__getRequestValueAsArray('student_ids');
        $old_students = StudentClass::find([
            'conditions' => 'class_id = :class_id:',
            'bind' => [
                'class_id' => $model->getId()
            ]
        ]);
        if(count($old_students) > 0){
            foreach($old_students as $old_student){
                $is_removed = false;
                if (count($student_ids) && is_array($student_ids)) {
                    if(!in_array($old_student->getStudentId(), $student_ids)){
                        $is_removed = true;
                        $result = $old_student->__quickRemove();
                        if (!$result['success']) {
                            $this->db->rollback();
                            goto end;
                        }
                    }
                } else {
                    $is_removed = true;
                    $result = $old_student->__quickRemove();
                    if (!$result['success']) {
                        $this->db->rollback();
                        goto end;
                    }
                }
            }
        }
        if (count($student_ids) && is_array($student_ids)) {
            foreach($student_ids as $student_id){
                $student = Student::findFirst([
                    'conditions' => 'status <> -1 and id = :id:',
                    'bind' => [
                        'id' => $student_id
                    ]
                ]);
                if($student instanceof  Student){
                    $studentClass = StudentClass::findFirst([
                        'conditions' => 'student_id = :student_id: and class_id = :class_id:',
                        'bind' => [
                            'student_id' => $student_id,
                            'class_id' => $model->getId()
                        ]
                    ]);
                    if(!$studentClass){
                        $studentClass =  new StudentClass();
                        $studentClass->setClassId($model->getId());
                        $studentClass->setStudentId($student_id);
                        $create_field_in_group = $studentClass->__quickCreate();
                        if(!$create_field_in_group['success']){
                            $result = $create_field_in_group;
                            $this->db->rollback();
                            goto end;
                        }
                    }
                }
            }
        }

        $schedules = Helpers::__getRequestValueAsArray('schedules');
        $old_schedules = ClassroomSchedule::find([
            'conditions' => 'classroom_id = :class_id:',
            'bind' => [
                'class_id' => $model->getId()
            ]
        ]);
        if(count($old_schedules) > 0){
            foreach($old_schedules as $old_schedule){
                $result = $old_schedule->__quickRemove();
                if (!$result['success']) {
                    $this->db->rollback();
                    goto end;
                }
            }
        }
        if (count($schedules) && is_array($schedules)) {
            foreach($schedules as $schedule){
                $scheludeItem =  new ClassroomSchedule();
                $scheludeItem->setClassroomId($model->getId());
                $scheludeItem->setDayOfWeek($schedule["day_of_week"]["value"]);
                $scheludeItem->setFrom($schedule["from"]);
                $scheludeItem->setTo($schedule["to"]);
                $create_field_in_group = $scheludeItem->__quickCreate();
                if(!$create_field_in_group['success']){
                    $result = $create_field_in_group;
                    $this->db->rollback();
                    goto end;
                }
            }
        }
        
        if ($result['success']) {
            $this->db->commit();
        } else {
            $this->db->rollback();
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
        $this->checkAjaxPut();

        $data = Helpers::__getRequestValuesArray();

        $result = [
            'success' => false,
            'message' => 'CLASSROOM_NOT_FOUND_TEXT'
        ];

        if ($id == null || !Helpers::__isValidId($id)) {
            goto end;
        }

        $classroom = Classroom::findFirstById($id);
        if (!$classroom instanceof Classroom) {
            goto end;
        }

        $this->db->begin();

        $classroom->setStatus(Classroom::STATUS_ARCHIVED);
        $result = $classroom->__quickUpdate();
        if (!$result['success']) {
            $result['message'] = 'DATA_DELETE_FAIL_TEXT';
        }

        $return = $classroom->__quickRemove();
        if (!$return['success']) {
            $return['message'] = "DATA_DELETE_FAIL_TEXT";
            $this->db->rollback();
        } else {
            $return['message'] = "DATA_DELETE_SUCCESS_TEXT";


            $this->db->commit();
        }
        $result = $return;

        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function getStudentsAction(string $uuid = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $result = [
            'success' => false,
            'message' => 'CLASSROOM_NOT_FOUND_TEXT'
        ];

        if (!Helpers::__isValidUuid($uuid)) {
            goto end;
        }

        $classroom = Classroom::findFirstByUuid($uuid);
        if (!$classroom instanceof Classroom) {
            goto end;
        }
        $data = [];

        $studentClasses = StudentClass::getAllStudentOfClass($classroom->getId());

        foreach ($studentClasses as $studentClass) {
            $student = $studentClass->getStudent();
            if($student && $student->getStatus() != Student::STATUS_DELETED){
                $dataArray = $studentClass->toArray();
                $dataArray['student'] = $student->toArray();
                $data[] = $dataArray;
            }
        }

        $result = [
            'success' => true,
            'data' => $data
        ];

        end:

        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function addStudentClassAction()
    {
        $this->view->disable();
        $this->checkAcl(AclHelper::ACTION_EDIT, AclHelper::CONTROLLER_CLASSROOM);

        $this->checkAjaxPost();

        $result = [
            'success' => false,
            'message' => 'CLASSROOM_NOT_FOUND_TEXT'
        ];
        $data = Helpers::__getRequestValuesArray();
        if (!Helpers::__isValidUuid($data["classroom_uuid"])) {
            goto end;
        }

        $classroom = Classroom::findFirstByUuid($data["classroom_uuid"]);
        if (!$classroom instanceof Classroom|| $classroom->getStatus() == Classroom::STATUS_ARCHIVED) {
            goto end;
        }

        $result = [
            'success' => false,
            'message' => 'STUDENT_NOT_FOUND_TEXT'
        ];

        if (!Helpers::__isValidUuid($data["student_uuid"])) {
            
            goto end;
        }

        $student = Student::findFirstByUuid($data["student_uuid"]);
        if (!$student instanceof Student || $student->getStatus() == Student::STATUS_DELETED) {
            goto end;
        }

        $studentClass = StudentClass::findFirst([
            'conditions' => 'student_id = :student_id: and class_id = :class_id:',
            'bind' => [
                'class_id' => $classroom->getId(),
                'student_id' => $student->getId()
            ],
        ]);

        if ($studentClass instanceof StudentClass) {
            $result = [
                'success' => false,
                'message' => 'STUDENT_BELONGED_TO_CLASS_TEXT'
            ];

            goto end;
        }

        $model = new StudentClass();
        $model->setStudentId($student->getId());
        $model->setClassId($classroom->getId());

        $resultCreate = $model->__quickCreate();
        if ($resultCreate['success']) {
            $result = [
                'success' => true,
                'data' => $model->toArray(),
                'message' => 'DATA_SAVE_SUCCESS_TEXT',
            ];
        } else {
            $result = [
                'success' => false,
                'detail' => is_array($resultCreate['detail']) ? implode(". ", $resultCreate['detail']) : $resultCreate,
                'message' => 'DATA_SAVE_FAIL_TEXT',
            ];
        }

        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function removeStudentClassAction(string $id = '')
    {
        $this->view->disable();
        $this->checkAcl(AclHelper::ACTION_EDIT, AclHelper::CONTROLLER_CLASSROOM);

        $this->checkAjaxDelete();
        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        $studentClass = StudentClass::findFirst([
            'conditions' => 'id = :id:',
            'bind' => [
                'id' => $id,
            ],
        ]);

        if (!$studentClass instanceof StudentClass) {
            goto end;
        }

        $resultRemove = $studentClass->__quickRemove();
        if ($resultRemove['success']) {
            $result = [
                'success' => true,
                'message' => 'DATA_REMOVE_SUCCESS_TEXT',
            ];
        } else {
            $result = [
                'success' => false,
                'detail' => is_array($resultRemove['detail']) ? implode(". ", $resultRemove['detail']) : $resultRemove,
                'message' => 'DATA_REMOVE_FAILED_TEXT',
            ];
        }

        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
