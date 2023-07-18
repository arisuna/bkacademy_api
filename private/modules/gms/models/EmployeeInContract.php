<?php

namespace Reloday\Gms\Models;

class EmployeeInContract extends \Reloday\Application\Models\EmployeeInContractExt
{

    /**
     * @param string $condition
     * @return array
     */
    public static function loadList($condition = '')
    {
        $result_search_contract = Contract::__getAllOfCurrentGMS();
        $contracts = $result_search_contract['data'];
        if (count($contracts) > 0) {
            $contract_ids = [];
            foreach ($contracts as $contract) {
                $contract_ids[] = $contract->getId();
            }
            $employees_in_contracts = self::find('contract_id IN (' . implode(',', $contract_ids) . ')');

            $employees_arr = [];

            if (count($employees_in_contracts)) {
                $employee_ids = [];
                foreach ($employees_in_contracts as $u) {
                    $employee_ids[] = $u->getEmployeeId();
                }
                $employees = Employee::find([
                    'conditions' => 'id IN (' . implode(',', $employee_ids) . ')' . ($condition ? " AND " . $condition : ''),
                    'order' => 'id DESC'
                ]);

                if (count($employees)) {
                    foreach ($employees as $employee) {
                        $data = $employee->toArray();
                        $data['company_name'] = $employee->getCompany()->getName();
                        $data['office_name'] = $employee->getOffice() ? $employee->getOffice()->getName() : '';
                        $data['department_name'] = $employee->getDepartment() ? $employee->getDepartment()->getName() : '';
                        $data['team_name'] = $employee->getTeam() ? $employee->getTeam()->getName() : '';
                        $employees_arr[$employee->getId()] = $data;
                    }
                }
            }
            return [
                'success' => true,
                'data' => array_values($employees_arr),
                'detail' => $contracts
            ];
        } else {
            return [
                'success' => true,
                'data' => [],
                'message' => 'NO_ASSIGNEES_TEXT'
            ];
        }
    }
}