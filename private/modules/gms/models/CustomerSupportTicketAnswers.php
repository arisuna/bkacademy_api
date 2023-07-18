<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Gms\Models\ModuleModel;

class CustomerSupportTicketAnswers extends \Reloday\Application\Models\CustomerSupportTicketAnswersExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    /**
     *
     */
    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('customer_support_ticket_id', 'Reloday\Gms\Models\CustomerSupportTicket', 'id', [
            'alias' => 'CustomerSupportTicket',
            'cache' => [
                'key' => 'CUSTOMER_SUPPORT_TICKET_' . $this->getCustomerSupportTicketId(),
                'lifetime' => CacheHelper::__TIME_24H
            ],
            'reusable' => true,
        ]);

        $this->belongsTo('user_profile_id', 'Reloday\Gms\Models\UserProfile', 'id', [
            'alias' => 'UserProfile',
            'cache' => [
                'key' => 'USER_PROFILE_' . $this->getUserProfileId(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
            'reusable' => true,
        ]);
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
        $data = $req->getPost();

        if ($req->isPut()) {
            // Request update
            // 
            $data_id = isset($custom['data_id']) && $custom['data_id'] > 0 ? $custom['data_id'] : $req->getPut('id');

            if ($data_id > 0) {
                $model = $this->findFirstById($data_id);
                if (!$model instanceof $this) {
                    return [
                        'success' => false,
                        'message' => 'DATA_NOT_FOUND_TEXT',
                    ];
                }
            }
            $data = $req->getPut();
        }
        /** @var [varchar] [set uunique id] */
        if (property_exists($model, 'uuid') && method_exists($model, 'getUuid') && method_exists($model, 'setUuid')) {
            $uuid = isset($data['uuid']) ? $data['uuid'] : (isset($custom['uuid']) ? $custom['uuid'] : $model->getUuid());
            if ($model->getUuid() == '') {
                $random = new Random;
                $uuid = $random->uuid();
            }
            if ($uuid != '') {
                $model->setUuid($uuid);
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
                    $model->set(
                        $field_name,
                        isset($custom[$field_name]) ? $custom[$field_name] :
                            (isset($data[$field_name]) ? $data[$field_name] : $model->get($field_name))
                    );
                } else {
                    $field_name_value = isset($custom[$field_name]) ? $custom[$field_name] :
                        (isset($data[$field_name]) ? $data[$field_name] : $model->get($field_name));
                    if (is_numeric($field_name_value) && $field_name_value != '' && !is_null($field_name_value) && !empty($field_name_value)) {
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
                    'detail' => $msg
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
        }
    }

}
