<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;

class AssignmentRequest extends \Reloday\Application\Models\AssignmentRequestExt
{
    const LIMIT_PER_PAGE = 20;
    const FILTER_DEFAULT = 'FILTER_STATUS_PENDING_TEXT';

    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('contract_id', 'Reloday\Gms\Models\Contract', 'id', [
            'alias' => 'Contract'
        ]);

        $this->belongsTo('assignment_id', 'Reloday\Gms\Models\Assignment', 'id', [
            'alias' => 'Assignment'
        ]);

        $this->belongsTo('relocation_id', 'Reloday\Gms\Models\Relocation', 'id', [
            'alias' => 'Relocation'
        ]);

        $this->belongsTo('company_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'Company'
        ]);

        $this->belongsTo('owner_company_id', 'Reloday\Gms\Models\Company', 'id', [
            'alias' => 'OwnerCompany'
        ]);
    }

    /**
     * @param $options
     * @return array
     */
    public static function __findWithFilter($options = [], $orders = [])
    {
        $bindArray = [];
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\AssignmentRequest', 'AssignmentRequest');
        $queryBuilder->distinct(true);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Assignment', 'AssignmentRequest.assignment_id = Assignment.id', 'Assignment');
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Employee', 'Employee.id = Assignment.employee_id', 'Employee');
        $queryBuilder->leftjoin('\Reloday\Gms\Models\Company', 'AssignmentRequest.owner_company_id = Company.id', 'Company');

        $queryBuilder->leftJoin('\Reloday\Gms\Models\AssignmentBasic', 'AssignmentBasic.id = Assignment.id', 'AssignmentBasic');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\AssignmentDestination', 'AssignmentDestination.id = Assignment.id', 'AssignmentDestination');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Country', 'AssignmentBasic.home_country_id = OriginCountry.id', 'OriginCountry');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Country', 'AssignmentDestination.destination_country_id = DestinationCountry.id', 'DestinationCountry');

        $queryBuilder->leftjoin('\Reloday\Gms\Models\Relocation', 'Relocation.assignment_id = Assignment.id AND Relocation.creator_company_id = ' . ModuleModel::$company->getId() . ' AND Relocation.active = ' . Relocation::STATUS_ACTIVATED, 'Relocation');

        //Hide all request auto created by DSP
        $queryBuilder->leftjoin('\Reloday\Gms\Models\UserProfile', 'UserProfile.id = AssignmentRequest.user_profile_id', 'UserProfile');

        $queryBuilder->where('AssignmentRequest.company_id = :gms_company_id:', [
            'gms_company_id' => ModuleModel::$company->getId()
        ]);
        $queryBuilder->andwhere('Assignment.archived <> :status_archived:', [
            'status_archived' => Assignment::ARCHIVED_YES
        ]);
        //$queryBuilder->andwhere('AssignmentRequest.message is not null');
        $queryBuilder->andwhere("AssignmentRequest.id > 0");
        $queryBuilder->andWhere("Assignment.status = " . Assignment::STATUS_ACTIVE);
        $queryBuilder->andWhere("Assignment.is_terminated = " . ModelHelper::NO);
        $queryBuilder->andWhere("UserProfile.company_id != :user_company_id:", [
            'user_company_id' => ModuleModel::$company->getId()
        ]);
        $queryBuilder->groupBy('AssignmentRequest.id');

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("Assignment.name LIKE :query:
             OR Employee.firstname LIKE :query: OR Employee.lastname LIKE :query: OR CONCAT(Employee.firstname,' ',Employee.lastname) LIKE :query:
             OR Employee.workemail LIKE :query: 
             OR Company.name LIKE :query:
             OR OriginCountry.name LIKE :query: OR OriginCountry.cio LIKE :query:
              OR DestinationCountry.name LIKE :query: OR DestinationCountry.cio LIKE :query:", [
                'query' => '%' . $options['query'] . '%',
            ]);
            $bindArray['query'] = '%' . $options['query'] . '%';
        }

        if (isset($options['assignees']) && is_array($options['assignees']) && count($options['assignees']) > 0) {
            $queryBuilder->andwhere("Assignment.employee_id IN ({assignees:array})", [
                'assignees' => $options['assignees'],
            ]);
        }

        if (isset($options['companies']) && is_array($options['companies']) && count($options['companies']) > 0) {
            $queryBuilder->andwhere("AssignmentRequest.owner_company_id IN ({companies:array})", [
                'companies' => $options['companies'],
            ]);
        }

        if (isset($options['status_list']) && is_array($options['status_list']) && count($options['status_list']) > 0) {
            $queryBuilder->andwhere("AssignmentRequest.status IN ({status_list:array})", [
                'status_list' => $options['status_list'],
            ]);
        }

        if (isset($options['created_at']) && is_array($options['created_at'])
            && isset($options['created_at']['startDate']) && Helpers::__isTimeSecond($options['created_at']['startDate'])
            && isset($options['created_at']['endDate']) && Helpers::__isTimeSecond($options['created_at']['endDate'])) {

            $queryBuilder->andwhere("AssignmentRequest.created_at >= :create_date_range_begin: AND AssignmentRequest.created_at <= :create_date_range_end:", [
                'create_date_range_begin' => date('Y-m-d H:i:s', Helpers::__getStartTimeOfDay($options['created_at']['startDate'])),
                'create_date_range_end' => date('Y-m-d H:i:s', Helpers::__getEndTimeOfDay($options['created_at']['endDate'])),
            ]);
        }


        if (isset($options['confirmed_at']) && is_array($options['confirmed_at'])
            && isset($options['confirmed_at']['startDate']) && Helpers::__isTimeSecond($options['confirmed_at']['startDate'])
            && isset($options['confirmed_at']['endDate']) && Helpers::__isTimeSecond($options['confirmed_at']['endDate'])) {

            $queryBuilder->andwhere("AssignmentRequest.confirmed_at >= :confirmed_at_range_begin: AND AssignmentRequest.confirmed_at <= :confirmed_at_range_end:", [
                'confirmed_at_range_begin' => date('Y-m-d H:i:s', Helpers::__getStartTimeOfDay($options['confirmed_at']['startDate'])),
                'confirmed_at_range_end' => date('Y-m-d H:i:s', Helpers::__getEndTimeOfDay($options['confirmed_at']['endDate'])),
            ]);
        }


        if (isset($options['hr_company_id']) && is_numeric($options['hr_company_id']) && $options['hr_company_id'] > 0) {
            $queryBuilder->andwhere("AssignmentRequest.owner_company_id = :hr_company_id:", [
                'hr_company_id' => $options['hr_company_id'],
            ]);
        }

        if (isset($options['employee_id']) && is_numeric($options['employee_id']) && $options['employee_id'] > 0) {
            $queryBuilder->andwhere("Employee.id = :employee_id:", [
                'employee_id' => $options['employee_id'],
            ]);
        }

        if (isset($options['owner_uuid']) && Helpers::__isValidUuid($options['owner_uuid']) && ModuleModel::$user_profile->isAdminOrManager() == true) {
            $queryBuilder->leftjoin('\Reloday\Gms\Models\DataUserMember', 'DataUserOwner.object_uuid = Relocation.uuid', 'DataUserOwner');
            $queryBuilder->andwhere("DataUserOwner.user_profile_uuid = :user_profile_uuid: AND DataUserOwner.member_type_id = :member_type_owner:",
                [
                    'user_profile_uuid' => $options['owner_uuid'],
                    'member_type_owner' => DataUserMember::MEMBER_TYPE_OWNER
                ]
            );
        }

        if (isset($options['filter_config_id'])) {


            $tableField = [
                'ACCOUNT_ARRAY_TEXT' => 'AssignmentRequest.owner_company_id',
                'ASSIGNEE_ARRAY_TEXT' => 'Employee.id',
                'OWNER_ARRAY_TEXT' => 'Owner.user_profile_id',
                'ORIGIN_ARRAY_TEXT' => 'AssignmentBasic.home_country_id',
                'DESTINATION_ARRAY_TEXT' => 'AssignmentDestination.destination_country_id',
                'STATUS_ARRAY_TEXT' => 'AssignmentRequest.status',
                'INITIATION_ON_TEXT' => 'AssignmentRequest.created_at',
                'START_OF_WORK_DATE_TEXT' => 'AssignmentRequest.confirmed_at',
                'HAS_RELOCATION_TEXT' => 'AssignmentRequest.relocation_id',
                'PREFIX_DATA_OWNER_TYPE' => 'Owner.member_type_id',
                'PREFIX_OBJECT_UUID' => 'Relocation.uuid',
            ];

            $dataType = [
            ];

            Helpers::__addFilterConfigConditions($queryBuilder, $options['filter_config_id'], $options['is_tmp'], FilterConfig::ASSIGNMENT_REQUEST_FILTER_TARGET, $tableField, $dataType, ModuleModel::$user_profile->getUuid());
        }

        $queryBuilder->orderBy(['AssignmentRequest.created_at DESC']);
        /** process order */
        if (count($orders)) {
            $order = reset($orders);

            if ($order['field'] == "status") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Assignment.approval_status ASC', 'Assignment.created_at DESC']);
                } else {
                    $queryBuilder->orderBy(['Assignment.approval_status DESC', 'Assignment.created_at DESC']);
                }
            }

            if ($order['field'] == "created_at") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['AssignmentRequest.created_at ASC']);
                } else {
                    $queryBuilder->orderBy(['AssignmentRequest.created_at DESC']);
                }
            }

            if ($order['field'] == "employee") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Employee.firstname ASC', 'Employee.lastname ASC']);
                } else {
                    $queryBuilder->orderBy(['Employee.firstname DESC', 'Employee.lastname DESC']);
                }
            }

            if ($order['field'] == "start_date") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Assignment.effective_start_date ASC']);
                } else {
                    $queryBuilder->orderBy(['Assignment.effective_start_date DESC']);
                }
            }

            if ($order['field'] == "end_date") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Assignment.end_date ASC']);
                } else {
                    $queryBuilder->orderBy(['Assignment.end_date DESC']);
                }
            }
        }

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        if (!isset($options['page'])) {
            $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
            $page = intval($start / $limit) + 1;
        } else {
            $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 1;
        }

        try {

            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page,
            ]);
            $pagination = $paginator->getPaginate();

            $assignments_request_array = [];
            if ($pagination->items->count() > 0) {
                foreach ($pagination->items as $request) {
                    $item = $request->toArray();
                    if ($request->getOwnerCompany()) {
                        $item['hr_company'] = $request->getOwnerCompany()->getName();
                        $item['hr_company_uuid'] = $request->getOwnerCompany()->getUuid();
                    } else {
                        $item['hr_company'] = '';
                        $item['hr_company_uuid'] = '';
                    }
                    $assignment = $request->getAssignment();
                    if (!$assignment) {
                        continue;
                    }
                    $item['assignment_reference'] = $assignment->getReference();
                    $item['assignment_uuid'] = $assignment->getUuid();
                    $item['employee_uuid'] = $assignment->getEmployee()->getUuid();
                    $item['employee_name'] = $assignment->getEmployee()->getFirstname() . " " . $assignment->getEmployee()->getLastname();

                    $item['home_country_iso2'] = ($assignment->getHomeCountry()) ? $assignment->getHomeCountry()->getCioFlag() : "";
                    $item['destination_country_iso2'] = ($assignment->getAssignmentDestination() && $assignment->getAssignmentDestination()->getDestinationCountry()) ? $assignment->getAssignmentDestination()->getDestinationCountry()->getCioFlag() : "";

                    $relocation = $request->getRelocation();
                    if ($relocation) {
                        $servicesRelocation = $relocation->getRelocationServiceCompanies([
                            'conditions' => 'status = :status_active:',
                            'bind' => [
                                'status_active' => RelocationServiceCompany::STATUS_ACTIVE
                            ]
                        ]);
                        $progress = 0;
                        $count = 0;
                        if (count($servicesRelocation) > 0) {
                            foreach ($servicesRelocation as $serviceRelocationItem) {
                                $progress += $serviceRelocationItem->getEntityProgressValue();
                                $count += 1;
                            }
                        }
                        if ($count > 0) {
                            $item['progress'] = floor($progress / $count);
                        } else {
                            $item['progress'] = $progress;
                        }
                        $item['relocation_uuid'] = $relocation->getUuid();
                        $owner = $relocation->getDataOwner();
                        if ($owner) {
                            $item['owner_uuid'] = $owner->getUuid();
                            $item['owner_name'] = $owner->getFirstname() . " " . $owner->getLastname();
                        }
                    }
                    $assignments_request_array[] = $item;
                }
            }

            return [
                'sql' => $queryBuilder->getQuery()->getSql(),
                'success' => true,
                'orders' => $orders,
                'page' => $page,
                'data' => $assignments_request_array,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages
            ];

        } catch (\Phalcon\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }
}
