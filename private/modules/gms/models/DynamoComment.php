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
use \Phalcon\Security\Random;

class  DynamoComment extends \Reloday\Application\Lib\DynamodbModel
{

    const DEFAULT_EXECUTE_TIME = -1; //valeur of time
    const REPORT_YES = 1;
    const REPORT_NO = 0;

    public function __construct()
    {
        $this->tableName = getenv('AWS_COMMENTS_TABLE');
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
        return getenv('AWS_COMMENTS_TABLE');
    }

    /**
     * @return array|false|string
     */
    public static function getSource()
    {
        return getenv('AWS_COMMENTS_TABLE');
    }

    /**
     * @return array|false|string
     */
    public static function getCloudTableName()
    {
        return getenv('AWS_COMMENTS_TABLE');
    }

    /**
     * get colum map
     * @return array
     */
    public function getColumnMap()
    {
        $this->columnMap = [
            ['name' => 'uuid', 'type' => 'S'],
            ['name' => 'created_at', 'type' => 'N'],
            ['name' => 'updated_at', 'type' => 'N'],
            ['name' => 'report', 'type' => 'N'],
            ['name' => 'message', 'type' => 'S'],
            ['name' => 'object_uuid', 'type' => 'S'],
            ['name' => 'user_name', 'type' => 'S'],
            ['name' => 'user_profile_uuid', 'type' => 'S'],
            ['name' => 'email', 'type' => 'S'],
            ['name' => 'data', 'type' => 'S'],
            ['name' => 'external_email', 'type' => 'S'],
            ['name' => 'persons', 'type' => 'M']

            /*
             * ['user_profile' => uuid, 'user_name' => 'nolanrachelback',
             */
        ];
        return $this->columnMap;
    }


    /**
     * @param $params
     */
    public function addData($data)
    {
        foreach ($data as $key => $item) {
            $params = $this->getColumnMap();
            if (isset($params[$key])) {
                if ($params[$key]['type'] == self::TYPE_STRING) {
                    $this->setAttribute($key, $item);
                }

                if ($params[$key]['type'] == self::TYPE_NUMERIC) {
                    $this->setAttribute($key, $item);
                }
            }
        }
    }

    /**
     * @return bool|void
     */
    public function create()
    {
        if ($this->getAttribute('uuid') == '') {
            $random = new Random();
            $this->setAttribute('uuid', $random->uuid());
        }

        if ($this->getAttribute('created_at') == '' || $this->getAttribute('created_at') == null || $this->getAttribute('created_at') == 0) {
            $this->setAttribute('created_at', time());
        }

        if ($this->getAttribute('updated_at') == '' || $this->getAttribute('updated_at') == null || $this->getAttribute('updated_at') == 0) {
            $this->setAttribute('updated_at', time());
        }
        return parent::create();
    }

    public function update()
    {
        return parent::update();
    }

    public function delete()
    {
        return parent::delete();
    }


}