<?php

namespace Reloday\Gms\Controllers\API;

use \Reloday\Gms\Controllers\ModuleApiController;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class TestController extends ModuleApiController
{
    /**
     * @Route("/test", paths={module="gms"}, methods={"GET"}, name="gms-test-index")
     */
    public function indexAction()
    {

//        $connection = $this->di->get('db');
//        $connectionRead = $this->di->get('dbRead');
//        $dbCommunicationRead = $this->di->get('dbCommunicationRead');
//        $dbCommunicationWrite = $this->di->get('dbCommunicationWrite');
//
//        $this->response->setJsonContent([
//            $connection,  $connectionRead, $dbCommunicationRead, $dbCommunicationWrite
//        ]);
//        return $this->response->send();
    }

    public function testHtmlAction()
    {
        $htmlString = file_get_contents(BASE_PATH . "/test_html1.html");
        var_dump($htmlString);
        $data = json_encode([
            'success' => false,
            'content' => $htmlString
        ]);
        var_dump($data);

        $this->response->send();
    }
}
