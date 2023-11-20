<?php

namespace SMXD\api\Controllers\api;

use Mpdf\Tag\Br;
use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Api\Models\Attributes;
use SMXD\Api\Models\AttributesValue;
use SMXD\Api\Models\AttributesValueTranslation;
use SMXD\Api\Models\Brand;
use SMXD\Api\Models\Category;
use SMXD\Api\Models\Company;
use SMXD\Api\Models\ObjectAvatar;
use SMXD\Api\Models\Product;
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
        $params['is_basic'] = Helpers::__getRequestValue('is_basic');
        $params['has_make'] = Helpers::__getRequestValue('has_make');

        $result = Category::__findWithFilters($params);

        $this->view->disable();

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
        ];
        $params = [];

        $params['query'] = Helpers::__getRequestValue('query');
        $params['has_make'] = Helpers::__getRequestValue('has_make');

        $result['data'] = Category::getList($params);

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
//        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);

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

    public function detailAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        if (Helpers::__isValidUuid($uuid)) {
            $category = Category::findFirstByUuid($uuid);
        } else {
            $category = Category::findFirstById($uuid);
        }
        $data = $category instanceof Category ? $category->toArray() : [];

        $data['parent'] = $category->getParent();

        $this->response->setJsonContent([
            'success' => true,
            'data' => $data
        ]);

        end:
        $this->response->send();
    }
}