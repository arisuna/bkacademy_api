<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;

class  TaskHistory extends \Reloday\Application\Lib\DynamodbModel {

    /**
     * @var array list actions
     */
    static $actions_array = [
        'UPDATE',
        'CREATE',
        'SET_DONE',
        'SET_IN_PROGRESS',
        'SET_TODO',
        'ADD_COMMENT',
        'SET_REMINDER',
        'CREATE_SUB_TASK',
        'ADD_NEW_VIEWER',
        'REMOVE_VIEWER',
    ];

    public function __construct()
    {
        parent::__construct();

    }

    /**
     * @return string
     */
    public function getKeyAttribute()
    {
        return "id";
    }

    /**
     *
     */
    public function getColumnMap(){
        $this->columnMap = [
            ['name' => 'id', 'type' => 'S'],
            ['name' => 'time', 'type' => 'N'],
            ['name' => 'user_profile_uuid', 'type' => 'S'],
            ['name' => 'user_name', 'type' => 'S'],
            ['name' => 'task_uuid', 'type' => 'S'],
            ['name' => 'action', 'type' => 'S'],
            ['name' => 'content', 'type' => 'S'],
            ['name' => 'ip', 'type' => 'S'],
        ];
        return $this->columnMap;
    }

    public function save(){
        return parent::save();
    }

    public function update(){
        return parent::update();
    }

    public function delete(){
        return parent::delete();
    }

    /**
     * @return string
     */
    public  function getTableName()
    {
        return "reloday_task_history";
    }
}