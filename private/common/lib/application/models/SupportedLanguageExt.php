<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace SMXD\Application\Models;

use Phalcon\Http\Request;
use Phalcon\Security;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Traits\ModelTraits;

class SupportedLanguageExt extends SupportedLanguage
{
    use ModelTraits;
    const LANG_EN = 'en';
    const LANG_VI = 'vi';

    static $languages = [
        self::LANG_EN, self::LANG_VI
    ];

    public $options;

    static function getTable()
    {
        $instance = new SupportedLanguage();
        return $instance->getSource();
    }

    public function initialize()
    {
        /*$this->addBehavior(new \Phalcon\Mvc\Model\Behavior\Timestampable(
            array(
                'beforeValidationOnCreate' => array(
                    'field' => array(
                        'created_at', 'updated_at'
                    ),
                    'format' => 'Y-m-d H:i:s'
                ),
                'beforeValidationOnUpdate' => array(
                    'field' => 'updated_at',
                    'format' => 'Y-m-d H:i:s'
                )
            )
        ));*/
    }

    public function beforeValidation()
    {

        return $this->validationHasFailed() != true;
    }

    public function beforeSave()
    {

    }

    /**
     * @param array $custom
     * @return array|SupportedLanguage|SupportedLanguageExt
     */
    public function __save($custom = [])
    {
        $req = new Request();
        $model = $this;
        $data = $req->getPost();

        if ($req->isPut()) { // Request update
            $model = $this->findFirst($req->getPut('id'));
            if (!$model instanceof $this) {
                return [
                    'success' => false,
                    'message' => 'LANGUAGE_NOT_FOUND_TEXT'
                ];
            }

            $data = $req->getPut();
        }

        /*$model->setName(isset($custom['name']) ? $custom['name'] : (isset($data['name']) ? $data['name'] : $model->getName()));
        $model->setCode(isset($custom['code']) ? $custom['code'] : (isset($data['code']) ? $data['code'] : $model->getCode()));
        $model->setDescription(isset($custom['description']) ? $custom['description'] : (isset($data['description']) ? $data['description'] : $model->getDescription()));*/


        if ($model->save()) {
            return $model;
        } else {
            $msg = [];
            foreach ($model->getMessages() as $message) {
                $msg[$message->getField()] = $message->getMessage();
            }
            $result = [
                'success' => false,
                'message' => 'SAVE_LANGUAGE_FAIL_TEXT',
                'detail' => $msg
            ];
            return $result;
        }

    }

    /**
     * [afterFetch description]
     * @return [type] [description]
     */
    public function afterFetch()
    {
        $options = json_decode($this->data);
        if (json_last_error() === 0) {
            return $this->options = $options;
        } else {
            return null;
        }
    }

    /**
     * [getOptions description]
     * @return [type] [description]
     */
    public function getOptions()
    {
        $options = json_decode($this->data);
        if (json_last_error() === 0) {
            return $options;
        } else {
            return null;
        }
    }

    /**
     * [getAll description]
     * @return [type] [description]
     */
    public static function getAll()
    {
        return self::find([
            "order" => "name ASC",
        ]);
    }

    /**
     * @param $lang
     */
    public static function __getLangData($lang = self::LANG_EN)
    {
        return self::findFirst([
            'conditions' => 'name = :name:',
            'bind' => [
                'name' => $lang
            ],
        ]);
    }
}