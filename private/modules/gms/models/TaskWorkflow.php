<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class TaskWorkflow extends \Reloday\Application\Models\TaskWorkflowExt
{
    const WORKFLOW = 1;
    const TASK_WORKFLOW = 0;
    const TASK_TEMPLATE_COMPANY_WORKFLOW = 2;

    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('object_uuid', '\Reloday\Gms\Models\Workflow', 'uuid', [
            'alias' => 'Workflow',
            'reusable' => true,
            'cache' => [
                'key' => 'WORKFLOW_' . $this->getObjectUuid(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
        ]);

        $this->belongsTo('object_uuid', '\Reloday\Gms\Models\TaskTemplateCompany', 'uuid', [
            'alias' => 'TaskTemplateCompany',
            'reusable' => true,
            'cache' => [
                'key' => 'TASK_TEMPLATE_COMPANY_' . $this->getObjectUuid(),
                'lifetime' => CacheHelper::__TIME_6_MONTHS
            ],
        ]);
    }

    /**
     * @return bool
     */
    public function belongsToGms()
    {
        if ($this->getWorkflow()){
            return $this->getWorkflow() && $this->getWorkflow()->belongsToGms();

        }else if($this->getTaskTemplateCompany()){
            return $this->getTaskTemplateCompany() && $this->getTaskTemplateCompany()->belongsToGms();

        }else{
            return false;
        }
    }

    /**
     * @param $uuid
     * @return \Phalcon\Mvc\Model\ResultSetInterface|\Reloday\Application\Models\TaskWorkflow|\Reloday\Application\Models\TaskWorkflow[]
     */
    public static function getByWorkflowUuid($uuid)
    {
        $task = self::find([
            'conditions' => 'object_uuid = :uuid: AND link_type = :type: ',
            'bind' => [
                'uuid' => $uuid,
                'type' => self::WORKFLOW
            ],
            'order' => 'sequence ASC'
        ]);
        return ($task);
    }

    /**
     * @param $uuid
     * @return \Phalcon\Mvc\Model\ResultSetInterface|\Reloday\Application\Models\TaskWorkflow|\Reloday\Application\Models\TaskWorkflow[]
     */
    public static function __getByTaskTemplateCompanyUuid($uuid)
    {
        $task = self::find([
            'conditions' => 'object_uuid = :uuid: AND link_type = :type: ',
            'bind' => [
                'uuid' => $uuid,
                'type' => self::TASK_TEMPLATE_COMPANY_WORKFLOW
            ],
            'order' => 'sequence ASC'
        ]);
        return ($task);
    }
}
