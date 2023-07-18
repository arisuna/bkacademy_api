<?php


namespace Reloday\Gms\Models;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\Contract;
use Reloday\Gms\Models\ModuleModel;

class AllowanceType extends \Reloday\Application\Models\AllowanceTypeExt {

	/**
	 * create new allowance type
	 * @param  array  $params [description]
	 * @return [type]         [description]
	 */
	public static function __create($params = []){

		if( !isset($params['company_id']) || !isset($params['name'])  ){
			return ['success' => false, 'message' => 'PARAMS_NOT_FOUND', 'detail' => $params ];
		}
		$random = new \Phalcon\Security\Random();
		$model = new self();
		$model->setName( $params['name'] );
		$model->setCompanyId( $params['company_id'] );
		$model->setUuid( $random->uuid() );
		if( $model->save() ){
			return $model;
		}else{
			$messages = $model->getMessages();
			return ['success' => false, 'message' => implode(',', $messages )];
		}

	}
	/**
	 * load all allowance type associated with an GMS with contract
	 * @return [type] [description]
	 */
	public static function __loadAllAllowanceTypeOfGms(){
		$companies = Company::__loadAllCompaniesOfGms();
		if( count( $companies ) > 0 ){
			$company_ids = [];
			foreach( $companies as $company ){
				$company_ids[] = $company->getId();
			}
		}
		/*
		$allowance_types = self::find([
			"conditions" => "company_id IN (:company_ids:)",
			"bind"	=> [
				'company_ids' => $company_ids
			]
		]);
		*/
		$allowance_types = self::find();

		if( count($allowance_types) ){
			return $allowance_types;
		}
	}
	/**
	 * check is belong to GMS
	 * @return [type] [description]
	 */
	public function belongsToGms(){
		$company = ModuleModel::$company;
		if( $company ){
			$contract = Contract::findFirst([
				"conditions" => "from_company_id = :from_company_id: AND to_company_id = :to_company_id:",
				"bind"	=> [
					"from_company_id" => $this->getCompanyId(),
					"to_company_id"	  => $company->getId()
				]
			]);
			if( $contract ){
				return true;
			}else{
				return false;
			}
		}else{
			return false;
		}
	}

	 /**
     * [getAllCurrentAssignmentType description]
     * @return [type] [description]
     */
    public static function getAllCurrentAllowanceType( $company_id = ''){

        $current_company_gms = ModuleModel::$company;
        $companies = Company::__loadAllCompaniesOfGms();
        $company_ids = [];


        if( $companies ){

            foreach( $companies as $company ){
                $company_ids[] = $company->getId();
            }

            if( is_numeric($company_id) && $company_id > 0 ){
                if( in_array($company_id, $company_ids ) ){
                    $allowances = self::find([
                        'conditions' => 'company_id = :company_id:',
                        'bind'  => [
                            'company_id' => $company_id,
                        ]
                    ]);
                    return $allowances;
                }else{
                    return null;
                }

            }else{
                $allowances = self::find([
                    'conditions' => 'company_id IN({company_ids:array})',
                    'bind'  => [
                        'company_ids' => $company_ids,
                    ]
                ]);
            }

            return $allowances;
        }
    }

}