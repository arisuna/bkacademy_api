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
     * Save data
     */
    public function saveAction()
    {
    	$this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);

        $model = new Constant();
        if ((int)$this->request->getPost('id') > 0) {
            $model = Constant::findFirst((int)$this->request->getPost('id'));
            if (!$model instanceof Constant) {
                exit(json_encode([
                    'success' => false,
                    'msg' => 'Allowance title was not found'
                ]));
            }
        }

        $model->setName($this->request->getPost('name'));
        $model->setValue($this->request->getPost('value'));

        $this->db->begin();
        if ($model->save()) {

            // Save constant translate
            $data_translated = $this->request->getPost('data_translated');
            if (is_array($data_translated) & !empty($data_translated)) {
                // Get current data translated
                $current_data = ConstantTranslation::find('constant_id=' . $model->getId());
                if (count($current_data)) {
                    foreach ($current_data as $item) {
                        $is_break = false;
                        foreach ($data_translated as $index => $translated) {
                            if (isset($translated['id'])) {
                                if ($translated['id'] == $item->getId()) {
                                    // try update this translated
                                    if ($item->getValue() != $translated['value']) {
                                        $item->setValue($translated['value']);
                                        if (!$item->save()) {
                                            $this->db->rollback();
                                            exit(json_encode([
                                                'success' => false,
                                                'msg' => 'Try update constant translate to ' . strtoupper($item->getLanguage()) . ' was error'
                                            ]));
                                        }
                                    }
                                    unset($data_translated[$index]);
                                    $is_break = true;
                                    break;
                                }
                            }
                        }
                        if (!$is_break) {
                            // Delete current translated, because, it was not found in list posted
                            if (!$item->delete()) {
                                $this->db->rollback();
                                exit(json_encode([
                                    'success' => false,
                                    'msg' => 'Try unset constant translate was error'
                                ]));
                            }
                        }
                    }
                }

                // Try to add translate data if has new
                if (count($data_translated)) {
                    foreach ($data_translated as $item) {
                        $object = new ConstantTranslation();
                        $object->setLanguage($item['language']);
                        $object->setValue($item['value']);
                        $object->setConstantId($model->getId());

                        if (!$object->save()) {
                            $this->db->rollback();
                            exit(json_encode([
                                'success' => false,
                                'msg' => 'Try add new constant translate to ' . strtoupper($item['language']) . ' was error'
                            ]));
                        }
                    }
                }
            }

            // Update constant success
            $this->db->commit();

            $this->response->setJsonContent([
                'success' => true
            ]);
        } else {
            $this->db->rollback();
            $msg = [];
            foreach ($model->getMessages() as $message) {
                $msg[] = $message->getMessage();
            }
            $this->response->setJsonContent([
                'success' => false,
                'msg' => 'DATA_SAVE_FAIL_TEXT',
                'detail' => $msg
            ]);
        }

        end:
        $this->response->send();
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
