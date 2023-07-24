<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace SMXD\App\Models;

use Phalcon\Http\Request;
use SMXD\Application\Models;
use Phalcon\Security;
use SMXD\Application\Validation\UserPasswordValidation;

class UserLogin extends Models\UserLoginExt
{

    /*
    * create new user login from [email,password,app_id,user_group_id]
    */
    public function createNewUserLogin($data = array())
    {
        $model = new self();
        $userPasswordValidation = new UserPasswordValidation();

        if ( $userPasswordValidation->check( $data['password'] ) ) {
            $security = new Security();
            $model->setPassword($security->hash($data['password']));
        } else {
            $result = [
                'success' => false,
                'message' => $userPasswordValidation->getFirstMessage(),
                'detail' => $userPasswordValidation->getMessages(),
                'password' => $data['password'],
            ];
            return $result;
        }

        $model->setEmail($data['email']);
        $model->setStatus(self::STATUS_ACTIVATED);
        $model->setCreatedAt(date('Y-m-d H:i:s'));
        $model->setUserGroupId($data['user_group_id']);
        $model->setAppId($data['app_id']);
        try {
            if ($model->save()) {
                return $model;
            } else {
                $error_message = [];
                foreach ($model->getMessages() as $message) {
                    $error_message[$message->getField()] = $message->getMessage();
                }
                $result = [
                    'success' => false,
                    'message' => 'CREATE_USER_FAIL_TEXT',
                    'detail' => $error_message
                ];
            }
        } catch (\PDOException $e) {
            $result = [
                'success' => false,
                'message' => 'CREATE_USER_FAIL_TEXT',
                'detail' => $e->getMessage()
            ];
        }
        return $result;
    }

    /**
     * @param array $data
     * @return array|UserLogin
     */
    public static function __createNewUserLogin($data = array())
    {
        $model = new self();
        $userPasswordValidation = new UserPasswordValidation();

        if ( $userPasswordValidation->check( $data['password'] ) ) {
            $security = new Security();
            $model->setPassword($security->hash($data['password']));
        } else {
            $result = [
                'success' => false,
                'message' => $userPasswordValidation->getFirstMessage(),
                'detail' => $userPasswordValidation->getMessages(),
                'password' => $data['password'],
            ];
            return $result;
        }

        $model->setEmail($data['email']);
        $model->setStatus(self::STATUS_ACTIVATED);
        $model->setCreatedAt(date('Y-m-d H:i:s'));
        $model->setUserGroupId($data['user_group_id']);
        $model->setAppId($data['app_id']);
        try {
            if ($model->save()) {
                return $model;
            } else {
                $error_message = [];
                foreach ($model->getMessages() as $message) {
                    $error_message[$message->getField()] = $message->getMessage();
                }
                $result = [
                    'success' => false,
                    'message' => 'CREATE_USER_FAIL_TEXT',
                    'detail' => $error_message
                ];
            }
        } catch (\PDOException $e) {
            $result = [
                'success' => false,
                'message' => 'CREATE_USER_FAIL_TEXT',
                'detail' => $e->getMessage()
            ];
        }
        return $result;
    }

    /**
     * @param $email
     * @return bool
     */
    public static function ifEmailAvailable($email)
    {

        $user_login = self::findFirstByEmail($email);
        if ($user_login) {
            return false;
        } else {
            return true;
        }
    }

    /**
     *
     */
    public function beforeSave()
    {
        // Encode password
        $security = new Security();
        $req = new Request();
        if ($req->getPost('password') || $req->getPut('password'))
            $this->setPassword($security->hash($this->getPassword()));
    }
}