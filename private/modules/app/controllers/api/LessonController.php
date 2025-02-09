<?php

namespace SMXD\App\Controllers\API;

use Phalcon\Config;
use SMXD\App\Models\Acl;
use SMXD\App\Models\ClassroomSchedule;
use SMXD\App\Models\Company;
use SMXD\App\Models\Student;
use SMXD\App\Models\StudentClass;
use SMXD\App\Models\StudentScore;
use SMXD\App\Models\LessonCategory;
use SMXD\App\Models\Classroom;
use SMXD\App\Models\LessonClass;
use SMXD\App\Models\Lesson;
use SMXD\App\Models\LessonType;
use SMXD\App\Models\ExamType;
use SMXD\App\Models\Category;
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
        $lesson = Lesson::findFirstById((int)$id);
        $data = $lesson instanceof Lesson ? $lesson->toArray() : [];
        $data['students'] = [];
        $data['category_ids'] = [];
        $data['home_category_ids'] = [];
        $data['categories'] = [];
        $data['home_categories'] = [];
        $class = Classroom::findFirstById($lesson->getClassId());
        $lesson_categories = LessonCategory::findByLessonId($id);
        foreach ($lesson_categories as $lesson_category) {
            $category = $lesson_category->getCategory();
            if($category){
                if($lesson_category->getIsHomeCategory() == Helpers::YES){
                    $data['home_category_ids'][] = $category->getId();
                    $data['home_categories'][] = $category->toArray();

                } else {
                    $data['category_ids'][] = $category->getId();
                    $data['categories'][] = $category->toArray();

                }
            }
        }
        $studentClasses = StudentClass::getAllStudentOfClass($class ? $class->getId() : 0);

        foreach ($studentClasses as $studentClass) {
            $student = $studentClass->getStudent();
            if($student && $student->getStatus() != Student::STATUS_DELETED){
                $dataArray = $studentClass->toArray();
                $dataArray['student'] = $student->toArray();
                $dataArray['categories'] = [];
                $dataArray['home_categories'] = [];
                $category_scores = StudentScore::find([
                    'conditions' => 'student_id = :student_id: and lesson_id = :lesson_id: and is_home_score = 0',
                    'bind'=> [
                        'student_id' => $student->getId(),
                        'lesson_id' => $id
                    ]
                ]);
                if(count($category_scores) > 0){
                    foreach($category_scores as $category_score){
                        if($category_score->getIsMainScore() == Helpers::YES){
                            $dataArray['score'] = intval($category_score->getScore());
                        } else {
                            $dataArray['categories'][$category_score->getCategoryId()] = $category_score->toArray();
                        }
                    }
                }
                $home_category_scores = StudentScore::find([
                    'conditions' => 'student_id = :student_id: and lesson_id = :lesson_id: and is_home_score = 1',
                    'bind'=> [
                        'student_id' => $student->getId(),
                        'lesson_id' => $id
                    ]
                ]);
                if(count($home_category_scores) > 0){
                    foreach($home_category_scores as $home_category_score){
                        if($home_category_score->getIsMainScore() == Helpers::YES){
                            $dataArray['home_score'] = intval($home_category_score->getScore());
                        } else {
                            $dataArray['home_categories'][$home_category_score->getCategoryId()] = $home_category_score->toArray();
                        }
                    }
                }
                $data['students'][] = $dataArray;
            }
        }
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
    public function generateBulkAction()
    {
    	$this->view->disable();
        $this->checkAcl(AclHelper::ACTION_CREATE, AclHelper::CONTROLLER_LESSON);
        $this->checkAjaxPost();
        $week = Helpers::__getRequestValue("week");
        if(!is_numeric(($week)) || !$week > 0){
            $result = [
                'success' => false,
                'message' => 'WEEK_NOT_VALID_TEXT'
            ];
            goto end;
        }
        $today = time();
        $start_date_of_year = strtotime('first day of july this year');
        if($today < $start_date_of_year){
            $start_date_of_year = strtotime('first day of july last year');
        }
        $start_date = $start_date_of_year +($week - 1) * 7 * 60 * 60 * 24;
        $classrooms = Classroom::find([
            "conditions" => "is_deleted <> 1"
        ]);
        if(count($classrooms) > 0){
            $this->db->begin();
            foreach($classrooms as $classroom){
                $schedules = ClassroomSchedule::getAllScheduleOfClass($classroom->getId());
                foreach ($schedules as $schedule) {
                    $model = new Lesson();
                    $model->setWeek($week);
                    $model->setWeekReport($week);
                    $model->setClassId($classroom->getId());
                    $model->setLessonTypeId(LessonType::LESSON_TYPE_MAIN);
                    $model->setDate($start_date + 60 * 60 * 24 * ($schedule->getDayOfWeek() - 1));
                    $date_name = Lesson::DATE_NAME[date('D', $model->getDate())];
                    $model->setCode($classroom->getName().'.'.LessonType::LESSON_TYPE_MAIN_CODE. '.'. $date_name.'.'.date('d', $model->getDate()).date('m', $model->getDate()));
                    $model->setName($classroom->getName().'.'.LessonType::LESSON_TYPE_MAIN_CODE. '.'. $date_name.'.'.date('d', $model->getDate()).date('m', $model->getDate()));
                    $resultCreate = $model->__quickCreate();
                    if(!$resultCreate["success"])
                    {
                        $this->db->rollback();
                        $result = ([
                            'success' => false,
                            'detail' => $resultCreate,
                            'message' => 'DATA_SAVE_FAIL_TEXT',
                        ]);
                        goto end;
                    }
                }
                
            }
            $this->db->commit();
            $result = [
                'success' => true,
                'data' => $model->toArray(),
                'message' => 'DATA_SAVE_SUCCESS_TEXT'
            ];
        }
        end:
        $this->response->setJsonContent($result);
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
        $model->setWeek(round($datediff / 7) + 1);
        $model->setWeekReport(round($datediff / 7) + 1);
        $model->setMonthReport(date('m', $model->getDate()));
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
            $category_ids = Helpers::__getRequestValueAsArray('category_ids');
            if (count($category_ids) && is_array($category_ids)) {
                foreach($category_ids as $category_id){
                    $category = Category::findFirstById($category_id);
                    if($category){
                        $lesson_category =  new LessonCategory();
                        $lesson_category->setLessonId($model->getId());
                        $lesson_category->setCategoryId($category_id);
                        $resultCreateLessonCategory = $lesson_category->__quickSave();
                        if(!$resultCreateLessonCategory['success']){
                            $result = $resultCreateLessonCategory;
                            $this->db->rollback();
                            goto end;
                        }
                    }
                }
            }
            $this->db->commit();
            $result = [
                'success' => true,
                'data' => $model->toArray(),
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
                $model->setHadHomework($data["had_homework"]);
                $model->setWeekReport($data["had_homework"]);
                

                $this->db->begin();
                $resultCreate = $model->__quickUpdate();

                if ($resultCreate['success'] == true) {
                    $category_ids = Helpers::__getRequestValueAsArray('category_ids');
                    $old_categories = LessonCategory::find([
                        'conditions' => 'lesson_id = :lesson_id: and is_home_category = 0',
                        'bind' => [
                            'lesson_id' => $model->getId()
                        ]
                    ]);
                    if(count($old_categories) > 0){
                        foreach($old_categories as $old_category){
                            if (count($category_ids) && is_array($category_ids)) {
                                if(!in_array($old_category->getCategoryId(), $category_ids)){
                                    $is_removed = true;
                                    $resultRemove = $old_category->__quickRemove();
                                    if (!$resultRemove['success']) {
                                        $result = $resultRemove;
                                        $this->db->rollback();
                                        goto end;
                                    }
                                }
                            } else {
                                $is_removed = true;
                                $resultRemove = $old_category->__quickRemove();
                                if (!$resultRemove['success']) {
                                    $result = $resultRemove;
                                    $this->db->rollback();
                                    goto end;
                                }
                            }
                        }
                    }
                    if (count($category_ids) && is_array($category_ids)) {
                        foreach($category_ids as $category_id){
                            $category = Category::findFirstById($category_id);
                            if($category){
                                $lesson_category = LessonCategory::findFirst([
                                    'conditions' => 'lesson_id = :lesson_id: and category_id = :category_id: and is_home_category = 0',
                                    'bind' => [
                                        'lesson_id' => $model->getId(),
                                        'category_id' => $category_id,
                                    ]
                                ]);
                                if(!$lesson_category){
                                    $lesson_category =  new LessonCategory();
                                    $lesson_category->setLessonId($model->getId());
                                    $lesson_category->setCategoryId($category_id);
                                    $resultCreateLessonCategory = $lesson_category->__quickSave();
                                    if(!$resultCreateLessonCategory['success']){
                                        $result = $resultCreateLessonCategory;
                                        $this->db->rollback();
                                        goto end;
                                    }
                                }
                            }
                        }
                    }
                    $home_category_ids = Helpers::__getRequestValueAsArray('home_category_ids');
                    $old_home_categories = LessonCategory::find([
                        'conditions' => 'lesson_id = :lesson_id: and is_home_category = 1',
                        'bind' => [
                            'lesson_id' => $model->getId()
                        ]
                    ]);
                    if(count($old_home_categories) > 0){
                        foreach($old_home_categories as $old_home_category){
                            if (count($home_category_ids) && is_array($home_category_ids)) {
                                if(!in_array($old_home_category->getCategoryId(), $home_category_ids)){
                                    $is_removed = true;
                                    $resultRemove = $old_home_category->__quickRemove();
                                    if (!$resultRemove['success']) {
                                        $result = $resultRemove;
                                        $this->db->rollback();
                                        goto end;
                                    }
                                }
                            } else {
                                $is_removed = true;
                                $resultRemove = $old_home_category->__quickRemove();
                                if (!$resultRemove['success']) {
                                    $result = $resultRemove;
                                    $this->db->rollback();
                                    goto end;
                                }
                            }
                        }
                    }
                    if (count($home_category_ids) && is_array($home_category_ids)) {
                        foreach($home_category_ids as $home_category_id){
                            $home_category = Category::findFirstById($home_category_id);
                            if($home_category){
                                $lesson_home_category = LessonCategory::findFirst([
                                    'conditions' => 'lesson_id = :lesson_id: and category_id = :category_id: and is_home_category = 0',
                                    'bind' => [
                                        'lesson_id' => $model->getId(),
                                        'category_id' => $category_id,
                                    ]
                                ]);
                                if(!$lesson_home_category){
                                    $lesson_home_category =  new LessonCategory();
                                    $lesson_home_category->setLessonId($model->getId());
                                    $lesson_home_category->setCategoryId($home_category_id);
                                    $lesson_home_category->setIsHomeCategory(Helpers::YES);
                                    $resultCreateLessonCategory = $lesson_home_category->__quickSave();
                                    if(!$resultCreateLessonCategory['success']){
                                        $result = $resultCreateLessonCategory;
                                        $this->db->rollback();
                                        goto end;
                                    }
                                }
                            }
                        }
                    }
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

        if (is_object($params['date'])) {
            $params['date'] = (array)$params['date'];
        }
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
        $lesson_types = Helpers::__getRequestValue('lesson_types');
        if (is_array($lesson_types) && count($lesson_types) > 0) {
            foreach ($lesson_types as $lesson_type) {
                $params['lesson_types'][] = $lesson_type->id;
            }
        }
        $result = Lesson::__findWithFilters($params, $ordersConfig);
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function updateScoreAction()
    {

    	$this->view->disable();
        $this->checkAcl(AclHelper::ACTION_EDIT, AclHelper::CONTROLLER_LESSON);
        $this->checkAjaxPut();
        $data = Helpers::__getRequestValuesArray();
        $id = $data['lesson_id'];

        $result = [
            'success' => false,
            'student_id' => $data,
            'message' => 'Data not found'
        ];
        $lesson = Lesson::findFirstById($id);
        if (!$lesson) {
            $result = [
                'success' => false,
                'student_id' => $data,
                'message' => 'LESSON_NOT_FOUND_TEXT'
            ];
            goto end;
        }
        $this->db->begin();

        $student_score = StudentScore::findFirst([
            'conditions' => 'student_id = :student_id: and lesson_id = :lesson_id: and is_main_score = 1',
            'bind'=> [
                'student_id' => $data['student_id'],
                'lesson_id' => $data['lesson_id']
            ]
        ]);
        if($student_score){
            $student_score->setScore($data['score']);
            $result = $student_score->__quickUpdate();
            if (!$result['success']) {
                $this->db->rollback();
                $result = ([
                    'success' => false,
                    'message' => 'DATA_SAVE_FAIL_TEXT',
                    'detail' => $result
                ]);
                goto end;
            }
        } else {
            $student_score = new StudentScore();
            $student_score->setIsMainScore(Helpers::YES);
            $student_score->setStudentId($data['student_id']);
            $student_score->setExamTypeId($lesson->getExamTypeId());
            $student_score->setLessonId($data['lesson_id']);
            $student_score->setScore($data['score']);
            $result = $student_score->__quickCreate();
            if (!$result['success']) {
                $this->db->rollback();
                $result = ([
                    'success' => false,
                    'message' => 'DATA_SAVE_FAIL_TEXT',
                    'detail' => $result
                ]);
                goto end;
            }
        }

        $old_student_scores = StudentScore::find([
            'conditions' => 'student_id = :student_id: and lesson_id = :lesson_id: and is_main_score <> 1',
            'bind'=> [
                'student_id' => $data['student_id'],
                'lesson_id' => $data['lesson_id']
            ]
        ]);

        $lesson_categories = LessonCategory::findByLessonId($id);
        $category_ids = [];
        foreach ($lesson_categories as $lesson_category) {
            $category = $lesson_category->getCategory();
            if($category){
                $category_ids[] = $category->getId();
            }
        }

        $updated_category_ids = [];

        if(count($old_student_scores) > 0){
            foreach($old_student_scores as $old_student_score){
                if (count($category_ids) && is_array($category_ids)) {
                    if(!in_array($old_student_score->getCategoryId(), $category_ids)){
                        $is_removed = true;
                        $resultRemove = $old_student_score->__quickRemove();
                        if (!$resultRemove['success']) {
                            $result = $resultRemove;
                            $this->db->rollback();
                            goto end;
                        }
                    } else {
                        if(isset($data['categories'][$old_student_score->getCategoryId()]) && isset($data['categories'][$old_student_score->getCategoryId()]['score'])){
                            $old_student_score->setScore($data['categories'][$old_student_score->getCategoryId()]['score']);
                            $old_student_score->setCorrect($data['categories'][$old_student_score->getCategoryId()]['correct']);
                            $old_student_score->setWrong($data['categories'][$old_student_score->getCategoryId()]['wrong']);
                            $old_student_score->setNotDone($data['categories'][$old_student_score->getCategoryId()]['not_done']);
                            $resultRemove = $old_student_score->__quickUpdate();
                            if (!$resultRemove['success']) {
                                $result = $resultRemove;
                                $this->db->rollback();
                                goto end;
                            }
                            $updated_category_ids[] = $old_student_score->getCategoryId();
                        }
                    }
                } else {
                    $is_removed = true;
                    $resultRemove = $old_student_score->__quickRemove();
                    if (!$resultRemove['success']) {
                        $result = $resultRemove;
                        $this->db->rollback();
                        goto end;
                    }
                }
            }
        }
        if (count($category_ids) && is_array($category_ids)) {
            foreach($category_ids as $category_id){
                if(isset($data['categories'][$category_id]) && !in_array($category_id, $updated_category_ids)){
                    $student_score = new StudentScore();
                    $student_score->setIsMainScore(Helpers::NO);
                    $student_score->setStudentId($data['student_id']);
                    $student_score->setLessonId($data['lesson_id']);
                    $student_score->setCategoryId($category_id);
                    $student_score->setScore($data['categories'][$category_id]['score']);
                    $student_score->setCorrect($data['categories'][$category_id]['correct']);
                    $student_score->setWrong($data['categories'][$category_id]['wrong']);
                    $student_score->setNotDone($data['categories'][$category_id]['not_done']);
                    $updateMainScore = $student_score->__quickCreate();
                    if (!$updateMainScore['success']) {
                        $this->db->rollback();
                        $result = ([
                            'success' => false,
                            'message' => 'DATA_SAVE_FAIL_TEXT',
                            'detail' => $updateMainScore
                        ]);
                        goto end;
                    }

                }
                
            }
        }
        
        $this->db->commit();
        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
