<?php

namespace SMXD\App\Controllers\API;


use SMXD\App\Models\EmailTemplate;
use SMXD\App\Models\EmailTemplateDefault;
use SMXD\App\Models\SupportedLanguage;
use SMXD\Application\Lib\Helpers;

class EmailTemplateController extends BaseController
{
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $query = Helpers::__getRequestValue('query');
        $param = null;

        if (isset($query) && is_string($query) && $query != '') {
            $param = ['conditions' => 'name LIKE :query: OR description LIKE :query: ',
                'bind' => [
                    'query' => '%' . $query . '%'
                ]];
        }

        $list = EmailTemplateDefault::find($param);

        $this->response->setJsonContent([
            'success' => true,
            'data' => $list
        ]);
        end:
        $this->response->send();
    }

    public function initializeAction()
    {
        $this->view->disable();
//        $hasPermission = $this->checkPermission();
//        if (!$hasPermission) {
//            $this->response->setJsonContent([
//                'success' => false,
//                'msg' => "You don't have permission access this resource"
//            ]);
//            goto end;
//        }

        $supported_languages = SupportedLanguage::find();
        if (count($supported_languages)) {
            $_tmp = [];
            foreach ($supported_languages as $item) {
                $_tmp[$item->getIso()] = $item->getName();
            }
            $supported_languages = $_tmp;
        } else {
            $supported_languages = [];
        }

        $this->response->setJsonContent([
            'success' => true,
            'languages' => $supported_languages
        ]);

        end:
        $this->response->send();
    }

    /**
     * @param string $id
     */
    public function detailAction(int $id = 0)
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $return = ['success' => false, 'message' => "Data not found"];

        if (Helpers::__isValidId($id)) {
            $emailTemplateDefault = EmailTemplateDefault::findFirstById($id);

            if ($emailTemplateDefault) {
                // Find list template on this
                $emailTemplateDefaultArray = $emailTemplateDefault->toArray();
                $languages = SupportedLanguage::getAll();

                foreach ($languages as $language) {
                    $emailTemplate = $emailTemplateDefault->getTemplate($language->getName());

                    if ($emailTemplate) {
                        $emailTemplateDefaultArray['items'][] = $emailTemplate->toArray();
                    } else {
                        $emailTemplate = new EmailTemplate();
                        $emailTemplate->setEmailTemplateDefaultId($emailTemplateDefault->getId());
                        $emailTemplate->setLanguage($language->getName());
                        $emailTemplate->setSubject('');
                        $emailTemplate->setText('');
                        //$emailTemplate->__quickCreate();
                        $emailTemplateDefaultArray['items'][] = $emailTemplate->toArray();
                    }
                }
                $return = ['success' => true, 'data' => $emailTemplateDefaultArray];
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $emailTemplate = new EmailTemplateDefault();
        $emailTemplate->setName(Helpers::__getRequestValue('name'));
        $emailTemplate->setDescription(Helpers::__getRequestValue('description'));
        $result = $emailTemplate->__quickCreate();
        if ($result['success']) {
            $result['message'] = 'DATA_SAVE_SUCCESS_TEXT';
        } else {
            $result['message'] = 'DATA_SAVE_FAIL_TEXT';
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function updateAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $id = Helpers::__getRequestValue('id');
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if (Helpers::__isValidId($id)) {
            $emailTemplateDefault = EmailTemplateDefault::findFirstById($id);
            if (!$emailTemplateDefault) {
                goto end;
            }

            $emailTemplateDefault->setName(Helpers::__getRequestValue('name'));
            $emailTemplateDefault->setDescription(Helpers::__getRequestValue('description'));

            $this->db->begin();
            $resultUpdate = $emailTemplateDefault->__quickUpdate();
            if (!$resultUpdate['success']) {
                $this->db->rollback();
                $return = $resultUpdate;
                goto end;
            }

            $items = Helpers::__getRequestValueAsArray('items');
            if (count($items) && is_array($items)) {
                foreach ($items as $key => $item) {
                    if (isset($item['subject']) && trim($item['subject']) != '') {
                        $emailContent = new EmailTemplate();
                        if (isset($item['id']) && (int)$item['id'] > 0) {
                            $emailContent = EmailTemplate::findFirst((int)$item['id']);
                            if (!$emailContent instanceof EmailTemplate) {
                                $this->db->rollback();
                                $return = [
                                    'success' => false,
                                    'message' => "EMAIL_TEMPLATE_NOT_FOUND_TEXT"
                                ];
                                goto end;
                            }
                        }
                        $emailContent->setEmailTemplateDefaultId($emailTemplateDefault->getId());
                        $emailContent->setLanguage($item['language']);
                        $emailContent->setSubject($item['subject']);
                        $emailContent->setText($item['text'] ?? null);

                        $resultUpdate = $emailContent->__quickSave();
                        if (!$resultUpdate['success']) {
                            $this->db->rollback();
                            $return = [
                                'success' => false,
                                'error' => $resultUpdate,
                                'message' => "DATA_SAVE_FAIL_TEXT",
                            ];
                            goto end;
                        }
                    }
                }
            }

            $this->db->commit();
            $return = [
                'success' => true,
                'data' => $emailTemplateDefault,
                'message' => 'DATA_SAVE_SUCCESS_TEXT',
            ];
        }

        end:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $id
     */
    public function deleteAction($id)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];

        $model = EmailTemplateDefault::findFirstById($id);
        if ($model instanceof EmailTemplateDefault) {
            $result = $model->__quickRemove();
            if (!$result['success']){
                $return['message'] = "DATA_SAVE_FAIL_TEXT";
            }
        }

        $this->response->setJsonContent($result);
        $this->response->send();
    }
}
