<?php

namespace Reloday\Gms\Models;

use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class EmployeeSupportContact extends \Reloday\Application\Models\EmployeeSupportContactExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    public function initialize()
    {
        parent::initialize();

        $this->belongsTo('employee_id', 'Reloday\Gms\Models\Employee', 'id', [
            'alias' => 'Employee'
        ]);

        $this->belongsTo('contact_employee_id', 'Reloday\Gms\Models\Employee', 'id', [
            'alias' => 'ContactEmployee'
        ]);

        $this->belongsTo('contact_user_profile_id', 'Reloday\Gms\Models\UserProfile', 'id', [
            'alias' => 'ContactUserProfile'
        ]);
    }

    /**
     * @param int $employeeId
     * @param int $relocationId
     * @return \Phalcon\Mvc\Model\ResultSetInterface|\Reloday\Application\Models\EmployeeSupportContact|\Reloday\Application\Models\EmployeeSupportContact[]
     */
    public static function __getSupportContacts($employeeId,  $relocationId = 0)
    {
        $filter = [
            'employee_id' => $employeeId,
            'is_buddy' => false
        ];

        if($relocationId && $relocationId > 0) {
            $filter = [
                'employee_id' => $employeeId,
                'is_buddy' => false,
                'relocation_id' => $relocationId
            ];
            return self::__findWithFilters($filter);

        }else{
            return [];
        }

    }

    /**
     * @param $employeeId
     * @return \Phalcon\Mvc\Model\ResultSetInterface|\Reloday\Application\Models\EmployeeSupportContact|\Reloday\Application\Models\EmployeeSupportContact[]
     */
    public static function __getRelocationSupportContact($relocationId)
    {
        $items = self::__findWithFilters([
            'relocation_id' => $relocationId,
            'is_buddy' => false,
            'limit' => 1
        ]);
        if (is_array($items) && count($items) > 0) {
            return reset($items);
        }
    }

    /**
     * @param $employeeId
     * @return \Phalcon\Mvc\Model\ResultSetInterface|\Reloday\Application\Models\EmployeeSupportContact|\Reloday\Application\Models\EmployeeSupportContact[]
     */
    public static function __getBuddyContacts($employeeId)
    {
        return self::__findWithFilters([
            'employee_id' => $employeeId,
            'is_buddy' => true,
        ]);
    }

    /**
     * @param $userProfile
     * @param $relocation
     */
    public static function __addRelocationSupportContact($userProfile, $relocation)
    {
        $supportContact = new self();
        $supportContact->setIsBuddy(ModelHelper::NO);
        $supportContact->setEmployeeId($relocation->getEmployeeId());
        $supportContact->setRelocationId($relocation->getId());
        $supportContact->setJobtitle($userProfile->getJobtitle());
        $supportContact->setCreatorCompanyId(ModuleModel::$company->getId());
        $supportContact->setContactProfileUuid($userProfile->getUuid());
        $supportContact->setCompanyName($userProfile->getCompany()->getName());
        $supportContact->setFirstname($userProfile->getFirstname());
        $supportContact->setLastname($userProfile->getLastname());
        $supportContact->setTelephone($userProfile->getPhonework());
        $supportContact->setMobile($userProfile->getMobilework());
        $supportContact->setEmail($userProfile->getWorkemail());
        $result = $supportContact->__quickCreate();
        return $result;
    }

    /**
     * @return array
     */
    public static function __findWithFilters($options = [])
    {

        $di = \Phalcon\DI::getDefault();
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\EmployeeSupportContact', 'EmployeeSupportContact');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\Employee', 'Employee.id = EmployeeSupportContact.contact_employee_id', 'Employee');
        $queryBuilder->leftJoin('\Reloday\Gms\Models\UserProfile', 'UserProfile.id = EmployeeSupportContact.contact_user_profile_id', 'UserProfile');
        $queryBuilder->distinct(true);

        $queryBuilder->columns([

            'EmployeeSupportContact.id',
            'EmployeeSupportContact.company_name',
            'EmployeeSupportContact.firstname',
            'EmployeeSupportContact.lastname',
            'EmployeeSupportContact.jobtitle',
            'EmployeeSupportContact.email',
            'EmployeeSupportContact.telephone',
            'EmployeeSupportContact.mobile',
            'EmployeeSupportContact.contact_employee_id',
            'EmployeeSupportContact.contact_user_profile_id',
            'EmployeeSupportContact.contact_profile_uuid',

            'employee_jobtitle' => 'Employee.jobtitle',
            'employee_workemail' => 'Employee.workemail',
            'employee_phonework' => 'Employee.phonework',
            'employee_firstname' => 'Employee.firstname',
            'employee_lastname' => 'Employee.lastname',
            'employee_mobilework' => 'Employee.mobilework',

            'user_profile_jobtitle' => 'UserProfile.jobtitle',
            'user_profile_workemail' => 'UserProfile.workemail',
            'user_profile_phonework' => 'UserProfile.phonework',
            'user_profile_firstname' => 'UserProfile.firstname',
            'user_profile_lastname' => 'UserProfile.lastname',
            'user_profile_mobilework' => 'UserProfile.mobilework',
        ]);

        $bindArray = [];
        
        $queryBuilder->where('EmployeeSupportContact.creator_company_id =  :creator_company_id:');
        $bindArray['creator_company_id'] = ModuleModel::$company->getId();



        if (isset($options['employee_id']) && is_numeric($options['employee_id']) && $options['employee_id'] > 0) {
            $queryBuilder->andWhere('EmployeeSupportContact.employee_id = :employee_id:');
            $bindArray['employee_id'] = $options['employee_id'];
        }

        if (isset($options['relocation_id']) && is_numeric($options['relocation_id']) && $options['relocation_id'] > 0) {
            $queryBuilder->andWhere('EmployeeSupportContact.relocation_id = :relocation_id:');
            $bindArray['relocation_id'] = $options['relocation_id'];
        }

        if (isset($options['is_buddy']) && is_bool($options['is_buddy']) && $options['is_buddy'] == false) {
            $queryBuilder->andWhere('EmployeeSupportContact.is_buddy =  :is_buddy_no:');
            $bindArray['is_buddy_no'] = ModelHelper::NO;
        }

        if (isset($options['is_buddy']) && is_bool($options['is_buddy']) && $options['is_buddy'] == true) {
            $queryBuilder->andWhere('EmployeeSupportContact.is_buddy =  :is_buddy_yes:');
            $bindArray['is_buddy_yes'] = ModelHelper::YES;
        }

        if (isset($options['limit']) && is_numeric($options['limit']) && $options['limit'] > 0) {
            $queryBuilder->limit($options['limit']);
        }

        $queryBuilder->orderBy('EmployeeSupportContact.created_at DESC');
        try {
            $items = $queryBuilder->getQuery()->execute($bindArray);
            $itemsArray = [];



            if ($items->count() > 0) {
                foreach ($items as $contactItem) {
                    $item = $contactItem->toArray();
                    if ($item['contact_employee_id'] > 0) {
                        $item['firstname'] = $item['employee_firstname'];
                        $item['lastname'] = $item['employee_lastname'];
                        $item['jobtitle'] = $item['employee_jobtitle'];
                        $item['telephone'] = $item['employee_phonework'];
                        $item['mobile'] = $item['employee_mobilework'];
                        $item['email'] = $item['employee_workemail'];
                    }

                    if ($item['contact_user_profile_id'] > 0) {
                        $item['firstname'] = $item['user_profile_firstname'];
                        $item['lastname'] = $item['user_profile_lastname'];
                        $item['jobtitle'] = $item['user_profile_jobtitle'];
                        $item['telephone'] = $item['user_profile_phonework'];
                        $item['mobile'] = $item['user_profile_mobilework'];
                        $item['email'] = $item['user_profile_workemail'];
                    }

                    unset($item['user_profile_workemail']);
                    unset($item['user_profile_firstname']);
                    unset($item['user_profile_lastname']);
                    unset($item['user_profile_jobtitle']);
                    unset($item['user_profile_phonework']);
                    unset($item['user_profile_mobilework']);

                    unset($item['employee_workemail']);
                    unset($item['employee_firstname']);
                    unset($item['employee_lastname']);
                    unset($item['employee_jobtitle']);
                    unset($item['employee_phonework']);
                    unset($item['employee_mobilework']);

                    $itemsArray[] = $item;
                }
            }

            return $itemsArray;
        } catch (\Phalcon\Exception $e) {
            Helpers::__trackError($e);
            return [];
        } catch (\PDOException $e) {
            Helpers::__trackError($e);
            return [];
        } catch (Exception $e) {
            Helpers::__trackError($e);
            return [];
        }
    }

    /**
     * @return bool
     */
    public function belongsToGms()
    {
        return $this->getCreatorCompanyId() == ModuleModel::$company->getId();
    }
}
