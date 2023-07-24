<?php

namespace SMXD\Application\Traits;

use SMXD\Application\Lib\CacheHelper;

trait DynamoModelTraits
{

    /**
     * @return array
     */
    public function __quickCreate()
    {
        try {
            $result = $this->save();
            if (is_bool($result) && $result == true) {
                $return = [
                    'success' => true,
                    'message' => 'DATA_SAVE_SUCCESS_TEXT',
                    'detail' => $this->asArray()];
            } elseif (is_object($result) && $result instanceof Result && $result->hasKey('@metadata') && $result->get('@metadata')['statusCode'] == 200) {
                $return = [
                    'success' => true,
                    'message' => 'DATA_SAVE_SUCCESS_TEXT',
                    'detail' => $this->asArray()
                ];
            } else {
                $return = [
                    'success' => false,
                    'message' => 'DATA_SAVE_FAILED_TEXT',
                    'detail' => $result,
                    'model' => $this->asArray()
                ];
            }
        } catch (\Exception $e) {
            $return = [
                'success' => false,
                'message' => 'DATA_SAVE_FAILED_TEXT',
                'model' => $this->asArray(),
                'detail' => $e->getMessage()
            ];
        }
        return $return;
    }

    /**
     * @return array
     */
    public function __quickUpdate()
    {
        try {
            $result = $this->save();
            if (is_bool($result) && $result == true) {
                $return = [
                    'success' => true,
                    'message' => 'DATA_SAVE_SUCCESS_TEXT',
                    'detail' => $this->asArray()];
            } elseif (is_object($result) && $result instanceof Result && $result->hasKey('@metadata') && $result->get('@metadata')['statusCode'] == 200) {
                $return = [
                    'success' => true,
                    'message' => 'DATA_SAVE_SUCCESS_TEXT',
                    'detail' => $this->asArray()
                ];
            } else {
                $return = [
                    'success' => false,
                    'message' => 'DATA_SAVE_FAILED_TEXT',
                    'model' => $this->asArray(),
                    'detail' => $result
                ];
            }
        } catch (\Exception $e) {
            $return = [
                'success' => false,
                'message' => 'DATA_SAVE_FAILED_TEXT',
                'model' => $this->asArray(),
                'detail' => $e->getMessage()
            ];
        }
        return $return;

    }

    /**
     * @return array
     */
    public function __quickRemove()
    {
        try {
            $result = $this->delete();
            $return = [
                'success' => true,
                'message' => 'DATA_REMOVE_SUCCESS_TEXT',
                'result' => $result];

        } catch (\Exception $e) {
            $return = [
                'success' => false,
                'message' => 'DATA_REMOVE_FAILED_TEXT',
                'detail' => $e->getMessage()
            ];
        }
        return $return;

    }
}

