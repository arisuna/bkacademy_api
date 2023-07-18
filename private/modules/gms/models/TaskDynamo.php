<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;

class  TaskDynamo extends \Reloday\Application\Lib\DynamodbModel {
    /**
     * TaskDynamo constructor.
     */
    public function __construct()
    {
        parent::__construct();

    }

    /**
     * @return string
     */
    public function getKeyAttribute()
    {
        return "task_uuid";
    }

    /**
     * column map
     */
    public function getColumnMap(){
        $this->columnMap = [
            ['name' => 'task_uuid', 'type' => 'S'],
            ['name' => 'number', 'type' => 'S'],
            ['name' => 'object_uuid', 'type' => 'S'],
            ['name' => 'company_uuid', 'type' => 'S'],
            ['name' => 'name', 'type' => 'S'],
            ['name' => 'description', 'type' => 'S'],
            ['name' => 'progress', 'type' => 'N'],
            ['name' => 'created_at', 'type' => 'N'],
            ['name' => 'parent_task_uuid', 'type' => 'S'],
            ['name' => 'user_profile_uuid', 'type' => 'S'], //creator user profile uuid
            ['name' => 'data', 'type' => 'M'], // object
            ['name' => 'object_link_type', 'type' => 'N'],
        ];
        return $this->columnMap;
    }

    /**
     * save data
     */
    public function save(){
        return parent::save();
    }

    /**
     * update data
     */
    public function update(){
        return parent::update();
    }

    /**
     * @return bool
     */
    public function delete(){
        return parent::delete();
    }

    /**
     * @return string
     */
    public  function getTableName()
    {
        return "reloday_task";
    }
}