<?php


namespace Reloday\Gms\Models;
use Reloday\Gms\Models\Contract;
use Reloday\Gms\Models\ModuleModel;

class AllowanceTitle extends \Reloday\Application\Models\AllowanceTitleExt {

	/**
	 * create new allowance type
	 * @param  array  $params [description]
	 * @return [type]         [description]
	 */
	public static function __save($params = []){

		if( !isset($params['company_id']) || !isset($params['name'])  || !isset( $params['allowance_type_id'] ) ){
			return ['success' => false, 'message' => 'PARAMS_NOT_FOUND', 'detail' => $params ];
		}

        $model = new self();

        if (isset( $params['id'] ) ){
            // Request update
            $allowance_title_id = isset($params['id']) && $params['id']  > 0 ? $params['id'] : null;
            if( $allowance_title_id > 0 ){
                $model = self::findFirstById($allowance_title_id);
                if (!$model instanceof self) {
                    return [
                        'success' => false,
                        'message' => 'ALLOWANCE_TITLE_NOT_FOUND_TEXT',
                    ];
                }
            }
        }

		$model->setName( $params['name'] );
		$model->setCompanyId( $params['company_id'] );
        /** @var  $uuid :check uuid*/
        $random = new \Phalcon\Security\Random();
		$uuid = isset($params['uuid']) && $params['uuid']!="" ? $params['uuid']:$model->getUuid();
		if( $uuid == '' ){
            $uuid =$random->uuid();
        }
		$model->setUuid( $uuid );
		$model->setAllowanceTypeId( $params['allowance_type_id'] );


		if( $model->save() ){
			return $model;
		}else{
			$messages = $model->getMessages();
            $msg = [];
            foreach ($model->getMessages() as $message) {
                $msg[$message->getField()] = $message->getMessage();
            }
			return ['success' => false, 'message' => 'ALLOWANCE_TITLE_SAVE_FAILED', 'detail' => $msg ];
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
		$allowance_types = [];
        if( count($company_ids) > 0) {
            $allowance_types = self::find([
                "conditions" => "company_id IN ({company_ids:array})",
                "bind" => [
                    'company_ids' => $company_ids
                ]
            ]);
        }
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
    public static function getAllCurrentAllowanceTitle( $company_id = '', $allowance_type_id = ''){

        $current_company_gms = ModuleModel::$company;
        $companies = Company::__loadAllCompaniesOfGms();
        $company_ids = [];


        if( $companies ){

            foreach( $companies as $company ){
                $company_ids[] = $company->getId();
            }

            $conditions = "";
            $bind = [];
            if( is_numeric($company_id) && $company_id > 0 ){
                if( in_array($company_id, $company_ids ) ){
                	$conditions.= 'company_id = :company_id:';
                	$bind['company_id'] = $company_id;
               	}
            }

            if( is_numeric($allowance_type_id) && $allowance_type_id > 0 ){
                $conditions.= 'AND allowance_type_id = :allowance_type_id:';
                $bind['allowance_type_id'] = $allowance_type_id;
            }



            if( $conditions != "" && count($bind) > 0 ){

                $allowances = self::find([
                    'conditions' => $conditions,
                    'bind'  => $bind,
                ]);
                return $allowances;
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

    /**
     * remove data ta
     */
    public function remove(){
        try{
            if ($this->delete()) {
                return [
                    'success' => true,
                    'message'   => 'DATA_DELETE_SUCESS_TEXT'
                ];
            } else {
                $msg = [];
                foreach ($model->getMessages() as $message) {
                    $msg[$message->getField()] = $message->getMessage();
                }
                $result = [
                    'success' => false,
                    'message' => 'DATA_DELETE_FAIL_TEXT',
                    'detail' => $msg
                ];
                return $result;
            }
        }catch(\PDOException $e){
            $result = [
                'success'   => false,
                'message'       => 'DATA_DELETE_FAIL_TEXT',
                'detail'    => $e->getMessage(),
            ];
            return $result;
        }
    }
}