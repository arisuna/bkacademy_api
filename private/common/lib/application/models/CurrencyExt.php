<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace SMXD\Application\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security;
use SMXD\Application\Lib\CacheHelper;
use SMXD\Application\Lib\ModelHelper;
use SMXD\Application\Traits\ModelTraits;
use Phalcon\Validation;

class CurrencyExt extends Currency
{
    use ModelTraits;

    const CURRENCY_ACTIVE = 1;
    const CURRENCY_DESACTIVE = 0;
    const CURRENCY_PRINCIPAL = 1;
    const CURRENCY_NOT_PRINCIPAL = 0;

    public function beforeValidation()
    {
        $validator = new Validation();
        $validator->add(
            'code',
            new Validation\Validator\PresenceOf([
                'model' => $this,
                'message' => 'CURRENCY_CODE_REQUIRED_TEXT'

            ])
        );

        $validator->add(
            'code',
            new Validation\Validator\Uniqueness([
                'model' => $this,
                'message' => 'CURRENCY_CODE_UNIQUE_TEXT'

            ])
        );

        return $this->validate($validator);
    }

    /**
     * @param array $custom
     */
    public function setData($custom = [])
    {

        ModelHelper::__setData($this, $custom);

        /****** YOUR CODE ***/


        /****** END YOUR CODE **/
    }

    public function __quickUpdate($returnObject = false)
    {
        try {
            if (method_exists($this, 'getId') && $this->getId() > 0 || (method_exists($this, 'isExists') && $this->isExists())) {
                $resultUpdate = $this->update();
                if ($resultUpdate) {
                    if (!$returnObject) {
                        return ["success" => true, "message" => "DATA_SAVE_SUCCESS_TEXT", "data" => $this];
                    } else {
                        return $this;
                    }
                } else {
                    return [
                        "success" => false,
                        "message" => ModelHelper::__getDisplayErrorMessage($this),
                        "errorMessage" => ModelHelper::__getErrorMessages($this),
                        "errorMessageLast" => ModelHelper::__getErrorMessage($this),
                        "data" => $this
                    ];
                }
            } else {
                return ['success' => false, "message" => "DATA_UPDATE_FAIL_TEXT"];
            }
        } catch (\PDOException $e) {
            \Sentry\captureException($e);
            return [
                "success" => false,
                "errorTraceAsString" => $e->getTraceAsString(),
                "errorMessage" => $e->getMessage(),
                "message" => "DATA_SAVE_FAIL_TEXT",
                "detail" => $e->getMessage()
            ];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return [
                "success" => false,
                "errorTraceAsString" => $e->getTraceAsString(),
                "errorMessage" => $e->getMessage(),
                "message" => "DATA_SAVE_FAIL_TEXT",
                "detail" => $e->getMessage()
            ];
        }
    }

    public function __quickCreate($returnObject = false)
    {
        try {
            if (method_exists($this, 'isExists')) {
                if ($this->isExists()) {
                    return ["success" => false, "message" => "DATA_EXISTED_TEXT", "detail" => 'Data Existed'];
                }
            }
            $create = $this->create();
            if ($create) {
                if (!$returnObject)
                    return ["success" => true, "message" => "DATA_SAVE_SUCCESS_TEXT", "data" => $this, "create" => $create];
                else return $this;
            } else {
                return [
                    "success" => false,
                    "message" => ModelHelper::__getDisplayErrorMessage($this),
                    "errorMessage" => ModelHelper::__getErrorMessages($this),
                    "errorMessageLast" => ModelHelper::__getErrorMessage($this),
                    "data" => $this
                ];
            }

        } catch (\PDOException $e) {
            \Sentry\captureException($e);
            return ["success" => false, "message" => "DATA_SAVE_FAIL_TEXT", "detail" => $e->getMessage(), "errorMessage" => $e->getMessage(), "data" => $this];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return ["success" => false, "message" => "DATA_SAVE_FAIL_TEXT", "detail" => $e->getMessage(), "errorMessage" => $e->getMessage()];
        }
    }
}