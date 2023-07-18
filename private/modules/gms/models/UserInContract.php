<?php

namespace Reloday\Gms\Models;

class UserInContract extends \Reloday\Application\Models\UserInContractExt
{

    /**
     * @param string $condition
     * @return array
     */
    public function loadList($condition = '')
    {

        // 1. Load user profile
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

                    // Find list user in contract
                    if (count($contracts)) {
                        $contract_ids = [];
                        foreach ($contracts as $contract) {
                            $contract_ids[] = $contract->getId();
                        }

                        if (!count($contract_ids)) {
                            return [
                                'success' => false,
                                'message' => 'EMPTY_CONTRACT'
                            ];
                        }
                        $userInContracts = UserInContract::find('contract_id IN (' . implode(',', $contract_ids) . ')');
                        if (count($userInContracts)) {
                            $user_ids = [];
                            foreach ($userInContracts as $u) {
                                $user_ids[] = $u->getUserId();
                            }

                            $users = UserProfile::find([
                                'conditions' => 'id IN (' . implode(',', $user_ids) . ')' . ($condition ? " AND " . $condition : ''),
                                'order' => 'id DESC'
                            ]);
                            if (count($users)) {
                                $users_arr = [];
                                $companies_arr = [];
                                $company_ids = [];
                                $offices_arr = [];
                                $office_ids = [];
                                $departments_arr = [];
                                $department_ids = [];
                                $teams_arr = [];
                                $team_ids = [];

                                $roles_arr = [];
                                $roles = UserGroup::find();
                                if(count($roles)) {
                                    foreach ($roles as $role) {
                                        $roles_arr[$role->getId()] = $role->toArray();
                                    }
                                }

                                foreach ($users as $user) {
                                    if ($user->getCompanyId())
                                        $company_ids[$user->getCompanyId()] = $user->getCompanyId();
                                    if ($user->getOfficeId())
                                        $office_ids[$user->getOfficeId()] = $user->getOfficeId();
                                    if ($user->getDepartmentId())
                                        $department_ids[$user->getDepartmentId()] = $user->getDepartmentId();
                                    if ($user->getTeamId())
                                        $team_ids[$user->getTeamId()] = $user->getTeamId();
                                }

                                if (count($company_ids)) {
                                    $companies = Company::find('id IN (' . implode(',', $company_ids) . ')');
                                    if (count($companies)) {
                                        foreach ($companies as $company) {
                                            $companies_arr[$company->getId()] = $company->toArray();
                                        }
                                    }
                                }

                                if (count($office_ids)) {
                                    $offices = Office::find('id IN (' . implode(',', $office_ids) . ')');
                                    if (count($offices)) {
                                        foreach ($offices as $office) {
                                            $offices_arr[$office->getId()] = $office->toArray();
                                        }
                                    }
                                }

                                if (count($department_ids)) {
                                    $departments = Department::find('id IN (' . implode(',', $department_ids) . ')');
                                    if (count($departments)) {
                                        foreach ($departments as $department) {
                                            $departments_arr[$department->getId()] = $department->toArray();
                                        }
                                    }
                                }

                                if (count($team_ids)) {
                                    $teams = Team::find('id IN (' . implode(',', $team_ids) . ')');
                                    foreach ($teams as $team) {
                                        $teams_arr[$team->getId()] = $team->toArray();
                                    }
                                }

                                // Add key to list user
                                foreach ($users as $user) {
                                    $data = $user->toArray();
                                    if (array_key_exists($data['company_id'], $companies_arr))
                                        $data['company_name'] = $companies_arr[$data['company_id']]['name'];
                                    if (array_key_exists($data['office_id'], $offices_arr))
                                        $data['office_name'] = $offices_arr[$data['office_id']]['name'];
                                    if (array_key_exists($data['department_id'], $departments_arr))
                                        $data['department_name'] = $departments_arr[$data['department_id']]['name'];
                                    if (array_key_exists($data['team_id'], $teams_arr))
                                        $data['team_name'] = $teams_arr[$data['team_id']]['name'];
                                    if(array_key_exists($data['user_group_id'], $roles_arr))
                                        $data['role_name'] = $roles_arr[$data['user_group_id']]['name'];
                                    $users_arr[$user->getId()] = $data;
                                }

                                return [
                                    'success' => true,
                                    'users' => $users_arr,
                                    'companies' => $companies_arr,
                                    'offices' => $offices_arr,
                                    'departments' => $departments_arr,
                                    'teams' => $teams_arr,
                                    'roles' => $roles_arr
                                ];

                            } else {
                                return [
                                    'success' => false,
                                    'message' => 'NO_USER_IN_CONTRACT'
                                ];
                            }

                        }
                    } else {
                        return [
                            'success' => false,
                            'message' => 'NO_USER_IN_CONTRACT'
                        ];
                    }
                }
            }
        } else {
            return [
                'success' => false,
                'message' => 'CAN_NOT_FIND_LOGGER'
            ];
        }
    }
}