<?php

namespace Reloday\Gms\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\AclHelper;

class TaskTemplate extends \Reloday\Application\Models\TaskTemplateExt
{	
	public function initialize(){
		parent::initialize(); 

        $this->belongsTo('object_uuid', 'Reloday\Gms\Models\ServiceCompany', 'uuid', [
            'alias' => 'ServiceCompany'
        ]);

        $this->hasMany('uuid', 'Reloday\Gms\Models\TaskTemplateReminder', 'object_uuid', [
            'alias' => 'Reminders'
        ]);

        $this->hasMany('uuid', 'Reloday\Gms\Models\TaskFile', 'object_uuid', [
            'alias' => 'Files'
        ]);

        $this->belongsTo('object_uuid', 'Reloday\Gms\Models\Workflow', 'uuid', [
            'alias' => 'Workflow'
        ]);
	}

    /**
     * @return bool
     */
    public function belongsToGms()
    {
        if($this->getWorkflow()){
            return $this->getWorkflow() && $this->getWorkflow()->belongsToGms();
        }else{
            return $this->getServiceCompany() && $this->getServiceCompany()->belongsToGms();
        }
    }

    /**
     * @param $uuid
     * @return \Phalcon\Mvc\Model\ResultSetInterface|\Reloday\Application\Models\TaskWorkflow|\Reloday\Application\Models\TaskWorkflow[]
     */
    public static function getByWorkflowUuid($uuid, $type = 0)
    {
        if($type == self::IS_NORMAL_TASK || $type == self::IS_ASSIGNEE_TASK){
            $task = self::find([
                'conditions' => 'object_uuid = :uuid: and task_type = :task_type:',
                'bind' => [
                    'uuid' => $uuid,
                    'task_type' => $type
                ],
                'order' => 'sequence ASC'
            ]);
        }else{
            $task = self::find([
                'conditions' => 'object_uuid = :uuid:',
                'bind' => [
                    'uuid' => $uuid,
                ],
                'order' => 'sequence ASC'
            ]);
        }

        return ($task);
    }

    public static function __findWithFilters(array $options = [])
    {
        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\TaskTemplate', 'TaskTemplate');
        $queryBuilder->distinct(true);

        $queryBuilder->where('TaskTemplate.id > 0');

        if (isset($options['object_uuid'])) {
            $queryBuilder->andWhere('TaskTemplate.object_uuid = :object_uuid:', [
                'object_uuid' => $options['object_uuid']
            ]);
        }
        if (isset($options['has_file'])) {
            $queryBuilder->andWhere('TaskTemplate.has_file = :has_file:', [
                'has_file' => $options['has_file']
            ]);
        }

        if (isset($options['task_type'])) {
            $queryBuilder->andWhere('TaskTemplate.task_type = :task_type:', [
                'task_type' => $options['task_type']
            ]);
        }

        if (isset($options['query']) && is_string($options['query']) && $options['query'] != '') {
            $queryBuilder->andwhere("TaskTemplate.name LIKE :query:", [
                'query' => '%' . $options['query'] . '%',
            ]);
        }

        $queryBuilder->orderBy(["TaskTemplate.sequence ASC"]);
        $queryBuilder->groupBy('TaskTemplate.id');

        try {
            $items = $queryBuilder->getQuery()->execute();


            return [
                'success' => true,
                'data' => count($items) > 0 ? $items->toArray() : []
            ];
        } catch (\Phalcon\Exception $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'data' => [], 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\PDOException $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'data' => [], 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        } catch (\Exception $e) {
            Helpers::__trackError($e);
            return ['success' => false, 'data' => [], 'detail' => [$e->getTraceAsString(), $e->getMessage()]];
        }
    }

}
