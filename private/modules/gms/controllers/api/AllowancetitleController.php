<?php

namespace Reloday\Gms\Controllers\API;

use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Controllers\API\BaseController;
use \Reloday\Gms\Models\AllowanceTitle;
use \Reloday\Gms\Models\AllowanceTitleDefault;
use \Reloday\Gms\Models\AllowanceType;
use \Reloday\Gms\Models\AllowanceTypeDefault;
use \Reloday\Gms\Models\Company;
/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class AllowancetitleController extends BaseController
{
	/**
     * @Route("/allowancetitle", paths={module="gms"}, methods={"GET"}, name="gms-allowancetitle-index")
     */
    public function indexAction()
    {
    	$this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index',$this->router->getControllerName());

        //load all allowance type of all companies of current gms company
        //
        //
        $allowance_types = AllowanceTitle::__loadAllAllowanceTypeOfGms();
        $types = [];
        if( count($allowance_types) ){
	        foreach( $allowance_types as $allowance ){
	        	$types[] = [
	        		'id' 			=> $allowance->getId(),
	        		'name' 			=> $allowance->getName(),
	        		'company_name'	=> $allowance->getCompany()->getName(),
	        		'type_name'		=> $allowance->getAllowanceType()->getName()
	        	];
	        }
	    }
       	$results = ['success' => true, 'data' => $types ];
       	$this->response->setJsonContent($results);
        $this->response->send();
    }

    /**
     * init controller
     * @return [type] [description]
     */
    public function initAction(){
    	$this->view->disable();
        $this->checkAjaxGet();

        $types = AllowanceType::find();
        $this->response->setJsonContent(["success" => true, "data" => $types->toArray()]);
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
        $allowances = AllowanceTitle::getAllCurrentAllowanceTitle( $company_id );
        //assignment type in contract
        $results = [];

        if( count($allowances) ){
            foreach( $allowances  as $allowance_title ){
                $results[] = [
                    'id'                => $allowance_title->getId(),
                    'name'              => $allowance_title->getName(),
                ];
            }
        }

        $this->response->setJsonContent(['success' => true , 'message' => '', 'data' => $results ]);
        return $this->response->send();
    }
    /**
     * [searchAction description]
     * @return [type] [description]
     */
    public function searchAction( $params = []){
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index',$this->router->getControllerName());


        $company_id = $this->dispatcher->getParam(0);
        $allowance_type_id = $this->dispatcher->getParam(1);

        $allowances = AllowanceTitle::getAllCurrentAllowanceTitle( $company_id , $allowance_type_id );
        //assignment type in contract
        $results = [];

        if( count($allowances) ){
            foreach( $allowances  as $allowance_title ){
                $results[] = [
                    'id'                => $allowance_title->getId(),
                    'name'              => $allowance_title->getName(),
                ];
            }
        }
        $this->response->setJsonContent(['success' => true , 'message' => '', 'data' => $results ]);
        return $this->response->send();
    }


    /**
     * [createAction description]
     * @return [type] [description]
     */
    public function saveAction(){
        $this->view->disable();

        $this->checkAjax(['PUT','POST']);
        $this->checkAcl(['create','edit'],$this->router->getControllerName());

        $data = $this->request->getJsonRawBody();
        $result = AllowanceTitle::__save([
            "id"                => isset( $data->id ) ? $data->id:null,
            "uuid"              => isset( $data->uuid ) ? $data->uuid:null,
            "company_id"        => $data->company_id,
            "name"		        => $data->name,
            "allowance_type_id" => $data->allowance_type_id,
        ]);

        if( $result instanceof AllowanceTitle ){
            $this->response->setJsonContent(["success" => true, "msg" => "ALLOWANCE_SAVE_SUCCESS_TEXT", "data" => $result]);
            $this->response->send();
        }else{
            $this->response->setJsonContent(["success" => false, "msg" => $result['message'] ]);
            $this->response->send();
        }
    }

    /**
     * @param $id : id for allowance title
     */
    public function detailAction($id){
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index',$this->router->getControllerName());

        if( is_numeric( $id ) && $id > 0 ){
            $allowance_title = AllowanceTitle::findFirstById( $id );
            if( $allowance_title && $allowance_title instanceof AllowanceTitle  && $allowance_title->belongsToGms() == true ){
                $return = [
                    'success' => true,
                    'message'   => 'DATA_FOUND_SUCCESS_TEXT',
                    'data' => $allowance_title
                ];
            }else{
                $return = [
                    'success' => false,
                    'message'   => 'DATA_NOT_FOUND_TEXT'
                ];
            }
        }

        $this->response->setJsonContent( $return );
        return $this->response->send();
    }

    /**
     * @param $id
     */
    public function deleteAction(){
        $this->view->disable();
        $this->checkAjaxDelete();

        $action = "delete";
        $access = $this->canAccessResource($this->router->getControllerName(), $action);
        if (!$access['success']) {
            exit(json_encode($access));
        }
        $data = $this->request->getJsonRawBody();
        $id = isset($data->id) ? $data->id: null ;
        $uuid = isset($data->uuid) ? $data->uuid: null;

        if( is_numeric( $id ) && $id > 0 ){
            $allowance_title = AllowanceTitle::findFirstById( $id );
            if( $allowance_title && $allowance_title instanceof AllowanceTitle  && $allowance_title->belongsToGms() == true ){
                $return = $allowance_title->remove();
            }else{
                $return = [
                    'success' => false,
                    'message'   => 'DATA_NOT_FOUND_TEXT'
                ];
            }
        }else{
            $return = [
                'success' => false,
                'message'   => 'DATA_NOT_FOUND_TEXT'
            ];
        }

        $this->response->setJsonContent( $return );
        return $this->response->send();
    }
}
