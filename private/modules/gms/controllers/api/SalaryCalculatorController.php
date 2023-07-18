<?php

namespace Reloday\Gms\Controllers\API;
use Reloday\Gms\Models\ScCountries;


/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class SalaryCalculatorController extends BaseController
{


    /**
     * @Route("/role", paths={module="gms"}, methods={"GET"}, name="gms-role-index")
     */
    public function indexAction()
    {


    }

    /**
     *
     */
    public function getScCountriesAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $countries = ScCountries::find();
        $this->response->setJsonContent([
            'success' => true,
            'data' => $countries,
        ]);
        $this->response->send();
    }

}
