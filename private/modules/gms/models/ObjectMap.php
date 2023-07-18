<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Assignment;
use Reloday\Gms\Models\RelocationServiceCompany;
use Reloday\Gms\Models\Task;
use Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\HistoryOld;

class ObjectMap extends \Reloday\Application\Models\ObjectMapExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;


    public function initialize()
    {
        parent::initialize();
    }


    /**
     * [__save description]
     * @param  {Array}  $custom [description]
     * @return {[type]}         [description]
     */
    public function saveObject($object)
    {
        $model = $this;
        $model->setUuid($object->getUuid());
        $model->setTable($object->getSource());

        if ($model->getUuid() != '' && $model->getTable() != '') {
            try {
                if ($model->save()) {
                    $result = [
                        'success' => true,
                        'message' => 'DATA_SAVE_SUCCESS_TEXT',
                    ];
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
                }
            } catch (\PDOException $e) {
                $result = [
                    'success' => false,
                    'message' => 'DATA_SAVE_FAIL_TEXT',
                    'detail' => $e->getMessage(),
                ];
            } catch (Exeception $e) {
                $result = [
                    'success' => false,
                    'message' => 'DATA_SAVE_FAIL_TEXT',
                    'detail' => $e->getMessage(),
                ];
            }
        } else {
            $result = [
                'success' => false,
                'message' => 'DATA_SAVE_FAIL_TEXT',
            ];
        }
        return $result;
    }

    /**
     *
     */
    public static function findOrCreate()
    {
        $object = self::findFirstByUuid();
    }

    /**
     * @return \Phalcon\Mvc\Model\ResultInterface|\Reloday\Application\Models\ObjectFolder
     */
    public function getMyDspFolder()
    {
        return ObjectFolder::findFirst([
            "conditions" => "object_uuid = :uuid: and hr_company_id is null and employee_id is null and dsp_company_id = :dsp_company_id:",
            "bind" => [
                "uuid" => $this->getUuid(),
                "dsp_company_id" => ModuleModel::$company->getId()
            ]
        ]);
    }
}
