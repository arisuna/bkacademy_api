<?php

namespace Reloday\Gms\Models;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\EmployeeInContract;
use Reloday\Gms\Models\ServiceCompany;
use Reloday\Gms\Models\RelocationServiceCompany;
use Reloday\Gms\Models\ServicePack;

use Phalcon\Mvc\Model\Transaction\Failed as TransationFailed;
use Phalcon\Mvc\Model\Transaction\Manager as TransactionManager;

class PolicyItem extends \Reloday\Application\Models\PolicyItemExt {

	/**
     * Initialize method for model.
     */
    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('allowance_title_id', 'Reloday\Gms\Models\AllowanceTitle', 'id', ['alias' => 'AllowanceTitle']);
        $this->belongsTo('allowance_type_id', 'Reloday\Gms\Models\AllowanceType', 'id', ['alias' => 'AllowanceType']);
        $this->belongsTo('company_id', 'Reloday\Gms\Models\Company', 'id', ['alias' => 'Company']);
        $this->belongsTo('policy_id', 'Reloday\Gms\Models\Policy', 'id', ['alias' => 'Policy']);
    }
    /**
	 * [__save description]
	 * @return [type] [description]
	 */
	public function __save( $custom = []){
		$req = new Request();

        $model = $this;
        $data = $req->getPost();

        if ($req->isPut()) {
            // Request update
            // 
            $policy_item_id = isset($custom['policy_item_id']) && $custom['policy_item_id']  > 0 ? $custom['policy_item_id'] : $req->getPut('id');

            if( $policy_id > 0 ){
                $model = $this->findFirstById($policy_item_id);
                if (!$model instanceof $this) {
                    return [
                        'success' => false,
                        'message' => 'POLICY_ITEM_NOT_FOUND_TEXT',
                    ];
                }
            }
            $data = $req->getPut();
        }
        /** @var [varchar] [set uunique id] */
        $uuid = isset($data['uuid']) ? $data['uuid'] : (isset($custom['uuid']) ? $custom['uuid'] : $model->getUuid());
        if( $uuid == ''){
        	$random = new Random;
        	$uuid = $random->uuid();
        	$model->setUuid( $uuid );
        }
        /** @var [integer] [set status] */
        $status = isset($custom['status']) ? $custom['status'] : (isset($data['status']) ? $data['status'] : $model->getStatus());
        if( $status == null || $status == '' ){
        	$status = self::STATUS_ACTIVE;
        }
        $model->setStatus($status);
        //$model->setName(array_key_exists('name', $data) ? $data['name'] : (isset($custom['name'])? $custom['name'] : $model->getName()));
        $model->setCompanyId(isset($custom['company_id']) ? $custom['company_id'] : (isset($data['company_id']) ? $data['company_id'] : $model->getCompanyId()));
        $model->setPolicyId(isset($custom['policy_id']) ? $custom['policy_id'] : (isset($data['policy_id']) ? $data['policy_id'] : $model->getPolicyId()));
        $model->setAllowanceTypeId(isset($custom['allowance_type_id']) ? $custom['allowance_type_id'] : (isset($data['allowance_type_id']) ? $data['allowance_type_id'] : $model->getAllowanceTypeId()));
        $model->setAllowanceTitleId(isset($custom['allowance_title_id']) ? $custom['allowance_title_id'] : (isset($data['allowance_title_id']) ? $data['allowance_title_id'] : $model->getAllowanceTitleId()));

        $model->setProviderPolicy(isset($custom['provider_policy']) ? $custom['provider_policy'] : (isset($data['provider_policy']) ? $data['provider_policy'] : $model->getProviderPolicy()));
        $model->setBillingPolicy(isset($custom['billing_policy']) ? $custom['billing_policy'] : (isset($data['billing_policy']) ? $data['billing_policy'] : $model->getBillingPolicy()));
        $model->setSubjectToCap(isset($custom['subject_to_cap']) ? $custom['subject_to_cap'] : (isset($data['subject_to_cap']) ? $data['subject_to_cap'] : $model->getSubjectToCap()));
        $model->setSubjectToCapText(isset($custom['subject_to_cap_text']) ? $custom['subject_to_cap_text'] : (isset($data['subject_to_cap_text']) ? $data['subject_to_cap_text'] : $model->getSubjectToCapText()));
        $model->setBudget(isset($custom['budget']) ? $custom['budget'] : (isset($data['budget']) ? $data['budget'] : $model->getBudget()));
        $model->setCurrency(isset($custom['currency']) ? $custom['currency'] : (isset($data['currency']) ? $data['currency'] : $model->getCurrency()));
        $model->setComments(isset($custom['comments']) ? $custom['comments'] : (isset($data['comments']) ? $data['comments'] : $model->getComments()));


        try{
            if( $model->getId() == null ){
                
            }
            if ($model->save()) {
                return $model;
            } else {
                $msg = [];
                foreach ($model->getMessages() as $message) {
                    $msg[$message->getField()] = $message->getMessage();
                }
                $result = [
                    'success' => false,
                    'message' => 'SAVE_POLICY_ITEM_FAIL_TEXT',
                    'detail' => $msg
                ];
                return $result;
            }
        }catch(\PDOException $e){
            $result = [
                'success'   => false,
                'message'       => 'SAVE_POLICY_ITEM_SUCCESS_TEXT',
                'detail'    => $e->getMessage(),
            ];
            return $result;
        }
	}

    public function afterFetch(){


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