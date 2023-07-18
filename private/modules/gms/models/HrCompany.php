<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;

use Reloday\Application\Lib\CacheHelper;
use Reloday\Gms\Models\MediaAttachment as MediaAttachment;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;
use Phalcon\Paginator\Adapter\NativeArray as PaginatorArray;
use Phalcon\Paginator\Adapter\QueryBuilder as PaginatorQueryBuilder;
use Phalcon\Paginator\Factory;

class HrCompany extends \Reloday\Application\Models\CompanyExt
{
    protected $isBelongsToGms = null;

    protected $canEdit = null;

    const LIMIT_PER_PAGE = 20;

    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('country_id', 'Reloday\Gms\Models\Country', 'id', [
            'alias' => 'Country'
        ]);
        $this->hasOne('id', 'Reloday\Gms\Models\CompanyBusinessDetail', 'id', [
            'alias' => 'CompanyBusinessDetail'
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

        $this->hasOne('id', 'Reloday\Application\Models\Subscription', 'company_id', [
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
            if ($this->getSubscription() || $this->getChargebeeCustomer()) {
                $this->canEdit = false;
            } else {
                $this->canEdit = true;
            }
        }
        return $this->canEdit;
    }

    /**
     *
     */
    public function __loadCurrentAccountsList($options = array())
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $user_profile_uuid = ModuleModel::$user_profile->getUuid();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Company', 'Company');
        $queryBuilder->columns([
            'id' => 'Company.id',
            'uuid' => 'Company.uuid',
            'name' => 'Company.name'
        ]);
        $queryBuilder->distinct(true);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'Contract.from_company_id = Company.id', 'Contract');
        $queryBuilder->where('Contract.to_company_id = :gms_company_id:');
        $queryBuilder->andWhere('Contract.status = :contract_activated:');
        $queryBuilder->andWhere('Company.company_type_id = :company_type_id:');
        //$queryBuilder->limit(1); //@bug in phalcon
        $bindArray = [
            'company_type_id' => self::TYPE_HR,
            'gms_company_id' => intval(ModuleModel::$company->getId()),
            'contract_activated' => intval(Contract::STATUS_ACTIVATED),
        ];

        if (ModuleModel::$user_profile->isGmsAdminOrManager() == false) {
            $queryBuilder->innerJoin('\Reloday\Gms\Models\Assignment', 'Company.id = Assignment.company_id', 'Assignment');
            $queryBuilder->leftJoin('\Reloday\Gms\Models\Relocation', 'Assignment.id = Relocation.assignment_id', 'Relocation');
            $queryBuilder->leftJoin('\Reloday\Gms\Models\DataUserMember', 'Assignment.uuid = DataUserMemberAssignment.object_uuid', 'DataUserMemberAssignment');
            $queryBuilder->leftJoin('\Reloday\Gms\Models\DataUserMember', 'Relocation.uuid = DataUserMemberRelocation.object_uuid', 'DataUserMemberRelocation');
            $queryBuilder->andWhere('Assignment.archived = :assignment_not_archived:');
            $queryBuilder->andWhere('Relocation.active = :relocation_activated:');
            $queryBuilder->andWhere("Relocation.active = :relocation_activated:");
            $queryBuilder->andWhere('DataUserMemberAssignment.user_profile_uuid = :user_profile_uuid:  OR DataUserMemberRelocation.user_profile_uuid = :user_profile_uuid:');

            $bindArray['assignment_not_archived'] = intval(Assignment::ARCHIVED_NO);
            $bindArray['relocation_activated'] = intval(Relocation::STATUS_ACTIVATED);
            $bindArray['user_profile_uuid'] = ModuleModel::$user_profile->getUuid();
        }
        //var_dump( $queryBuilder->getPhql() ); die();
        $companies = ($di->get('modelsManager')->executeQuery($queryBuilder->getPhql(), $bindArray));
        $data = [];
        if (count($companies)) {
            foreach ($companies as $company) {
                $data[] = [
                    'id' => (int)$company['id'],
                    'uuid' => $company['uuid'],
                    'name' => $company['name']
                ];
            }
            return $data;
        }
        return [];
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
     * @param $uuid
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
     * @param array $options
     * @return array
     */
    public static function __findHrWithFilter($options = array(), $orders = array(), $isFullMode = true)
    {
        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Company', 'Company');
        $queryBuilder->distinct(true);
        $queryBuilder->innerjoin('\Reloday\Gms\Models\Contract', 'Contract.from_company_id = Company.id', 'Contract');
        $queryBuilder->where('Contract.to_company_id = :to_company_id:', [
            'to_company_id' => ModuleModel::$company->getId()
        ]);
        $queryBuilder->andwhere("Contract.status = :status_active:", [
            'status_active' => Contract::STATUS_ACTIVATED
        ]);

        if ($isFullMode == false) {
            $queryBuilder->columns(['Company.id', 'Company.name']);
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

        $queryBuilder->orderBy('Company.created_at DESC');
        $queryBuilder->groupBy('Company.id');

        try {

            $builder = [
                "builder" => $queryBuilder,
            ];

            if (isset($options['hasPagination']) && is_bool($options['hasPagination']) && $options['hasPagination'] == true) {
                $start = isset($options['start']) && is_numeric($options['start']) && $options['start'] > 0 ? $options['start'] : 0;
                $page = intval($start / self::LIMIT_PER_PAGE) + 1;

                $builder['limit'] = self::LIMIT_PER_PAGE;
                $builder['page'] = $page;
            } else {
                $builder['limit'] = 10000;
                $builder['page'] = 0;
            }

            $paginator = new PaginatorQueryBuilder($builder);
            $pagination = $paginator->getPaginate();

            $companiesArray = [];
            if ($pagination->items->count() > 0) {
                if ($isFullMode == true) {
                    foreach ($pagination->items as $company) {
                        $companiesArray[$company->getId()] = $company->toArray();
                        $companiesArray[$company->getId()]['country_name'] = $company->getCountry() ? $company->getCountry()->getName() : '';
                        $invitation_request = InvitationRequest::findFirst([
                            "conditions" => "from_company_id = :from_company_id: and to_company_id = :to_company_id: and is_deleted = 0",
                            "bind" => [
                                "from_company_id" => ModuleModel::$company->getId(),
                                "to_company_id" => $company->getId()
                            ]
                        ]);
                        $companiesArray[$company->getId()]['invitation_request'] = $invitation_request instanceof InvitationRequest ? intval($invitation_request->getId()) : '';
                    }
                } else {
                    foreach ($pagination->items as $company) {
                        $companiesArray[$company['id']] = $company;
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
     * @param $hrCompanyId
     * @return bool
     */
    public static function __checkIsEditableById($hrCompanyId)
    {
        $check = Subscription::findFirst([
            'conditions' => 'company_id = :company_id:',
            'bind' => [
                'company_id' => $hrCompanyId,
            ],
            'cache' => [
                'key' => "SUBSCRIPTION_COMPANY_" . $hrCompanyId,
                'lifetime' => CacheHelper::__TIME_5_MINUTES,
            ]
        ]);
        if ($check) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param $email
     */
    public static function __findAccountOfEmail(String $email){

        $userProfile = UserProfile::findFirstByWorkemail($email);

        if( $userProfile->isGms() )


        $companyCount = self::countByEmail($email);
        if( $companyCount > 1 ){

        }


        if (!$targetCompany instanceof Company) {
            $userProfile = UserProfile::findFirstByWorkemail($email);
            if ($userProfile instanceof UserProfile && $userProfile->isHr() && $userProfile->isAdmin() && $userProfile->isActive()) {
                $targetCompany = $userProfile->getCompany();
            }
        }
    }
}