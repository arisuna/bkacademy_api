<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Test\Mvc\Model\Behavior\Helper;
use Reloday\Application\Lib\ModelHelper;
use \Reloday\Gms\Controllers\ModuleApiController;

use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Article;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class ArticleController extends BaseController
{
    /**
     * @Route("/article", paths={module="gms"}, methods={"GET"}, name="gms-article-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
//        $this->checkAclIndex();
        $result = ['success' => true, 'data' => []];
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @Route("/article", paths={module="gms"}, methods={"GET"}, name="gms-article-index")
     */
    public function initializeAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
//        $this->checkAclIndex();
        $result = ['success' => true, 'data' => []];
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function searchAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $this->checkAclIndex();

        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');
        $params['type_id'] = Helpers::__getRequestValue('type_id');
        $params['type_ids'] = Helpers::__getRequestValue('type_ids');

        $params['limit'] = Helpers::__getRequestValue('limit');;
        $params['length'] = Helpers::__getRequestValue('length');
        $params['start'] = Helpers::__getRequestValue('start');
        $params['simple'] = Helpers::__getRequestValue('simple');
        $params['active'] = Helpers::__getRequestValue('active');
        $params['page'] = Helpers::__getRequestValue('page');
        /****** destination ****/

        /****** origin ****/
        $order = Helpers::__getRequestValueAsArray('sort');
        $ordersConfig = Helpers::__getApiOrderConfig([$order]);
        $return = Article::__findWithFilter($params, $ordersConfig);
        if ($return['success'] == true) {
            $return['recordsFiltered'] = ($return['total_items']);
            $return['recordsTotal'] = ($return['total_items']);
            $return['length'] = ($return['total_items']);
            $return['draw'] = Helpers::__getRequestValue('draw');
        }

        $return['params'] = $params;

        $return['hasWelcomeMessage'] = ModuleModel::$company->getWelcomeMessage() ? true : false;

        if ($return['success'] == true) {
            $this->response->setJsonContent($return);
            $this->response->send();
        } else {
            $this->response->setJsonContent($return);
            $this->response->send();
        }
    }

    public function getWelcomeMessageAction(){
        $this->view->disable();
        $this->checkAjaxGet();

        $welcomeMessage = Article::findFirst([
           'conditions' => 'type_id = :type_id: and company_id = :company_id:',
            'bind' => [
                'type_id' => Article::TYPE_WELCOME,
                'company_id' => ModuleModel::$company->getId()
            ]
        ]);

        $return = [
            'success' => true, 'data' => $welcomeMessage
        ];

        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function preCreateAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();
        $typeId = Helpers::__getRequestValue('type_id');

        $article = new Article();
        $article->setUuid(Helpers::__uuid());
        $article->setTypeId($typeId);
        $article->setIsPublish(ModelHelper::YES);

        $result = [
            'success' => true, 'data' => $article
        ];
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();

        $data = Helpers::__getRequestValuesArray();

        if ($data['type_id'] == Article::TYPE_WELCOME && ModuleModel::$company->getWelcomeMessage()){
            $result = ['success' => false, 'message' => 'WELCOME_MESSAGE_EXISTED_TEXT'];
            goto end_of_function;
        }

        $article = new Article();
        $data['company_id'] = ModuleModel::$company->getId();
        if(isset($data['content']) && $data['content'] != null && $data['content'] != ''){
            $data['content'] = rawurldecode(base64_decode($data['content']));
        }
        $article->setData($data);
        $article->setUuid($data['uuid']);
        $article->setCreatorUserProfileUuid(ModuleModel::$user_profile->getUuid());
        $result = $article->__quickCreate();
        if ($result['success'] == false) {
            $result['message'] = 'DATA_CREATE_FAIL_TEXT';
        } else {
            $result['message'] = 'OBJECT_CREATE_SUCCESS_TEXT';
        }

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function detailAction($id)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if (Helpers::__isValidId($id) == false) {
            goto end_of_function;
        }

        $article = Article::findFirstById($id);
        if ($article && $article->belongsToCompany() && $article->isArchived() == false) {
            $articleArray = $article->toArray();
            $result = ['success' => true, 'data' => $articleArray];
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getContentAction($id)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if (Helpers::__isValidId($id) == false) {
            goto end_of_function;
        }

        $article = Article::findFirstById($id);
        if ($article && $article->belongsToCompany() && $article->isArchived() == false) {
            $content = $article->getContent();
            $result = ['success' => true, 'data' => $content];
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function deleteAction($id)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclDelete();

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if (Helpers::__isValidId($id) == false) {
            goto end_of_function;
        }

        $article = Article::findFirstById($id);
        if ($article && $article->belongsToCompany()) {
            $result = $article->__quickRemove();
            if ($result['success'] == false) {
                $result['message'] = 'DATA_REMOVE_FAILED_TEXT';
            } else {
                $result['message'] = 'DATA_REMOVE_SUCCESS_TEXT';
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $id
     */
    public function editAction($id)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclUpdate();

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if (Helpers::__isValidId($id) == false) {
            goto end_of_function;
        }
        $article = Article::findFirstById($id);
        if ($article && $article->belongsToCompany()) {

            $data = Helpers::__getRequestValuesArray();
            $data['company_id'] = ModuleModel::$company->getId();
            if(isset($data['content']) && $data['content'] != null && $data['content'] != ''){
                $data['content'] = rawurldecode(base64_decode($data['content']));
            }
            $article->setData($data);

            $result = $article->__quickUpdate();
            if ($result['success'] == false) {
                $result['message'] = 'DATA_SAVE_FAIL_TEXT';
            } else {
                $result['message'] = 'DATA_SAVE_SUCCESS_TEXT';
            }
        }

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $id
     */
    public function editContentAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclUpdate();
        $id = Helpers::__getRequestValue('id');
        $content = Helpers::__getRequestValue('content');
        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if (Helpers::__isValidId($id) == false) {
            goto end_of_function;
        }
        $article = Article::findFirstById($id);
        if ($article && $article->belongsToCompany()) {
            $result = $article->updateContent($content);
            if ($result['success'] == false) {
                $result['message'] = 'UPDATE_DATA_FAIL_TEXT';
            } else {
                $result['message'] = 'UPDATE_DATA_SUCCESS_TEXT';
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
