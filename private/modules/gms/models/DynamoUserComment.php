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

class  DynamoUserComment extends \Reloday\Application\Lib\DynamodbModel
{

    const DEFAULT_EXECUTE_TIME = -1; //valeur of time
    const REPORT_YES = 1;
    const REPORT_NO = 0;

    public function __construct()
    {
        $this->tableName = getenv('AWS_USER_COMMENTS_TABLE');
        parent::__construct();

    }

    /**
     * @return string
     */
    public function getKeyAttribute()
    {
        return "user_profile_uuid";
    }

    /**
     * @return string
     */
    public function getTableName()
    {
        return getenv('AWS_USER_COMMENTS_TABLE');
    }

    /**
     * @return array|false|string
     */
    public static function getSource()
    {
        return getenv('AWS_USER_COMMENTS_TABLE');
    }

    /**
     * @return array|false|string
     */
    public static function getCloudTableName()
    {
        return getenv('AWS_USER_COMMENTS_TABLE');
    }

    /**
     * get colum map
     * @return array
     */
    public function getColumnMap()
    {
        $this->columnMap = [
            ['name' => 'user_profile_uuid', 'type' => 'S'],
            ['name' => 'comment_uuid', 'type' => 'S'],
        ];
        return $this->columnMap;
    }


    /**
     * @return bool|void
     */
    public function create()
    {
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