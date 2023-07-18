<?php

namespace Reloday\Gms\Controllers\API;

use PhpParser\Node\Expr\AssignOp\Mod;
use \Reloday\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Lib\RelodayDynamoORM;
use Reloday\Application\Lib\RelodayObjectMapHelper;
use Reloday\Application\Models\ApplicationModel;
use Reloday\Application\Models\CompanyReportExt;
use Reloday\Application\Models\CompanyTypeExt;
use Reloday\Application\Models\CountryExt;
use Reloday\Application\Models\CountryReportExt;
use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Controllers\API\BaseController;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\CompanyType;
use Reloday\Gms\Models\Contract;
use Reloday\Gms\Models\Country;
use Reloday\Gms\Models\DataUserMember;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\InvitationRequest;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\NeedFormRequest;
use \Reloday\Gms\Models\Relocation;
use \Reloday\Gms\Models\Assignment;
use Reloday\Gms\Models\RelocationServiceCompany;
use Reloday\Gms\Models\ReminderConfig;
use \Reloday\Gms\Models\Task;
use Reloday\Gms\Models\UserGroup;
use Reloday\Gms\Models\UserProfile;
use Reloday\Gms\Module;
use SebastianBergmann\Comparator\MockObjectComparator;


/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class DashboardController extends BaseController
{
    /**
     * @return mixed
     */
    public function initAction()
    {
        $this->view->disable();

        $return = [
            'relocation_count' => Relocation::countOngoing(ModuleModel::$user_profile->getUuid()),
            'task_count' => Task::countTodo(ModuleModel::$user_profile->getUuid()),
            'assignment_count' => Assignment::countEndingSoon(ModuleModel::$user_profile->getUuid()),
            'assignment_count_approval' => Assignment::countApproval(ModuleModel::$user_profile->getUuid()),
        ];

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function relocationCountEndingSoonAction()
    {
        $this->view->disable();
        $this->checkAjax(['GET', 'PUT']);
        $data = $this->request->getJsonRawBody();
        $user_profile_uuid = isset($data->user_profile_uuid) && $data->user_profile_uuid != '' ? $data->user_profile_uuid : null;
        if ($user_profile_uuid == null) {
            $user_profile_uuid = ModuleModel::$user_profile->getUuid();
        }
        $return = ['success' => false, 'message' => 'RELOCATION_NOT_FOUND', 'count' => 0];
        try {
            $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
            $queryBuilder->addFrom('\Reloday\Gms\Models\Relocation', 'Relocation');
            $queryBuilder->distinct(true);
            $queryBuilder->columns(["Relocation.id as id"]);
            $queryBuilder->innerjoin('\Reloday\Gms\Models\Assignment', 'Relocation.assignment_id = Assignment.id', 'Assignment');
            $queryBuilder->innerjoin('\Reloday\Gms\Models\Employee', 'Relocation.employee_id = Employee.id', 'Employee');
            $queryBuilder->innerjoin('\Reloday\Gms\Models\AssignmentInContract', 'AssignmentInContract.assignment_id = Assignment.id', 'AssignmentInContract');
            $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'AssignmentInContract.contract_id = Contract.id', 'Contract');
            if (ModuleModel::$user_profile->isAdminOrManager() == false) {
                $queryBuilder->innerjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserMember.object_uuid = Relocation.uuid', 'DataUserMember');
                $queryBuilder->where("DataUserMember.user_profile_uuid = :user_profile_uuid:", [
                    'user_profile_uuid' => $user_profile_uuid
                ]);
            } else {
                $queryBuilder->where('Relocation.creator_company_id = :company_id:', [
                    'company_id' => ModuleModel::$company->getId()
                ]);
            }

            $calDate = date('Y-m-d', strtotime('+30 days'));
            $queryBuilder->andWhere("Relocation.end_date >= :today: and Relocation.end_date <= :calculate_date:", [
                'today' => date('Y-m-d'),
                'calculate_date' => $calDate
            ]);

//            $queryBuilder->andWhere("Relocation.status = :status_on_going:", [
//                'status_on_going' => Relocation::STATUS_ONGOING
//            ]);

            $queryBuilder->andwhere('Relocation.active = :relocation_activated:', [
                'relocation_activated' => Relocation::STATUS_ACTIVATED
            ]);

            $paginator = new PaginatorQueryBuilder([
                'builder' => $queryBuilder,
                'limit' => 1,
                'page' => 1
            ]);

            $paginate = $paginator->getPaginate();
            $return = ['success' => true, 'count' => $paginate->total_items];

        } catch (\PDOException $e) {
            $return = ['success' => false, 'count' => 0, 'message' => $e->getMessage()];
        } catch (Exception $e) {
            $return = ['success' => false, 'count' => 0, 'message' => $e->getMessage()];
        }
        $this->response->setJsonContent($return);
        return $this->response->send();

    }

    /**
     * @param $user_profile_uuid
     */
    public function relocationCountOnGoingAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $data = $this->request->getJsonRawBody();
        $user_profile_uuid = isset($data->user_profile_uuid) && $data->user_profile_uuid != '' ? $data->user_profile_uuid : null;
        if ($user_profile_uuid == null) {
            $user_profile_uuid = ModuleModel::$user_profile->getUuid();
        }
        $created_at_period = isset($data->created_at_period) && $data->created_at_period != '' ? $data->created_at_period : null;
        $return = ['success' => false, 'message' => 'RELOCATION_NOT_FOUND', 'count' => 0];

        try {
            $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
            $queryBuilder->addFrom('\Reloday\Gms\Models\Relocation', 'Relocation');
            $queryBuilder->columns(["uuid" => "Relocation.uuid"]);
            $queryBuilder->distinct(true);
            $queryBuilder->innerjoin('\Reloday\Gms\Models\Assignment', 'Relocation.assignment_id = Assignment.id', 'Assignment');
            $queryBuilder->innerjoin('\Reloday\Gms\Models\Employee', 'Relocation.employee_id = Employee.id', 'Employee');
            $queryBuilder->innerjoin('\Reloday\Gms\Models\AssignmentInContract', 'AssignmentInContract.assignment_id = Assignment.id', 'AssignmentInContract');
            $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'AssignmentInContract.contract_id = Contract.id', 'Contract');
            $queryBuilder->andwhere('Relocation.hr_company_id = Assignment.company_id');
            $queryBuilder->andwhere('Employee.company_id = Assignment.company_id');
            $queryBuilder->andwhere('Contract.from_company_id = Assignment.company_id');
            /********/

            $bindArray = [];

            $queryBuilder->andwhere('Relocation.active  = :relocation_active:', [
                'relocation_active' => Relocation::STATUS_ACTIVATED
            ]);
            /********/
            $queryBuilder->andwhere('Contract.status = :contract_active:', [
                'contract_active' => Contract::STATUS_ACTIVATED
            ]);
            /********/
            $queryBuilder->andWhere('Relocation.creator_company_id = :creator_company_id:', [
                'creator_company_id' => intval(ModuleModel::$company->getId())
            ]);
            /********/
            $queryBuilder->andWhere("Relocation.status = :status_on_going:", [
                'status_on_going' => Relocation::STATUS_ONGOING
            ]);
            /********/
            $queryBuilder->andwhere("Assignment.archived = :archived_no:", [
                'archived_no' => Assignment::ARCHIVED_NO
            ]);

            if ($created_at_period != null && is_string($created_at_period) && $created_at_period == '1_month') {
                $created_at = date('Y-m-d', strtotime('- 1 month'));
                $queryBuilder->andWhere(" Relocation.created_at >=  DATE('" . $created_at . "')");
            } elseif ($created_at_period != null && is_string($created_at_period) && $created_at_period == '3_months') {
                $created_at = date('Y-m-d', strtotime('- 3 months'));
                $queryBuilder->andWhere(" Relocation.created_at >=  DATE('" . $created_at . "')");
            } elseif ($created_at_period != null && is_string($created_at_period) && $created_at_period == '6_months') {
                $created_at = date('Y-m-d', strtotime('- 6 months'));
                $queryBuilder->andWhere(" Relocation.created_at >=  DATE('" . $created_at . "')");
            } elseif ($created_at_period != null && is_string($created_at_period) && $created_at_period == '1_year') {
                $created_at = date('Y-m-d', strtotime('- 1 year'));
                $queryBuilder->andWhere(" Relocation.created_at >=  DATE('" . $created_at . "')");
            }
            /********/

            $queryBuilder->groupBy('Relocation.uuid');
            $paginator = new PaginatorQueryBuilder([
                'builder' => $queryBuilder,
                'limit' => 1,
                'page' => 1
            ]);

            $paginate = $paginator->getPaginate();
            $return = ['success' => true, 'count' => $paginate->total_items];


        } catch (\PDOException $e) {
            $return = ['success' => false, 'count' => 0, 'message' => $e->getMessage(), 'bindData' => []];
        } catch (Exception $e) {
            $return = ['success' => false, 'count' => 0, 'message' => $e->getMessage(), 'bindData' => []];
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * list of relocation associated with current user
     * and status = ON GOING
     */
    public function relocationTodayListAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', $this->router->getControllerName());


        $return = ['success' => false, 'message' => 'RELOCATION_NOT_FOUND'];
        $di = $this->di;

        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Relocation', 'Relocation');
        $queryBuilder->distinct(true);


        /** load all task of company if ADMIN or MANAGER */
        $bindArray = array();
        $params = [];
        $params = ['status' => Relocation::STATUS_ONGOING];
        if (ModuleModel::$user_profile->isAdminOrManager() == false) {
            $params['user_profile_uuid'] = ModuleModel::$user_profile->getUuid();
            $return = Relocation::__findWithFilter($params);
        } else {
            $return = Relocation::__findWithFilter($params);
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $user_profile_uuid
     */
    public function relocationListOnGoingAction()
    {
        $this->view->disable();
        $this->checkAjax(['PUT', 'POST']);
        $this->checkAcl('index', $this->router->getControllerName());

        $user_profile_uuid = Helpers::__getRequestValue('user_profile_uuid');
        if ($user_profile_uuid == null) {
            $user_profile_uuid = ModuleModel::$user_profile->getUuid();
        }

        $params = ['status' => Relocation::STATUS_ONGOING];
        $params['user_profile_uuid'] = $user_profile_uuid;
        $params['is_owner'] = true;
        $params['created_at_period'] = Helpers::__getRequestValue('created_at_period');
        $params['page'] = Helpers::__getRequestValue('page');
        $return = Relocation::__findWithFilter($params);
        $return['count'] = $return['total_items'];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @param $user_profile_uuid
     */
    public function taskCountTodoAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

        try {
            $task = Task::__findWithFilter(false, ['limit' => 1, 'statuses' => [Task::STATUS_DRAFT], 'is_count_all' => true]);

            $return = ['success' => true, 'count' => $task['total_items']];
        } catch (\PDOException $e) {
            $return = ['success' => false, 'count' => 0, 'message' => $e->getMessage()];
        } catch (Exception $e) {
            $return = ['success' => false, 'count' => 0, 'message' => $e->getMessage()];
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $user_profile_uuid
     */
    public function taskCountOnGoingAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', $this->router->getControllerName());

        try {
            $task = Task::__findWithFilter(false, ['limit' => 1, 'statuses' => [Task::STATUS_IN_PROCESS], 'is_count_all' => true]);
            $return = ['success' => true, 'count' => $task['total_items']];
        } catch (\PDOException $e) {
            $return = ['success' => false, 'count' => 0, 'message' => $e->getMessage()];
        } catch (Exception $e) {
            $return = ['success' => false, 'count' => 0, 'message' => $e->getMessage()];
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * count ending soon
     * @return mixed
     */
    public function assignmentCountEndingSoonAction()
    {
        $this->view->disable();
        $this->checkAjax(['PUT', 'GET']);
        $data = $this->request->getJsonRawBody();
        $user_profile_uuid = isset($data->user_profile_uuid) && $data->user_profile_uuid != '' ? $data->user_profile_uuid : null;
        if ($user_profile_uuid == null) {
            $user_profile_uuid = ModuleModel::$user_profile->getUuid();
        }
        $return = ['success' => false, 'message' => 'RELOCATION_NOT_FOUND', 'count' => 0];
        $di = $this->di;
        try {
            $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
            $queryBuilder->addFrom('\Reloday\Gms\Models\Assignment', 'Assignment');
            $queryBuilder->distinct(true);


            /** load all task of company if ADMIN or MANAGER */
            if (ModuleModel::$user_profile->isAdminOrManager() == false) {
                $queryBuilder->innerjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserMember.object_uuid = Assignment.uuid', 'DataUserMember');
                $queryBuilder->where("DataUserMember.user_profile_uuid = '" . $user_profile_uuid . "'");
            } else {
                $queryBuilder->innerjoin('\Reloday\Gms\Models\AssignmentInContract', 'AssignmentInContract.assignment_id = Assignment.id', 'AssignmentInContract');
                $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'AssignmentInContract.contract_id = Contract.id', 'Contract');
                $queryBuilder->innerjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserMember.object_uuid = Assignment.uuid', 'DataUserMember');
                $queryBuilder->where('Contract.to_company_id = ' . ModuleModel::$company->getId());
                $queryBuilder->andwhere('Contract.status = ' . Contract::STATUS_ACTIVATED);
            }

            $from_date = date('Y-m-d');
            $to_date = date('Y-m-d', strtotime(date('Y-m-d')) + 14 * 24 * 60 * 60);
            $queryBuilder->andWhere("Assignment.end_date BETWEEN '" . $from_date . "' AND '" . $to_date . "'");

            $paginator = new PaginatorQueryBuilder([
                'builder' => $queryBuilder,
                'limit' => 10,
                'page' => 1
            ]);
            $paginate = $paginator->getPaginate();
            $return = ['success' => true, 'count' => $paginate->total_items];
        } catch (\PDOException $e) {
            $return = ['success' => false, 'count' => 0, 'message' => $e->getMessage()];
        } catch (Exception $e) {
            $return = ['success' => false, 'count' => 0, 'message' => $e->getMessage()];
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * list on
     * @return mixed
     */
    public function assignmentListOnGoingAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');

        $data = $this->request->getJsonRawBody();
        $user_profile_uuid = isset($data->user_profile_uuid) && $data->user_profile_uuid != '' ? $data->user_profile_uuid : null;
        if ($user_profile_uuid == null) {
            $user_profile_uuid = ModuleModel::$user_profile->getUuid();
        }
        $created_at_period = isset($data->created_at_period) && $data->created_at_period != '' ? $data->created_at_period : null;
        $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT', 'count' => 0];

        try {
            $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
            $queryBuilder->addFrom('\Reloday\Gms\Models\Assignment', 'Assignment');
            $queryBuilder->distinct(true);
            $queryBuilder->innerjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserMember.object_uuid = Assignment.uuid', 'DataUserMember');
            $queryBuilder->innerjoin('\Reloday\Gms\Models\AssignmentInContract', 'AssignmentInContract.assignment_id = Assignment.id', 'AssignmentInContract');
            $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'Contract.id = AssignmentInContract.contract_id', 'Contract');
            $queryBuilder->where("DataUserMember.user_profile_uuid = :user_profile_uuid: and DataUserMember.member_type_id = " . DataUserMember::MEMBER_TYPE_OWNER, [
                'user_profile_uuid' => $user_profile_uuid
            ]);
            $queryBuilder->andwhere('Contract.to_company_id = :gms_company_id:', [
                'gms_company_id' => intval(ModuleModel::$company->getId())
            ]);
            $queryBuilder->andwhere('Contract.from_company_id = Assignment.company_id');

            $queryBuilder->andwhere('Contract.status = :contract_active:', [
                'contract_active' => Contract::STATUS_ACTIVATED
            ]);

//            $queryBuilder->andWhere("Assignment.approval_status <= :approval_status:", [
//                'approval_status' => Assignment::STATUS_APPROVED
//            ]);

//            $queryBuilder->andWhere("(Assignment.end_date >= :end_date: OR Assignment.end_date IS NULL)", [
//                'end_date' => date('Y-m-d')
//            ]);

            $queryBuilder->andwhere("Assignment.archived = :archived:", [
                'archived' => Assignment::ARCHIVED_NO
            ]);

            $queryBuilder->andwhere('Assignment.is_terminated = :is_terminate_no:', [
                'is_terminate_no' => ModelHelper::NO
            ]);

            if ($created_at_period != null && is_string($created_at_period) && $created_at_period == '1_month') {
                $created_at = date('Y-m-d', strtotime('- 1 month'));
                $queryBuilder->andWhere(" Assignment.created_at >=  DATE('" . $created_at . "')");
            } elseif ($created_at_period != null && is_string($created_at_period) && $created_at_period == '3_months') {
                $created_at = date('Y-m-d', strtotime('- 3 months'));
                $queryBuilder->andWhere(" Assignment.created_at >=  DATE('" . $created_at . "')");
            } elseif ($created_at_period != null && is_string($created_at_period) && $created_at_period == '6_months') {
                $created_at = date('Y-m-d', strtotime('- 6 months'));
                $queryBuilder->andWhere(" Assignment.created_at >=  DATE('" . $created_at . "')");
            } elseif ($created_at_period != null && is_string($created_at_period) && $created_at_period == '1_year') {
                $created_at = date('Y-m-d', strtotime('- 1 year'));
                $queryBuilder->andWhere(" Assignment.created_at >=  DATE('" . $created_at . "')");
            }

            $queryBuilder->groupBy('Assignment.id');
            $queryBuilder->orderBy(['Assignment.created_at DESC']);

            $paginator = new PaginatorQueryBuilder([
                'builder' => $queryBuilder,
                'limit' => Assignment::LIMIT_PER_PAGE,
                'page' => Helpers::__getRequestValue('page') ? Helpers::__getRequestValue('page') : 1
            ]);
            $paginate = $paginator->getPaginate();
            $assignmentArray = [];
            foreach ($paginate->items as $item) {
                $assignmentArray[] = [
                    'id' => $item->getId(),
                    'name' => $item->getNumber(),
                    'uuid' => $item->getUuid(),
                    'employee_name' => $item->getEmployee()->getFullname(),
                    'is_terminated' => $item->getIsTerminated(),
                    'employee_uuid' => $item->getEmployee()->getUuid(),
                    'company_name' => $item->getCompany()->getName(),
                    'state' => $item->getFrontendState(),
                    'assignment_type' => ($item->getAssignmentType()) ? $item->getAssignmentType()->getName() : "",
                    'worker_status_label' => $item->getWorkerStatus($user_profile_uuid),
                    'approval_status' => $item->approval_status,
                ];
            }
            $return = ['success' => true, 'data' => $assignmentArray, 'count' => $paginate->total_items, 'rawSql' => $queryBuilder->getQuery()->getSql(),
                'total_pages' => $paginate->total_pages,
                'current' => Helpers::__getRequestValue('page') ? Helpers::__getRequestValue('page') : 1];
        } catch (\PDOException $e) {
            $return = ['success' => false, 'count' => 0, 'message' => $e->getMessage()];
        } catch (Exception $e) {
            $return = ['success' => false, 'count' => 0, 'message' => $e->getMessage()];
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     *  list of task due at today and associated with current profile (viewer / reporter / viewers)
     *   should use query builder
     */
    public function taskTodayListAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $return = ['success' => false, 'message' => 'TASK_NOT_FOUND_TEXT'];
        $params = array();
        $params['due_date'] = date('Y-m-d');
        $params['limit'] = 100;

        $return = Task::__findWithFilter(true, $params);
        if ($return['success'] == true) {
            $return['recordsFiltered'] = ($return['total_items']);
            $return['recordsTotal'] = ($return['total_items']);
            $return['length'] = ($return['total_items']);
            $return['draw'] = Helpers::__getRequestValue('draw');
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $user_profile_uuid
     */
    public function getTaskActiveByUserAction($user_profile_uuid = '')
    {
        $this->view->disable();
        $this->checkAjax(['PUT', 'GET']);
        $this->checkAcl('index', $this->router->getControllerName());
        $data = $this->request->getJsonRawBody();
        $user_profile_uuid = Helpers::__getRequestValue('user_profile_uuid');
        if ($user_profile_uuid == null) {
            $user_profile_uuid = ModuleModel::$user_profile->getUuid();
        }
        $created_at_period = isset($data->created_at_period) && $data->created_at_period != '' ? $data->created_at_period : null;
        $return = ['success' => false, 'message' => 'TASK_NOT_FOUND_TEXT', 'count' => 0];

        try {
            $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
            $queryBuilder->addFrom('\Reloday\Gms\Models\Task', 'Task');
            $queryBuilder->distinct(true);
            $queryBuilder->innerjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserMember.object_uuid = Task.uuid', 'DataUserMember');
            $queryBuilder->where("DataUserMember.user_profile_uuid = :user_profile_uuid:", [
                'user_profile_uuid' => $user_profile_uuid
            ]);
            $queryBuilder->andWhere("DataUserMember.member_type_id = :member_type_id:", [
                'member_type_id' => DataUserMember::MEMBER_TYPE_OWNER
            ]);
            $queryBuilder->andWhere("Task.progress = :progress:", [
                'progress' => Task::STATUS_IN_PROCESS
            ]);
            $queryBuilder->andWhere("Task.status <> :status_archived:", [
                'status_archived' => Task::STATUS_ARCHIVED
            ]);
            if ($created_at_period != null && is_string($created_at_period) && $created_at_period == '1_month') {
                $created_at = date('Y-m-d', strtotime('- 1 month'));
                $queryBuilder->andWhere(" Task.created_at >=  DATE('" . $created_at . "')");
            } elseif ($created_at_period != null && is_string($created_at_period) && $created_at_period == '3_months') {
                $created_at = date('Y-m-d', strtotime('- 3 months'));
                $queryBuilder->andWhere(" Task.created_at >=  DATE('" . $created_at . "')");
            } elseif ($created_at_period != null && is_string($created_at_period) && $created_at_period == '6_months') {
                $created_at = date('Y-m-d', strtotime('- 6 months'));
                $queryBuilder->andWhere(" Task.created_at >=  DATE('" . $created_at . "')");
            } elseif ($created_at_period != null && is_string($created_at_period) && $created_at_period == '1_year') {
                $created_at = date('Y-m-d', strtotime('- 1 year'));
                $queryBuilder->andWhere(" Task.created_at >=  DATE('" . $created_at . "')");
            }
            $queryBuilder->groupBy('Task.uuid');
            $paginator = new PaginatorQueryBuilder([
                'builder' => $queryBuilder,
                'limit' => 100,
                'page' => 1
            ]);
            $paginate = $paginator->getPaginate();
            $tasksArray = [];
            foreach ($paginate->items as $item) {
                $taskOwner = $item->getDataOwner();
                $linkedActivity = $item->getLinkedActivity();
                $tasksArray[] = [
                    'id' => $item->getId(),
                    'uuid' => $item->getUuid(),
                    'number' => $item->getNumber(),
                    'name' => $item->getName(),
                    'progress' => $item->getProgress(),
                    'is_flag' => $item->getIsFlag(),
                    'is_priority' => $item->getIsPriority(),
                    'has_file' => $item->getHasFile(),
                    'due_at' => $item->getDueAt(),
                    'employee_uuid' => $item->getEmployee() ? $item->getEmployee()->getUuid() : '',
                    'employee_name' => $item->getEmployee() ? $item->getEmployee()->getFullname() : '',
                    'owner_name' => $taskOwner != null ? $taskOwner->getFirstname() . " " . $taskOwner->getLastname() : '',
                    'linked_activity' => $linkedActivity ? [
                        'number' => $linkedActivity->getNumber(),
                        'state' => $linkedActivity->getFrontendState(),
                    ] : null,
                    'state' => $item->getFrontendState($user_profile_uuid),
                ];
            }
            $return = ['success' => true, 'data' => $tasksArray, 'count' => $paginate->total_items];
        } catch (\PDOException $e) {
            $return = ['success' => false, 'count' => 0, 'message' => $e->getMessage()];
        } catch (Exception $e) {
            $return = ['success' => false, 'count' => 0, 'message' => $e->getMessage()];
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * assignent close to end date + 14 dat
     * end date > current date and date difference <= 14 days
     */
    public function assignmentTodayListAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex();

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT '];
        $di = $this->di;
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Assignment', 'Assignment');
        $queryBuilder->distinct(true);
        $bindArray = array();
        /** load all task of company if ADMIN or MANAGER */
        if (ModuleModel::$user_profile->isAdminOrManager() == false) {
            $queryBuilder->innerjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserMember.object_uuid = Assignment.uuid', 'DataUserMember');
            $queryBuilder->where("DataUserMember.user_profile_uuid = :user_profile_uuid:");
            $bindArray['user_profile_uuid'] = ModuleModel::$user_profile->getUuid();
        } else {
            $queryBuilder->innerjoin('\Reloday\Gms\Models\AssignmentInContract', 'AssignmentInContract.assignment_id = Assignment.id', 'AssignmentInContract');
            $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'AssignmentInContract.contract_id = Contract.id', 'Contract');
            $queryBuilder->innerjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserMember.object_uuid = Assignment.uuid', 'DataUserMember');
            $queryBuilder->where('Contract.to_company_id = :company_id:');
            $queryBuilder->andwhere('Contract.status = :contract_status_activated:');
            $bindArray['company_id'] = ModuleModel::$company->getId();
            $bindArray['contract_status_activated'] = Contract::STATUS_ACTIVATED;
        }

        $queryBuilder->andWhere("Assignment.approval_status <= :approval_status_approved:");
        $bindArray['approval_status_approved'] = Assignment::STATUS_APPROVED;

        $queryBuilder->andWhere("Assignment.approval_status <> :approval_status_rejected:");
        $bindArray['approval_status_rejected'] = Assignment::STATUS_REJECTED;

        $queryBuilder->andWhere("Assignment.archived = :archived_no:");
        $bindArray['archived_no'] = Assignment::ARCHIVED_NO;

        $queryBuilder->andWhere("Assignment.is_terminated = :is_terminated_no:");
        $bindArray['is_terminated_no'] = ModelHelper::NO;

        $queryBuilder->andWhere("Assignment.end_date >= :current_date:");
        $bindArray['current_date'] = date('Y-m-d');
        //$bindArray['to_date'] = date('Y-m-d', strtotime(date('Y-m-d')) + 14 * 24 * 60 * 60);
        $queryBuilder->limit(0, 100);
        $modelManager = $di->get('modelsManager');
        try {
            $assignments = $modelManager->executeQuery($queryBuilder->getPhql(), $bindArray);
            $assignments_array = [];
            if ($assignments->count()) {

                foreach ($assignments as $assignment) {
                    $assignments_array[$assignment->getUuid()]['id'] = $assignment->getId();
                    $assignments_array[$assignment->getUuid()]['uuid'] = $assignment->getUuid();
                    $assignments_array[$assignment->getUuid()]['number'] = $assignment->getNumber();
                    $assignments_array[$assignment->getUuid()]['status'] = $assignment->getStatus();
                    $assignments_array[$assignment->getUuid()]['state'] = $assignment->getFrontendState();
                    $assignments_array[$assignment->getUuid()]['approval_status'] = $assignment->getApprovalStatus();
                    $assignments_array[$assignment->getUuid()]['company_name'] = $assignment->getCompany()->getName();
                    $assignments_array[$assignment->getUuid()]['end_date'] = $assignment->getEndDate();

                    $assignments_array[$assignment->getUuid()]['employee']['firstname'] = $assignment->getEmployee()->getFirstname();
                    $assignments_array[$assignment->getUuid()]['employee']['lastname'] = $assignment->getEmployee()->getLastname();
                    $assignments_array[$assignment->getUuid()]['employee']['uuid'] = $assignment->getEmployee()->getUuid();
                    $assignments_array[$assignment->getUuid()]['employee']['state'] = $assignment->getEmployee()->getFrontendState();
                    $assignments_array[$assignment->getUuid()]['host_country'] = ($assignment->getAssignmentBasic()
                        && $assignment->getAssignmentBasic()->getHomeCountry()) ? $assignment->getAssignmentBasic()->getHomeCountry()->getName() : "";
                    $assignments_array[$assignment->getUuid()]['destination_country'] = ($assignment->getAssignmentDestination()
                        && $assignment->getAssignmentDestination()->getDestinationCountry()) ? $assignment->getAssignmentDestination()->getDestinationCountry()->getName() : "";
                }
            }

            $return = ['success' => true, 'data' => array_values($assignments_array)];
        } catch (\PDOException $e) {
            $return = ['success' => false, 'message' => $e->getMessage()];
        } catch (Exception $e) {
            $return = ['success' => false, 'message' => $e->getMessage()];
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /*
     *
     */
    public function getDashboardMembersAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex();

        if (ModuleModel::$user_profile->isGmsAdminOrManager()) {
            $contacts = UserProfile::getGmsWorkers();
        } else {
            $contacts = [ModuleModel::$user_profile];
        }

        $this->response->setJsonContent(['success' => true, 'data' => $contacts]);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getCountriesOriginRelocationMapAction()
    {
        $this->view->disable();
        $this->checkAjax(['GET', 'PUT']);
        $this->checkAclIndex();

        $params = Helpers::__getRequestValuesArray();
        $countriesArray = CountryExt::__getCountRelocationByCountryOriginGSM(ModuleModel::$company->getId(), $params);
        $mapData = [];
        foreach ($countriesArray as $countryItem) {
            $mapData[$countryItem['cio']] = intval($countryItem['number']);
        }
        $this->response->setJsonContent(['success' => true, 'data' => $mapData]);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getCountriesDestinationRelocationMapAction()
    {
        $this->view->disable();
        $this->checkAjax(['GET', 'PUT']);
        $this->checkAclIndex();

        $params = Helpers::__getRequestValuesArray();

        $countriesArray = CountryExt::__getCountRelocationByCountryDestinationGSM(ModuleModel::$company->getId(), $params);
        $mapData = [];
        foreach ($countriesArray as $countryItem) {
            $mapData[$countryItem['cio']] = intval($countryItem['number']);
        }
        $this->response->setJsonContent(['success' => true, 'data' => $mapData]);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getCountriesOriginRelocationAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex();

        $countriesArray = CountryExt::__getCountRelocationByCountryOriginGSM(ModuleModel::$company->getId());
        $mapData = [];
        foreach ($countriesArray as $countryItem) {
            $mapData[] = ['country_cio' => $countryItem['cio'], 'country_name' => $countryItem['label'], 'count_relocation' => $countryItem['number']];
        }
        $this->response->setJsonContent(['success' => true, 'data' => $mapData]);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getAccountsOriginRelocationAction()
    {
        $this->view->disable();
        $this->checkAjax(['GET', 'PUT']);
        $this->checkAclIndex();

        $params = Helpers::__getRequestValuesArray();

        $companies = Company::__getCountRelocationByAccountGMS(ModuleModel::$company->getId(), $params);

        $mapData = [];
        foreach ($companies as $companyItem) {
            $mapData[] = ['company_name' => $companyItem['company_name'], 'count_relocation' => $companyItem['number']];
        }

        $this->response->setJsonContent(['success' => true, 'data' => $mapData]);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getBookersOriginRelocationAction()
    {
        $this->view->disable();
        $this->checkAjax(['GET', 'PUT']);
        $this->checkAclIndex();
        $params = Helpers::__getRequestValuesArray();

        $companies = Company::__getCountRelocationByBookerGMS(ModuleModel::$company->getId(), $params);
        $mapData = [];
        foreach ($companies as $companyItem) {
            $mapData[] = ['company_name' => $companyItem['company_name'], 'count_relocation' => $companyItem['number']];
        }

        $this->response->setJsonContent(['success' => true, 'data' => $mapData]);
        return $this->response->send();
    }

    /**
     * Get more information for panel
     * @return mixed
     */
    public function getMoreDashboardInfosAction()
    {
        $averageDurationDays = 0;
        $averageOfServicesPerRelocation = 0;
        // Count assignee
        $countActiveAssignee = Employee::searchSimpleList(['limit' => 1, 'active' => Employee::ACTIVE_YES])['total_items'];
        $countAllAssignee = Employee::searchSimpleList(['limit' => 1])['total_items'];

        // Count ongoing assignment
        $countAllAssignmentOnGoing = Assignment::__findWithFilter(['limit' => 1, 'active' => true])['total_items'];

        // Calculate average of services in per relocation.
        $allRelocations = Relocation::__findWithFilter(['limit' => 1])['total_items'];

        $allRelocationServices = RelocationServiceCompany::__findWithFilter(['limit' => 1])['total_items'];

        //if no relocation
        if ($allRelocations > 0) {
            $averageOfServicesPerRelocation = round($allRelocationServices / $allRelocations, 1);
        } else {
            $averageOfServicesPerRelocation = 0;
        }


        // Calculate average time spent between Service is in Progress and Service is Done

//        $relocationServices = RelocationServiceCompany::find([
//            'conditions' => 'status = :status:',
//            'bind' => [
//                'status' => RelocationServiceCompany::STATUS_ACTIVE
//            ]
//        ]);

        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\RelocationServiceCompany', 'RelocationServiceCompany');
        $queryBuilder->distinct(true);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Relocation', 'Relocation.id = RelocationServiceCompany.relocation_id', 'Relocation');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\EntityProgress', 'EntityProgress.object_uuid = RelocationServiceCompany.uuid', 'EntityProgress');
        $queryBuilder->andwhere("RelocationServiceCompany.status = :status:", [
            'status' => RelocationServiceCompany::STATUS_ACTIVE
        ]);
        $queryBuilder->andwhere("EntityProgress.value = :value:", [
            'value' => 100
        ]);
        $queryBuilder->andwhere("Relocation.creator_company_id = :creator_company_id:", [
            'creator_company_id' => ModuleModel::$company->getId()
        ]);
        $queryBuilder->limit(1000);
        $queryBuilder->groupBy('RelocationServiceCompany.id');
        $queryBuilder->orderBy(['RelocationServiceCompany.id DESC']);

        $relocationServices = $queryBuilder->getQuery()->execute();

        $allDayLefts = [];
        foreach ($relocationServices as $relocationService) {
            if ($relocationService->getEntityProgressValue() == 100) {
                $firstServiceProgress = $relocationService->getEntityProgressList()->getFirst();
                $lastServiceProgress = $relocationService->getEntityProgressListFirstItem()->getFirst();

                // Calculate days remain

                $dayleft = strtotime($firstServiceProgress->getCreatedAt()) - strtotime($lastServiceProgress->getCreatedAt());
                $allDayLefts[] = $dayleft;
            }
        }
        if (count($allDayLefts) > 0) {
            $averageDurationDays = (array_sum($allDayLefts)) / count($allDayLefts);
            $averageDurationDays = round($averageDurationDays / (60 * 60 * 24), 2);
        } else {
            $averageDurationDays = round((array_sum($allDayLefts)) / (60 * 60 * 24), 2);
        }


        //Get cancel relocation
        $allRelocationsCancel = Relocation::__findWithFilter(['limit' => 1, 'statuses' => [Relocation::STATUS_CANCELED]])['total_items'];
        // Get Total HR account
        $user_group_ids = UserGroup::__getHrRoleIds();
        $allHrAccounts = Company::__findHrWithFilter(['limit' => 1])['total_items'];
        // Get Invitation
        $arrPendingAndSuccessStatus = [InvitationRequest::STATUS_PENDING, InvitationRequest::STATUS_SUCCESS];
        $allInvitations = InvitationRequest::count([
            'conditions' => 'is_deleted = :is_deleted: AND from_company_id = :from_company_id: AND status IN ({status_array:array})',
            'bind' => [
                'is_deleted' => 0,
                'from_company_id' => ModuleModel::$company->getId(),
                'status_array' => $arrPendingAndSuccessStatus
            ]
        ]);

        // Get Questionnaire Answered
//        $allQuestionnaireAnswered = NeedFormRequest::count([
//            'conditions' => 'status = :status:',
//            'bind' => [
//                'status' => NeedFormRequest::STATUS_ANSWERED
//            ]
//        ]);

        $return = [
            'success' => true,
            'data' => [
                'activated_assignees' => $countActiveAssignee,
                'total_assignees' => $countAllAssignee,
                'ongoing_assignments' => $countAllAssignmentOnGoing,
                'average_services_per_relocation' => $averageOfServicesPerRelocation,
                'average_services_duration' => $averageDurationDays,
                'all_relocation_active' => $allRelocations,
                'all_relocation_services' => $allRelocationServices,
                'all_relocation_cancel' => $allRelocationsCancel,
                'requests_of_service' => Assignment::__countActiveRequests(),
                'total_hr_accounts' => $allHrAccounts,
                'invitations' => $allInvitations,
//                'all_questionnaire_answered' => $allQuestionnaireAnswered
            ]
        ];

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     *  list of due tasks for dashboard
     *   should use query builder
     */
    public function dueTasksListAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $params = array();
        $params['length'] = Helpers::__getRequestValue('limit');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['open'] = true;
        $params['due_date_begin'] = Helpers::__getRequestValue('due_date_begin');
        $params['due_date_end'] = Helpers::__getRequestValue('due_date_end');

        if (!ModuleModel::$user_profile->isAdminOrManager()) {
            $params['owners'] = [ModuleModel::$user_profile->getUuid()];
        }

        $ordersConfig = [];
        $orders = Helpers::__getRequestValue('orders');
        if ($orders && is_array($orders)) {
            $orders = reset($orders);
            if (is_object($orders)) {
                $ordersConfig[] = [
                    "field" => strtolower($orders->column),
                    "order" => isset($orders->descending) && $orders->descending == true ? 'desc' : 'asc'
                ];
            }
        }

        $return = Task::__findWithFilter(true, $params, $ordersConfig);
        if ($return['success'] == true) {
            $return['recordsFiltered'] = ($return['total_items']);
            $return['recordsTotal'] = ($return['total_items']);
            $return['length'] = ($return['total_items']);
            $return['draw'] = Helpers::__getRequestValue('draw');
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param string $object
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getReminderCounterByObjectAction($object = '')
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $timezone_offset = Helpers::__getHeaderValue(Helpers::TIMEZONE_OFFSET) * 60;
        //$timezone_offset = 0;
        $oneDaySecond = 24 * 60 * 60 - 1;
        $startTimeDay = Helpers::__getStartTimeOfDay() - $timezone_offset;
        $endTimeDay = $startTimeDay + $oneDaySecond;
        $return = ['success' => true, 'data' => []];
        $data = [];
        //today
        $today = ReminderConfig::__countByTime($startTimeDay, $endTimeDay, ModuleModel::$user_profile->getUuid());
        if (!$today['success']) {
            $return = $today;
            goto end_of_function;
        }
        $data['today'] = $today['count'];
        //yesterday
        $startTimeYesterday = strtotime('-1 day', $startTimeDay);
        $endTimeYesterday = $startTimeYesterday + $oneDaySecond;
        $yesterday = ReminderConfig::__countByTime($startTimeYesterday, $endTimeYesterday, ModuleModel::$user_profile->getUuid());
        if (!$yesterday['success']) {
            $return = $yesterday;
            goto end_of_function;
        }
        $data['yesterday'] = $yesterday['count'];
        //last 7 day
        $yesterdayTime = strtotime('-1 day', $startTimeDay);
        $startTimeLast7Days = strtotime('-8 days', $startTimeDay);
        $endTimeLast7Days = $yesterdayTime + $oneDaySecond;
        $last7Days = ReminderConfig::__countByTime($startTimeLast7Days, $endTimeLast7Days, ModuleModel::$user_profile->getUuid());
        if (!$last7Days['success']) {
            $return = $last7Days;
            goto end_of_function;
        }
        $data['last_seven_days'] = $last7Days['count'];
        //next 7 day
        $startTimeNext7Days = $endTimeDay;
        $endNext7Days = strtotime('+8 day', $startTimeDay);
        $next7Days = ReminderConfig::__countByTime($startTimeNext7Days, $endNext7Days, ModuleModel::$user_profile->getUuid());
        if (!$next7Days['success']) {
            $return = $next7Days;
            goto end_of_function;
        }
        $data['next_seven_days'] = $next7Days['count'];

        $return['data'] = $data;
        $return['today'] = $today;
        $return['yesterday'] = $yesterday;
        $return['last_seven_days'] = $last7Days;
        $return['next_seven_days'] = $next7Days;
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Get reminder items
     */
    public function getDashBoardReminderItemsAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');

        $type = Helpers::__getRequestValue('type');
        $limitSearch = Helpers::__getRequestValue('limit');
        $startKeySearch = Helpers::__getRequestValue('lastObject') ? Helpers::__getRequestValue('lastObject') : (
        $this->request->get('lastObject') ? $this->request->get('lastObject') : false);
        $timezone_offset = Helpers::__getHeaderValue(Helpers::TIMEZONE_OFFSET) * 60;
        //$timezone_offset = 0;
        $oneDaySecond = 24 * 60 * 60 - 1;
        $startTimeDay = Helpers::__getStartTimeOfDay() - $timezone_offset;
        $endTimeDay = $startTimeDay + $oneDaySecond;
        switch ($type) {
            case 'YESTERDAY_TEXT':
                $startTimeDay = strtotime('-1 day', $startTimeDay);
                $endTimeDay = $startTimeDay + $oneDaySecond;
                break;
            case 'LAST_7_DAY_TEXT':
                $yesterday = strtotime('-1 day', $startTimeDay);
                $startTimeDay = strtotime('-8 days', $startTimeDay);
                $endTimeDay = $yesterday + $oneDaySecond;
                break;
            case 'NEXT_7_DAY_TEXT':
                $next_sven_days = strtotime('+8 day', $startTimeDay);
                $startTimeDay = $endTimeDay;
                $endTimeDay = $next_sven_days;
                break;
            case 'NEXT_LAST_7_DAY_TEXT':
                $next_sven_days = strtotime('+8 day', $startTimeDay);
                $startTimeDay = strtotime('-8 days', $startTimeDay);
                $endTimeDay = $next_sven_days;
                break;
            default:
                //TODAY
                break;
        }
        $params = [];
        $params['owner_profile_uuid'] = ModuleModel::$user_profile->getUuid();
        $params['start_at'] = $startTimeDay;
        $params['end_at'] = $endTimeDay;
        $params['limit'] = Helpers::__getRequestValue('limit');

        $ordersConfig = [];
        $orders = Helpers::__getRequestValue('orders');
        if ($orders && is_array($orders)) {
            $orders = reset($orders);
            if (is_object($orders)) {
                $ordersConfig[] = [
                    "field" => strtolower($orders->column),
                    "order" => isset($orders->descending) && $orders->descending == true ? 'desc' : 'asc'
                ];
            }
        }

        $return = ReminderConfig::__findWithFilters($params, $ordersConfig);
        $return['params'] = $params;
        goto end_of_function;

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();


//        try {
//            $dynamoReminder = RelodayDynamoORM::factory('\Reloday\Gms\Models\DynamoReminderConfig')
//                ->index('CompanyIdStartAt')
//                ->whereWithExpression('company_id = :company_id AND start_at BETWEEN :d1 AND :d2')
//                ->filterWithExpression("owner_profile_uuid = :owner_profile_uuid");
//
//            $bind[':company_id']["N"] = (string)intval(ModuleModel::$company->getId());
//            $bind[':d1']["N"] = (string)$startTimeDay;
//            $bind[':d2']["N"] = (string)$endTimeDay;
//            $bind[':owner_profile_uuid']["S"] = ModuleModel::$user_profile->getUuid();
//            if ($startKeySearch != false) {
//                $dynamoReminder->setExclusiveStartKey(json_decode(json_encode($startKeySearch), true));
//            }
//
//            $data = $dynamoReminder->setExpressionAttributeValues($bind)
//                ->limit($limitSearch)
//                ->findMany(['ScanIndexForward' => false]);
//
//
//            $reminderArray = [];
//            if (count($data) > 0) {
//                foreach ($data as $item) {
//                    $data = [];
//                    $data['task_name'] = $item->getTask()->getName();
//                    $data['object_uuid'] = $item->getObjectUuid();
//                    $data['start_at'] = $item->getStartAt();
//                    $data['end_at'] = $item->getEndAt();
//                    $task = $item->getTask();
//                    if ($task) {
//                        $employee = $task->getEmployee();
//                    }
//                    $data['employee_name'] = isset($employee) && $employee ? $employee->getFullname() : null;
//                    $data['employee_uuid'] = isset($employee) && $employee ? $employee->getUuid() : null;
//                    $reminderArray[] = $data;
//                }
//            }
//
//            $lastObject = $dynamoReminder->getLastEvaluatedKey();
//        } catch (\Exception $e) {
//            \Sentry\captureException($e);
//            $return = [
//                'bind' => $bind,
//                'success' => false,
//                'exception' => $e,
//                'data' => [],
//                'lastObject' => null,
//                'count' => 0,
//            ];
//            goto end_of_function;
//        }
//
//        $return = [
//            'success' => true,
//            'data' => $reminderArray,
//            'lastObject' => $lastObject,
//            'start' => $startTimeDay,
//            'end' => $endTimeDay,
//            'count' => $dynamoReminder->getCount(),
//        ];
    }


    /**
     * list on service relocation
     * @return mixed
     */
    public function getRelocationServicesListAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');

        $data = $this->request->getJsonRawBody();
        $user_profile_uuid = isset($data->user_profile_uuid) && $data->user_profile_uuid != '' ? $data->user_profile_uuid : null;
        if ($user_profile_uuid == null) {
            $user_profile_uuid = ModuleModel::$user_profile->getUuid();
        }
        $created_at_period = isset($data->created_at_period) && $data->created_at_period != '' ? $data->created_at_period : null;

        $page = Helpers::__getRequestValue('page');
        $return = ['success' => false, 'message' => 'ASSIGNMENT_NOT_FOUND_TEXT', 'count' => 0];
        $userMembers = DataUserMember::getAllObjectsByOwner($user_profile_uuid);
        if (count($userMembers['data']) == 0) {
            $return = ['success' => true, 'data' => [], 'count' => 0];
            goto end_of_function;
        }
        $object_uuids = [];
        foreach ($userMembers['data'] as $userMember) {
            $object_uuids[] = $userMember->getObjectUuid();
        }

        /** get array of relocation_service_company uuid */
        try {
            $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
            $queryBuilder->addFrom('\Reloday\Gms\Models\RelocationServiceCompany', 'RelocationServiceCompany');
            $queryBuilder->distinct(true);
            $queryBuilder->leftJoin('\Reloday\Gms\Models\Relocation', 'Relocation.id = RelocationServiceCompany.relocation_id', 'Relocation');
            $queryBuilder->andwhere("RelocationServiceCompany.status = :status:", [
                'status' => RelocationServiceCompany::STATUS_ACTIVE
            ]);

            $queryBuilder->andwhere("Relocation.active = :relocation_active:", [
                'relocation_active' => Relocation::STATUS_ACTIVATED
            ]);

            $queryBuilder->andwhere("Relocation.status <> :relocation_status:", [
                'relocation_status' => Relocation::STATUS_CANCELED
            ]);

            $queryBuilder->andwhere("RelocationServiceCompany.uuid IN ({uuids:array} )",
                [
                    'uuids' => $object_uuids
                ]
            );

            $queryBuilder->andwhere("RelocationServiceCompany.progress = :progress: and RelocationServiceCompany.progress_value < 100", [
                'progress' => RelocationServiceCompany::STATUS_IN_PROCESS
            ]);

            $queryBuilder->groupBy('RelocationServiceCompany.id');
            $queryBuilder->orderBy(['RelocationServiceCompany.created_at DESC']);

            if ($created_at_period != null && is_string($created_at_period) && $created_at_period == '1_month') {
                $created_at = date('Y-m-d', strtotime('- 1 month'));
                $queryBuilder->andWhere(" RelocationServiceCompany.created_at >=  DATE('" . $created_at . "')");
            } elseif ($created_at_period != null && is_string($created_at_period) && $created_at_period == '3_months') {
                $created_at = date('Y-m-d', strtotime('- 3 months'));
                $queryBuilder->andWhere(" RelocationServiceCompany.created_at >=  DATE('" . $created_at . "')");
            } elseif ($created_at_period != null && is_string($created_at_period) && $created_at_period == '6_months') {
                $created_at = date('Y-m-d', strtotime('- 6 months'));
                $queryBuilder->andWhere(" RelocationServiceCompany.created_at >=  DATE('" . $created_at . "')");
            } elseif ($created_at_period != null && is_string($created_at_period) && $created_at_period == '1_year') {
                $created_at = date('Y-m-d', strtotime('- 1 year'));
                $queryBuilder->andWhere(" RelocationServiceCompany.created_at >=  DATE('" . $created_at . "')");
            }

            $paginator = new PaginatorQueryBuilder([
                'builder' => $queryBuilder,
                'limit' => 20,
                'page' => isset($page) && is_numeric($page) && $page > 0 ? $page : 1
            ]);
            $paginate = $paginator->getPaginate();
            $assignmentArray = [];
            foreach ($paginate->items as $item) {
                $assignmentArray[] = [
                    'uuid' => $item->getUuid(),
                    'name' => $item->getName(),
                    'number' => $item->getNumber(),
                    'relocation_uuid' => $item->getRelocation()->getUuid(),
                    'relocation_name' => $item->getRelocation()->getName(),
                    'employee_uuid' => $item->getRelocation()->getEmployee()->getUuid(),
                    'employee_name' => $item->getRelocation()->getEmployee()->getFullname(),
                    'progress' => $item->getEntityProgressValue()
                ];
            }

            $return = [
                'success' => true,
                'data' => $assignmentArray,
                'total_pages' => $paginate->total_pages,
                'current' => $paginate->current,
                'count' => $paginate->total_items
            ];
        } catch (\PDOException $e) {
            $return = ['success' => false, 'count' => 0, 'message' => $e->getMessage()];
        } catch (Exception $e) {
            $return = ['success' => false, 'count' => 0, 'message' => $e->getMessage()];
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /*
     * Count All Assignee
     */
    public function countAllAssigneesAction(){
        $countAllAssignee = Employee::searchSimpleList(['limit' => 1])['total_items'];
        $countActiveAssignee = Employee::searchSimpleList(['limit' => 1, 'active' => Employee::ACTIVE_YES])['total_items'];

        $return = [
            'success' => true,
            'data' => [
                'activated_assignees' => $countActiveAssignee,
                'total_assignees' => $countAllAssignee,
            ]
        ];

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /*
     * Count Ongoing Assignment
     */
    public function countOngoingAssignmentAction(){
        // Count ongoing assignment
        $countAllAssignmentOnGoing = Assignment::__findWithFilter(['limit' => 1, 'active' => true, 'is_count_all' => true])['total_items'];

        $return = [
            'success' => true,
            'data' => $countAllAssignmentOnGoing
        ];

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /*
     * Count All Relocation
     */
    public function countAllRelocationsAction(){
        // Calculate average of services in per relocation.
        $allRelocations = Relocation::__findWithFilter(['limit' => 1, 'is_count_all' => true])['total_items'];
        //Cancel relocation
        $allRelocationsCancel = Relocation::__findWithFilter(['limit' => 1, 'statuses' => [Relocation::STATUS_CANCELED], 'is_count_all' => true])['total_items'];
        $allRelocationServices = RelocationServiceCompany::__findWithFilter(['limit' => 1, 'is_count_all' => true])['total_items'];


        //if no relocation
        if ($allRelocations > 0) {
            $averageOfServicesPerRelocation = round($allRelocationServices / $allRelocations, 1);
        } else {
            $averageOfServicesPerRelocation = 0;
        }

        $return = [
            'success' => true,
            'data' => [
                'all_relocation_active' => $allRelocations,
                'all_relocation_cancel' => $allRelocationsCancel,
                'all_relocation_services' => $allRelocationServices,
                'average_services_per_relocation' => $averageOfServicesPerRelocation,
            ]
        ];

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function countRequestServicesAction(){
        $return =  [
            'success' => true,
            'data' =>  Assignment::__countActiveRequests(),
        ];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function getAverageServiceDurationAction(){
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\RelocationServiceCompany', 'RelocationServiceCompany');
        $queryBuilder->distinct(true);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Relocation', 'Relocation.id = RelocationServiceCompany.relocation_id', 'Relocation');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\EntityProgress', 'EntityProgress.object_uuid = RelocationServiceCompany.uuid', 'EntityProgress');
        $queryBuilder->andwhere("RelocationServiceCompany.status = :status:", [
            'status' => RelocationServiceCompany::STATUS_ACTIVE
        ]);
        $queryBuilder->andwhere("EntityProgress.value = :value:", [
            'value' => 100
        ]);
        $queryBuilder->andwhere("Relocation.creator_company_id = :creator_company_id:", [
            'creator_company_id' => ModuleModel::$company->getId()
        ]);
        $queryBuilder->limit(1000);
        $queryBuilder->groupBy('RelocationServiceCompany.id');
        $queryBuilder->orderBy(['RelocationServiceCompany.id DESC']);

        $relocationServices = $queryBuilder->getQuery()->execute();

        $allDayLefts = [];
        foreach ($relocationServices as $relocationService) {
            if ($relocationService->getEntityProgressValue() == 100) {
                $firstServiceProgress = $relocationService->getEntityProgressList()->getFirst();
                $lastServiceProgress = $relocationService->getEntityProgressListFirstItem()->getFirst();

                // Calculate days remain

                $dayleft = strtotime($firstServiceProgress->getCreatedAt()) - strtotime($lastServiceProgress->getCreatedAt());
                $allDayLefts[] = $dayleft;
            }
        }
        if (count($allDayLefts) > 0) {
            $averageDurationDays = (array_sum($allDayLefts)) / count($allDayLefts);
            $averageDurationDays = round($averageDurationDays / (60 * 60 * 24), 2);
        } else {
            $averageDurationDays = round((array_sum($allDayLefts)) / (60 * 60 * 24), 2);
        }

        $return = [
            'success' => true,
            'data' => $averageDurationDays
        ];

        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function getInvitedAccountInfosAction(){
        $allHrAccounts = Company::__findHrWithFilter(['limit' => 1], [], false)['total_items'];
        // Get Invitation
        $arrPendingAndSuccessStatus = [InvitationRequest::STATUS_PENDING, InvitationRequest::STATUS_SUCCESS];
        $allInvitations = InvitationRequest::count([
            'conditions' => 'is_deleted = :is_deleted: AND from_company_id = :from_company_id: AND status IN ({status_array:array})',
            'bind' => [
                'is_deleted' => 0,
                'from_company_id' => ModuleModel::$company->getId(),
                'status_array' => $arrPendingAndSuccessStatus
            ]
        ]);

        $return =  [
            'success' => true,
            'data' =>  [
                'total_hr_accounts' => $allHrAccounts,
                'invitations' => $allInvitations,
            ]
        ];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function getMembersWorkloadAction(){
        $this->view->disable();
        $this->checkAjax('PUT');

        $page = Helpers::__getRequestValue('page');
        $limit = Helpers::__getRequestValue('limit');
        $query = Helpers::__getRequestValue('query');

        $return = UserProfile::__getMembersWorkload([
            'page' => $page,
            'limit' => $limit,
            'query' => $query,
        ]);

        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
