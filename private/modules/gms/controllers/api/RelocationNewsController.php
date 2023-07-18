<?php
/**
 * Created by PhpStorm.
 * User: macbook
 * Date: 11/9/19
 * Time: 10:23 PM
 */

namespace Reloday\Gms\Controllers\API;


use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\Article;
use Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\ObjectAvatar;

class RelocationNewsController extends BaseController
{
    /** Find all News from relocation uuid
     * @param $uuid
     * @return mixed
     */
    public function getRelocationNewsAction($uuid){
        $this->view->disable();
        $this->checkAjax('GET');
        $return = ['success' => true, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];
        $relocation = Relocation::findFirstByUuid($uuid);
        if (!$uuid || !$relocation){
            goto end_of_function;
        }

        if (!$relocation->checkActivateNews()){
            $return = ['success' => true, 'data' => [], 'new_status' => $relocation->checkActivateNews()];
            goto end_of_function;
        }

        $return = Article::__findWithFilter([
            'type_id' => Article::TYPE_NEWS,
            'limit' => 1000,
            'is_logo' => true,
            'is_publish' => Article::IS_PUBLISH_YES
        ]);

        $return['new_status'] = $relocation->checkActivateNews();

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /** Change relocation activate news
     * @param $relocation_uuid
     * @return mixed
     */
    public function activateRelocationNewsAction($relocation_uuid){
        $this->view->disable();
        $this->checkAjax('GET');
//        $this->checkAcl('manage_news', 'relocation');

        $relocation = Relocation::findFirstByUuid($relocation_uuid);
        $this->db->begin();
        $relocation->setIsActivateNews(Relocation::ACTIVATE_NEWS);
        $return = $relocation->__quickSave();

        if ($return['success'] == false){
            $this->db->rollback();
            $return['message'] = 'ACTIVATE_RELOCATION_NEWS_FAIL_TEXT';
            goto end_of_function;
        }
        $this->db->commit();
        $return['success'] = true;
        $return['message'] = 'ACTIVATE_RELOCATION_NEWS_SUCCESS_TEXT';
        $return['relocation'] = $relocation;
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /** Change relocation deactivate news
     * @param $relocation_uuid
     * @return mixed
     */
    public function deactivateRelocationNewsAction($relocation_uuid){
        $this->view->disable();
        $this->checkAjax('GET');
//        $this->checkAcl('manage_news', 'relocation');

        $relocation = Relocation::findFirstByUuid($relocation_uuid);
        $this->db->begin();

        $relocation->setIsActivateNews(Relocation::DEACTIVATE_GUIDE);
        $return = $relocation->__quickSave();

        if ($return['success'] == false){
            $this->db->rollback();
            $return['message'] = 'DEACTIVATE_RELOCATION_NEWS_FAIL_TEXT';
            goto end_of_function;
        }
        $this->db->commit();
        $return['success'] = true;
        $return['message'] = 'DEACTIVATE_RELOCATION_NEWS_SUCCESS_TEXT';
        $return['relocation'] = $relocation;
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
