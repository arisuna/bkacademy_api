<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class NeedFormRequest extends \Reloday\Application\Models\NeedFormRequestExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_NOT_SENT = 0;
    const STATUS_ACTIVE = 1;

    const STATUS_SENT = 1;
    const STATUS_READ = 2;
    const STATUS_ANSWERED = 3;

    const VIEWED_YES = 1;
    const VIEWED_NO = 0;

    /**
     *
     */
    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('user_profile_uuid', 'Reloday\Gms\Models\Employee', 'uuid', ['alias' => 'Employee']);
        $this->belongsTo('service_company_id', 'Reloday\Gms\Models\ServiceCompany', 'id', ['alias' => 'ServiceCompany']);
        $this->belongsTo('company_id', 'Reloday\Gms\Models\Company', 'id', ['alias' => 'Company']);
        $this->belongsTo('owner_company_id', 'Reloday\Gms\Models\Company', 'id', ['alias' => 'OwnerCompany']);
        $this->belongsTo('relocation_id', 'Reloday\Gms\Models\Relocation', 'id', ['alias' => 'Relocation']);
        $this->belongsTo('relocation_service_company_id', 'Reloday\Gms\Models\RelocationServiceCompany', 'id', ['alias' => 'RelocationServiceCompany']);
        $this->belongsTo('need_form_gabarit_id', 'Reloday\Gms\Models\NeedFormGabarit', 'id', ['alias' => 'NeedFormGabarit']);
        $this->hasMany('need_form_gabarit_id', 'Reloday\Gms\Models\NeedFormGabaritItem', 'id', ['alias' => 'NeedFormGabaritItem']);
        $this->hasMany('id', 'Reloday\Gms\Models\NeedFormRequestAnswer', 'need_form_request_id', ['alias' => 'NeedFormRequestAnswer']);
        $this->belongsTo('assignment_id', 'Reloday\Gms\Models\Assignment', 'id', ['alias' => 'Assignment']);
    }

    /**
     * [get description]
     * @param  {[type]} $name [description]
     * @return {[type]}       [description]
     */
    public function get($name)
    {
        return $this->$name;
    }

    /**
     * [set description]
     * @param {[type]} $name     [description]
     * @param {[type]} $variable [description]
     */
    public function set($name, $value)
    {
        $this->$name = $value;
    }

    /**
     * [__save description]
     * @param  {Array}  $custom [description]
     * @return {[type]}         [description]
     */
    public function __save($custom = [])
    {

        $req = new Request();
        $model = $this;

        if (!($model->getId() > 0)) {
            if ($req->isPut()) {
                $data_id = isset($custom['need_form_request_id']) && $custom['need_form_request_id'] > 0 ? $custom['need_form_request_id'] : $req->getPut('need_form_request_id');
                if ($data_id > 0) {
                    $model = $this->findFirstById($data_id);
                    if (!$model instanceof $this) {
                        return [
                            'success' => false,
                            'message' => 'DATA_NOT_FOUND_TEXT',
                        ];
                    }
                }
            }
        }

        /** @var [varchar] [set uunique id] */
        if (property_exists($model, 'uuid') && method_exists($model, 'getUuid') && method_exists($model, 'setUuid')) {
            if (property_exists($model, 'uuid') && method_exists($model, 'getUuid') && method_exists($model, 'setUuid')) {
                if ($this->getUuid() == '') {
                    $random = new Random;
                    $uuid = $random->uuid();
                    $model->setUuid($uuid);
                }
            }
        }

        /****** ALL ATTRIBUTES ***/
        $metadataManager = $model->getModelsMetaData(); //get Meta Data Manager
        $fields = $metadataManager->getAttributes($model);  //get Attributes from MetaData Manager
        $fields_numeric = $metadataManager->getDataTypesNumeric($model);

        foreach ($fields as $key => $field_name) {
            if ($field_name != 'id'
                && $field_name != 'uuid'
                && $field_name != 'created_at'
                && $field_name != 'updated_at'
                && $field_name != "password") {

                if (!isset($fields_numeric[$field_name])) {
                    $field_name_value = Helpers::__getRequestValueWithCustom($field_name, $custom);
                    $field_name_value = Helpers::__coalesce($field_name_value, $model->get($field_name));
                    $field_name_value = $field_name_value != '' ? $field_name_value : $model->get($field_name);
                    $model->set($field_name, $field_name_value);

                } else {

                    $field_name_value = Helpers::__getRequestValueWithCustom($field_name, $custom);
                    $field_name_value = Helpers::__coalesce($field_name_value, $model->get($field_name));
                    if ($field_name_value != '' && !is_null($field_name_value)) {
                        $model->set($field_name, $field_name_value);
                    }
                }
            }
        }
        /****** YOUR CODE ***/

        /****** END YOUR CODE **/
        try {
            if ($model->getId() == null) {

            }
            if ($model->save()) {
                return $model;
            } else {
                $msg = [];
                foreach ($model->getMessages() as $message) {
                    $msg[$message->getField()] = $message->getMessage();
                }
                $result = [
                    'success' => false,
                    'message' => 'DATA_SAVE_FAIL_TEXT',
                    'detail' => $msg,
                    'model' => $model,
                ];
                return $result;
            }
        } catch (\PDOException $e) {
            $result = [
                'success' => false,
                'message' => 'DATA_SAVE_FAIL_TEXT',
                'detail' => $e->getMessage(),
            ];
            return $result;
        } catch (Exception $e) {
            $result = [
                'success' => false,
                'message' => 'DATA_SAVE_FAIL_TEXT',
                'detail' => $e->getMessage(),
            ];
            return $result;
        }
    }

    /**
     * @return string
     */
    public function getLandingUrl()
    {
        return ModuleModel::$app->getBaseUrl() . "/needform/#/landing/" . $this->getUuid();
    }

    /**
     * @return mixed
     */
    public function getFrontendUrl()
    {
        if ($this->getRelocationServiceCompany()) {
            return $this->getRelocationServiceCompany()->getSimpleFrontendUrl();
        }
    }

    /**
     * @return string
     */
    public function getEmployeeLoginUrl()
    {
        if ($this->getCompany()) {
            if ($this->getCompany()->getApp()) {
                return $this->getCompany()->getApp()->getEmployeeUrl() . "#/app/my-questionnaires/detail/" . $this->getUuid();
            }
        }
    }

    /**
     *
     */
    public function belongsToGms()
    {
        if ($this->getOwnerCompanyId() == ModuleModel::$company->getId()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * return all items of answer
     * @return array
     */
    public function getAnswerItems()
    {
        $return = [];
        if ($this->getStatus() == NeedFormRequest::STATUS_ANSWERED) {
            $needFormRequestAnswerList = $this->getNeedFormRequestAnswer();
            if ($needFormRequestAnswerList->count() > 0) {
                foreach ($needFormRequestAnswerList as $needFormRequestAnswerItem) {
                    $return[$needFormRequestAnswerItem->getNeedFormGabaritItemId()] = $needFormRequestAnswerItem;
                }
            }
        }
        return $return;
    }

    /**
     * @return array
     */
    public function getFormBuilderStructure()
    {
        $needFormGabarit = $this->getNeedFormGabarit();
        if ($needFormGabarit) {
            // Load need assessment sections
            $sections = NeedFormGabaritSection::find([
                'conditions' => 'need_form_gabarit_id=:id:',
                'bind' => [
                    'id' => $needFormGabarit->getId()
                ],
                'order' => 'position ASC']);
            $formBuilder = [];
            foreach ($sections as $section) {
                $formBuilder[$section->getPosition()] = $section->toArray();
                $formBuilder[$section->getPosition()]['index'] = (int)$section->getPosition();
                $formBuilder[$section->getPosition()]['goToSection'] = (int)$section->getNextSectionId();
                $formBuilder[$section->getPosition()]['items'] = $section->getDetailSectionContent();
            }
            return $formBuilder;
        }
    }


    /**
     * @return array
     */
    public function getFormBuilderStructureAnswered($needFormRequest)
    {
        $needFormGabarit = $this->getNeedFormGabarit();
        if ($needFormGabarit) {
            // Load need assessment sections
            $sections = NeedFormGabaritSection::find([
                'conditions' => 'need_form_gabarit_id=:id:',
                'bind' => [
                    'id' => $needFormGabarit->getId()
                ],
                'order' => 'position ASC']);
            $formBuilder = [];
            foreach ($sections as $section) {
                $formBuilder[$section->getPosition()] = $section->toArray();
                $formBuilder[$section->getPosition()]['index'] = (int)$section->getPosition();
                $formBuilder[$section->getPosition()]['goToSection'] = (int)$section->getNextSectionId();
                $formBuilder[$section->getPosition()]['items'] = $section->getDetailSectionContentMappingWithRequest($needFormRequest);
            }
            return $formBuilder;
        }
    }
}
