<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Models\City;

class CityController extends BaseController
{
    /**
     * @Route("/city", paths={module="backend"}, methods={"GET"}, name="backend-city-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $countryId = Helpers::__getRequestValue('country_id');
        if ($countryId > 0) {
            $results = City::__findWithFilters([
                'country_id' => Helpers::__getRequestValue('country_id'),
                'query' => Helpers::__getRequestValue('query'),
                'limit' => Helpers::__getRequestValue('limit'),
                'page' => Helpers::__getRequestValue('page'),
            ]);
            $this->response->setJsonContent($results);
        } else {
            $this->response->setJsonContent(['success' => true, 'data' => []]);
        }
        end:
        $this->response->send();
    }

    /**
     *
     */
    public function findAll()
    {
        $this->view->disable();
        $countryId = Helpers::__getRequestValue('country_id');
        if ($countryId > 0) {
            $cities = City::findWithCache([
                'conditions' => 'country_id = :country_id:',
                'bind' => [
                    'country_id' => $countryId
                ]
            ], CacheHelper::__TIME_24H);
            $this->response->setJsonContent([
                'success' => true,
                'data' => count($cities) ? $cities : []
            ]);
        }
        end:
        $this->response->send();
    }

    /**
     * @Route("/city", paths={module="backend"}, methods={"GET"}, name="backend-city-index")
     */
    public function itemAction(int $geonameid)
    {
        $this->view->disable();
        if ($geonameid > 0 && Helpers::__isValidId($geonameid)) {
            $city = City::__findFirstByGeonameidWithCache($geonameid);
            $this->response->setJsonContent(['success' => true, 'data' => $city]);
        } else {
            $this->response->setJsonContent(['success' => true, 'data' => false]);
        }
        end:
        $this->response->send();
    }
}

