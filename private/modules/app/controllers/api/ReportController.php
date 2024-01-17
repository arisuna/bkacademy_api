<?php

namespace SMXD\App\Controllers\API;

use Phalcon\Config;
use SMXD\App\Models\Acl;
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
class ReportController extends BaseController
{

    /**
     * @Route("/constant", paths={module="backend"}, methods={"GET"}, name="backend-constant-index")
     */
    public function getReportByWeekAction()
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
        $params['week'] = Helpers::__getRequestValue('week');
        $params['date'] = Helpers::__getRequestValue('date');
        $params['is_main_score'] = Helpers::YES;

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
        $result = StudentScore::__findWithFilters($params, $ordersConfig);
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
