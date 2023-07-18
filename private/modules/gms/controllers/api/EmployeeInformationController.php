<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Models\Office;
use Reloday\Gms\Models\Assignment;
use Reloday\Gms\Models\AssignmentBasic;
use Reloday\Gms\Models\AssignmentDestination;
use Reloday\Gms\Models\AssignmentType;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\Contract;
use Reloday\Gms\Models\Country;
use Reloday\Gms\Models\Department;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\EmployeeInContract;
use Reloday\Gms\Models\HousingProposalDetail;
use Reloday\Gms\Models\HousingProposals;
use Reloday\Gms\Models\HousingProposalsN;
use Reloday\Gms\Models\MediaAttachment;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Property;
use Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\Team;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class EmployeeInformationController extends BaseController
{
    /**
     * @Route("/employeeinformation", paths={module="gms"}, methods={"GET"}, name="gms-employeeinformation-index")
     */
    public function indexAction()
    {
        $this->view->disable();

        $company_id = ModuleModel::$user_profile->getCompanyId();
        $result = [];

        // Find all company has contract with current GMS
        $contract = Contract::find([
            'from_company_id=' . (int)$company_id . ' OR to_company_id=' . (int)$company_id
        ]);

        // Find all employee in contract
        if (count($contract) > 0) {
            $contract_ids = [];
            foreach ($contract as $item) {
                $contract_ids[] = $item->getId();
            }

            // Find all employee in list contract
            $employee_in_contract = EmployeeInContract::find(['contract_id IN (' . implode(',', $contract_ids) . ')']);
            if (count($employee_in_contract) > 0) {
                $employee_ids = [];
                foreach ($employee_in_contract as $em) {
                    $employee_ids[] = $em->getEmployeeId();
                }

                // Load list of employee activated
                $employees = Employee::find([
                    'id IN (' . implode(',', $employee_ids) . ')'
                    . ' AND status=' . Employee::STATUS_ACTIVATED
                ]);

                if (count($employees) > 0) {
                    foreach ($employees as $index => $employee) {
                        $result[$index] = $employee->toArray();
                        $company = Company::findFirst($employee->getCompanyId());
                        $result[$index]['company_name'] = $company->getName();
                        $result[$index]['avatar'] = $employee->getAvatar();
                    }
                }

            }
        }


        $this->response->setJsonContent([
            'success' => true,
            'list' => $result
        ]);
        $this->response->send();
    }

    public function detailAction($id = 0)
    {
        $this->view->disable();
        $employee = Employee::findFirstById((int)$id);

        if ($employee instanceof Employee) {
            $data = $employee->toArray();
            $company = Company::findFirst((int)$employee->getCompanyId());
            if ($company instanceof Company) {
                $data['company_name'] = $company->getName();
            }

            $data['avatar'] = $employee->getAvatar();

            $result = [
                'success' => true,
                'data' => $data
            ];
        } else {
            $result = [
                'success' => false,
                'message' => 'EMPLOYEE_NOT_FOUND_TEXT'
            ];
        }

        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * @param $number
     */
    public function itemAction( $number ){
        $this->view->disable();

        $result = [
            'success' => false,
            'message' => 'EMPLOYEE_NOT_FOUND_TEXT'
        ];
        if( $number != '' ) {
            $employee = Employee::getEmployeeByNumber( $number );
            if ($employee instanceof Employee && $employee->belongsToGms() == true) {
                $data = $employee->toArray();
                $company = $employee->getCompany();
                if ($company instanceof Company) {
                    $data['company_name'] = $company->getName();
                }
                $data['avatar'] = $employee->getAvatar();
                $result = [
                    'success' => true,
                    'data' => $data
                ];
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Load list of offices, departments and teams
     * @param int $company_id
     */
    public function moreInfoAction($company_id = 0)
    {
        $this->view->disable();

        $offices_arr = [];
        $departments_arr = [];
        $teams_arr = [];

        $offices = Office::find('company_id=' . (int)$company_id);
        if (count($offices) > 0) {
            $office_ids = [];
            foreach ($offices as $office) {
                $office_ids[] = $office->getId();
                $offices_arr[$office->getId()] = $office->toArray();
            }

            $departments = Department::find('office_id IN (' . implode(',', $office_ids) . ')');
            if (count($departments) > 0) {
                $department_ids = [];
                foreach ($departments as $department) {
                    $department_ids[] = $department->getId();
                    $departments_arr[$department->getId()] = $department->toArray();
                }

                $teams = Team::find('department_id IN (' . implode(',', $department_ids) . ')');
                if (count($teams) > 0) {
                    foreach ($teams as $team) {
                        $teams_arr[$team->getId()] = $team->toArray();
                    }
                }
            }
        }

        $this->response->setJsonContent([
            'success' => true,
            'offices' => $offices_arr,
            'departments' => $departments_arr,
            'teams' => $teams_arr
        ]);
        $this->response->send();
    }

    public function documentAction($ee_id = 0)
    {
        $this->view->disable();

        exit(json_encode([
            'success' => true,
            'data' => $this->__loadListDocument($ee_id)
        ]));
    }

    public function assignmentAction($ee_id = 0)
    {
        $this->view->disable();

        exit(json_encode([
            'success' => true,
            'data' => $this->__loadListAssignment($ee_id)
        ]));
    }

    public function hrAction($ee_id = 0)
    {
        $this->view->disable();

        exit(json_encode([
            'success' => true,
            'data' => $this->__loadListAssignment($ee_id, true)
        ]));
    }

    public function housingAction($ee_id = 0)
    {
        $this->view->disable();

        // Find last relocation of this employee
        $relocation = Relocation::findFirst([
            'employee_id=' . (int)$ee_id,
            'order' => 'id DESC'
        ]);
        if (!$relocation instanceof Relocation) {
            $this->response->setJsonContent([
                'success' => false,
                'message' => 'RELOCATION_NOT_FOUND_TEXT'
            ]);
            $this->response->send();
            return;
        }

        // Find housing proposal detail
        $housing_proposal = HousingProposals::findFirst('relocation_id=' . $relocation->getId());
        if ($housing_proposal instanceof HousingProposals) {
            $housing_proposal_n = HousingProposalsN::find(['housing_proposal_id=' . $housing_proposal->getId()]);
            if (count($housing_proposal_n) > 0) {
                $ids = [];
                foreach ($housing_proposal_n as $item) {
                    $ids[] = $item->getId();
                }
                $proposal_detail = HousingProposalDetail::findFirst(
                    'housing_proposal_n_id IN (' . implode(',', $ids) . ') AND selected=1'
                );
                if ($proposal_detail instanceof HousingProposalDetail) {
                    // Find property selected
                    $property = Property::findFirst((int)$proposal_detail->getPropertyId());
                    $proposal_detail = $proposal_detail->toArray();
                    $proposal_detail['slider'] = [];
                    if ($property instanceof Property) {
                        $proposal_detail['description'] = $property->getDescription();
                        $files = MediaAttachment::__load_attachments($property->getUuid());
                        if (count($files) > 0) {
                            foreach ($files as $file) {
                                $proposal_detail['slider'][] = [
                                    'image' => $file['image_data']['url_full']
                                ];
                            }
                        }
                    }

                    $this->response->setJsonContent([
                        'success' => true,
                        'relocation' => $relocation->toArray(),
                        'proposal' => $proposal_detail
                    ]);
                    $this->response->send();
                    return;

                }
            }
        }

        $this->response->setJsonContent([
            'success' => true,
            'relocation' => $relocation->toArray(),
            'proposal' => []
        ]);
        $this->response->send();
        return;
    }

    public function financialAction($ee_id = 0) {
        $this->view->disable();

        exit(json_encode([
            'success' => true,
            'data' => $this->__loadListAssignment($ee_id)
        ]));
    }

    ///////////////////
    // PRIVATE -------------
    ///////////////////
    private function __loadListDocument($ee_id = 0)
    {
        $result = [];

        /* Load list file uploaded by himself */
        // developer in next version ...

        /* Load document release in assignment */
        $assignments = Assignment::find([
            'employee_id=' . (int)$ee_id
        ]);

        $uuid = [];
        if (count($assignments) > 0) {
            foreach ($assignments as $assignment) {
                $uuid[$assignment->getNumber()] = $assignment->getUuid();
            }
        }

        /* Load document release in relocation */
        $relocation_list = Relocation::find(['']);

        if ($relocation_list instanceof Relocation) {

        }

        // Find list file by uuid
        $files = MediaAttachment::__load_attachments($uuid);

        if (count($files)) {
            $groups = [];
            foreach ($files as $file) {
                $image = false;
                if (in_array($file['file_extension'], ['gif', 'png', 'jpg', 'svg', 'jpeg'])) {
                    $image = true;
                }
                $groups[$file['uuid']] = [
                    'uuid' => $file['object_uuid'],
                    'image' => $image,
                    'extension' => $file['file_extension'],
                    'type' => $file['file_type'],
                    'name' => $file['name'],
                    'url' => $file['image_data']['url_full']
                ];
            }

            foreach ($uuid as $k => $v) {
                foreach ($groups as $inc => $group) {
                    if ($group['uuid'] == $v) {
                        unset($group['uuid']);
                        $result[$k][$inc] = $group;
                    }
                }
            }
        }

        return $result;

    }

    private function __loadListAssignment($ee_id = 0, $basic = false)
    {
        $assignments = Assignment::find([
            'employee_id=' . (int)$ee_id,
            'order' => 'end_date ASC'
        ]);

        $result = [];
        if (count($assignments) > 0) {
            foreach ($assignments as $k => $item) {
                $result[$k] = $item->toArray();
                // Get destination
                $assignment_des = AssignmentDestination::findFirst('id=' . (int)$item->getId());

                if ($assignment_des instanceof AssignmentDestination) {
                    $country = Country::findFirst((int)$assignment_des->getDestinationCountryId());
                    $result[$k] = array_merge($result[$k], $assignment_des->toArray());
                    if ($country instanceof Country) {
                        $result[$k]['destination_country'] = $country->getName();
                    }
                }
                $assignment_type = AssignmentType::findFirst((int)$item->getAssignmentTypeId());
                if ($assignment_type instanceof AssignmentType) {
                    $result[$k]['assignment_type'] = $assignment_type->getName();
                }

                if ($basic) {
                    $assignment_basic = AssignmentBasic::findFirst('id=' . (int)$item->getId());
                    if ($assignment_basic instanceof AssignmentBasic) {
                        $result[$k] = array_merge($result[$k], $assignment_basic->toArray());
                    }
                }
                if (strtotime($item->getEndDate()) > time()) {
                    $result[$k]['activated'] = true;
                }
            }
        }

        return $result;
    }

    /**
     *
     */
    public function menuAction( $number ){

        $this->view->disable();

        $this->checkAjaxGet();

        $action = "index";
        $access = $this->canAccessResource($this->router->getControllerName(), $action);
        if (!$access['success']) {
            exit(json_encode($access));
        }
        $result = [
            'success' => false,
            'message' => 'EMPLOYEE_NOT_FOUND_TEXT'
        ];

        if( $number != '' ) {
            $employee = Employee::getEmployeeByNumber( $number );
            if ($employee instanceof Employee && $employee->belongsToGms() == true) {
                $menus_items = array (
                    0 =>
                        array (
                            'text' => 'Information',
                            'sref' => 'app.employees-page.view.information',
                        ),
                    1 =>
                        array (
                            'text' => 'Documents',
                            'sref' => 'app.employees-page.view.documents',
                        ),
                    2 =>
                        array (
                            'text' => 'Assignments',
                            'sref' => 'app.employees-page.view.assignments',
                        ),
                    3 =>
                        array (
                            'text' => 'Compensation',
                            'sref' => 'app.employees-page.view.compensation',
                        ),
                    4 =>
                        array (
                            'text' => 'Benefits',
                            'sref' => 'app.employees-page.view.benefits',
                        ),
                    5 =>
                        array (
                            'text' => 'HR',
                            'sref' => 'app.employees-page.view.hr',
                        ),
                    6 =>
                        array (
                            'text' => 'Financial',
                            'sref' => 'app.employees-page.view.financial',
                        ),
                    7 =>
                        array (
                            'text' => 'Housing',
                            'sref' => 'app.employees-page.view.housing',
                        ),
                    8 =>
                        array (
                            'text' => 'Immigration',
                            'sref' => 'app.employees-page.view.immigration',
                        ),
                    9 =>
                        array (
                            'text' => 'HistoryOld',
                            'sref' => 'app.employees-page.view.history.service',
                        ),
                );
                if (count($menus_items) > 0) {
                    foreach ($menus_items as $key => $menu_item) {
                        $menus_items[$key]['params'] = ['number' => $number];
                    }
                }
            }
        }
        $this->response->setJsonContent($menus_items);
        return $this->response->send();
    }

}
