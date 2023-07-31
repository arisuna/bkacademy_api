<?php

namespace SMXD\App\Controllers\API;

use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\SMXDS3Helper;
use SMXD\App\Models\Constant;
use SMXD\App\Models\ConstantTranslation;
use SMXD\App\Models\SupportedLanguage;
use SMXD\Application\Lib\AclHelper;

/**
 * Concrete implementation of Backend module controller
 *
 * @RoutePrefix("/backend/api")
 */
class ConstantController extends BaseController
{

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function initializeAction()
    {
    	$this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxGet();
        // Load list allowance type
        $supported_language = SupportedLanguage::find(['columns' => 'iso, description', 'order' => 'name']);
        $this->response->setJsonContent([
            'success' => true,
            'supported_language' => count($supported_language) ? $supported_language->toArray() : []
        ]);
        return $this->response->send();
    }

    /**
     * Get detail of object
     * @param int $id
     */
    public function detailAction($id = 0)
    {
    	$this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxGet();
        $data = Constant::findFirst((int)$id);
        $data = $data instanceof Constant ? $data->toArray() : [];

        $data_translated = [];
        if ($data) {
            $data_translated = ConstantTranslation::find('constant_id=' . (int)$id);
            $data_translated = count($data_translated) ? $data_translated->toArray() : [];
        }

        $this->response->setJsonContent([
            'success' => true,
            'data' => $data,
            'data_translated' => $data_translated
        ]);

        end:
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function createAction()
    {
    	$this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPost();
        $checkIfExist = Constant::findFirst([
            'conditions' => 'name = :name:',
            'bind' => [
                'name' => Helpers::__getRequestValue('name')
            ]
            ]);
        if($checkIfExist){
            $result = [
                'success' => false,
                'message' => 'CONSTANT_MUST_UNIQUE_TEXT'
            ];
            goto end;
        }
        $model = new Constant();
        $model->setName(Helpers::__getRequestValue('name'));
        $model->setValue(Helpers::__getRequestValue('value'));

        $this->db->begin();
        $resultCreate = $model->__quickCreate();

        if ($resultCreate['success'] == true) {

            $data_translated = Helpers::__getRequestValueAsArray('data_translated');
            $resultAddItem = $model->createTranslatedData($data_translated);

            if ($resultAddItem['success'] == false) {
                $this->db->commit();
                $result = $resultAddItem;
            } else {
                $this->db->commit();
                $result = [
                    'success' => true,
                    'message' => 'DATA_SAVE_SUCCESS_TEXT'
                ];
            }
        } else {
            $this->db->rollback();
            $result = ([
                'success' => false,
                'message' => 'DATA_SAVE_FAIL_TEXT',
            ]);
        }
        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function updateAction($id)
    {

    	$this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPut();

        $result = [
            'success' => false,
            'message' => 'Data not found'
        ];

        if (Helpers::__isValidId($id)) {

            $model = Constant::findFirstById($id);
            if ($model) {
                $checkIfExist = Constant::findFirst([
                    'conditions' => 'name = :name: and id <> :id:',
                    'bind' => [
                        'name' => Helpers::__getRequestValue('name'),
                        'id' => $id
                    ]
                    ]);
                if($checkIfExist){
                    $result = [
                        'success' => false,
                        'message' => 'CONSTANT_MUST_UNIQUE_TEXT'
                    ];
                    goto end;
                }
                $model->setName(Helpers::__getRequestValue('name'));
                $model->setValue(Helpers::__getRequestValue('value'));

                $this->db->begin();
                $resultCreate = $model->__quickUpdate();

                if ($resultCreate['success'] == true) {

                    $data_translated = Helpers::__getRequestValueAsArray('data_translated');
                    $resultAddItem = $model->createTranslatedData($data_translated);

                    if ($resultAddItem['success'] == false) {
                        $this->db->commit();
                        $result = $resultAddItem;
                    } else {
                        $this->db->commit();
                        $result = [
                            'success' => true,
                            'message' => 'DATA_SAVE_SUCCESS_TEXT'
                        ];
                    }
                } else {
                    $this->db->rollback();
                    $result = ([
                        'success' => false,
                        'message' => 'DATA_SAVE_FAIL_TEXT',
                    ]);
                }
            }
        }
        end:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Delete data
     */
    public function deleteAction($id)
    {

    	$this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxDelete();

        $constant = Constant::findFirstById($id);
        $translations = $constant->getConstantTranslations();
        $result = $constant->__quickRemove();
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     *
     */
    public function synchronizeAction()
    {
    	$this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);

        $uploaded = true;
        $result = ['success' => false];

        $languages = SupportedLanguage::find();
        $constants = Constant::find();
        foreach ($languages as $lang) {
            $results = array();
            foreach ($constants as $constant) {
                $results[trim($constant->name)] = trim($constant->value);
                $translations = $constant->getConstantTranslations();
                foreach ($translations as $translation) {
                    if ($translation->language === $lang->name) {
                        $results[trim($constant->name)] = trim($translation->value);
                    }
                }
            }
            // Push data to amazon
            $result = SMXDS3Helper::__uploadSingleFileWithRegion(getenv('AWS_S3_TRANSLATION_REGION'), 'translate/' . $lang->name . '.json', json_encode($results), getenv('AWS_S3_TRANSLATION_BUCKET'), SMXDS3Helper::ACL_PUBLIC_READ, 'application/json');
            if (!$result['success']) {
                $uploaded = false;
                break;
            }
        }

        // Clear cache
        if ($uploaded) {
            $cloudFront = $this->getDi()->get('aws')->createCloudFront();
            $resultCloudFront = $cloudFront->createInvalidation([
                'DistributionId' => getenv('AWS_CLOUDFRONT_STATIC_ID'), // REQUIRED
                'InvalidationBatch' => [ // REQUIRED
                    'CallerReference' => time(), // REQUIRED
                    'Paths' => [ // REQUIRED
                        'Items' => ['/translate/*'],
                        'Quantity' => 1, // REQUIRED
                    ],
                ],
            ]);
            $result['dataSyncedResult'] = $resultCloudFront;
        }
        $result['AWSCOnfig'] = [
            'cloudFront' => getenv('AWS_CLOUDFRONT_STATIC_ID'),
            's3BucketRegion' => getenv('AWS_S3_TRANSLATION_REGION'),
            's3BucketName' => getenv('AWS_S3_TRANSLATION_BUCKET'),
        ];

        end:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * @Route("/constant", paths={module="backend"}, methods={"GET"}, name="backend-constant-index")
     */
    public function searchAction()
    {
    	$this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPutGet();
        $params = [];
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['order'] = Helpers::__getRequestValue('order');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['search'] = Helpers::__getRequestValue('query');
        $result = Constant::__findWithFilters($params);
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

}
