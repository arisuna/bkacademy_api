<?php
/**
 * Created by PhpStorm.
 * User: nguyenthuy
 * Date: 7/11/18
 * Time: 5:41 PM
 */

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\MediaAttachment;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\TaskTemplate;
use Reloday\Gms\Models\TaskTemplateChecklist;
use Reloday\Gms\Models\TaskWorkflow;
use Reloday\Gms\Models\Workflow;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/workflow")
 */
class WorkflowController extends BaseController
{

    /**
     * @Route("/service", paths={module="gms"}, methods={"GET"}, name="gms-service-index")
     */
    public function indexAction($type)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', $this->router->getControllerName());
        $workflows = Workflow::getListOfMyCompany($type);

        $this->response->setJsonContent([
            'success' => true,
            'data' => $workflows,
        ]);
        $this->response->send();

    }

    public function searchAction(){
        $this->view->disable();
        $this->checkAjaxPutGet();
        $this->checkAclIndex();

        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');
        $params['type'] = Helpers::__getRequestValue('type');

        $params['limit'] = Helpers::__getRequestValue('limit');;
        $params['length'] = Helpers::__getRequestValue('length');
        $params['start'] = Helpers::__getRequestValue('start');
        $params['simple'] = Helpers::__getRequestValue('simple');
        $params['active'] = Helpers::__getRequestValue('active');
        $params['page'] = Helpers::__getRequestValue('page');
        /****** destination ****/

        /****** origin ****/
        $order = Helpers::__getRequestValueAsArray('sort');
        $ordersConfig = Helpers::__getApiOrderConfig([$order]);

        $return =  Workflow::__findWithFilter($params, $ordersConfig);

        if ($return['success'] == true) {
            $return['recordsFiltered'] = ($return['total_items']);
            $return['recordsTotal'] = ($return['total_items']);
            $return['length'] = ($return['total_items']);
            $return['draw'] = Helpers::__getRequestValue('draw');
        }

        $return['params'] = $params;
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * load detail of workflow
     * @method GET
     * @route /workflow/detail
     * @param int $id
     */
    public function detailAction($id = 0)
    {
        $this->view->disable();
        $this->checkAjax(['GET', 'POST']);
        $this->checkAclIndex();

        $id = $id ? $id : $this->request->get('id');
        $workflow = Workflow::findFirst($id ? $id : 0);

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT',
        ];

        $params = [];
        $params['object_uuid'] = $workflow->getUuid();
        $params['has_file'] = Helpers::__getRequestValue('has_file');
        $params['query'] = Helpers::__getRequestValue('query');

        if ($workflow && $workflow->belongsToGms()) {
            $workflowArray = $workflow->toArray();
            $params['task_type'] = TaskTemplate::IS_NORMAL_TASK;
            $taskTemplates =  TaskTemplate::__findWithFilters($params);
            $workflowArray['task_list'] = $taskTemplates['success'] ? $taskTemplates['data'] : [];

            $params['task_type'] = TaskTemplate::IS_ASSIGNEE_TASK;
            $assigneeTaskTemplates =  TaskTemplate::__findWithFilters($params);
            $workflowArray['assignee_task_list'] = $assigneeTaskTemplates['success'] ? $assigneeTaskTemplates['data'] : [];

            $return = [
                'success' => true,
                'message' => 'LOAD_DETAIL_SUCCESS_TEXT',
                'data' => $workflowArray
            ];
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * load detail of workflow
     * @method GET
     * @route /workflow/detail
     * @param int $id
     */
    public function getTaskListAction($uuid = 0)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex();

        $taskWorkflow = TaskTemplate::findFirstByUuid($uuid);

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT',
        ];

        if ($taskWorkflow && $taskWorkflow->belongsToGms()) {

            $return = [
                'success' => true,
                'message' => 'LOAD_DETAIL_SUCCESS_TEXT',
                'data' => $taskWorkflow->getTaskTemplateChecklists()
            ];
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();

        $workflowItem = Helpers::__getRequestValuesArray();
        $workflow = new Workflow();
        $workflowItem['company_id'] = ModuleModel::$company->getId();

        $workflow->setData($workflowItem);
        $workflow->setStatus(Workflow::STATUS_ACTIVE);
        $result = $workflow->__quickCreate();

        if ($result['success'] == false) {
            if (isset($result['detail']) && ($result['detail'][0] == "SHORTNAME_UNIQUE_TEXT" || $result['detail'][0] == "NAME_UNIQUE_TEXT"))
                $result['message'] = $result['detail'][0];
            else {
                $result['message'] = 'SAVE_WORKFLOW_FAIL_TEXT';
                if (isset($result['errorMessage']) && is_array($result['errorMessage'])) {
                    $result['message'] = reset($result['errorMessage']);
                }
            }
        } else {
            $result['message'] = 'SAVE_WORKFLOW_SUCCESS_TEXT';
        }

        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function removeAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclDelete();

        $return = [
            'success' => false,
            'message' => 'WORKFLOW_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $workflow = Workflow::findFirstByUuid($uuid);
            if ($workflow instanceof Workflow && $workflow->belongsToGms()) {
                $return = $workflow->__quickRemove();
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * Save service action
     */
    public function editAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclUpdate();

        $uuid = Helpers::__getRequestValue("uuid");

        $return = [
            'success' => false,
            'message' => 'WORKFLOW_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $workflow = Workflow::findFirstByUuid($uuid);
            if ($workflow instanceof Workflow && $workflow->belongsToGms()) {
                $serviceReturn = $workflow->__update(Helpers::__getRequestValuesArray());
                if ($serviceReturn['success'] == true) {
                    $return = [
                        'success' => true,
                        'data' => $workflow,
                        'message' => 'SAVE_WORKFLOW_SUCCESS_TEXT',
                    ];
                } else {
                    $return = [
                        'success' => false,
                        'message' => 'SAVE_WORKFLOW_FAIL_TEXT',
                    ];
                    if (isset($serviceReturn['errorMessage']) && is_array($serviceReturn['errorMessage'])) {
                        $return['message'] = reset($serviceReturn['errorMessage']);
                    }
                }
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function saveAttachementsAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclUpdate();

        $uuid = Helpers::__getRequestValue("workflow_uuid");

        $return = ['success' => false, 'message' => 'WORKFLOW_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $workflow = Workflow::findFirstByUuid($uuid);
            if ($workflow instanceof Workflow && $workflow->belongsToGms()) {
                $attachments = Helpers::__getRequestValue('attachments');
                $return = ['success' => true];
                if (is_array($attachments) && count($attachments)) {

                    $return = MediaAttachment::__createAttachments([
                        'objectUuid' => $uuid,
                        'fileList' => $attachments,
                    ]);

                    if ($return['success'] == true) {
                        $return['message'] = 'SAVE_WORKFLOW_SUCCESS_TEXT';
                    } else {
                        $return['message'] = 'SAVE_WORKFLOW_FAIL_TEXT';
                    }
                }
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();

    }

    /**
     * @return mixed
     */
    public function createTaskWorkflowAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclUpdate();

        $uuid = Helpers::__getRequestValue("object_uuid");

        $return = ['success' => false, 'message' => 'WORKFLOW_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $workflow = Workflow::findFirstByUuid($uuid);

            if ($workflow instanceof Workflow && $workflow->belongsToGms()) {

                $taskItem = Helpers::__getRequestValuesArray();
                $task = new TaskWorkflow();
                $taskItem['object_uuid'] = $uuid;
                $taskItem['link_type'] = TaskWorkflow::WORKFLOW;
                $task->setData($taskItem);
                $resultTask = $task->__quickCreate();

                if ($resultTask['success'] == false) {
                    $return = $resultTask;
                    $return['message'] = 'TASK_WORKFLOW_FAIL_TEXT';
                    goto end_of_function;
                }
                $return = [
                    'success' => true,
                    'data' => $task,
                    'input' => $taskItem,
                    'message' => 'TASK_WORKFLOW_SUCCESS_TEXT',
                ];
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function editTaskWorkflowAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclUpdate();

        $uuid = Helpers::__getRequestValue("object_uuid");

        $return = ['success' => false, 'message' => 'WORKFLOW_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $workflow = Workflow::findFirstByUuid($uuid);

            if ($workflow instanceof Workflow && $workflow->belongsToGms()) {

                $taskItem = Helpers::__getRequestValuesArray();

                $task = TaskWorkflow::findFirstById($taskItem['id']);

                if ($task) {

                    if($task->getObjectUuid() != $uuid) {
                        $return = [
                            'success' => false,
                            'message' => 'TASK_WORKFLOW_FAIL_TEXT',
                        ];
                        goto end_of_function;
                    }

                    $task->setData($taskItem);
                    $resultTask = $task->__quickUpdate();

                    if ($resultTask['success'] == true) {
                        $return = $resultTask;
                        $return['message'] = 'TASK_WORKFLOW_FAIL_TEXT';
                        goto end_of_function;
                    }

                    $return = [
                        'success' => true,
                        'data' => $task,
                        'message' => 'TASK_WORKFLOW_SUCCESS_TEXT',
                    ];
                }
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function sortTaskWorkflowAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclUpdate();

        $itemIds = Helpers::__getRequestValue('itemIds');

        $return = ['success' => false, 'message' => 'WORKFLOW_NOT_FOUND_TEXT'];

        $i = 1;
        $this->db->begin();
        foreach($itemIds as $id){
            $taskWorkflow = TaskWorkflow::findFirstById($id);
            if($taskWorkflow instanceof TaskWorkflow){
                $taskWorkflow->setSequence($i);
                $resultUpdate = $taskWorkflow->__quickUpdate();

                if(!$resultUpdate['success']){
                    $return = $resultUpdate;
                    $this->db->rollback();
                    goto  end_of_function;
                }
                $i++;
            }
        }

        $return['success'] = true;
        $return['message'] = 'ORDER_SUCCESSFULLY_TEXT';
        $this->db->commit();

        end_of_function:

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    public function removeTaskTemplateWorkflowAction($uuid = ''){
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclUpdate();

        $return = ['success' => false, 'message' => 'TASK_TEMPLATE_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $task = TaskTemplate::findFirstByUuid($uuid);
            if ($task instanceof TaskTemplate) {
                $resultDelete = $task->__quickRemove();
                if ($resultDelete['success']) {
                    $return = [
                        'success' => true,
                        'message' => 'TASK_DELETE_SUCCESS_TEXT',
                    ];
                } else {
                    $return = [
                        'detail' => $resultDelete,
                        'success' => true,
                        'message' => 'TASK_DELETE_FAIL_TEXT',
                    ];
                }

            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function removeTaskWorkflowAction($uuid)
    {

        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclUpdate();

        $return = ['success' => false, 'message' => 'TASK_WORKFLOW_NOT_FOUND_TEXT'];


        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $task = TaskWorkflow::findFirstByUuid($uuid);

            if ($task instanceof TaskWorkflow && $task->belongsToGms()) {
                $resultDelete = $task->__quickRemove();
                if ($resultDelete['success']) {
                    $return = [
                        'success' => true,
                        'message' => 'TASK_WORKFLOW_DELETE_SUCCESS_TEXT',
                    ];
                } else {
                    $return = [
                        'detail' => $resultDelete,
                        'success' => true,
                        'message' => 'TASK_WORKFLOW_DELETE_FAIL_TEXT',
                    ];
                }
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function createSubTaskAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclUpdate();

        $uuid = Helpers::__getRequestValue("object_uuid");
        $name = Helpers::__getRequestValue("name");
        if (!$name || $name == "") {
            $return = ['success' => false, 'message' => 'NAME_REQUIRED_TEXT'];
            goto end_of_function;
        }
        $return = ['success' => false, 'message' => 'TASK_WORKFLOW_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $task_workflow = TaskTemplate::findFirstByUuid($uuid);

            if ($task_workflow instanceof TaskTemplate && $task_workflow->belongsToGms()) {

                $taskItem = Helpers::__getRequestValuesArray();
                $task = new TaskTemplateChecklist();
                $taskItem['object_uuid'] = $uuid;
                $task->setData($taskItem);
                $resultTask = $task->__quickCreate();

                if ($resultTask['success'] == false) {
                    $return = $resultTask;
                    $return['message'] = 'TASK_WORKFLOW_FAIL_TEXT';
                    goto end_of_function;
                }
                $return = [
                    'success' => true,
                    'data' => $task,
                    'message' => 'TASK_WORKFLOW_SUCCESS_TEXT',
                ];
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function editSubTaskAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclUpdate();

        $parent_uuid = Helpers::__getRequestValue("object_uuid");
        $name = Helpers::__getRequestValue("name");
        if (!$name || $name == "") {
            $return = ['success' => false, 'message' => 'NAME_REQUIRED_TEXT'];
            goto end_of_function;
        }
        $return = ['success' => false, 'message' => 'TASK_WORKFLOW_NOT_FOUND_TEXT'];

        if ($parent_uuid != '' && Helpers::__isValidUuid($parent_uuid)) {
            $task_workflow = TaskTemplate::findFirstByUuid($parent_uuid);

            if ($task_workflow instanceof TaskTemplate && $task_workflow->belongsToGms()) {

                $taskItem = Helpers::__getRequestValuesArray();

                $task = TaskTemplateChecklist::findFirstById($taskItem['id']);

                if ($task && $task->getObjectUuid() != $parent_uuid) {
                    $return = [
                        'success' => false,
                        'message' => 'TASK_WORKFLOW_FAIL_TEXT',
                    ];
                    goto end_of_function;
                }

                $task->setData($taskItem);
                $resultTask = $task->__quickUpdate();

                if ($resultTask['success'] == true) {
                    $return = $resultTask;
                    $return['message'] = 'TASK_WORKFLOW_FAIL_TEXT';
                    goto end_of_function;
                }

                $return = [
                    'success' => true,
                    'data' => $task,
                    'message' => 'TASK_WORKFLOW_SUCCESS_TEXT',
                ];
            }
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function removeSubTaskAction($uuid)
    {

        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclUpdate();

        $return = ['success' => false, 'message' => 'TASK_WORKFLOW_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $checkList = TaskTemplateChecklist::findFirstByUuid($uuid);
            if ($checkList instanceof TaskTemplateChecklist) {
                $parentTask = TaskTemplate::findFirstByUuid($checkList->getObjectUuid());
                if ($parentTask instanceof TaskTemplate && $parentTask->belongsToGms()) {
                    $resultDelete = $checkList->__quickRemove();
                    if ($resultDelete['success']) {
                        $return = [
                            'success' => true,
                            'message' => 'TASK_WORKFLOW_DELETE_SUCCESS_TEXT',
                        ];
                    } else {
                        $return = [
                            'detail' => $resultDelete,
                            'success' => true,
                            'message' => 'TASK_WORKFLOW_DELETE_FAIL_TEXT',
                        ];
                    }

                }
            }

        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

}
