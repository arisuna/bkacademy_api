<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\Helpers;
use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\ObjectTag;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class ObjectTagController extends BaseController
{
    /**
     * @Route("/objecttag", paths={module="gms"}, methods={"GET"}, name="gms-objecttag-index")
     */
    public function initialize()
    {
        $this->view->disable();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getTagsAction()
    {
        $this->checkAjaxPut();
        $uuid = Helpers::__getRequestValue('uuid');
        $tagGroup = Helpers::__getRequestValue('tagGroup');

        $tagObjects = ObjectTag::find([
            'conditions' => 'object_uuid = :uuid: AND tag_group_name = :tag_group_name:',
            'bind' => [
                'uuid' => $uuid,
                'tag_group_name' => $tagGroup,
            ]
        ]);
        $this->response->setJsonContent([
            'success' => true,
            'data' => $tagObjects,
        ]);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function addTagAction()
    {
        $this->checkAjaxPost();
        $uuid = Helpers::__getRequestValue('uuid');
        $tagGroup = Helpers::__getRequestValue('tagGroup');
        $tagName = Helpers::__getRequestValue('tagName');

        $tagObject = new ObjectTag();
        $tagObject->setObjectUuid($uuid);
        $tagObject->setTagGroupName($tagGroup);
        $tagObject->setTagName($tagName);
        $tagObject->setLanguage(ModuleModel::$language);
        $return = $tagObject->__quickCreate();
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function removeTagAction()
    {
        $this->checkAjaxPut();
        $uuid = Helpers::__getRequestValue('uuid');
        $tagGroup = Helpers::__getRequestValue('tagGroup');
        $tagName = Helpers::__getRequestValue('tagName');
        $objectTagId = Helpers::__getRequestValue('objectTagId');
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if (Helpers::__isValidId($objectTagId)) {
            $tagObject = ObjectTag::findFirstById($objectTagId);
        } else {
            $tagObject = ObjectTag::findFirst([
                'conditions' => 'object_uuid = :uuid: AND tag_group_name = :tag_group_name: AND tag_name = :tag_name:',
                'bind' => [
                    'uuid' => $uuid,
                    'tag_group_name' => $tagGroup,
                    'tag_name' => $tagName
                ]
            ]);
        }
        if ($tagObject) {
            $return = $tagObject->__quickRemove();
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
