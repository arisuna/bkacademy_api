<?php

namespace Reloday\Gms\Controllers\API;

use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Models\Assignment;
use Reloday\Gms\Models\AssignmentType;
use Reloday\Gms\Models\Country;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\MoveManagementServiceInformation;
use Reloday\Gms\Models\MoveManagementServiceQuote;
use Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\RelocationServiceCompany;
use Reloday\Gms\Models\Service;
use Reloday\Gms\Models\ServiceCompany;
use Reloday\Gms\Models\ServiceCompanyHasServiceProvider;
use Reloday\Gms\Models\ServiceProviderCompany;
use Reloday\Gms\Models\ServiceProviderType;
use Reloday\Gms\Models\Task;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class MoveManagementController extends BaseController
{
    /**
     * @Route("/movemanagement", paths={module="gms"}, methods={"GET"}, name="gms-movemanagement-dashboard")
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

        // 1. Load home search by relocation_id if existed
        // 2. If home search is not existed, check relocation has service 'Home search' or not
        // 3. If relocation doesn't include "Home search" service, redirect to relocation dashboard
        // 4. If "Home search" service found, create new "Home Search Request"
        // 4.1 Find task list of "Home Search", if it has task, add task to "Task"

        // step 1
        $move_management_obj = MoveManagementServiceInformation::findFirst([
            'relocation_id=' . (int)$relocation->getId() .
            ' AND creator_company_id=' . ModuleModel::$user_profile->getCompanyId()]);

        if ($move_management_obj instanceof MoveManagementServiceInformation) {
            $move_uuid = $move_management_obj->getUuid();
            $relocation_service_company_id = $move_management_obj->getRelocationServiceCompanyId();
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
                $service_move_management = ServiceCompany::findFirst([
                    'conditions' => 'service_id=' . Service::MOVE_MANAGEMENT . ' AND (id=' . implode(' OR id=', $service_id_list) . ')'
                ]);

                /**
                 * Check home search result
                 */
                if ($service_move_management instanceof ServiceCompany) {
                    /**
                     * Create new request for this relocation
                     */
                    $move_management_obj = new MoveManagementServiceInformation();
                    $relocation_service_company_id = array_search($service_move_management->getId(), $service_id_list);
                    $move_management_obj = $move_management_obj->__save([
                        'relocation_id' => $relocation->getId(),
                        'relocation_service_company_id' => $relocation_service_company_id,
                        'creator_company_id' => ModuleModel::$user_profile->getCompanyId(),
                        'specific_information' => json_encode([
                            'currency' => ''
                        ])
                    ]);
                    if (!$move_management_obj instanceof MoveManagementServiceInformation) {
                        // Save home search error
                        exit(json_encode([
                            'success' => true,
                            'has_service' => false,
                            'message' => $move_management_obj['message'],
                            'detail' => $move_management_obj['detail']
                        ]));
                        // EXIT ------------------
                    } else {
                        // Create move management success
                        $move_uuid = $move_management_obj->getUuid();
                    }
                } else { // Home search not found
                    exit(json_encode([
                        'success' => true,
                        'has_service' => false,
                        'relocation' => $relocation->toArray(),
                        'message' => 'SERVICE_HOME_SEARCH_NOT_FOUND_TEXT'
                    ]));
                    // EXIT ----------------
                }
            } else { // No service, include home search
                exit(json_encode([
                    'success' => true,
                    'has_service' => false,
                    'relocation' => $relocation->toArray(),
                    'message' => 'SERVICE_HOME_SEARCH_NOT_FOUND_TEXT'
                ]));
                // EXIT ----------------
            }
        }

        /**`
         * Find tasks are associated with home search by
         * default: relocation_service_company_id
         * manual: object_uuid (UUID of home search request)
         */
        $tasks = Task::find([
            'conditions' => "(relocation_service_company_id=" . $relocation_service_company_id . " AND relocation_id=" .
                (int)$relocation->getId() . ") OR object_uuid='$move_uuid'"
        ]);
        if (count($tasks)) {
            $task_list = $tasks->toArray();
        }

        $move_management = $move_management_obj->toArray();
        $move_management['specific_information'] = json_decode($move_management['specific_information']);

        echo json_encode([
            'success' => true,
            'has_service' => true,
            'relocation' => $relocation->toArray(),
            'move_management' => $move_management,
            'task_list' => $task_list
        ]);

    }

    /**
     * @param string $uuid UUID of relocation
     */
    public function quotesAction($uuid = '')
    {
        $this->view->disable();
        $relocation = Relocation::findFirst('uuid="' . $uuid . '"');
        if (!$relocation instanceof Relocation) {
            exit(
            [
                'success' => false,
                'message' => 'RELOCATION_NOT_FOUND_TEXT'
            ]
            );
        } // ------------

        // Load data
        /*$quotes = MoveManagementServiceQuote::find([
            'relocation_id=' . $relocation->getId(),
            'order' => 'provider'
        ]);
        $list = [];
        $provider_types = [];
        if (count($quotes)) {
            $provider_id = 0;
            $len = 0;
            $start = 0;
            foreach ($quotes as $k => $quote) {
                $list[$k] = $quote->toArray();
                // Find provider type
                if (!isset($provider_types[$quote->getType()])) {
                    $provider_type = ServiceProviderType::findFirst($quote->getType());
                    if ($provider_type instanceof ServiceProviderType) {
                        $provider_types[$quote->getType()] = $provider_type->getName();
                        // Apply name of provider type to list
                        $list[$k]['type'] = $provider_type->getName();
                    }
                } else {
                    $list[$k]['type'] = $provider_types[$quote->getType()];
                }

                $len++;

                // Check first element in list
                if ($provider_id != $quote->getProvider() & $k > 0 || $k == count($quotes) - 1) {
                    if ($k == count($quotes) - 1) {
                        // Last element
                        for ($i = $start; $i <= $k; $i++) {
                            $list[$i]['len'] = $len;
                        }
                        $list[$k]['merged'] = true;
                    } else {
                        // Finished
                        for ($i = $start; $i < $k; $i++) {
                            $list[$i]['len'] = $len - 1;
                        }
                        $list[$k - 1]['merged'] = true;

                        $list[$k]['merged'] = false;
                    }

                    $start = $k;
                    $len = 1; // Begin for this is first element
                } else {
                    $list[$k]['merged'] = false;
                }

                $provider_id = $quote->getProvider();
            }
        }*/

        // Order by type
        $quotes = MoveManagementServiceQuote::find([
            'relocation_id=' . $relocation->getId(),
            'order' => 'type'
        ]);
        $list = [];
        $provider_types = [];
        if (count($quotes)) {
            $type = 0;
            $len = 0;
            $start = 0;
            foreach ($quotes as $k => $quote) {
                $list[$k] = $quote->toArray();
                // Find provider type
                if (!isset($provider_types[$quote->getType()])) {
                    $provider_type = ServiceProviderType::findFirst($quote->getType());
                    if ($provider_type instanceof ServiceProviderType) {
                        $provider_types[$quote->getType()] = $provider_type->getName();
                        // Apply name of provider type to list
                        $list[$k]['type'] = $provider_type->getName();
                    }
                } else {
                    $list[$k]['type'] = $provider_types[$quote->getType()];
                }

                $len++;

                // Check first element in list
                if ($type != $quote->getType() & $k > 0 || $k == count($quotes) - 1) {
                    if ($k == count($quotes) - 1) {
                        // Last element
                        $list[$start]['len'] = $len;
                        $list[$k]['merged'] = false;
                    } else {
                        $list[$start]['len'] = $len - 1;
                    }
                    $list[$start]['merged'] = true;
                    $start = $k;
                    $len = 1; // Begin for this is first element
                } else {
                    $list[$k]['merged'] = false;
                }

                $type = $quote->getType();
            }
        }

        echo json_encode([
            'success' => true,
            'quotes' => $list,
        ]);
    }

    public function quote_detailAction()
    {
        $this->view->disable();
        $relocation_id = $this->request->get('relocation_id');
        $provider_id = $this->request->get('provider_id');
        $quote_id = $this->request->get('quote_id');

        // Find quote list by criteria
        $quote_detail = MoveManagementServiceQuote::find(['relocation_id=' . (int)$relocation_id
            . ' AND provider=' . (int)$provider_id . ' AND id=' . (int)$quote_id]);

        $data = [];
        $provider_types = [];
        if (count($quote_detail)) {
            foreach ($quote_detail as $inc => $item) {
                $data[$item->getType()] = $item->toArray();
                $provider_types[$inc]['id'] = $item->getType();
                $provider_type = ServiceProviderType::findFirst($item->getType());
                if ($provider_type instanceof ServiceProviderType) {
                    $provider_types[$inc]['name'] = $provider_type->getName();
                }
            }
        }

        echo json_encode([
            'success' => true,
            'quote' => $data,
            'provider_types' => $provider_types
        ]);
    }

    public function quote_saveAction()
    {
        $this->view->disable();

        $result = [
            'success' => true,
            'message' => 'SAVE_QUOTE_SUCCESS_TEXT'
        ];

        $data = $this->request->getPost('data');
        foreach ($data as $item) {
            $quote = new MoveManagementServiceQuote();
            $item['company_id'] = ModuleModel::$user_profile->getCompanyId();
            $quote = $quote->__save($item);

            if (!$quote instanceof MoveManagementServiceQuote) {
                $_msg = [];
                foreach ($quote->getMessages() as $message) {
                    $_msg[] = $message->getMessage();
                }
                $result = [
                    'success' => false,
                    'message' => 'SAVE_QUOTE_ERROR_TEXT',
                    'detail' => $_msg
                ];
                break;
            }
        }

        echo json_encode($result);
    }

    public function quote_sendAction() {
        $this->view->disable();

        $quotes = $this->request->getPost('list');
        $comment = $this->request->getPost('comment');
        $employee_id = $this->request->getPost('employee_id'); // Find email of employee

        // Update quote
        foreach ($quotes as $quote) {
            $quote = MoveManagementServiceQuote::findFirst($quote['id']);
            if($quote instanceof MoveManagementServiceQuote) {
                $quote->setSent(1);
                $quote->save();
            }
        }

        // Send mail action
        // ...

        echo json_encode([
            'success' => true,
            'message' => 'SEND_QUOTE_SUCCESS_TEXT'
        ]);
    }

    /**
     * Find employee info, assignment info, provider list
     * @param string $uuid
     */
    public function informationAction($uuid = '')
    {

        $this->view->disable();

        /**
         * Save basic or specific information
         */
        if ($this->request->isPut()) {
            $specific_info = MoveManagementServiceInformation::findFirst((int)$this->request->getPut('id'));
            if ($specific_info instanceof MoveManagementServiceInformation) {
                $is_basic = $this->request->getPut('isBasic');
                if ($is_basic === 'true') {
                    $is_basic = true;
                } else {
                    $is_basic = false;
                }

                if ($is_basic) {
                    $specific_info->setServiceProviderId((int)$this->request->getPut('service_provider_id'));
                    $specific_info->setAuthorisedDate($this->request->getPut('authorised_date'));
                    $specific_info->setStartDate($this->request->getPut('start_date'));
                    $specific_info->setEndDate($this->request->getPut('end_date'));
                    $specific_info->setCompletionDate($this->request->getPut('completion_date'));
                    $specific_info->setExpiryDate($this->request->getPut('expiry_date'));
                } else {
                    $specific_info->setSpecificInformation($this->request->getPut('specific_information'));
                }

                if ($specific_info->save()) {
                    // Make reminder related with event
                    // developing ...

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
                    'message' => 'MOVE_MANAGEMENT_NOT_FOUND_TEXT'
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

        $relocation = Relocation::findFirst('uuid="' . $uuid . '"');
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
                $move_management = MoveManagementServiceInformation::findFirst('relocation_id=' . $relocation->getId());
                if ($move_management instanceof MoveManagementServiceInformation) {
                    $relocation_service_company = RelocationServiceCompany::findFirst($move_management->getRelocationServiceCompanyId());
                    if ($relocation_service_company instanceof RelocationServiceCompany) {
                        $service_provider_list = ServiceCompanyHasServiceProvider::find('service_company_id=' . $relocation_service_company->getServiceCompanyId());
                        if (count($service_provider_list) > 0) {
                            // One provider can support multi type, so, we will have duplicate provider info
                            $provider_list = [];
                            $provider_type_list = [];
                            foreach ($service_provider_list as $k => $item) {
                                if (!isset($provider_list[$item->getServiceProviderCompanyId()])) {
                                    $provider_list[$item->getServiceProviderCompanyId()] = [];
                                    $provider_company = ServiceProviderCompany::findFirst((int)$item->getServiceProviderCompanyId());
                                    if ($provider_company instanceof ServiceProviderCompany) {
                                        $provider_list[$item->getServiceProviderCompanyId()]['info'] = $provider_company->toArray();
                                        $country = Country::findFirst($provider_company->getCountryId());
                                        if ($country instanceof Country) {
                                            $provider_list[$item->getServiceProviderCompanyId()]['info']['country'] = $country->getName();
                                        }
                                    }
                                    $provider_list[$item->getServiceProviderCompanyId()]['type_support'] = [];
                                }

                                // Parse list provider type supported
                                if (!isset($provider_type_list[$item->getServiceProviderTypeId()])) {
                                    // Load from database
                                    $provider_type = ServiceProviderType::findFirst($item->getServiceProviderTypeId());
                                } else {
                                    $provider_type = $provider_type_list[$item->getServiceProviderTypeId()];
                                }
                                $provider_list[$item->getServiceProviderCompanyId()]['type_support'][] = [
                                    'id' => $provider_type->getId(),
                                    'name' => $provider_type->getName()
                                ];

                            }
                            $result['provider_list'] = $provider_list;
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
}
