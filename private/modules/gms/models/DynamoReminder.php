<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;
use \Reloday\Application\Lib\DynamodbModel;
use \Firebase\JWT\JWT;

class  DynamoReminder extends \Reloday\Application\Lib\DynamodbModel {

    const DEFAULT_EXECUTE_TIME = -1; //valeur of time

    public function __construct()
    {
        $this->tableName = getenv('AWS_REMINDER_ITEM_TABLE');
        parent::__construct();

    }

    /**
     * @return string
     */
    public function getKeyAttribute()
    {
        return "uuid";
    }

    /**
     * @return string
     */
    public function getTableName()
    {
        return getenv('AWS_REMINDER_ITEM_TABLE');
    }

    /**
     * @return array|false|string
     */
    public static function getSource(){
        return getenv('AWS_REMINDER_ITEM_TABLE');
    }

    /**
     * get colum map
     * @return array
     */
    public function getColumnMap(){
        $this->columnMap = [
            ['name' => 'uuid', 'type' => 'S'],
            ['name' => 'task_uuid', 'type' => 'S'],
            ['name' => 'user_profile_uuid', 'type' => 'S'],
            ['name' => 'end_at', 'type' => 'N'],
            ['name' => 'execute_at', 'type' => 'N'],
            //

            //for user profile
            ['name' => 'created_at', 'type' => 'N'], //create time
            ['name' => 'user_name', 'type' => 'S'],
            ['name' => 'end_at', 'type' => 'N'], // reminder from time
            ['name' => 'data', 'type' => 'M'], //save JWT here of data here
            ['name' => 'quantity', 'type' => 'N'], //total number of reminder
        ];
        return $this->columnMap;
    }


    /**
     * @return bool|void
     */
    public function create(){
        return parent::create();
    }

    public function update(){
        return parent::update();
    }

    public function delete(){
        return parent::delete();
    }


}