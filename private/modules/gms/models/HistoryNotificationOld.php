<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;
use Reloday\Gms\Models\HistoryOld as History;
use \Firebase\JWT\JWT;

class  HistoryNotificationOld extends HistoryOld{

    const DEFAULT_READ = -1; //valeur of time

    public function __construct()
    {
        $this->tableName = getenv('AWS_HISTORY_NOTIFICATION_TABLE');
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
    public  function getTableName()
    {
        return getenv('AWS_HISTORY_NOTIFICATION_TABLE');
    }

    /**
     *
     */
    public function getColumnMap(){
        $this->columnMap = [
            ['name' => 'uuid', 'type' => 'S'],
            ['name' => 'user_profile_uuid', 'type' => 'S'],
            ['name' => 'created_at', 'type' => 'N'],
            ['name' => 'owner_user_profile_uuid', 'type' => 'S'],
            ['name' => 'user_name', 'type' => 'S'],
            ['name' => 'object_uuid', 'type' => 'S'],
            ['name' => 'action', 'type' => 'S'],
            ['name' => 'ip', 'type' => 'S'],
            ['name' => 'type', 'type' => 'S'],
            ['name' => 'data', 'type' => 'S'], //save JWT here of data here
            ['name' => 'readtime', 'type' => 'N'], //save Read time
        ];
        return $this->columnMap;
    }

    /**
     * @return bool|void
     */
    public function save(){
        if( ( $this->type == self::TYPE_RELOCATION
                || $this->type == self::TYPE_OTHERS
                || $this->type == self::TYPE_TASK
                || $this->type == self::TYPE_ASSIGNMENT ) &&
            in_array($this->action, self::$actions_array )
        ){
            return parent::save();
        }else{
            return [
                'success' => false,
                'message' => 'Action not allowed or type of data not allowed'
            ];
        }
    }

    /**
     * @return bool|void
     */
    public function create(){
        if( ( $this->type == self::TYPE_RELOCATION
                || $this->type == self::TYPE_OTHERS
                || $this->type == self::TYPE_TASK
                || $this->type == self::TYPE_ASSIGNMENT ) &&
            in_array($this->action, self::$actions_array )
        ){
            return parent::create();
        }else{
            return false;
        }

    }

    public function update(){
        return parent::update();
    }

    public function delete(){
        return parent::delete();
    }


}