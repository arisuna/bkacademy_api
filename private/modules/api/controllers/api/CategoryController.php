<?php

namespace SMXD\api\controllers\api;

use Mpdf\Tag\Br;
use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Api\Models\Attributes;
use SMXD\Api\Models\AttributesValue;
use SMXD\Api\Models\AttributesValueTranslation;
use SMXD\Api\Models\Brand;
use SMXD\Api\Models\Category;
use SMXD\Api\Models\Company;
use SMXD\Api\Models\SupportedLanguage;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\ModelHelper;

class CategoryController extends ModuleApiController
{

    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function searchAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();

        $params = [];
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['order'] = Helpers::__getRequestValue('order');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['search'] = Helpers::__getRequestValue('query');
        $result = Category::__findWithFilters($params);

        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);

        $items = Category::find([
            'conditions' => 'parent_category_id is null',
            'order' => 'pos ASC'
        ]);
        $this->response->setJsonContent([
            'success' => true,
            'data' => $items
        ]);
        return $this->response->send();

        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\ResponseInterface
     */
    public function getListAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $result = [
            'success' => true,
            'data' => []
        ];
        $query = Helpers::__getRequestValue('query');
        $hasMake = Helpers::__getRequestValue('has_make');

        $categories = Category::find([
            'conditions' => 'name LIKE :query: and parent_category_id is null',
            'bind' => [
                'query' => "%" . $query . "%",
            ],
            'order' => 'pos ASC'
        ]);

        if ($categories && count($categories) > 0) {
            $categoriesChildArr = [];
            $makesArr = [];

            $categoriesChild = Category::find([
                'conditions' => 'parent_category_id is not null',
                'order' => 'pos ASC'
            ]);

            if ($categoriesChild && count($categoriesChild) > 0) {
                foreach ($categoriesChild as $item) {
                    $itemArr = $item->toArray();
                    $itemArr['category_name'] = $itemArr['name'];
                    $itemArr['name'] = $itemArr['label'];
                    $categoriesChildArr[$item->getParentCategoryId()][] = $itemArr;
                }
            }

            if ($hasMake){
                $makes = Brand::find([
                    'conditions' => 'status = :status:',
                    'bind' => [
                        'status' => Brand::STATUS_ACTIVE
                    ],
                ]);
                $makesArr = $makes->toArray();
            }

            foreach ($categories as $item) {
                $itemArr = $item->toArray();
                $itemArr['category_name'] = $itemArr['name'];
                $itemArr['name'] = $itemArr['label'];
                $itemArr['items'] = $categoriesChildArr[$item->getId()] ?: [];
                if ($hasMake){
                    $itemArr['makes'] = $makesArr ?: [];
                }

                $result['data'][] = $itemArr;
            }
        }

        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getAllLevel2ItemsAction()
    {
        $this->view->disable();

        $items = Category::find([
            'conditions' => 'parent_category_id is not null',
            'order' => 'parent_category_id ASC'
        ]);
        $data_array = [];
        foreach ($items as $item) {
            $item_array = $item->toArray();
            if ($item->getParent()) {
                $item_array['parent_name'] = $item->getParent()->getName();
                $data_array[] = $item_array;
            }
        }
        $this->response->setJsonContent([
            'success' => true,
            'data' => $data_array
        ]);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getLevel1ItemsAction()
    {
        $this->view->disable();

        $items = Category::find([
            'conditions' => 'parent_category_id is null',
            'order' => 'pos ASC'
        ]);
        $this->response->setJsonContent([
            'success' => true,
            'data' => $items
        ]);
        return $this->response->send();
    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getChildrenItemsAction($id)
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);

        $items = Category::find([
            'conditions' => 'parent_category_id = :id:',
            'bind' => [
                'id' => $id
            ],
            'order' => 'pos ASC'
        ]);
        $this->response->setJsonContent([
            'success' => true,
            'data' => $items
        ]);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getParentCategoriesAction()
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPost();

        $data = Helpers::__getRequestValuesArray();
        if ($data['level'] >= 1) {
            $data['level'] = 1;
        }
        if (isset($data['parentCategoryId']) && is_numeric($data['parentCategoryId'])) {

            $conditions = '(parent_category_id = :parentCategoryId: or parent_category_id is null) and level <= :level: and id != :currentId:';

            $items = Category::find([
                'conditions' => $conditions,
                'bind' => [
                    'level' => $data['level'],
                    'parentCategoryId' => $data['parentCategoryId'],
                    'currentId' => $data['currentId'],
                ],
                'order' => 'pos ASC',
            ]);

        } else {
            $conditions = 'parent_category_id IS NULL AND level <= :level: and id != :currentId:';

            $items = Category::find([
                'conditions' => $conditions,
                'bind' => [
                    'level' => $data['level'],
                    'currentId' => $data['currentId'],
                ],
                'order' => 'pos ASC',
            ]);
        }


        end_of_function:
        $this->response->setJsonContent([
            'success' => true,
            'data' => $items
        ]);
        return $this->response->send();
    }

    /**
     * Set position Action
     */
    public function setPositionAction()
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPut();

        $positions = Helpers::__getRequestValue('positions');

        $result = ['success' => false];
        if (count($positions) > 0) {
            $this->db->begin();
            foreach ($positions as $position) {
                $position = (array)$position;
                $categoryItem = Category::findFirstById($position['id']);
                if ($categoryItem) {
                    $categoryItem->setPosition($position['position']);
                    $resultUpdate = $categoryItem->__quickUpdate();
                    if ($resultUpdate['success'] == false) {
                        $result = ['success' => false, 'error' => $resultUpdate];
                        goto end_of_function;
                    }
                }
            }
            $result = ['success' => true];
            $this->db->commit();
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}