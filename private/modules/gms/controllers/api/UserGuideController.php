<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Models\UserGuideTopic;
use Reloday\Gms\Models\Tag;
use Reloday\Gms\Models\ModuleModel;
use Phalcon\Paginator\Adapter\Model as PaginatorModel;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class UserGuideController extends BaseController
{
    /**
     * @Route("/userguide", paths={module="gms"}, methods={"GET"}, name="gms-userguide-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $language = ModuleModel::$language;

        $topics = UserGuideTopic::find([
            "conditions" => "language = :language: AND user_guide_topic_id = :zero_topic_id: AND status  = :status_active: AND environment_type = :environment_type:",
            "bind" => [
                "environment_type" => UserGuideTopic::TYPE_GMS,
                "language" => $language,
                "zero_topic_id" => intval(0),
                "status_active" => UserGuideTopic::STATUS_ACTIVE
            ]
        ]);

        $res = [];

        foreach ($topics as $topic) {
            $item = new \stdClass;
            $item->info = $topic->toArray();
            $item->info['content'] = '';
            $childrens = $topic->getUserGuideTopics();
            if (count($childrens) > 0) {
                $item->children = $childrens;
            }
            $res[] = $item;
        }

        $this->response->setJsonContent([
            'success' => true,
            'data' => $res,
        ]);
        return $this->response->send();
    }

    /**
     * Get list tags
     */
    public function listTopicTagsAction()
    {
        $this->view->disable();
        $language = ModuleModel::$language;
        $topic_tags = Tag::findByLanguage($language);
        $this->response->setJsonContent([
            'success' => true,
            'data' => $topic_tags
        ]);
        return $this->response->send();
    }

    /**
     * Get topic's content
     */
    public function getContentAction($id)
    {
        $this->view->disable();
        $item = new \stdClass;

        $topic = UserGuideTopic::findFirstById($id);
        if ($topic && $topic->isActive()) {
            $item->info = $topic;
            $tags_ids = json_decode($topic->getTags());
            $children = $topic->getUserGuideTopics();
            if (count($children) > 0) {
                $item->children = $children;
            } else {
                $item->children = [];
            }
            $tags = [];
            if (count($tags_ids) > 0) {
                $tags = Tag::find([
                    "id IN ({ids:array})",
                    "bind" => ['ids' => $tags_ids]
                ]);
            }
            $item->tags = $tags;
        }

        $this->response->setJsonContent(['success' => true, 'data' => $item]);
        return $this->response->send();
    }

    /**
     * Get Basic Data
     */

    public function getMenuAction()
    {

        $this->view->disable();

        $menu_home = new \stdClass;
        $menu_home->text = "USER_GUIDE_HOME_TEXT";
        $menu_home->sref = "app.user-guide.home";

        $menu = [];

        $language = ModuleModel::$language;

        $topics = UserGuideTopic::find([
            "conditions" => "language = :language: AND user_guide_topic_id = :zero_topic_id: AND status  = :status_active: AND environment_type = :environment_type:",
            "bind" => [
                "environment_type" => UserGuideTopic::TYPE_GMS,
                "language" => $language,
                "zero_topic_id" => 0,
                "status_active" => UserGuideTopic::STATUS_ACTIVE
            ]
        ]);

        foreach ($topics as $topic) {
            $item = new \stdClass;
            $item->text = $topic->getSubject();
            $item->id = $topic->getId();
            $parentId = $topic->getId();
            $children = UserGuideTopic::find("user_guide_topic_id = " . $parentId);
            if (count($children) > 0) {
                $item->submenu = [];
                foreach ($children as $child) {
                    $sub_menu = new \stdClass;
                    $sub_menu->text = $child->getSubject();
                    $sub_menu->sref = 'app.user-guide.detail';
                    $sub_menu->params = new \stdClass();
                    $sub_menu->params->id = $child->getId();
                    $item->submenu[] = $sub_menu;
                }
                $item->sref = "#";
            } else {
                $item->sref = "app.user-guide.detail";
                $item->params = new \stdClass();
                $item->params->id = $topic->getId();
            }
            $menu[] = $item;
        }

        $this->response->setJsonContent(['success' => true, 'data' => $menu]);
        return $this->response->send();
    }

    /**
     * Get Topics by Tag
     */

    public function getTopicsByTagAction($tag_id)
    {
        $this->view->disable();
        $data = new \stdClass;
        $data->tag = Tag::findFirstById($tag_id);
        $topics = UserGuideTopic::find();
        $data->topics = [];
        foreach ($topics as $topic) {
            $tags = json_decode($topic->getTags());
            foreach ($tags as $tag) {
                if ($tag == $tag_id) {
                    $item = new \stdClass;
                    $item->info = $topic;
                    $tags_obj = Tag::find([
                        "id IN ({ids:array})",
                        "bind" => [
                            'ids' => $tags
                        ]
                    ]);
                    $item->tags = $tags_obj;
                    $data->topics[] = $item;
                }
            }
        }
        $this->response->setJsonContent(['success' => true, 'data' => $data]);
        return $this->response->send();
    }

    /**
     * Get Most of tags using
     */
    public function getMostOfTagsAction()
    {
        $this->view->disable();
        $allTopics = UserGuideTopic::find();
        $tagsOfAllTopics = [];
        foreach ($allTopics as $topic) {
            $tags = json_decode($topic->getTags());
            $tagsOfAllTopics = array_merge($tagsOfAllTopics, $tags);
        }

        $result = array_count_values($tagsOfAllTopics);
        arsort($result);
        $topTagIds = [];
        foreach ($result as $key => $value) {
            array_push($topTagIds, $key);
        }
        $topTagIds = array_slice($topTagIds, 0, 5);
        $tags_obj = Tag::find([
            "id IN ({ids:array})",
            "bind" => ['ids' => $topTagIds]
        ]);
        $this->response->setJsonContent(['success' => true, 'data' => $tags_obj]);
        return $this->response->send();
    }

    /**
     *
     */

    public function getTopicUpdatedAction()
    {
        $this->view->disable();
        $language = ModuleModel::$language;
        $topics = UserGuideTopic::find([
            "language = '" . $language . "'",
            "order" => "updated_at DESC",
            "limit" => 3
        ]);
        $this->response->setJsonContent(['success' => true, 'data' => $topics]);
        return $this->response->send();
    }

    /**
     * Search User Guide
     */

    public function searchGuideAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $request = $this->request->getJsonRawBody();
        $currentPage = (int)$request->page;
        $language = ModuleModel::$language;
        $condition = "language = '" . $language . "'";
        if (isset($request->search) && $request->search !== '') {
            $condition .= " AND subject LIKE '%" . $request->search . "%'";
        }
        $guides = UserGuideTopic::find([
            $condition,
            'order' => 'updated_at DESC'
        ]);
        $paginator = new PaginatorModel([
            "data" => $guides,
            "limit" => 5,
            "page" => $currentPage
        ]);

        $pagination = $paginator->getPaginate();
        $return = [
            'success' => true,
            'data' => $pagination->items,
            'before' => $pagination->before,
            'next' => $pagination->next,
            'last' => $pagination->last,
            'current' => $pagination->current,
            'total_items' => $pagination->total_items,
            'total_pages' => $pagination->total_pages
        ];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Get Tags by Array
     */

    public function tagsByIdsAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $request = $this->request->getJsonRawBody();
        $arrIds = json_decode($request->tags);
        $tags = Tag::find([
            "id IN ({ids:array})",
            "bind" => ['ids' => $arrIds]
        ]);
        $this->response->setJsonContent(['success' => true, 'data' => $tags]);
        return $this->response->send();
    }
}
