<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;

use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Models\CompanySettingDefaultExt;
use Reloday\Gms\Models\MediaAttachment as MediaAttachment;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Paginator\Factory;

class Company extends \Reloday\Application\Models\CompanyExt
{
    const LIMIT_PER_PAGE = 50;

    protected $isBelongsToGms = null;

    protected $canEdit = null;

    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('country_id', 'Reloday\Gms\Models\Country', 'id', [
            'alias' => 'Country'
        ]);

        $this->hasOne('theme_id', 'Reloday\Gms\Models\Theme', 'id', [
            'alias' => 'Theme',
        ]);
        $this->hasOne('id', 'Reloday\Gms\Models\CompanyBusinessDetail', 'id', [
            'alias' => 'CompanyBusinessDetail'
        ]);

        $this->hasOne('id', 'Reloday\Gms\Models\CompanySetting', 'company_id', [
            'alias' => 'RecurrentExpenseTimeOld',
            'params' => [
                'conditions' => 'company_setting_default_id = ' . CompanySettingDefault::RECURRING_EXPENSE_OLD
            ]
        ]);

        $this->hasOne('id', 'Reloday\Gms\Models\CompanySetting', 'company_id', [
            'alias' => 'RecurrentExpenseTime',
            'params' => [
                'conditions' => 'company_setting_default_id = ' . CompanySettingDefault::RECURRING_EXPENSE_TIME
            ]
        ]);

        $this->hasOne('id', 'Reloday\Gms\Models\CompanySetting', 'company_id', [
            'alias' => 'RecurrentExpenseApproval',
            'params' => [
                'conditions' => 'company_setting_default_id = ' . CompanySettingDefault::EXPENSE_APPROVAL_REQUIRE
            ]
        ]);

        $this->hasOne('id', 'Reloday\Gms\Models\CompanyFinancialDetail', 'id', [
            'alias' => 'CompanyFinancialDetail'
        ]);
        $this->belongsTo('app_id', 'Reloday\Gms\Models\App', 'id', [
            'alias' => 'App'
        ]);

        $this->hasMany('id', 'Reloday\Gms\Models\CompanySetting', 'company_id', [
            'alias' => 'CompanySetting'
        ]);

        $this->hasOne('id', 'Reloday\Gms\Models\Subscription', 'company_id', [
            'alias' => 'Subscription',
            'reusable' => true,
        ]);

        $this->hasOne('id', 'Reloday\Gms\Models\ChargebeeCustomer', 'company_id', [
            'alias' => 'ChargebeeCustomer',
            'reusable' => true,
        ]);
    }

    /**
     * @param string $more_condition
     * @return array
     */
    public static function loadList($more_condition = '')
    {
        $result = [];
        $countries_arr = [];

        // Load user profile
        $user_profile = ModuleModel::$user_profile;

        // Find company of user
        if ($user_profile instanceof UserProfile) {
            $company = Company::findFirst($user_profile->getCompanyId() ? $user_profile->getCompanyId() : 0);
            if (!$company instanceof Company) {
                return [
                    'success' => false,
                    'message' => 'COMPANY_INFO_NOT_FOUND'
                ];
            } else {
                // 2. Check type of company
                if (!$company->getCompanyTypeId() == Company::TYPE_GMS) {
                    return [
                        'success' => false,
                        'message' => 'COMPANY_TYPE_DIFFERENT'
                    ];
                } else {
                    /**
                     * All has been validated
                     */
                    // 3. Load list company in contract
                    $contracts = Contract::find([
                        'conditions' => 'to_company_id=' . $user_profile->getCompanyId()
                    ]);

                    // 4. Load companies in contract
                    if (count($contracts)) {
                        $cid = [];
                        foreach ($contracts as $v) {
                            $cid[] = $v->getFromCompanyId();
                        }

                        if (!empty($cid)) {
                            $companies = Company::find([
                                'conditions' => 'id IN (' . implode(',', $cid) . ')' . ($more_condition ? " AND " . $more_condition : "")
                            ]);

                            if (count($companies)) {
                                $countries = Country::find();
                                if (count($countries)) {
                                    foreach ($countries as $country) {
                                        $countries_arr[$country->getId()] = $country->toArray();
                                    }
                                }

                                // Add country name to companies object
                                foreach ($companies as $com) {
                                    $result[] = array_merge(
                                        [
                                            'country_name' => isset($countries_arr[$com->getCountryId()]) ? $countries_arr[$com->getCountryId()]['name'] : ''
                                        ],
                                        $com->toArray()
                                    );

                                }
                            }

                        }
                    }
                    return [
                        'success' => true,
                        'data' => $result,
                        'countries' => $countries_arr
                    ];
                }
            }
        } else {
            return [
                'success' => false,
                'message' => 'USER_NOT_FOUND'
            ];
        }
    }

    /**
     * load all companies active in all contract of current gms Company
     * @return [type] [description]
     */
    public static function __loadAllCompaniesOfGms()
    {

        $user_profile = ModuleModel::$user_profile;
        $company = ModuleModel::$company;

        // Find company of user
        if (!$company || !($company->getCompanyTypeId() == Company::TYPE_GMS)) {
            return false;
        } else {

            $contracts = Contract::find([
                'conditions' => 'to_company_id=' . $company->getId()
            ]);


            // 4. Load companies in contract
            if (count($contracts)) {
                $cid = [];
                foreach ($contracts as $v) {
                    $cid[] = $v->getFromCompanyId();
                }

                if (!empty($cid)) {
                    $companies = Company::find([
                        'conditions' => 'id IN (' . implode(',', $cid) . ') AND company_type_id = ' . CompanyType::TYPE_HR,
                    ]);

                    if (count($companies)) {
                        return $companies;
                    }
                }
            }
            return false;
        }
    }

    /**
     * load all companies active in all contract of current gms Company
     * @return [type] [description]
     */
    public static function getCompanyIdsOfGms()
    {

        $user_profile = ModuleModel::$user_profile;
        $company = ModuleModel::$company;

        // Find company of user
        if (!$company || !($company->getCompanyTypeId() == Company::TYPE_GMS)) {
            return false;
        } else {

            $contracts = Contract::find([
                'conditions' => 'to_company_id=' . $company->getId()
            ]);


            // 4. Load companies in contract
            if (count($contracts)) {
                $cid = [];
                foreach ($contracts as $v) {
                    $cid[] = $v->getFromCompanyId();
                }

                if (!empty($cid)) {
                    $companies = Company::find([
                        'columns' => 'id',
                        'conditions' => 'id IN (' . implode(',', $cid) . ') AND company_type_id = ' . CompanyType::TYPE_HR,
                    ]);

                    if (count($companies)) {
                        $results = [];
                        foreach ($companies as $company) {
                            $results[] = $company->id;
                        }
                        return $results;
                    }
                }
            }
            return false;
        }
    }

    /** return to invoice logo */
    public function getAvatarInvoice()
    {
        $invoice_logo = MediaAttachment::__getLastAttachment($this->getUuid(), "avatar");
        if (!$invoice_logo) {
            $invoice_logo = null;
        }
        return $invoice_logo;
    }

    /** get avatar */
    public function getLogo()
    {
        $logo = MediaAttachment::__getLastAttachment($this->getUuid(), "logo");
        if ($logo) {
            return $logo;
        } else {
            return null;
        }
    }

    /**
     * @return bool
     */
    public function isCurrentGms()
    {
        return $this->getId() == ModuleModel::$company->getId();
    }

    /**
     * check if gms is allowed to see company details
     */
    public function belongsToGms()
    {
        if (is_bool($this->isBelongsToGms) && !is_null($this->isBelongsToGms)) {
            return $this->isBelongsToGms;
        }
        if ($this->isHr()) {
            $numberContractActive = Contract::count([
                'conditions' => 'from_company_id=:from_company_id: AND to_company_id = :to_company_id: AND status = :status_active:',
                'bind' => [
                    'from_company_id' => $this->getId(),
                    'to_company_id' => ModuleModel::$company->getId(),
                    'status_active' => Contract::STATUS_ACTIVATED
                ]
            ]);
            if ($numberContractActive > 0) {
                $this->isBelongsToGms = true;
            } else {
                $this->isBelongsToGms = false;
            }
        } elseif ($this->isBooker()) {
            $this->isBelongsToGms = $this->getCreatedByCompanyId() == ModuleModel::$company->getId();
        } else {
            $this->isBelongsToGms = false;
        }
        return $this->isBelongsToGms;
    }

    /**
     * @return bool
     */
    public function isEditable()
    {
        if (is_bool($this->canEdit) && !is_null($this->canEdit)) {
            return $this->canEdit;
        }
        if ($this->belongsToGms() == false) {
            $this->canEdit = false;
        }
        if ($this->isBooker()) {
            $this->canEdit = true;
        }
        if ($this->isHr()) {
            if($this->getIsCorporateGroupAccount() == Helpers::YES){
                $this->canEdit = true;
            } else {
                $isSubscription = $this->getSubscription([
                    'conditions' => 'status != :status_cancelled:',
                    'bind' => [
                        'status_cancelled' => Subscription::STATUS_CANCELLED
                    ]
                ]);

                if ($isSubscription) {
                    $this->canEdit = false;
                } else {
                    $this->canEdit = true;
                }
            }
        }
        return $this->canEdit;
    }

    /**
     * @param array $options
     * @return array
     */
    public static function __loadCurrentAccountsList($options = array())
    {
        $di = \Phalcon\DI::getDefault();
        $user_profile_uuid = ModuleModel::$user_profile->getUuid();

        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Company', 'Company');
        $queryBuilder->distinct(true);
        $queryBuilder->columns([
            'Company.id',
            'Company.uuid',
            'Company.name'
        ]);
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Contract', 'Contract.from_company_id = Company.id', 'Contract');
        $bindArray = [];

        $queryBuilder->where('Contract.to_company_id = :gms_company_id:', [
            'gms_company_id' => intval(ModuleModel::$company->getId())
        ]);
        $bindArray['gms_company_id'] = intval(ModuleModel::$company->getId());

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andWhere('Company.name LIKE :query:', [
                'query' => '%' . $options['query'] . '%'
            ]);
            $bindArray['query'] = '%' . $options['query'] . '%';
        }

        if (isset($options['ids']) && is_array($options['ids']) && count($options['ids']) > 0) {
            $queryBuilder->andWhere('Company.id IN ({company_ids:array})', [
                'company_ids' => $options['ids']
            ]);
            $bindArray['company_ids'] = $options['ids'];
        }

        $queryBuilder->andWhere('Contract.status = :contract_activated:', [
            'contract_activated' => intval(Contract::STATUS_ACTIVATED)
        ]);
        $bindArray['contract_activated'] = intval(Contract::STATUS_ACTIVATED);

        $queryBuilder->andWhere('Company.company_type_id = :company_type_id:', [
            'company_type_id' => self::TYPE_HR
        ]);
        $bindArray['company_type_id'] = self::TYPE_HR;

        if (isset($options['hr_company_id']) && is_numeric($options['hr_company_id']) && $options['hr_company_id'] > 0) {
            $queryBuilder->andWhere('Company.id = :hr_company_id:', [
                'hr_company_id' => $options['hr_company_id']
            ]);
        }

        if (isset($options['any']) && is_bool($options['any']) && $options['any'] == true) { //7041

        } else {

            if (ModuleModel::$user_profile->isGmsAdminOrManager() == false) {
                $queryBuilder->innerJoin('\Reloday\Gms\Models\Assignment', 'Company.id = Assignment.company_id', 'Assignment');
                $queryBuilder->leftJoin('\Reloday\Gms\Models\Relocation', 'Assignment.id = Relocation.assignment_id', 'Relocation');
                $queryBuilder->leftJoin('\Reloday\Gms\Models\RelocationServiceCompany', 'Relocation.id = RelocationServiceCompany.relocation_id', 'RelocationServiceCompany');
                $queryBuilder->leftJoin('\Reloday\Gms\Models\DataUserMember', 'Assignment.uuid = DataUserMemberAssignment.object_uuid', 'DataUserMemberAssignment');
                $queryBuilder->leftJoin('\Reloday\Gms\Models\DataUserMember', 'Relocation.uuid = DataUserMemberRelocation.object_uuid', 'DataUserMemberRelocation');
                $queryBuilder->leftJoin('\Reloday\Gms\Models\DataUserMember', 'RelocationServiceCompany.uuid = DataUserMemberRelocationService.object_uuid', 'DataUserMemberRelocationService');
                $queryBuilder->andWhere('Assignment.archived = :assignment_not_archived:', [
                    'assignment_not_archived' => intval(Assignment::ARCHIVED_NO)
                ]);

                $queryBuilder->andWhere("Relocation.active = :relocation_activated:", [
                    'relocation_activated' => intval(Relocation::STATUS_ACTIVATED)
                ]);
                $queryBuilder->andWhere('DataUserMemberAssignment.user_profile_uuid = :user_profile_uuid:  OR DataUserMemberRelocation.user_profile_uuid = :user_profile_uuid: OR DataUserMemberRelocationService.user_profile_uuid = :user_profile_uuid:', [
                    'user_profile_uuid' => ModuleModel::$user_profile->getUuid()
                ]);

                $bindArray['assignment_not_archived'] = intval(Assignment::ARCHIVED_NO);
                $bindArray['relocation_activated'] = intval(Relocation::STATUS_ACTIVATED);
                $bindArray['user_profile_uuid'] = ModuleModel::$user_profile->getUuid();
            }
        }

        if (isset($options['hasBooker']) && is_bool($options['hasBooker']) && $options['hasBooker'] == true) {
            $queryBuilder->orWhere('Company.company_type_id = :type_booker: AND Company.created_by_company_id = :created_booker_company_id: AND Company.status = :booker_active: AND Company.name LIKE :_query:', [
                'type_booker' => self::TYPE_BOOKER,
                'created_booker_company_id' => ModuleModel::$company->getId(),
                'booker_active' => self::STATUS_ACTIVATED,
                '_query' => '%' . $options['query'] . '%'
            ]);

            if (isset($options['ids']) && is_array($options['ids']) && count($options['ids']) > 0) {
                $queryBuilder->andWhere('Company.id IN ({booker_ids:array})', [
                    'booker_ids' => $options['ids']
                ]);
            }

            if (isset($options['hr_company_id']) && is_numeric($options['hr_company_id']) && $options['hr_company_id'] > 0) {
                $queryBuilder->andWhere('Company.id = :hrCompanyId:', [
                    'hrCompanyId' => $options['hr_company_id']
                ]);
            }
        }

        $queryBuilder->orderBy(['Company.name ASC']);
        $queryBuilder->groupBy('Company.id');

        $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
        $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
        $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;

        if ($page == 0) {
            $page = intval($start / $limit) + 1;
        }

        try {
            $paginator = new PaginatorQueryBuilder([
                "builder" => $queryBuilder,
                "limit" => $limit,
                "page" => $page
            ]);
            $pagination = $paginator->getPaginate();

            $itemsArray = [];
            foreach ($pagination->items as $item) {
                $item = $item->toArray();
                $item['id'] = intval($item['id']);
                $itemsArray[] = $item;
            }

            return [
                'success' => true,
                'options' => $options,
//                'sql' => $queryBuilder->getQuery()->getSql(),
                'limit_per_page' => $limit,
                'page' => $page,
                'data' => $itemsArray,
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages,
                'total_rest_items' => $pagination->total_items - $limit * $pagination->current,
            ];

        } catch (\Phalcon\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }

    /**
     *
     */
    public static function __loadListBookers($options = array())
    {
        if (isset($options["query"]) && $options["query"] != '') {
            $bookers = self::find([
                'conditions' => 'company_type_id = :company_type_id: AND created_by_company_id = :created_by_company_id: AND status = :status_active: AND name LIKE :query:',
                'bind' => [
                    'company_type_id' => self::TYPE_BOOKER,
                    'created_by_company_id' => ModuleModel::$company->getId(),
                    'status_active' => self::STATUS_ACTIVATED,
                    'query' => '%' . $options["query"] . '%'
                ]
            ]);
        } else {
            $bookers = self::find([
                'conditions' => 'company_type_id = :company_type_id: AND created_by_company_id = :created_by_company_id: AND status = :status_active:',
                'bind' => [
                    'company_type_id' => self::TYPE_BOOKER,
                    'created_by_company_id' => ModuleModel::$company->getId(),
                    'status_active' => self::STATUS_ACTIVATED,
                ]
            ]);
        }
        $bookerArray = [];
        foreach ($bookers as $booker) {
            $bookerArray[$booker->getId()] = $booker->toArray();
            $bookerArray[$booker->getId()]['country_name'] = $booker->getCountry() ? $booker->getCountry()->getName() : '';
            $bookerArray[$booker->getId()]['country_iso2'] = $booker->getCountry()  ? $booker->getCountry()->getCio() : null;
        }

        return array_values($bookerArray);
    }

    /**
     * @param $uuid
     */
    public static function __findBooker($uuid)
    {
        return self::findFirst([
            'conditions' => 'uuid = :uuid: AND company_type_id = :company_type_id: AND created_by_company_id = :created_by_company_id: AND status = :status_active:',
            'bind' => [
                'uuid' => $uuid,
                'company_type_id' => self::TYPE_BOOKER,
                'created_by_company_id' => ModuleModel::$company->getId(),
                'status_active' => self::STATUS_ACTIVATED
            ]
        ]);
    }

    /**
     * @param int $id
     * @return \Reloday\Application\Models\Company
     */
    public static function __findBookerById(int $id)
    {
        return self::findFirst([
            'conditions' => 'id = :id: AND company_type_id = :company_type_id: AND created_by_company_id = :created_by_company_id: AND status = :status_active:',
            'bind' => [
                'id' => $id,
                'company_type_id' => self::TYPE_BOOKER,
                'created_by_company_id' => ModuleModel::$company->getId(),
                'status_active' => self::STATUS_ACTIVATED
            ]
        ]);
    }

    /**
     * @param int $id
     * @return \Reloday\Application\Models\Company
     */
    public static function __findHrById(int $id)
    {
        return self::findFirst([
            'conditions' => 'id = :id: AND company_type_id = :company_type_id: AND status = :status_active:',
            'bind' => [
                'id' => $id,
                'company_type_id' => self::TYPE_HR,
                'status_active' => self::STATUS_ACTIVATED
            ]
        ]);
    }

    /**
     * @param array $options
     * @return array
     */
    public static function __findHrWithFilter($options = array(), $orders = array(), $isFullMode = true)
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Company', 'Company');
        $queryBuilder->distinct(true);
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Contract', 'Contract.from_company_id = Company.id', 'Contract');

        $queryBuilder->where('Contract.to_company_id = :to_company_id:', [
            'to_company_id' => ModuleModel::$company->getId()
        ]);
        $queryBuilder->andwhere("Contract.status = :status_active:", [
            'status_active' => Contract::STATUS_ACTIVATED
        ]);

        if ($isFullMode == false) {
            $queryBuilder->columns(['Company.id', 'Company.name']);
        }

        if (isset($options['hasBooker']) && is_bool($options['hasBooker']) && $options['hasBooker'] == true) {
            $queryBuilder->orWhere('Company.company_type_id = :type_booker: AND Company.created_by_company_id = :created_booker_company_id: AND Company.status = :booker_active:', [
                'type_booker' => self::TYPE_BOOKER,
                'created_booker_company_id' => ModuleModel::$company->getId(),
                'booker_active' => self::STATUS_ACTIVATED,
            ]);
        }

        $bindArray = [];
        $bindArray['to_company_id'] = ModuleModel::$company->getId();
        $bindArray['status_active'] = Contract::STATUS_ACTIVATED;

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("Company.name LIKE :query:", ['query' => '%' . $options['query'] . '%']);
            $bindArray['query'] = $options['query'];
        }

        if (isset($options['company_ids']) && is_array($options['company_ids']) && count($options['company_ids']) > 0) {
            $queryBuilder->andwhere("Company.id IN ({company_ids:array})", ["company_ids" => $options['company_ids']]);
            $bindArray['company_ids'] = $options['company_ids'];
        }

        if (isset($options['except_company_ids']) && is_array($options['except_company_ids']) && count($options['except_company_ids']) > 0) {
            $queryBuilder->andwhere("Company.id NOT IN ({except_company_ids:array})", ["except_company_ids" => $options['except_company_ids']]);
            $bindArray['except_company_ids'] = $options['except_company_ids'];
        }

        if (isset($options['relocation_id']) && is_numeric($options['relocation_id']) && $options['relocation_id'] > 0) {
            $queryBuilder->leftJoin('\Reloday\Gms\Models\Assignment', 'Company.id = AssignmentR1.company_id OR AssignmentR1.booker_company_id = Company.id', 'AssignmentR1');
            $queryBuilder->leftJoin('\Reloday\Gms\Models\Relocation', 'AssignmentR1.id = RelocationR1.assignment_id', 'RelocationR1');
            $queryBuilder->andwhere("RelocationR1.id = :relocation_id:", ["relocation_id" => $options['relocation_id']]);
        }

        if (ModuleModel::$user_profile->isGmsAdminOrManager() == false) {
            $queryBuilder->innerJoin('\Reloday\Gms\Models\Assignment', 'Company.id = Assignment.company_id', 'Assignment');
            $queryBuilder->leftJoin('\Reloday\Gms\Models\Relocation', 'Assignment.id = Relocation.assignment_id', 'Relocation');
            $queryBuilder->leftJoin('\Reloday\Gms\Models\DataUserMember', 'Assignment.uuid = DataUserMemberAssignment.object_uuid', 'DataUserMemberAssignment');
            $queryBuilder->leftJoin('\Reloday\Gms\Models\DataUserMember', 'Relocation.uuid = DataUserMemberRelocation.object_uuid', 'DataUserMemberRelocation');
            $queryBuilder->andWhere('Assignment.archived = :assignment_not_archived:', [
                'assignment_not_archived' => intval(Assignment::ARCHIVED_NO)
            ]);
            $queryBuilder->andWhere('Relocation.active = :relocation_activated:', [
                'relocation_activated' => intval(Relocation::STATUS_ACTIVATED)
            ]);

            $queryBuilder->andWhere('DataUserMemberAssignment.user_profile_uuid = :user_profile_uuid:  OR DataUserMemberRelocation.user_profile_uuid = :user_profile_uuid:', [
                'user_profile_uuid' => ModuleModel::$user_profile->getUuid()
            ]);

            $bindArray['assignment_not_archived'] = intval(Assignment::ARCHIVED_NO);
            $bindArray['relocation_activated'] = intval(Relocation::STATUS_ACTIVATED);
            $bindArray['user_profile_uuid'] = ModuleModel::$user_profile->getUuid();
        }

        if (isset($options['filter_config_id'])) {

            $tableField = [
                'STATUS_TEXT' => 'Company.status',
                'HAS_ACCESS_TEXT' => 'Company.app_id',
            ];

            $dataType = [
            ];

            Helpers::__addFilterConfigConditions($queryBuilder, $options['filter_config_id'], $options['is_tmp'], FilterConfig::HR_ACCOUNT_FILTER_TARGET, $tableField, $dataType, ModuleModel::$user_profile->getUuid());
        }

        if (count($orders)) {
            $order = reset($orders);
            $queryBuilder->orderBy('Company.name ASC');

            if ($order['field'] == "name") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Company.name ASC']);
                } else {
                    $queryBuilder->orderBy(['Company.name DESC']);
                }
            }

        }else{
            $queryBuilder->orderBy('Company.name ASC');
        }


        $queryBuilder->groupBy('Company.id');

        try {

            $builder = [
                "builder" => $queryBuilder,
            ];

            if (isset($options['hasPagination']) && is_bool($options['hasPagination']) && $options['hasPagination'] == true) {
                $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
                $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
                $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;
                if ($page == 0) $page = intval($start / $limit) + 1;

                $builder['limit'] = $limit;
                $builder['page'] = $page;
            } else {
                $builder['limit'] = 10000;
                $builder['page'] = 0;
            }

            $paginator = new PaginatorQueryBuilder($builder);
            $pagination = $paginator->getPaginate();

            $companiesArray = [];

            $language = ModuleModel::$user_profile->getDisplayLanguage();
            if ($pagination->items->count() > 0) {
                if ($isFullMode == true) {
                    foreach ($pagination->items as $company) {
                        $companiesArray[$company->getId()] = $company->toArray();
                        $companiesArray[$company->getId()]['country_name'] = $company->getCountry() ? $company->getCountry()->getValueTranslationByLanguage($language) : '';
                        $invitation_request = InvitationRequest::findFirst([
                            "conditions" => "from_company_id = :from_company_id: and to_company_id = :to_company_id: and is_deleted = 0",
                            "bind" => [
                                "from_company_id" => ModuleModel::$company->getId(),
                                "to_company_id" => $company->getId()
                            ]
                        ]);
                        $companiesArray[$company->getId()]['is_booker'] = $company->isBooker();
                        $companiesArray[$company->getId()]['invitation_request'] = $invitation_request instanceof InvitationRequest ? intval($invitation_request->getId()) : '';
                        $companiesArray[$company->getId()]['country_iso2'] = $company->getCountry() ? $company->getCountry()->getCio() : null;
                    }
                } else {
                }
            }

            return [
                'success' => true,
                'page' => isset($page) ? $page : null,
                'data' => array_values($companiesArray),
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages
            ];

        } catch (\Phalcon\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }

    /**
     * @param $hrCompanyId : id company
     * @return \Reloday\Application\Models\Contract
     */
    public function getActiveContract($hrCompanyId)
    {
        return Contract::findFirst([
            'conditions' => 'from_company_id=:from_company_id: AND to_company_id = :to_company_id: AND status = :status_active:',
            'bind' => [
                'from_company_id' => $hrCompanyId,
                'to_company_id' => $this->getId(),
                'status_active' => Contract::STATUS_ACTIVATED
            ],
            'cache' => [
                'lifetime' => CacheHelper::__TIME_5_MINUTES,
                'key' => '_CACHE_CONTRACT_' . $hrCompanyId . '_' . $this->getId()
            ]
        ]);
    }

    /**
     * @return mixed
     */
    public function getPlan()
    {
        if ($this->getSubscription()) return $this->getSubscription()->getPlan();
    }

    /**
     * @return bool
     */
    public function isLegacy()
    {
        $plan = $this->getPlan();
        if ($plan) {
            return $plan->isLegacy();
        }
        return false;
    }

    /**
     * @param $fieldName
     * @param $fieldValue
     */
    public function saveCompanySetting($fieldName, $fieldValue)
    {
        $settings = $this->getCompanySetting([
            'conditions' => 'name = :name:',
            'bind' => [
                'name' => $fieldName,
            ]
        ]);
        if ($settings->count() > 0 && $settings->getFirst()) {
            $setting = $settings->getFirst();
            $setting->setValue($fieldValue);
            return $setting->__quickUpdate();
        } else {
            $defaultSetting = CompanySettingDefaultExt::findFirstByName($fieldName);
            if ($defaultSetting) {
                $setting = new CompanySetting();
                $setting->setCompanyId($this->getId());
                $setting->setCompanySettingDefaultId($defaultSetting->getId());
                $setting->setValue($fieldValue);
                $setting->setName($defaultSetting->getName());
                return $setting->__quickCreate();
            } else {
                return [
                    'success' => false,
                    'message' => 'SAVE_COMPANY_SETTING_FAIL_TEXT'
                ];
            }
        }
    }

    /**
     * @param $fieldName
     * @return bool|null
     */
    public function getCompanySettingValue($fieldName)
    {
        $defaultSetting = CompanySettingDefaultExt::findFirstByName($fieldName);

        if ($defaultSetting) {
            $settings = $this->getCompanySetting([
                'conditions' => 'name = :name: AND company_setting_default_id = :company_setting_default_id:',
                'bind' => [
                    'company_setting_default_id' => $defaultSetting->getId(),
                    'name' => $fieldName,
                ]
            ]);
            if ($settings->count() > 0) {

                $setting = $settings->getFirst();

                if ($fieldName == 'expense_approval_required' || $setting->getCompanySettingDefaultId() == CompanySettingDefaultExt::EXPENSE_APPROVAL_REQUIRE) {
                    return $setting->getValue() == 1 ? true : false;
                }

                if ($fieldName == 'recurring_expense' || $setting->getCompanySettingDefaultId() == CompanySettingDefaultExt::RECURRING_EXPENSE_OLD) {
                    return intval($setting->getValue());
                }

                if ($fieldName == 'display_self_service' || $setting->getCompanySettingDefaultId() == CompanySettingDefaultExt::DISPLAY_SELF_SERVICE) {
                    return intval($setting->getValue());
                }

                return $setting->getValue();
            }
        }
        return null;
    }

    public function getWelcomeMessage(){
        $welcomeMessage = Article::findFirst([
            'conditions' => 'type_id = :type_id: and company_id = :company_id:',
            'bind' => [
                'type_id' => Article::TYPE_WELCOME,
                'company_id' => $this->getId()
            ]
        ]);

        return $welcomeMessage;
    }

    /**
     * @param array $options
     * @return array
     */
    public static function __findBookerWithFilter($options = array(), $orders = array(), $isFullMode = true)
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Company', 'Company');
        $queryBuilder->distinct(true);

        $queryBuilder->where('Company.company_type_id = :type_booker:', [
            'type_booker' => self::TYPE_BOOKER,
        ]);

        $queryBuilder->andWhere('Company.created_by_company_id = :created_booker_company_id:', [
            'created_booker_company_id' => ModuleModel::$company->getId()
        ]);

        $queryBuilder->andWhere('Company.status = :status:', [
            'status' =>  self::STATUS_ACTIVATED,
        ]);


        if ($isFullMode == false) {
            $queryBuilder->columns(['Company.id', 'Company.name']);
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("Company.name LIKE :query:", ['query' => '%' . $options['query'] . '%']);
            $bindArray['query'] = $options['query'];
        }

        if (isset($options['company_ids']) && is_array($options['company_ids']) && count($options['company_ids']) > 0) {
            $queryBuilder->andwhere("Company.id IN ({company_ids:array})", ["company_ids" => $options['company_ids']]);
            $bindArray['company_ids'] = $options['company_ids'];
        }

        if (isset($options['except_company_ids']) && is_array($options['except_company_ids']) && count($options['except_company_ids']) > 0) {
            $queryBuilder->andwhere("Company.id NOT IN ({except_company_ids:array})", ["except_company_ids" => $options['except_company_ids']]);
            $bindArray['except_company_ids'] = $options['except_company_ids'];
        }

        if (count($orders)) {
            $order = reset($orders);
            $queryBuilder->orderBy('Company.name ASC');

            if ($order['field'] == "name") {
                if ($order['order'] == "asc") {
                    $queryBuilder->orderBy(['Company.name ASC']);
                } else {
                    $queryBuilder->orderBy(['Company.name DESC']);
                }
            }

        }else{
            $queryBuilder->orderBy('Company.name ASC');
        }


        $queryBuilder->groupBy('Company.id');

        try {

            $builder = [
                "builder" => $queryBuilder,
            ];

            if (isset($options['hasPagination']) && is_bool($options['hasPagination']) && $options['hasPagination'] == true) {
                $limit = isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0 ? $options['limit'] : self::LIMIT_PER_PAGE;
                $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
                $page = isset($options['page']) && is_numeric($options['page']) && $options['page'] > 0 ? $options['page'] : 0;
                if ($page == 0) $page = intval($start / $limit) + 1;

                $builder['limit'] = $limit;
                $builder['page'] = $page;
            } else {
                $builder['limit'] = 10000;
                $builder['page'] = 0;
            }

            $paginator = new PaginatorQueryBuilder($builder);
            $pagination = $paginator->getPaginate();

            $companiesArray = [];

            $language = ModuleModel::$user_profile->getDisplayLanguage();
            if ($pagination->items->count() > 0) {
                if ($isFullMode == true) {
                    foreach ($pagination->items as $company) {
                        $companiesArray[$company->getId()] = $company->toArray();
                        $country = $company->getCountry();
                        $companiesArray[$company->getId()]['country_name'] = $country ? $country->getValueTranslationByLanguage($language) : '';
                        $companiesArray[$company->getId()]['country_iso2'] = $country ? $country->getCio() : null;
                    }
                } else {
                    foreach ($pagination->items as $company) {
                        $companiesArray[$company['id']] = $company->toArray();
                    }
                }
            }

            return [
                'success' => true,
                'page' => isset($page) ? $page : null,
                'data' => array_values($companiesArray),
                'before' => $pagination->before,
                'next' => $pagination->next,
                'last' => $pagination->last,
                'current' => $pagination->current,
                'total_items' => $pagination->total_items,
                'total_pages' => $pagination->total_pages
            ];

        } catch (\Phalcon\Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (Exception $e) {
            return ['success' => false, 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }

    /**
     * @return void
     */
    public function parsedDataToArray(){
        $data = $this->toArray();
        $country = $this->getCountry();
        $language = ModuleModel::$user_profile->getDisplayLanguage();
        $data['country_name'] = $country ? $country->getValueTranslationByLanguage($language) : '';
        $data['country_iso2'] = $country ? $country->getCio() : null;

        return $data;
    }
}
