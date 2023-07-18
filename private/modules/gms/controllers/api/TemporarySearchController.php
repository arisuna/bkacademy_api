<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Gms\Models\HousingProposalDetail;
use Reloday\Gms\Models\HousingProposals;
use Reloday\Gms\Models\HousingProposalsN;
use Reloday\Gms\Models\MediaAttachment;
use Reloday\Gms\Models\ServiceCompany;
use Reloday\Gms\Models\PropertyType;
use Reloday\Gms\Help\Utils;
use Reloday\Gms\Models\Assignment;
use Reloday\Gms\Models\AssignmentDestination;
use Reloday\Gms\Models\AssignmentType;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\HomeSearchRequest;
use Reloday\Gms\Models\HomeSearchSuggestedProperty;
use Reloday\Gms\Models\MemberType;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Property;
use Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\RelocationServiceCompany;
use Reloday\Gms\Models\Service;
use Reloday\Gms\Models\ServiceCompanyHasServiceProvider;
use Reloday\Gms\Models\ServiceProviderCompany;
use Reloday\Gms\Models\Task;
use Reloday\Gms\Models\UserMember;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class TemporarySearchController extends BaseController
{
    /**
     * @Route("/homesearch", paths={module="gms"}, methods={"GET"}, name="gms-homesearch-index")
     * @param string $uuid
     */
    public function dashboardAction($uuid = '')
    {
        $this->view->disable();

        $relocation = Relocation::findFirst(['uuid="' . addslashes($uuid) . '"']);
        if (!$relocation instanceof Relocation) {
            exit(json_encode([
                'success' => false,
                'message' => 'RELOCATION_NOT_FOUND_TEXT'
            ]));
            // EXIT ----------------
        }

        /**
         * Init data
         */
        $task_list = [];
        $suggested_list = [];
        //$current_suggested_list = [];

        /**
         * Load list: vehicle_type, environment, property_type
         */
        // ----- property type ---------
        $property_type_list = PropertyType::find();
        if (count($property_type_list) > 0) {
            $tmp = [];
            foreach ($property_type_list as $item) {
                $tmp[$item->getId()] = $item->toArray();
            }
            $property_type_list = $tmp;
        } else {
            $property_type_list = [];
        }

        // 1. Load home search by relocation_id if existed
        // 2. If home search is not existed, check relocation has service 'Home search' or not
        // 3. If relocation doesn't include "Home search" service, redirect to relocation dashboard
        // 4. If "Home search" service found, create new "Home Search Request"
        // 4.1 Find task list of "Home Search", if it has task, add task to "Task"

        // step 1
        $home_search_request = HomeSearchRequest::findFirst([
            'relocation_id=' . (int)$relocation->getId() .
            ' AND type=' . HomeSearchRequest::TYPE_TEMPORARY .
            ' AND creator_company_id=' . ModuleModel::$user_profile->getCompanyId()]);
        if ($home_search_request instanceof HomeSearchRequest) {
            $home_search_uuid = $home_search_request->getUuid();
            $relocation_service_company_id = $home_search_request->getRelocationServiceCompanyId();

            $housing_proposal = HousingProposals::findFirst([
                'relocation_id=' . (int)$relocation->getId() . ' AND type=' . HomeSearchRequest::TYPE_TEMPORARY
            ]);
            if ($housing_proposal instanceof HousingProposals) {
                // Find list housing proposal has been suggested by time
                $housing_proposal_n = HousingProposalsN::find('housing_proposal_id=' . (int)$housing_proposal->getId());
                if (count($housing_proposal_n) > 0) {
                    foreach ($housing_proposal_n as $item) {
                        $suggested_list[$item->getCreatedAt()] = $item->getId();
                    }

                    $housing_proposal_detail_list = HousingProposalDetail::find('housing_proposal_n_id IN (' .
                        implode(',', $suggested_list) . ')');

                    if (count($housing_proposal_detail_list) > 0) {

                        $list = $suggested_list;

                        foreach ($housing_proposal_detail_list as $item) {
                            foreach ($list as $k => $housing_proposal_n_id) {
                                if ($item->getHousingProposalNId() == $housing_proposal_n_id) {
                                    if (!is_array($suggested_list[$k])) {
                                        $suggested_list[$k] = [];
                                    }
                                    $tmp = $item->toArray();
                                    // Find property type name
                                    if (!empty($property_type_list[$item->getPropertyTypeId()])) {
                                        $tmp['property_type_name'] = $property_type_list[$item->getPropertyTypeId()]['name'];
                                    }

                                    // Get description of property
                                    if ($item->getStatus() == HousingProposalDetail::STATUS_ACCEPTED) {
                                        $property = Property::findFirst((int)$item->getPropertyId());
                                        if ($property instanceof Property) {
                                            $tmp['description'] = $property->getDescription();
                                            // load image
                                            $images = MediaAttachment::__get_attachments_from_uuid($property->getUuid());
                                            if (count($images)) {
                                                $img_tmp = [];
                                                foreach ($images as $image) {
                                                    $img_tmp[] = $image['image_data']['url_full'];
                                                }
                                                $images = $img_tmp;
                                            }
                                            $tmp['images'] = $images;
                                        }
                                    }

                                    $suggested_list[$k][] = $tmp;
                                }
                            }
                        }
                    }

                }
            }

        } else {
            /**
             * step 2: find all current list service of this relocation
             */
            $relocation_service_company_list = RelocationServiceCompany::find([
                'conditions' => 'relocation_id=' . (int)$relocation->getId()
            ]);
            if (count($relocation_service_company_list)) {
                $service_id_list = [];
                foreach ($relocation_service_company_list as $_service) {
                    $service_id_list[$_service->getId()] = $_service->getServiceCompanyId();
                }
                $service_home_search = ServiceCompany::findFirst([
                    'conditions' => 'service_id=' . Service::TEMPORARY_SEARCH . ' AND (id=' . implode(' OR id=', $service_id_list) . ')'
                ]);

                /**
                 * Check home search result
                 */
                if ($service_home_search instanceof ServiceCompany) {
                    // Find destination data from attachment
                    $assignment_destination = AssignmentDestination::findFirst($relocation->getAssignmentId());

                    /**
                     * Create new request for this relocation
                     */
                    $home_search_request = new HomeSearchRequest();
                    $relocation_service_company_id = array_search($service_home_search->getId(), $service_id_list);
                    $home_search_request = $home_search_request->__save([
                        'relocation_id' => $relocation->getId(),
                        'type' => HomeSearchRequest::TYPE_TEMPORARY,
                        'creator_company_id' => ModuleModel::$user_profile->getCompanyId(),
                        'relocation_service_company_id' => $relocation_service_company_id,
                        'specific_information' => json_encode([
                            'destination_city' => $assignment_destination instanceof AssignmentDestination ?
                                $assignment_destination->getDestinationCity() : '',
                            'destination_country_id' => $assignment_destination instanceof AssignmentDestination ?
                                $assignment_destination->getDestinationCountryId() : ''
                        ])
                    ]);
                    if (!$home_search_request instanceof HomeSearchRequest) {
                        // Save home search error
                        exit(json_encode([
                            'success' => true,
                            'has_service' => false,
                            'message' => $home_search_request['message'],
                            'detail' => $home_search_request['detail']
                        ]));
                        // EXIT ------------------
                    } else {
                        // Create home search success
                        $home_search_uuid = $home_search_request->getUuid();
                    }
                } else { // Home search not found
                    exit(json_encode([
                        'success' => true,
                        'has_service' => false,
                        'relocation' => $relocation->toArray(),
                        'message' => 'TEMPORARY_ACCOMMODATION_SERVICE_NOT_FOUND_TEXT'
                    ]));
                    // EXIT ----------------
                }
            } else { // No service, include home search
                exit(json_encode([
                    'success' => true,
                    'has_service' => false,
                    'relocation' => $relocation->toArray(),
                    'message' => 'TEMPORARY_ACCOMMODATION_SERVICE_NOT_FOUND_TEXT'
                ]));
                // EXIT ----------------
            }
        }


        $home_search = $home_search_request ? $home_search_request->toArray() : [];

        if (count($home_search)) {
            $home_search['specific_information'] = json_decode($home_search['specific_information']);
        }

        echo json_encode([
            'success' => true,
            'has_service' => true,
            'relocation' => $relocation->toArray(),
            'home_search_request' => $home_search,
            'suggested_list' => $suggested_list,
            //'current_suggested_list' => $current_suggested_list, // convert to object
            //'task_list' => $task_list,
            /*'task_status_text' => Task::$progress_status_text,
            'task_status_color' => Task::$progress_status_color,*/
            'relocation_service_company_id' => $relocation_service_company_id,
            'property_type_list' => $property_type_list
        ]);

    }

    public function calendarAction()
    {
        $this->view->disable();
        $result = [];

        $sources = HousingProposalDetail::find([
            'visit_on >= NOW()'
        ]);

        if (count($sources)) {
            $property_id = [];
            $employees_id = [];
            foreach ($sources as $source) {
                $property_id[] = $source->getPropertyId();
                $employees_id[] = $source->getEmployeeId();
            }

            // Find data of
            $property_list = Property::find('id IN (' . implode(',', $property_id) . ')');
            if (count($property_list) > 0) {
                $tmp = [];
                foreach ($property_list as $property) {
                    $tmp[$property->getId()] = $property->getDescription();
                }
                $property_list = $tmp;
            } else {
                $property_list = [];
            }

            // Find employee information
            $employee_list = Employee::find('id IN (' . implode(',', $employees_id) . ')');
            if (count($employee_list) > 0) {
                $tmp = [];
                foreach ($employee_list as $employee) {
                    $tmp[$employee->getId()] = $employee->getFirstname() . ' ' . $employee->getLastname();
                }
                $employee_list = $tmp;
            } else {
                $employee_list = [];
            }

            foreach ($sources as $source) {
                $result[] = [
                    'title' => $source->getPropertyName(),
                    'start' => $source->getVisitOn(),
                    'employee_id' => $source->getEmployeeId(),
                    'property_detail' => isset($property_list[$source->getPropertyId()])
                        ? $property_list[$source->getPropertyId()] : '',
                    'full_name' => isset($employee_list[$source->getEmployeeId()])
                        ? $employee_list[$source->getEmployeeId()] : ''
                ];
            }
        }

        echo json_encode([
            'success' => true,
            'source' => $result
        ]);
    }

    public function informationAction($relocation_id = 0)
    {

        $this->view->disable();

        /**
         * Save basic or specific information
         */
        if ($this->request->isPut()) {
            $specific_info = HomeSearchRequest::findFirst((int)$this->request->getPut('id'));
            if ($specific_info instanceof HomeSearchRequest) {
                $specific_info->setServiceProviderId((int)$this->request->getPut('service_provider_id'));
                $specific_info->setAuthorisedDate($this->request->getPut('authorised_date'));
                $specific_info->setStartDate($this->request->getPut('start_date'));
                $specific_info->setEndDate($this->request->getPut('end_date'));
                $specific_info->setCompletionDate($this->request->getPut('completion_date'));
                $specific_info->setExpiryDate($this->request->getPut('expiry_date'));
                $specific_info->setSpecificInformation($this->request->getPut('specific_information'));

                if ($specific_info->save()) {
                    // Make reminder related with event

                    $this->response->setJsonContent([
                        'success' => true,
                        'message' => 'SAVE_' . ($this->request->getPut('isBasic') ? 'BASIC' : 'SPECIFIC') . '_INFORMATION_SUCCESS_TEXT'
                    ]);
                } else {
                    $this->response->setJsonContent([
                        'success' => false,
                        'message' => 'SAVE_' . ($this->request->getPut('isBasic') ? 'BASIC' : 'SPECIFIC') . '_INFORMATION_FALSE_TEXT'
                    ]);
                }

                $this->response->send([
                    'success' => false,
                    'message' => 'HOME_SEARCH_REQUEST_NOT_FOUND_TEXT'
                ]);
            } else {

            }
            return;
            // -------------------
        }

        $result = [
            'success' => true,
            'employee' => [],
            'assignment' => [
                'type' => '',
                'status' => ''
            ],
            'provider_list' => []
        ];

        $relocation = Relocation::findFirst((int)$relocation_id);
        if ($relocation instanceof Relocation) {
            // Find employee data
            $employee = Employee::findFirst($relocation->getEmployeeId());
            if ($employee instanceof Employee) {
                $result['employee'] = [
                    'first_name' => $employee->getFirstname(),
                    'last_name' => $employee->getLastname(),
                    'contact_number' => $employee->getPhonework(),
                    'contact_email' => $employee->getWorkemail()
                ];

                // Load ASSIGNMENT is associated with relocation
                $assignment = Assignment::findFirst(['id=' . (int)$relocation->getAssignmentId()]);
                if ($assignment instanceof Assignment) {
                    $assignment_type = AssignmentType::findFirst((int)$assignment->getAssignmentTypeId());
                    if ($assignment_type instanceof AssignmentType) {
                        $result['assignment']['type'] = $assignment_type->getName();
                    }
                    $status = Assignment::$status_text;
                    $result['assignment']['status'] =
                        isset($status[$assignment->getStatus()]) ? $status[$assignment->getStatus()] : $assignment[Assignment::STATUS_PRE_APROVAL];
                } else {
                    $this->response->setJsonContent([
                        'success' => false,
                        'message' => 'ASSIGNMENT_NOT_FOUND_TEXT'
                    ]);
                    $this->response->send();
                    return;
                }

                // Load provider list in setup for service related
                $home_search_request = HomeSearchRequest::findFirst('relocation_id=' . (int)$relocation_id);
                if ($home_search_request instanceof HomeSearchRequest) {
                    $relocation_service_company = RelocationServiceCompany::findFirst($home_search_request->getRelocationServiceCompanyId());
                    if ($relocation_service_company instanceof RelocationServiceCompany) {
                        $service_provider_list = ServiceCompanyHasServiceProvider::find('service_company_id=' . $relocation_service_company->getServiceCompanyId());
                        if (count($service_provider_list) > 0) {
                            foreach ($service_provider_list as $k => $item) {
                                $result['provider_list'][$k] = $item->toArray();
                                $provider_company = ServiceProviderCompany::findFirst((int)$item->getServiceProviderCompanyId());
                                if ($provider_company instanceof ServiceProviderCompany) {
                                    $result['provider_list'][$k]['provider_name'] = $provider_company->getName();
                                }
                            }
                        }
                    }
                }

            } else { // Employee not found
                $this->response->setJsonContent([
                    'success' => false,
                    'message' => 'EMPLOYEE_NOT_FOUND_TEXT'
                ]);
                $this->response->send();
                return;
            }
        } else { // Relocation not found
            $this->response->setJsonContent([
                'success' => false,
                'message' => 'RELOCATION_NOT_FOUND_TEXT'
            ]);
            $this->response->send();
            return;
        }

        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * Search list of property
     */
    public function proposeAction()
    {
        $this->view->disable();

        // Get information to suggest
        $req = $this->request;
        $n_bedrooms = $req->get('n_bedroom');
        $n_bathrooms = $req->get('n_bathroom');
        $rental_min = $req->get('rental_min');
        $rental_max = $req->get('rental_max');
        $rent_currency = $req->get('rental_currency');
        $deposit_min = $req->get('deposit_min');
        $deposit_max = $req->get('deposit_max');
        $deposit_currency = $req->get('deposit_currency');
        $period = $req->get('period');
        $advance_period_from = $req->get('n_period_from');
        $advance_period_to = $req->get('n_period_to');
        $property_type = $req->get('property_type');

        $property_type = Property::TYPE_BUILDING;

        $town = $req->get('town');
        $country_id = $req->get('country_id');

        // Just find in current company of user login relation
        $conditions = "company_id=" . ModuleModel::$user_profile->getCompanyId() . " AND status=" . Property::STATUS_ACTIVATED;

        $conditions .= " AND town LIKE '%$town%'";
        if ((int)$country_id > 0)
            $conditions .= " AND country_id=" . (int)$country_id;
        if ((int)$n_bathrooms > 0) {
            $conditions .= " AND nb_bathrooms=" . (int)$n_bathrooms;
        }
        if ((int)$n_bedrooms > 0) {
            $conditions .= " AND nb_bedrooms=" . (int)$n_bedrooms;
        }

        if ((int)$property_type > 0) {
            $conditions .= " AND property_type_id=" . (int)$property_type;
        }



        // RENT condition
        if (is_numeric($rental_min)) {
            $conditions .= " AND rent_amount >= " . $rental_min;
        }
        if (is_numeric($rental_max)) {
            $conditions .= " AND rent_amount <= " . $rental_max;
        }
        if (!empty($rent_currency)) {
            $conditions .= " AND rent_currency = '" . $rent_currency . "'";
        }

        // DEPOSIT condition
        if (is_numeric($deposit_min)) {
            $conditions .= " AND deposit_amount >= " . $deposit_min;
        }
        if (is_numeric($deposit_max)) {
            $conditions .= " AND deposit_amount <= " . $deposit_max;
        }
        if (!empty($deposit_currency)) {
            $conditions .= " AND deposit_currency = '" . $deposit_currency . "'";
        }

        // PERIOD condition
        if (!empty($period)) {
            $conditions .= " AND rent_period='" . addslashes($period) . "'";
        }
        if (!empty($advance_period_from)) {
            $conditions .= " AND adv_rent_period >= " . (int)$advance_period_from;
        }
        if (!empty($advance_period_to)) {
            $conditions .= " AND adv_rent_period <= " . (int)$advance_period_to;
        }

        // Check available property
        // $conditions .= " AND available_from < CURDATE()";
        //var_dump( $conditions ); die();

        $properties = Property::find([
            'columns' => 'id, uuid, name, property_type_id, description, rent_amount, rent_currency, rent_period, address1, address2, town, country_id',
            'conditions' => $conditions
        ]);
        $list = [];
        if (count($properties) > 0) {
            foreach ($properties as $k => $property) {
                $list[$k] = $property->toArray();
                // load list image of property
                $file = MediaAttachment::__getLastAttachment($property->uuid);

                if (!empty($file))
                    $list[$k]['image'] = $file['image_data']['url_full'];
                else
                    // Default image
                    $list[$k]['image'] = '/resources/img/image_not_available.jpeg';
            }
        }

        // Print property list to client
        echo json_encode([
            'success' => true,
            'data' => $list
        ]);
    }

    /**
     * Add list of property for suggest to employee
     */
    public function addSuggestAction()
    {
        $this->view->disable();
        $req = $this->request;

        if ($req->isPost()) {

            $suggest = HousingProposals::findFirst([
                'relocation_id=' . (int)$req->getPost('relocation_id') .
                ' AND type=' . HousingProposals::TYPE_TEMPORARY_ACCOMMODATION
            ]);
            if (!$suggest instanceof HousingProposals) {
                $suggest = new HousingProposals();

                $suggest = $suggest->__save([
                    'type' => HousingProposals::TYPE_TEMPORARY_ACCOMMODATION
                ]);

                if (!$suggest instanceof HousingProposals) {
                    exit(json_encode([
                        'success' => false,
                        'message' => 'CREATE_NEW_PROPOSAL_FAIL_TEXT',
                        'detail' => $suggest['detail']
                    ]));
                }
            } // ---------------

            // Save time create new list for proposal
            $n_of_proposal = new HousingProposalsN();
            $n_of_proposal = $n_of_proposal->__save([
                'housing_proposal_id' => $suggest->getId()
            ]);

            if ($n_of_proposal instanceof HousingProposalsN) {
                // Make new item for proposal
                $item_list = $req->getPost('proposal_list');
                if (is_array($item_list)) {
                    foreach ($item_list as $key => $item) {

                        $detail = new HousingProposalDetail();
                        $item['housing_proposal_n_id'] = $n_of_proposal->getId();
                        $item['employee_id'] = $suggest->getEmployeeId();
                        $detail = $detail->__save($item);

                        if (!$detail instanceof HousingProposalDetail) {
                            // Just remove from list will be sent latter
                            unset($item_list[$key]);
                        }

                        unset($detail);
                    }

                    /**
                     * Send list property is suggested
                     */
                    // ...

                    echo json_encode([
                        'success' => true,
                        'message' => 'SUGGEST_AND_SEND_SUCCESSFUL_TEXT'
                    ]);

                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'SUGGEST_LIST_IS_EMPTY_TEXT'
                    ]);
                }
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'MAKE_NEW_SUGGEST_LIST_FAIL_TEXT'
                ]);
            }

        } else {
            echo json_encode([
                'success' => false,
                'message' => 'RESTRICT_ACCESS_TEXT'
            ]);
        }
    }


    // ---------------------
    // ---------------------
    // ---------------------
    // ---------------------
    // ---------------------
    // ---------------------
    public function saveTaskAction()
    {
        $this->view->disable();
        $result = ['success' => true];

        $task_number = Task::SERVICE_PREFIX;
        $params = [];

        $task = new Task();
        if ($this->request->isPost()) {
            $object_name = $this->request->getPost('linked_to');
            $object_id = $this->request->getPost('linked_activity');
            switch ($object_name) {
                case 'relocation':
                    $relocation = Relocation::findFirst((int)$object_id);
                    if ($relocation instanceof Relocation) {
                        $date = preg_split('/,/', $relocation->getCreatedAt());
                        $task_number .= substr($date[0], -2) . $date[1]
                            . ' ' . Utils::format4DigitNumber($relocation->getNumber()); // YYMM
                    } else {
                        exit(json_encode([
                            'result' => false,
                            'message' => 'RELOCATION_NOT_FOUND_TEXT'
                        ]));
                    }
                    break;
                default:
                    // assignment
                    $assignment = Assignment::findFirst((int)$object_id);
                    if ($assignment instanceof Assignment) {
                        $date = preg_split('/,/', $assignment->getCreatedAt());
                        $task_number .= substr($date[0], -2) . $date[1]
                            . ' ' . Utils::format4DigitNumber($assignment->getNumber()); // YYMM
                    } else {
                        exit(json_encode([
                            'result' => false,
                            'message' => 'RELOCATION_NOT_FOUND_TEXT'
                        ]));
                    }
                    break;
            }

            // Find all task with prefix
            $tasks = Task::find(['conditions' => "number LIKE '$task_number%"]);
            $task_number .= Utils::format4DigitNumber(count($tasks) + 1);
            $params['number'] = $task_number;
        }

        $model = $task->__save($params);
        if (!$model instanceof Task) {
            $result = $model;
        }

        // Response result to client
        echo json_encode($result);
    }

    /**
     * Delete task
     * @param int $task_id
     */
    public function removeTask($task_id = 0)
    {
        $this->view->disable();

        $task = Task::findFirst((int)$task_id);
        if ($task instanceof Task) {
            if ($task->delete()) {
                $result = ['success' => true];
            } else {
                $result = [
                    'success' => false,
                    'message' => 'DELETE_TASK_FAIL_TEXT'
                ];
            }
        } else {
            $result = [
                'success' => false,
                'message' => 'TASK_NOT_FOUND_TEXT'
            ];
        }

        echo json_encode($result);
    }

    /**
     * Update or create new service member
     */
    public function saveMemberAction()
    {
        $this->view->disable();

        $user_member = new UserMember();
        $model = $user_member->__save([
            'member_type_id' => MemberType::VIEWER,
            'object_uuid' => $this->request('uuid'),
            'user_login_id' => $this->request('user_id')
        ]);

        if ($model instanceof UserMember) {
            $result = ['success' => true];
        } else {
            $result = $model;
        }

        echo json_encode($result);
    }

    /**
     * Delete viewer in home search service
     * @param int $id
     */
    public function deleteMemberAction($id = 0)
    {
        $this->view->disable();

        $user_member = UserMember::findFirst((int)$id);
        if ($user_member instanceof UserMember) {
            if ($user_member->delete()) {
                $result = ['success' => true];
            } else {
                $result = [
                    'success' => false,
                    'message' => 'DELETE_USER_MEMBER_FAIL_TEXT'
                ];
            }
        } else {
            $result = [
                'success' => false,
                'message' => 'USER_MEMBER_NOT_FOUND_TEXT'
            ];
        }

        echo json_encode($result);
    }

}
