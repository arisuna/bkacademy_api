<?php

namespace Reloday\Gms\Controllers\API;

use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Controllers\API\BaseController;
use \Reloday\Gms\Models\AllowanceType;
use \Reloday\Gms\Models\AllowanceTypeDefault;
use \Reloday\Gms\Models\Company;
/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class AllowancetypeController extends BaseController
{
	/**
     * @Route("/allowancetype", paths={module="gms"}, methods={"GET"}, name="gms-allowancetype-index")
     */
    public function indexAction()
    {
        $this->checkAjax('GET');
        $this->checkAcl('index',$this->router->getControllerName());

        //load all allowance type of all companies of current gms company
        //
        //
        $allowance_types = AllowanceType::__loadAllAllowanceTypeOfGms();
        $types = [];
        if( count($allowance_types) ){
	        foreach( $allowance_types as $allowance ){
	        	$types[] = [
	        		'id' 			=> $allowance->getId(),
	        		'name' 			=> $allowance->getName(),
	        		'company_name'	=> $allowance->getCompany()->getName()
	        	];
	        }
	    }
       	$results = ['success' => true, 'data' => $types ];
       	$this->response->setJsonContent($results);
        $this->response->send();

    }
    /**
     * [initAction description]
     * @return [type] [description]
     */
    public function initAction(){
    	$this->view->disable();

        $this->checkAjaxGet();

        $types = AllowanceTypeDefault::find();
        $this->response->setJsonContent(["success" => true, "data" => $types->toArray()]);
        $this->response->send();

    }
    /**
     * [createAction description]
     * @return [type] [description]
     */
    public function createAction(){
    	$this->view->disable();
        $this->checkAjax(['POST','PUT']);
        $this->checkAcl('create','edit',$this->router->getControllerName());


        $data = $this->request->getJsonRawBody();
        $result = AllowanceType::__create([
        	"company_id" => $data->company_id,
        	"name"		 => $data->name,
        ]);

        if( $result instanceof AllowanceType ){
        	$this->response->setJsonContent(["success" => true, "msg" => "ALLOWANCE_CREATED_SUCCESS"]);
        	$this->response->send();
        }else{
        	$this->response->setJsonContent(["success" => false, "msg" => $result['message']]);
        	$this->response->send();
        }

    }

    /**
     * load detail of company
     * @method GET
     * @route /company/detail
     * @param int $id
     */
    public function detailAction($id = 0)
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $id = $id ? $id : $this->request->get('id');
        $allowancetype = AllowanceType::findFirst($id ? $id : 0);

        $accept = $allowancetype instanceof AllowanceType && $allowancetype->belongsToGms() == true ? true : false;

        $this->response->setJsonContent([
            'success' 	=> $accept ? true : false,
            'message' 		=> $accept ? 'LOAD_DETAIL_SUCCESS_TEXT' : 'LOAD_DETAIL_FAIL_TEXT',
            'data' 		=> $accept ? $allowancetype : []
        ]);
        $this->response->send();
    }

    /**
     * load detail of company
     * @method GET
     * @route /company/detail
     * @param int $id
     */
    public function deleteAction()
    {
        $this->view->disable();
        $this->checkAjax(['PUT','DELETE']);
        $this->checkAcl('delete',$this->router->getControllerName());

        $data = $this->request->getJsonRawBody();
        $id = $data->id;

        $allowancetype = AllowanceType::findFirst($id ? $id : 0);

        $accept = $allowancetype instanceof AllowanceType && $allowancetype->belongsToGms() == true ? true : false;
        if( $accept == true ){
        	if( !$allowancetype->delete() ){
        		$accept = false;
        	}
        }
        $this->response->setJsonContent([
            'success' 	=> $accept ? true : false,
            'message' 		=> $accept ? 'DELETE_SUCCESS' : 'DELETE_FAILED',
            'data' 		=> $accept ? $allowancetype : []
        ]);
        $this->response->send();
    }

    /**
     * list assigmnet type by company
     * @param  string $company_id [description]
     * @return [type]             [description]
     */
    public function companyAction( $company_id = '' )
    {
        
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index',$this->router->getControllerName());
        $allowances = AllowanceType::getAllCurrentAllowanceType( $company_id );
        //assignment type in contract
        $results = [];
        $msg = "";
        $success = false;
        if( count($allowances) ){
            foreach( $allowances  as $allowance_type ){
                $results[] = [
                    'id'                => $allowance_type->getId(),
                    'name'              => $allowance_type->getName(),
                ];
            }
            $success = true;
        }else{
            $msg = "ALLOWANCE_TYPE_NOT_FOUND_TEXT";
        }
        $this->response->setJsonContent(['success' => $success , 'message' => $msg, 'data' => $results ]);
        $this->response->send();
    }
}
