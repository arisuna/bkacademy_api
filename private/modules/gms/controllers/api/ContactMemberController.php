<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\RelodayObjectMapHelper;
use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\Contact;
use Reloday\Gms\Models\DataContactMember;
use Reloday\Gms\Models\ObjectMap;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class ContactMemberController extends BaseController
{
    /**
     * get all members of list
     * @param $object_uuid
     * @return mixed
     */
    public function listAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $return = ['success' => false, 'data' => [], 'message' => 'OBJECT_NOT_FOUND_TEXT'];
        $member_object_uuid = Helpers::__getRequestValue('uuid');
        $bookerId = Helpers::__getRequestValue('bookerId');
        if ($bookerId && Helpers::__isValidId($bookerId)) {
            $booker = Company::__findBookerById($bookerId);
            if ($booker) {
                $contact_object_uuid = $booker->getUuid();
            }
        }

        if ($member_object_uuid != '' && Helpers::__isValidUuid($member_object_uuid)) {
            $return = DataContactMember::__findWithFilter([
                'member_object_uuid' => $member_object_uuid,
                'contact_object_uuid' => isset($contact_object_uuid) ? $contact_object_uuid : null,
                'limit' => 1000
            ]);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     *
     */
    public function addAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $return = ['success' => false, 'data' => [], 'message' => 'OBJECT_NOT_FOUND_TEXT'];
        $uuid = Helpers::__getRequestValue('uuid');
        $contactId = Helpers::__getRequestValueAsArray('contactId');
        $contactUuid = Helpers::__getRequestValueAsArray('contactUuid');

        if ($contactId && Helpers::__isValidId($contactId)) {
            $contact = Contact::findFirstById($contactId);
            if (!$contact || $contact->belongsToGms() == false) {
                goto end;
            }
        }

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $object = RelodayObjectMapHelper::__getObjectWithCache($uuid);
            if (!$object) {
                goto end;
            }
        }

        $return = DataContactMember::__add([
            'object_uuid' => $uuid,
            'object_source' => $object->getObjectType()
        ], $contact);

        end:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     *
     */
    public function removeAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $return = ['success' => false, 'data' => [], 'message' => 'OBJECT_NOT_FOUND_TEXT'];
        $uuid = Helpers::__getRequestValue('uuid');
        $contactId = Helpers::__getRequestValueAsArray('contactId');
        $contactUuid = Helpers::__getRequestValueAsArray('contactUuid');

        if ($contactId && Helpers::__isValidId($contactId)) {
            $contact = Contact::findFirstById($contactId);
            if (!$contact || $contact->belongsToGms() == false) {
                goto end;
            }
        }

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $object = RelodayObjectMapHelper::__getObjectWithCache($uuid);
            if (!$object) {
                goto end;
            }
        }

        $return = DataContactMember::__remove([
            'object_uuid' => $uuid,
            'object_source' => $object->getTable()
        ], $contact);

        end:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
