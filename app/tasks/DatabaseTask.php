<?php
/**
 * Created by PhpStorm.
 * User: anmeo
 * Date: 10/11/16
 * Time: 4:54 PM
 */

use \Phalcon\Cli\Task;
use \Email\Parse;
use \Phalcon\Mvc\Model\MetaData;
use \Phalcon\Db\Column as Column;

class DatabaseTask extends ModuleTask
{
    static $types = [
        Column::TYPE_INTEGER => 'int',
        Column::TYPE_VARCHAR => 'varchar',
        Column::TYPE_DATETIME => 'datetime',
        Column::TYPE_TIMESTAMP => 'timestamp',
        Column::TYPE_TEXT => 'text',
        Column::TYPE_FLOAT => 'float',
        Column::TYPE_DECIMAL => 'float',
        Column::TYPE_DATE => 'date',
        Column::TYPE_BOOLEAN => 'tinyint',
        Column::TYPE_DOUBLE => 'double',
        Column::TYPE_CHAR => 'char',
        Column::TYPE_TINYBLOB => 'tinyblob',
        Column::TYPE_BLOB => 'blob',
        Column::TYPE_MEDIUMBLOB => 'mediumblob',
        Column::TYPE_LONGBLOB => 'longblob',
        Column::TYPE_BIGINTEGER => 'biginteger',
        Column::TYPE_JSON => 'json',
    ];

    static $foreignKeys = [
        'acl_id' => [
            'table' => 'acl', 'field' => 'id', 'relation' => '>-'],
        'related_acl_id' => [
            'table' => 'acl', 'field' => 'id', 'relation' => '>-'],
        'origin_acl_id' => [
            'table' => 'acl', 'field' => 'id', 'relation' => '>-'],

        'allowance_type_id' => [
            'table' => 'allowance_type', 'field' => 'id', 'relation' => '>-'],

        'allowance_type_default_id' => [
            'table' => 'allowance_type_default', 'field' => 'id', 'relation' => '>-'],

        'app_setting_default_id' => [
            'table' => 'app_setting_default', 'field' => 'id', 'relation' => '>-'],

        'app_setting_group_default_id' => [
            'table' => 'app_setting_group_default', 'field' => 'id', 'relation' => '>-'],

        'booker_company_id' => [
            'table' => 'company', 'field' => 'id', 'relation' => '>-'],

        'assignment_type_id' => [
            'table' => 'assignment_type', 'field' => 'id', 'relation' => '>-'],

        'policy_id' => [
            'table' => 'policy', 'field' => 'id', 'relation' => '>-'],

        'home_country_id' => [
            'table' => 'country', 'field' => 'id', 'relation' => '>-'],


        'destination_country_id' => [
            'table' => 'country', 'field' => 'id', 'relation' => '>-'],

        'hr_assignment_owner_id' => [
            'table' => 'user_id', 'field' => 'id', 'relation' => '>-'],


        'departure_hr_office_id' => [
            'table' => 'office', 'field' => 'id', 'relation' => '>-'],

        'hr_user_id' => [
            'table' => 'user', 'field' => 'id', 'relation' => '>-'],

        'assignment_cost_id' => [
            'table' => 'assignment_cost', 'field' => 'id', 'relation' => '>-'],

        'dependant_id' => [
            'table' => 'dependant', 'field' => 'id', 'relation' => '>-'],

        'destination_hr_office_id' => [
            'table' => 'office', 'field' => 'id', 'relation' => '>-'],

        'assignment_id' => [
            'table' => 'assignment', 'field' => 'id', 'relation' => '>-'],

        'service_set_company_id' => [
            'table' => 'service_company', 'field' => 'id', 'relation' => '>-'],

        'employee_id' => [
            'table' => 'employee', 'field' => 'id', 'relation' => '>-'],

        'contract_id' => [
            'table' => 'contract', 'field' => 'id', 'relation' => '>-'],

        'user_id' => [
            'table' => 'user', 'field' => 'id', 'relation' => '>-'],

        'attributes_value_id' => [
            'table' => 'attributes_value', 'field' => 'id', 'relation' => '>-'],

        'communication_topic_id' => [
            'table' => 'communication_topic', 'field' => 'id', 'relation' => '>-'],

        'sender_user_id' => [
            'table' => 'user_id', 'field' => 'id', 'relation' => '>-'],

        'employee_company_id' => [
            'table' => 'company_id', 'field' => 'id', 'relation' => '>-'],

        'task_uuid' => [
            'table' => 'task', 'field' => 'uuid', 'relation' => '>-'],


        'contact_id' => [
            'table' => 'contact', 'field' => 'id', 'relation' => '>-'],

        'topic_id' => [
            'table' => 'communication_topic', 'field' => 'id', 'relation' => '>-'],


        'main_topic_id' => [
            'table' => 'communication_topic', 'field' => 'id', 'relation' => '>-'],


        'company_type_id' => [
            'table' => 'company_type', 'field' => 'id', 'relation' => '>-'],

        'holding_id' => [
            'table' => 'holding', 'field' => 'id', 'relation' => '>-'],

        'app_id' => [
            'table' => 'app', 'field' => 'id', 'relation' => '>-'],

        'created_by_company_id' => [
            'table' => 'company', 'field' => 'id', 'relation' => '>-'],

        'timezone_id' => [
            'table' => 'timezone', 'field' => 'id', 'relation' => '>-'],

        'company_uuid' => [
            'table' => 'company', 'field' => 'uuid', 'relation' => '>-'],

        'company_related_id' => [
            'table' => 'company', 'field' => 'id', 'relation' => '>-'],

        'company_pricelist_id' => [
            'table' => 'company_pricelist', 'field' => 'id', 'relation' => '>-'],

        'service_price_id' => [
            'table' => 'service_price', 'field' => 'id', 'relation' => '>-'],

        'company_setting_default_id' => [
            'table' => 'company_setting_default', 'field' => 'id', 'relation' => '>-'],

        'company_setting_group_id' => [
            'table' => 'company_setting_group', 'field' => 'id', 'relation' => '>-'],

        'constant_id' => [
            'table' => 'constant', 'field' => 'id', 'relation' => '>-'],

        'creator_user_id' => [
            'table' => 'user_id', 'field' => 'id', 'relation' => '>-'],

        'from_company_id' => [
            'table' => 'company', 'field' => 'id', 'relation' => '>-'],

        'ticket_id' => [
            'table' => 'customer_support_tickets', 'field' => 'id', 'relation' => '>-'],

        'office_id' => [
            'table' => 'office', 'field' => 'id', 'relation' => '>-'],


        'head_employee_id' => [
            'table' => 'employee_id', 'field' => 'id', 'relation' => '>-'],

        'member_type_id' => [
            'table' => 'member_type', 'field' => 'id', 'relation' => '>-'],

        'birth_country_id' => [
            'table' => 'country', 'field' => 'id', 'relation' => '>-'],


        'support_contact_user_id' => [
            'table' => 'user', 'field' => 'id', 'relation' => '>-'],


        'buddy_contact_user_id' => [
            'table' => 'user', 'field' => 'id', 'relation' => '>-'],

        'employee_uuid' => [
            'table' => 'employee', 'field' => 'uuid', 'relation' => '>-'],


        'faq_category_id' => [
            'table' => 'faq_category', 'field' => 'id', 'relation' => '>-'],

        'owner_user_id' => [
            'table' => 'user', 'field' => 'id', 'relation' => '>-'],

        'creator_user_id' => [
            'table' => 'user', 'field' => 'id', 'relation' => '>-'],

        'faq_content_id' => [
            'table' => 'faq_content', 'field' => 'id', 'relation' => '>-'],

        'email_template_default_id' => [
            'table' => 'email_template_default', 'field' => 'id', 'relation' => '>-'],

        'gms_company_id' => [
            'table' => 'company', 'field' => 'id', 'relation' => '>-'],

        'starter_company_id' => [
            'table' => 'company', 'field' => 'id', 'relation' => '>-'],

        'property_id' => [
            'table' => 'property', 'field' => 'id', 'relation' => '>-'],

        'property_uuid' => [
            'table' => 'property', 'field' => 'uuid', 'relation' => '>-'],

        'invoice_quote_id' => [
            'table' => 'invoice_quote', 'field' => 'id', 'relation' => '>-'],

        'owner_company_id' => [
            'table' => 'company', 'field' => 'id', 'relation' => '>-'],

        'company_id' => [
            'table' => 'company', 'field' => 'id', 'relation' => '>-'],

        'tax_rule_id' => [
            'table' => 'tax_rule', 'field' => 'id', 'relation' => '>-'],

        'service_pricing_id' => [
            'table' => 'service_pricing', 'field' => 'id', 'relation' => '>-'],

        'user_uuid' => [
            'table' => 'user', 'field' => 'uuid', 'relation' => '>-'],


        'media_type_id' => [
            'table' => 'media_type', 'field' => 'id', 'relation' => '>-'],

        'user_login_id' => [
            'table' => 'user_login', 'field' => 'id', 'relation' => '>-'],

        'media_uuid' => [
            'table' => 'media', 'field' => 'uuid', 'relation' => '>-'],


        'shared_employee_uuid' => [
            'table' => 'employee', 'field' => 'id', 'relation' => '>-'],


        'media_id' => [
            'table' => 'media', 'field' => 'id', 'relation' => '>-'],
        'media_folder_id' => [
            'table' => 'media_folder', 'field' => 'id', 'relation' => '>-'],
        'menu_id' => [
            'table' => 'menu', 'field' => 'id', 'relation' => '>-'],
        'move_quote_id' => [
            'table' => 'move_quote', 'field' => 'id', 'relation' => '>-'],
        'move_quote_type_id' => [
            'table' => 'move_quote_type', 'field' => 'id', 'relation' => '>-'],
        'need_form_field_id' => [
            'table' => 'need_form_field', 'field' => 'id', 'relation' => '>-'],
        'need_request_id' => [
            'table' => 'need_request', 'field' => 'id', 'relation' => '>-'],
        'need_form_category_id' => [
            'table' => 'need_form_category', 'field' => 'id', 'relation' => '>-'],
        'need_form_gabarit_id' => [
            'table' => 'need_form_gabarit', 'field' => 'id', 'relation' => '>-'],
        'relocation_id' => [
            'table' => 'relocation', 'field' => 'id', 'relation' => '>-'],
        'relocation_service_company_id' => [
            'table' => 'relocation_service_company', 'field' => 'id', 'relation' => '>-'],
        'need_form_request_id' => [
            'table' => 'need_form_request', 'field' => 'id', 'relation' => '>-'],
        'need_form_gabarit_item_id' => [
            'table' => 'need_form_gabarit_item', 'field' => 'id', 'relation' => '>-'],
        'head_user_id' => [
            'table' => 'user', 'field' => 'id', 'relation' => '>-'],
        'prepayroll_dataset_id' => [
            'table' => 'prepayroll_dataset', 'field' => 'id', 'relation' => '>-'],
        'allowance_title_id' => [
            'table' => 'allowance_title_id', 'field' => 'id', 'relation' => '>-'],
        'agent_svp_id' => [
            'table' => 'service_company', 'field' => 'id', 'relation' => '>-'],
        'landlord_svp_id' => [
            'table' => 'service_company', 'field' => 'id', 'relation' => '>-'],
        'last_user_login_id' => [
            'table' => 'user_login', 'field' => 'id', 'relation' => '>-'],
        'hr_company_id' => [
            'table' => 'company', 'field' => 'id', 'relation' => '>-'],
        'creator_company_id' => [
            'table' => 'company', 'field' => 'id', 'relation' => '>-'],
        'reminder_config_id' => [
            'table' => 'reminder_config', 'field' => 'id', 'relation' => '>-'],

        'service_provider_company_id' => [
            'table' => 'service_provider_company', 'field' => 'id', 'relation' => '>-'],

        'service_provider_type_id' => [
            'table' => 'service_provider_type', 'field' => 'id', 'relation' => '>-'],

        'service_field_type_id' => [
            'table' => 'service_field_type', 'field' => 'id', 'relation' => '>-'],

        'attributes_id' => [
            'table' => 'attributes', 'field' => 'id', 'relation' => '>-'],

        'service_field_group_id' => [
            'table' => 'service_field_group', 'field' => 'id', 'relation' => '>-'],

        'service_id' => [
            'table' => 'service', 'field' => 'id', 'relation' => '>-'],

        'service_field_id' => [
            'table' => 'service_field', 'field' => 'id', 'relation' => '>-'],

        'account_manager_user_id' => [
            'table' => 'user', 'field' => 'id', 'relation' => '>-'],

        'head_office_user_id' => [
            'table' => 'user', 'field' => 'id', 'relation' => '>-'],

        'creditor_country_id' => [
            'table' => 'country_id', 'field' => 'id', 'relation' => '>-'],

        'country_id' => [
            'table' => 'country', 'field' => 'id', 'relation' => '>-'],

        'parent_task_id' => [
            'table' => 'task', 'field' => 'id', 'relation' => '>-'],

        'task_template_company_id' => [
            'table' => 'task_template_company', 'field' => 'id', 'relation' => '>-'],

        'owner_user_id' => [
            'table' => 'user', 'field' => 'id', 'relation' => '>-'],

        'service_event_id' => [
            'table' => 'service_event', 'field' => 'id', 'relation' => '>-'],

        'service_company_id' => [
            'table' => 'service_company', 'field' => 'id', 'relation' => '>-'],

        'reminder_service_event_id' => [
            'table' => 'service_event', 'field' => 'id', 'relation' => '>-'],

        'user_group_id' => [
            'table' => 'user_group', 'field' => 'id', 'relation' => '>-'],

        'acl_id' => [
            'table' => 'acl', 'field' => 'id', 'relation' => '>-'],

        'user_guide_topic_id' => [
            'table' => 'user_guide_topic', 'field' => 'id', 'relation' => '>-'],

        'team_id' => [
            'table' => 'team', 'field' => 'id', 'relation' => '>-'],

        'department_id' => [
            'table' => 'department', 'field' => 'id', 'relation' => '>-'],

        'user_setting_default_id' => [
            'table' => 'user_setting_default', 'field' => 'id', 'relation' => '>-'],

        'user_setting_group_id' => [
            'table' => 'user_setting_group', 'field' => 'id', 'relation' => '>-'],

        'field_type_id' => [
            'table' => 'field_type', 'field' => 'id', 'relation' => '>-'],

        'language' => [
            'table' => 'supported_language', 'field' => 'name', 'relation' => '>-'],

    ];

    static $comments = [
        'id' => 'identify code numeric  of [name]',
        'uuid' => 'uuid of [name]',
        'number' => 'number of [name]',
        'firstname' => 'firstname of [name]',
        'first_name' => 'firstname of [name]',
        'lastname' => 'lastname of [name]',
        'last_name' => 'lastname of [name]',
        'fullname' => 'fullname of [name]',

        'mobilephone' => 'mobile phone number of [name]',
        'phonework' => 'work phone number of [name]',
        'workemail' => 'work email of [name]',
        'phonehome' => 'personal phone of [name]',
        'fax' => 'fax number of [name]',
        'email' => 'email of [name]',
        'title' => 'title of [name]',
        'privateemail' => 'private email of [name]',

        'mobile_phone' => 'mobile phone number of [name]',
        'work_phone' => 'work phone number of [name]',
        'home_phone' => 'personal phone of [name]',
        'private_email' => 'private email of [name]',
        'work_email' => 'work email of [name]',

        'country_id' => 'id of country',
        'team_id' => 'id of team',
        'office_id' => 'id of office',
        'company_id' => 'id of company',
        'assignment_id' => 'id of associated assignment',
        'relocation_id' => 'id of relocation',
        'contract_id' => 'id of contract',

        'created_at' => 'date of creation',
        'updated_at' => 'date of update',
        'birth_date' => 'date of birth',
        'jobtitle' => 'title of job',
        'website' => 'website of [name]',
        'citizenships' => 'list of nationality (in JSON ARRAY FORMAT)',

        'address' => 'address of [name]',
        'address1' => 'address (line 1 ) of [name]',
        'address2' => 'complement address (line 2) of [name]',

        'zipcode' => 'post code of [name]',
        'town' => 'town name or city name of [name]',
        'street' => 'street name or neigbourhood name of [name]',
        'reference' => 'reference number of [name]',
        'employee_id' => 'id of employee attached with [name]',
        'effective_start_date' => 'effective start date',
        'estimated_start_date' => 'estimated start date',
        'estimated_end_date' => 'estimated end date',
        'effective_end_date' => 'effective end date',
        'end_date' => 'effective end date',
        'approval_status' => 'Status of Approbation (1 = Pending, 2 = Approval on going, 3= Approved, 4 = Rejected, -1=Rejected, 5=Terminated)',


        'is_visible' => 'if [name] is visible (1 = Yes, 0 = No)',
        'is_active' => 'if [name] is active (1 = Yes, 0 = No)',
        'is_archived' => 'if [name] is archived (1 = Yes, 0 = No)',
        'is_archive' => 'if [name] is archived (1 = Yes, 0 = No)',
        'is_deleted' => 'if [name] is deleted (1 = Yes, 0 = No)',
        'is_viewed' => 'if [name] is viewed (1 = Yes, 0 = No)',
        'is_applied' => 'if [name] is applied (1 = Yes, 0 = No)',
        'is_shared' => 'if [name] is shared (1 = Yes, 0 = No)',
        'is_private' => 'if [name] is private (1 = Yes, 0 = No)',
        'is_building' => 'if [name] is a building or property appartment (1 = Yes, 0 = No)',
        'is_sent' => 'if [name] is sent (1 = Yes, 0 = No)',
        'is_visited' => 'if [name] is visited (1 = Yes, 0 = No)',
        'is_selected' => 'if [name] is selected (1 = Yes, 0 = No)',
        'is_hosted' => 'if [name] is hosted on AWS S3 (1 = Yes, 0 = No)',
        'is_replied' => 'if [name] is replied  (1 = Yes, 0 = No)',
        'is_paid' => 'if [name] is paid  (1 = Yes, 0 = No)',

        'include_assignee' => 'Assignee is included in [name] (1 = Yes, 0 = No)',


        'applied' => 'if [name] is applied (1 = Yes, 0 = No)',
        'active' => 'if [name] is active or not (1=Active, 0 =Not Active)',
        'status' => 'status of [name] (1=Active,0=Not Active or Draft,-1=Archived)',
        'archived' => 'if [name] is archived or deleted (1=Yes,0=No)',
        'visible' => 'if [name] is visible on Frontend (1=Yes,0=No)',
        'viewed' => 'if [name] is viewed (1 = Yes, 0 = No)',

        'label' => 'label of [name], value from constant table (ex : DISPLAY_TITLE_TEXT)',
        'languagues' => 'list of languages code ( in JSON FORMAT)',
        'description' => 'description',
        'summary_label' => 'summary label of [name], value from constant table (ex : DISPLAY_TITLE_TEXT)',
        'currency' => 'currency code (3 characters), value from currency table (ex: EUR, FRS, SGD, USD)',
        'currency_code' => 'currency code (3 characters), value from currency table (ex: EUR, FRS, SGD, USD)',
        'position' => 'position of [name], ex 1,2,3,4,5..',

        'end_at' => 'date of ending of date of expiration',
        'ended_at' => 'date of ending of date of expiration',
        'expired_at' => 'date of expiration',
        'value' => 'value of [name]',
        'comments' => 'comments of [name]',
        'code' => 'code of [name]',
        'user_login_id' => 'id of user login',
        'user_uuid' => 'uuid of user profile',
        'service_company_id' => 'id of service of company',
        'service_event_id' => 'id of service event',
        'service_id' => 'id of Service',
        'user_id' => 'id of user profile',
        'object_uuid' => 'Uuid of Object, Object can by anyone data in system who has an UUID',
        'data' => 'data of [name], in JSON FORMAT',
        'hash' => 'hash code of [name]',

        'before_after' => 'reminder setting : reminder fired before/after event or datetime (1=before,0=after)',
        'reminder_active' => 'active the reminder creation process / reminder setting : system can create reminder setting (1=Yes,0=No)',
    ];

    /**
     * @return array
     */
    public function getTypeArray()
    {
        return self::$types;
    }

    /**
     * @param $params
     */
    public function describeAction($params)
    {
        //var_dump(self::$types); die();
        $parseParams = $this->parseParams($params);
        $tableName = lcfirst(isset($parseParams['table']) ? $parseParams['table'] : "");
        $tableNames = (isset($parseParams['tables']) ? $parseParams['tables'] : "");
        $tableNameList = array_filter(explode(',', $tableNames));
        $indexOnly = (isset($parseParams['index-only']) ? true : false);


        if ($tableName != "") {
            $columns = $this->parseDataTable($tableName, $indexOnly);
            echo "$tableName \r\n";
            echo "-\r\n";

            foreach ($columns as $column) {
                echo "#" . $column['comments'] . "\r\n";
                unset($column['comments']);
                echo implode(" ", $column) . "\r\n";
            }
        } else {


            if (count($tableNameList) > 0) {
                $tableList = $tableNameList;
            } else {
                $tableList = $this->db->listTables();
            }

            $tableArray = [];
            foreach ($tableList as $tableName) {
                $tableArray[] = [
                    'name' => $tableName,
                    'columns' => $this->parseDataTable($tableName, $indexOnly, $tableList),
                ];
            }
            $file = fopen($this->config->application->originDir . ".configuration/schema/db.txt", "w+");

            foreach ($tableArray as $tableItem) {
                fputs($file, $tableItem['name'] . "\r\n" . "-\r\n");
                foreach ($tableItem['columns'] as $column) {
                    if (isset($column['comments'])) fputs($file, "#" . $column['comments'] . "\r\n");
                    unset($column['comments']);
                    fputs($file, implode(" ", $column) . "\r\n");
                }
                fputs($file, "\r\n");
                fputs($file, "\r\n");
            }
            fputs($file, "\r\n");
            fclose($file);
            echo "done";
        }
    }

    /**
     * @param $tableName
     */
    public function parseDataTable($tableName, $indexOnly = false, $tableList = [])
    {

        $columns = $this->db->describeColumns($tableName); // Table name
        $indexes = $this->db->describeIndexes($tableName);
        $columnArray = [];

        foreach ($columns as $column) {


            $columnArrayItem = [
                'name' => $column->getName(),
                'type' => self::$types[$column->getType()] . ($column->getSize() > 0 ? "(" . $column->getSize() . ")" : ""),
                'null' => ($column->isNotNull() ? "" : "null"),
                'pk' => ($column->isPrimary() ? "pk" : ""),
                'fk' => $this->parseFieldPk($column->getName(), $tableList),
                'comments' => $this->parseFieldToComment($column->getName(), $tableName),
                'index' => "",
            ];

            foreach ($indexes as $index) {
                $indexColumns = $index->getColumns();
                if (isset(array_flip($indexColumns)[$column->getName()]) && count($indexColumns) == 1 && $index->getType() != 'PRIMARY') {
                    $columnArrayItem['index'] = $index->getType();
                }
            }

            if ( $columnArrayItem['index'] == '' ) {
                if ($column->getName() == 'status') $columnArrayItem['index'] = 'INDEX';
                if ($column->getName() == 'type') $columnArrayItem['index'] = 'INDEX';
                if ($column->getName() == 'active') $columnArrayItem['index'] = 'INDEX';
                if ($column->getName() == 'archived') $columnArrayItem['index'] = 'INDEX';
                if ($column->getName() == 'archive') $columnArrayItem['index'] = 'INDEX';
                if ($column->getName() == 'visible') $columnArrayItem['index'] = 'INDEX';
                if ($column->getName() == 'is_visible') $columnArrayItem['index'] = 'INDEX';
                if ($column->getName() == 'is_deleted') $columnArrayItem['index'] = 'INDEX';
                if ($column->getName() == 'is_archived') $columnArrayItem['index'] = 'INDEX';
                if ($column->getName() == 'is_active') $columnArrayItem['index'] = 'INDEX';
                if ($column->getName() == 'is_building') $columnArrayItem['index'] = 'INDEX';
                if ($column->getName() == 'approval_status') $columnArrayItem['index'] = 'INDEX';
                if ($column->getName() == 'number') $columnArrayItem['index'] = 'INDEX';
                if ($column->getName() == 'identify') $columnArrayItem['index'] = 'INDEX';
                if ($column->getName() == 'reference') $columnArrayItem['index'] = 'INDEX';
                if ($column->getName() == 'object_name') $columnArrayItem['index'] = 'INDEX';
            }

            if ($column->isPrimary() == true) {
                $columnArrayItem['index'] = '';
            }


            if ($indexOnly == true) {
                if ($column->isPrimary() == true ||
                    preg_match("#_uuid$#", $column->getName()) ||
                    preg_match("#_id$#", $column->getName()) ||
                    $columnArrayItem['index'] != "" ||
                    $column->getName() == 'name' ||
                    $column->getName() == 'reference' ||
                    $column->getName() == 'number' ||
                    $column->getName() == 'description' ||
                    $column->getName() == 'value' ||
                    $column->getName() == 'language') {

                    $columnArray[] = $columnArrayItem;
                }
            } else {
                $columnArray[] = $columnArrayItem;
            }


        }
        return $columnArray;
    }

    /**
     *
     */
    public function parseFieldToComment($fieldName, $tableName)
    {

        $entityName = str_replace("_", " ", $tableName);
        $fieldNameSplit = str_replace("_", " ", $fieldName);
        $comment = str_replace("[name]", $entityName, isset(self::$comments[$fieldName]) ? self::$comments[$fieldName] : "$fieldNameSplit of [name]");
        return $comment;
    }

    /**
     * @param $fieldName
     * @param $tables
     * @return string
     */
    public function parseFieldPk($fieldName, $tables)
    {
        return
            isset(self::$foreignKeys[$fieldName]) && in_array(self::$foreignKeys[$fieldName]['table'], $tables) ?
                'fk ' . self::$foreignKeys[$fieldName]['relation'] . ' ' . self::$foreignKeys[$fieldName]['table'] . '.' . self::$foreignKeys[$fieldName]['field'] : '';
    }

}