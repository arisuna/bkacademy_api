<?php

namespace Reloday\Gms\Controllers\API;

use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Models\AssignmentType;
/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class AssignmentlistController extends ModuleApiController
{
	/**
     * @Route("/assignment", paths={module="gms"}, methods={"GET"}, name="gms-assignment-index")
     */
    public function listAction()
    {
    	$this->view->disable();
    	$checkLogin = $this->checkLogin();
    	if( $checkLogin == true ){
    		$assignment_types = AssignmentType::find();
    		//load assignment list
    		$this->response->setJsonContent(['success' => true , 'message' => '', 'data' => $assignment_types ]);
    		$this->response->send();
    	}
    }

    /**
     * @Route("/assignment", paths={module="gms"}, methods={"GET"}, name="gms-assignment-index")
     */
    public function createAction()
    {
    	$this->view->disable();
    	$checkLogin = $this->checkLogin();
    	if( $checkLogin == true ){
    		
    		$this->view->disable();

        	$msg = [];

        	if ($this->request->isPost() ) {


        	}

    		$this->response->setJsonContent(['success' => true , 'message' => '', 'data' => $assignments ]);
    		$this->response->send();
    	}
    }
}
