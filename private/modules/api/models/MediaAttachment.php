<?php

namespace SMXD\Api\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use SMXD\Api\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;

class MediaAttachment extends \SMXD\Application\Models\MediaAttachmentExt
{	

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

	public function initialize(){
		parent::initialize(); 
	}



    /**
     * create Attachments without
     * @param $params
     */
    public static function __createAttachments($params)
    {
        $objectUuid = isset($params['objectUuid']) && $params['objectUuid'] != '' ? $params['objectUuid'] : '';
        $object = isset($params['object']) && $params['object'] != null ? $params['object'] : null;
        $mediaList = isset($params['fileList']) && is_array($params['fileList']) ? $params['fileList'] : [];
        if (count($mediaList) == 0) {
            $mediaList = isset($params['files']) && is_array($params['files']) ? $params['files'] : [];
        }
        //$objectName = isset($params['objectName']) && $params['objectName'] != '' ? $params['objectName'] : self::MEDIA_OBJECT_DEFAULT_NAME;
        //$isShared = isset($params['isShared']) && is_bool($params['isShared']) ? $params['isShared'] : self::IS_SHARED_FALSE;

        $User = isset($params['User']) ? $params['User'] : null;
        $company = isset($params['company']) ? $params['company'] : null;
        $employee = isset($params['employee']) && is_object($params['employee']) && $params['employee'] != null ? $params['employee'] : null;
        $counterPartyCompany = isset($params['counterPartyCompany']) && is_object($params['counterPartyCompany']) && $params['counterPartyCompany'] != null ? $params['counterPartyCompany'] : null;

        if ($objectUuid == '' && !is_null($object) && is_object($object) && method_exists($object, 'getUuid')) {
            $objectUuid = $object->getUuid();
        }
        if ($objectUuid == '' && !is_null($object) && is_array($object) && isset($object['uuid'])) {
            $objectUuid = $object['uuid'];
        }

        if ($objectUuid != '') {
            $items = [];
            foreach ($mediaList as $attachment) {
                $forceIsShared = false;
                $attachResult = MediaAttachment::__createAttachment([
                    'objectUuid' => $objectUuid,
                    'file' => $attachment,
                    'User' => $User,
                ]);
                if ($attachResult['success'] == true) {
                    //share to my own company
                    $mediaAttachment = $attachResult['data'];

                    if (isset($company) && $company) {
                        $shareResult = $mediaAttachment->createAttachmentSharingConfig(['uuid' => $objectUuid], $company);
                        if ($shareResult['success'] == false) {
                            $return = ['success' => false, 'errorType' => 'MediaAttachmentSharingError', 'detail' => $shareResult];
                            goto end_of_function;
                        }
                    }

                    if (isset($employee) && $employee) {
                        $shareResult = $mediaAttachment->createAttachmentSharingConfig(['uuid' => $objectUuid], $employee);
                        if ($shareResult['success'] == false) {
                            $return = ['success' => false, 'errorType' => 'MediaAttachmentSharingError', 'detail' => $shareResult];
                            goto end_of_function;
                        }
                        $forceIsShared = true;
                    }

                    if (isset($counterPartyCompany) && $counterPartyCompany) {
                        $shareResult = $mediaAttachment->createAttachmentSharingConfig(['uuid' => $objectUuid], $counterPartyCompany);
                        if ($shareResult['success'] == false) {
                            $return = ['success' => false, 'errorType' => 'MediaAttachmentSharingError', 'detail' => $shareResult];
                            goto end_of_function;
                        }
                        $forceIsShared = true;
                    }

                    if ($forceIsShared == true) {
                        /** @var set force Shared $updateResult */
                        $updateResult = $mediaAttachment->setForceShared();
                        if ($updateResult['success'] == false) {
                            $return = ['success' => false, 'errorType' => 'MediaAttachmentError', 'detail' => $updateResult];
                            goto end_of_function;
                        }
                    }

                    $items[] = $mediaAttachment;

                } else {
                    $return = ['success' => false, 'errorType' => 'MediaAttachmentError', 'detail' => $attachResult];
                    goto end_of_function;
                }
            }
            $return = ['success' => true, 'data' => $items];
        }
        end_of_function:
        return $return;
    }
}
