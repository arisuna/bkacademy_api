<?php
/**
 * Created by PhpStorm.
 * User: macbook
 * Date: 11/9/19
 * Time: 10:23 PM
 */

namespace Reloday\Gms\Controllers\API;


use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\Guide;
use Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\RelocationGuide;
use Reloday\Gms\Models\ObjectAvatar;

class RelocationGuideController extends BaseController
{
    /** Find all guides from relocation uuid
     * @param $uuid
     * @return mixed
     */
    public function getRelocationGuidesAction($uuid){
        $this->view->disable();
        $this->checkAjax('GET');
        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];
        $relocation = Relocation::findFirstByUuid($uuid);
        if (!$uuid || !$relocation){
            goto end_of_function;
        }

        $relocationGuides = $relocation->getRelocationGuides();

        $data = [];
        $excepted_guide_ids = [];
        if (count($relocationGuides) > 0){
            foreach ($relocationGuides as $relocationGuide){
                $guide = $relocationGuide->getGuide()->toArray();
                $excepted_guide_ids[] = $guide['id'];
                $guide['relocation_guide_uuid'] = $relocationGuide->getUuid();
                $guide['relocation_id'] = $relocationGuide->getRelocationId();
                $guide['content'] = $relocationGuide->getGuide()->getContent();
                $logo = ObjectAvatar::__getLogo($guide['uuid']);
                $guide['logo'] = $logo ? $logo['image_data']['url_thumb'] : null;

                $guide['country_name'] = $relocationGuide->getGuide()->getCountry() ? $relocationGuide->getGuide()->getCountry()->getName() : null;
                $guide['hr_company_name'] = $relocationGuide->getGuide()->getHrCompany() ? $relocationGuide->getGuide()->getHrCompany()->getName() : null;
                $data[] = $guide;
            }
        }

        $guideAvailable = Guide::__findGuideForRelocationWithFilters([
            'excepted_guide_ids' => $excepted_guide_ids,
            'gms_company_id' => $relocation->getCreatorCompanyId(),
            'hr_company_id' => $relocation->getHrCompanyId(),
            'destination_country_id' => $relocation->getAssignment()->getAssignmentDestination() ? $relocation->getAssignment()->getAssignmentDestination()->getDestinationCountryId() : null,
            'destination_city' => $relocation->getAssignment()->getAssignmentDestination() ? $relocation->getAssignment()->getAssignmentDestination()->getDestinationCity() : null,
        ]);

        $return['success'] = true;
        $return['message'] = "RELOCATION_GUIDES_SUCCESS_TEXT";
        $return['guide_status'] = $relocation->checkActivateGuide();
        $return['guideAvailable'] = count($guideAvailable['data']);
        $return['data'] = $data;

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /** Find suggested guides from relocation uuid
     * @param $uuid
     * @return mixed
     */
    public function getSuggestedGuidesAction($uuid){
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('manage_assignee_experience', 'relocation');
        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];
        $relocation = Relocation::findFirstByUuid($uuid);
        if (!$uuid || !$relocation){
            goto end_of_function;
        }

        $return = Guide::__findGuideForRelocationWithFilters([
            'destination_country_id' => $relocation->getAssignment()->getAssignmentDestination()->getDestinationCountryId(),
            'destination_city' => $relocation->getAssignment()->getAssignmentDestination()->getDestinationCity(),
            'gms_company_id' => $relocation->getCreatorCompanyId(),
            'hr_company_id' => $relocation->getHrCompanyId()
        ]);

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /** Find suggested guides from relocation uuid
     * @param $uuid
     * @return mixed
     */
    public function getMoreGuidesAction(){
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAcl('manage_assignee_experience', 'relocation');
        $return = ['success' => false, 'data' => [], 'message' => 'RELOCATION_NOT_FOUND_TEXT'];

        $uuid = Helpers::__getRequestValue('uuid');
        $guides = Helpers::__getRequestValue('guides');

        $relocation = Relocation::findFirstByUuid($uuid);
        if (!$uuid || !$relocation){
            goto end_of_function;
        }

        if(count($guides) == 0){
            goto end_of_function;
        }

        $excepted_guide_ids = [];
        foreach ($guides as $guide){
            $excepted_guide_ids[] = $guide->id;
        }

        $return = Guide::__findGuideForRelocationWithFilters([
            'excepted_guide_ids' => $excepted_guide_ids,
            'gms_company_id' => $relocation->getCreatorCompanyId(),
            'hr_company_id' => $relocation->getHrCompanyId(),
            'destination_country_id' => $relocation->getAssignment()->getAssignmentDestination()->getDestinationCountryId(),
            'destination_city' => $relocation->getAssignment()->getAssignmentDestination()->getDestinationCity(),
        ]);

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /** Create one or many relocation guide for relocation
     * @return mixed
     */
    public function addGuidesToRelocationAction(){
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAcl('manage_assignee_experience', 'relocation');

        $guide_ids = Helpers::__getRequestValue('guide_ids');
        $relocation_uuid = Helpers::__getRequestValue('relocation_uuid');

        $return = ['success' => false, 'message' => 'DATA_SAVE_FAIL_TEXT'];

        if (count($guide_ids) < 0){
            $return['message'] = 'NO_GUIDE_SELECTED_TEXT';
            goto end_of_function;
        }

        if (!$relocation_uuid){
            $return['message'] = 'PARAMS_NOT_FOUND_TEXT';
            goto end_of_function;
        }

        $relocation = Relocation::findFirstByUuid($relocation_uuid);
        if (!$relocation){
            $return['message'] = 'PARAMS_NOT_FOUND_TEXT';
            goto end_of_function;
        }
        $this->db->begin();

        foreach ($guide_ids as $guide_id){
            $relocationGuide = RelocationGuide::__addRelocationGuide($relocation->getId(), $guide_id, $relocation->getEmployeeId());
            if ($relocationGuide['success'] == false){
                $return = $relocationGuide;
                $return['message'] = 'DATA_SAVE_FAIL_TEXT';
                $this->db->rollback();
                goto end_of_function;
            }
        }

        if ($relocation->checkActivateGuide() == false){
            $relocation->setIsActivateGuide(Relocation::ACTIVATE_GUIDE);
            $resultSave = $relocation->__quickSave();
            if ($resultSave['success'] == false){
                $this->db->rollback();
                $return = $resultSave;
                $return['message'] = 'DATA_SAVE_FAIL_TEXT';
            }else{
                $relocation = $resultSave['data'];
            }
        }

        $this->db->commit();
        $return['success'] = true;
        $return['message'] = "DATA_SAVE_SUCCESS_TEXT";
        $return['relocation'] = $relocation;
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /** Delete one guide based on uuid
     * @return mixed
     */
    public function deleteRelocationGuideAction(){
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAcl('manage_assignee_experience', 'relocation');

        $uuid = Helpers::__getRequestValue('uuid');
        $relocationId = Helpers::__getRequestValue('relocationId');

        $return = ['success' => false, 'message' => 'DATA_DELETE_FAIL_TEXT'];

        if (!$uuid || !$relocationId){
            $return['message'] = 'DATA_NOT_FOUND_TEXT';
            goto end_of_function;
        }

        $relocationGuide = RelocationGuide::findFirstByUuid($uuid);
        if (!$relocationGuide){
            $return['message'] = 'DATA_NOT_FOUND_TEXT';
            goto end_of_function;
        }
        $return = $relocationGuide->__quickRemove();
        if($return['success'] == false){
            goto end_of_function;
        }

        $relocationGuides = RelocationGuide::find([
            'conditions' => 'relocation_id = :relocation_id:',
            'bind'       => [
                'relocation_id' => $relocationId,
            ]
        ]);

        $relocation = Relocation::findFirstById($relocationId);
        if (count($relocationGuides) == 0){
            $relocation->setIsActivateGuide(Relocation::DEACTIVATE_GUIDE);
            $relocation->save();
            $return['guide_status'] = false;
        }else{
            $return['guide_status'] = true;
        }

        $return['relocation'] = $relocation;
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /** Delete guides and change relocation deactivate guide
     * @param $relocation_uuid
     * @return mixed
     */
    public function deactivateRelocationGuideAction($relocation_uuid){
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('manage_assignee_experience', 'relocation');

        $return = ['success' => false, 'message' => 'DEACTIVATE_RELOCATION_GUIDE_FAIL_TEXT'];
        $relocation = Relocation::findFirstByUuid($relocation_uuid);
        $this->db->begin();

        if (!$relocation->checkActivateGuide()){
            $this->db->rollback();
            $return['message'] = 'DEACTIVATE_RELOCATION_GUIDE_FAIL_TEXT';
            goto end_of_function;
        }

        $relocation->setIsActivateGuide(Relocation::DEACTIVATE_GUIDE);
        $relocation->save();

        $return = RelocationGuide::__removeAllRelocationGuide($relocation->getId());
        if ($return['success'] == false){
            $this->db->rollback();
            $return['message'] = 'DEACTIVATE_RELOCATION_GUIDE_FAIL_TEXT';
            goto end_of_function;
        }
        $this->db->commit();
        $return['success'] = true;
        $return['guide_status'] = false;
        $return['message'] = 'DEACTIVATE_RELOCATION_GUIDE_SUCCESS_TEXT';
        $return['relocation'] = $relocation;
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
