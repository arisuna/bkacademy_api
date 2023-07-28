<?php

namespace SMXD\Application\Lib;

use Aws\Credentials\CredentialProvider;
use Aws\Sqs\Exception\SqsException;
use Phalcon\Http\Client\Exception;
use Phalcon\Queue\Beanstalk;
use Phalcon\Queue\Beanstalk\Extended as BeanstalkExtended;
use Aws\Exception\AwsException;
use Phalcon\Security\Random;

class SMXDQueue
{
    /**
     * queue object
     * @var
     */
    protected $queue;

    /**
     * $queue name
     * @var
     */
    protected $queueName;

    /**
     * $queue url
     * @var
     */
    protected $queueUrl;

    /**
     * $queue arn
     * @var
     */
    protected $queueArn;
    /**
     * config object
     * @var
     */
    protected $config;
    /**
     * config driver
     * @var string
     */
    protected $driver;
    /**
     * @var
     */
    protected $client;
    /**
     * message
     * @var
     */
    protected $message;

    /**
     * @var string
     */
    protected $credentials = '';

    protected $environment = '';

    const ACTION_SEND_MAIL = "sendMail";

    const ACTION_SEND_NOTIFICATION = "sendNotification";

    const ACTION_SEND_NOTIFICATION_TO_ACCOUNT = "sendNotificationToAccount";

    const ACTION_ASSIGNEE_SEND_NOTIFICATION_TO_ACCOUNT = "assigneeSendNotificationToAccount";

    /**
     *   ACTIONS export REPORT
     */
    const ACTION_EXPORT_REPORT_ASSIGNMENT = "exportAssignment";
    const ACTION_EXPORT_REPORT_RELOCATION = "exportRelocation";
    const ACTION_EXPORT_REPORT_RELOCATION_SERVICE = "exportRelocationService";
    const ACTION_EXPORT_REPORT_ASSIGNMENT_HR = "exportAssignmentHr";

    const ACTION_SEND_HISTORY = "sendHistory";
    /**
     *
     */
    const DRIVER_DEFAULT = 'sqs';

    const DELAY_10_SECONDES = 10;

    const DELAY_5_SECONDES = 5;

    const DELAY_1_SECONDES = 1;

    const SIZE_4KB = 4096;

    const SIZE_16KB = 16384;

    const SIZE_64KB = 65535;

    const SIZE_128KB = 131072;

    const SIZE_256KB = 262144;

    const QUEUE_NOT_EXIST_ERROR = 'AWS.SimpleQueueService.NonExistentQueue';


    /**
     * @return SMXDQueue
     */
    public static function __getQueueRelocationService()
    {
        return new self(getenv('QUEUE_RELOCATION_SERVICE'));
    }

    /**
     * @return SMXDQueue
     */
    public static function __getQueueSendMail()
    {
        return new self(getenv('QUEUE_SEND_MAIL'));
    }

    /**
     * @return SMXDQueue
     */
    public static function __getQueueSendNotification()
    {
        return new self(getenv('QUEUE_SEND_NOTIFICATION'));
    }

    /**
     * @return SMXDQueue
     */
    public static function __getQueueSendReminder()
    {
        return new self(getenv('QUEUE_SEND_REMINDER'));
    }

    /**
     * @return SMXDQueue
     */
    public static function __getQueueScheduleRecurrentExpense()
    {
        return new self(getenv('QUEUE_SCHEDULE_EXPENSE'));
    }

    /**
     * @return SMXDQueue
     */
    public static function __getQueueTask()
    {
        return new self(getenv('QUEUE_TASK'));
    }

    /**
     * @return SMXDQueue
     */
    public static function __getQueueRelocation()
    {
        return new self(getenv('QUEUE_RELOCATION'));
    }

    /**
     * @return SMXDQueue
     */
    public static function __getQueueUpdateReminderCounter()
    {
        return new self(getenv('QUEUE_REMINDER_COUNTER'));
    }

    /**
     * @return SMXDQueue
     */
    public static function __getQueueGeneratePdf()
    {
        return new self(getenv('QUEUE_GENERATE_PDF'));
    }

    /**
     * @return SMXDQueue
     */
    public static function __getQueueQuestionnaireServiceField()
    {
        return new self(getenv('QUEUE_QUESTIONNAIRE_SERVICE_FIELD'));
    }

    /**
     * @return SMXDQueue
     */
    public static function __getQueueSendHistory()
    {
        return new self(getenv('QUEUE_SEND_HISTORY'));
    }

    /**
     * @return SMXDQueue
     */
    public static function __getQueueExportReport(): SMXDQueue
    {
        return new self(getenv('QUEUE_EXPORT_REPORT'));
    }

    /**
     * SMXDQueue constructor.
     * @param $queueNameUrl
     * @param null $environment
     */
    public function __construct($queueNameUrl, $environment = null)
    {
        if ($queueNameUrl == '' || $queueNameUrl == null) exit("QUEUE NOT FOUND : $queueNameUrl");

        if (!is_null($environment) && $environment != '') {
            $this->environment = $environment;
        }

        $this->configureSQS($queueNameUrl);
    }

    /**
     * @param $envir
     */
    public function __setEnvironment($envir)
    {
        $this->environment = $envir;
    }

    /**
     * Add content to tube "mailQueue"
     * @param array $data
     * @param string $tube
     * @return array
     */
    public function addQueue($data = [], $tube = '')
    {
        if (!isset($data['action']) || $data['action'] == '') return ['success' => false, 'detail' => 'Action not found'];
        return $this->addQueueSQS($data, $tube);
    }

    /**
     * Add content to tube "mailQueue"
     * @param array $data
     * @param string $tube
     * @return array
     */
    public function sendMail($data = [], $tube = '')
    {
        $data['action'] = self::ACTION_SEND_MAIL;
        return $this->addQueueSQS($data);
    }


    /**
     * @param string $queueNameUrl
     * @return bool
     */
    public function configureSQS($queueNameUrl = '')
    {
        if ($queueNameUrl == "") return false;
        $di = \Phalcon\DI::getDefault();
        try {
            $this->client = $di->get('aws')->createSqs();
        } catch (SqsException $e) {
            Helpers::__trackError($e);
            exit($e->getMessage());
        } catch (AwsException $e) {
            Helpers::__trackError($e);
            exit($e->getMessage());
        } catch (Exception $e) {
            Helpers::__trackError($e);
            exit($e->getMessage());
        }


        if (Helpers::__isUrl($queueNameUrl)) {
            $this->queueUrl = $queueNameUrl;
        } else {
            if ($this->environment != '') {
                if (strpos($queueNameUrl, '.fifo') !== false) {
                    $names = explode(".fifo", $queueNameUrl);
                    $this->queueName = $names[0] . "_" . strtolower($this->environment) . ".fifo";
                } else {
                    $this->queueName = $queueNameUrl . "_" . strtolower($this->environment);
                    if($queueNameUrl == getenv('QUEUE_ETL_HISTORY')){
                        $this->queueName = $queueNameUrl;
                    }
                }
            } else {
                if (strpos($queueNameUrl, '.fifo') !== false) {
                    $names = explode(".fifo", $queueNameUrl);
                    $this->queueName = $names[0] . "_" . strtolower($di->getShared('appConfig')->application->environment) . ".fifo";
                } else {
                    $this->queueName = $queueNameUrl . "_" . strtolower($di->getShared('appConfig')->application->environment);
                    if($queueNameUrl == getenv('QUEUE_ETL_HISTORY')){
                        $this->queueName = $queueNameUrl;
                    }
                }
                $this->queueUrl = $this->getQueueUrlSQS();
                $this->queueArn = $this->getQueueArnSQS();
            }
        }
    }

    /**
     * @param $data
     * @return array
     */
    public function addQueueSQS($data, $messageGroupId = '')
    {
        if ($this->queueUrl == '') {
            //get queueURL from QueueName
            $this->queueUrl = $this->getQueueUrlSQS();
        }


        if ($this->queueUrl && Helpers::__isUrl($this->queueUrl)) {
            if (strpos($this->queueUrl, '.fifo') !== false) {
                $params = [
//                    'DelaySeconds' => self::DELAY_5_SECONDES,
                    'MessageAttributes' => $this->convertDataArrayToMessageAttributes($data),
                    'MessageBody' => $this->convertDataArrayToMessageBodyJson($data),
                    'MessageGroupId' => ($messageGroupId != '' && $messageGroupId != null) ? $messageGroupId : 'FIFO',
                    'MessageDeduplicationId' => mt_rand() . '-' . time(),
                    'QueueUrl' => $this->queueUrl
                ];
            } else {
                $params = [
                    'DelaySeconds' => self::DELAY_5_SECONDES,
                    'MessageAttributes' => $this->convertDataArrayToMessageAttributes($data),
                    'MessageBody' => $this->convertDataArrayToMessageBodyJson($data),
                    'QueueUrl' => $this->queueUrl
                ];
            }

            try {
                $result = $this->client->sendMessage($params);
                return ['success' => true, 'params' => $params, 'detail' => $result];
            } catch (SqsException $e) {
                Helpers::__trackError($e);
                return ['success' => false, 'detail' => $e->getMessage(), 'params' => $params];
            } catch (AwsException $e) {
                // output error message if fails
                Helpers::__trackError($e);
                return ['success' => false, 'detail' => $e->getMessage(), 'params' => $params];
            } catch (\Exception $e) {
                Helpers::__trackError($e);
                return ['success' => false, 'detail' => $e->getMessage(), 'params' => $params];
            }
        } else {
            return ['success' => false, 'msg' => 'QUEUE_URL_NOT_FOUND_TEXT'];
        }
    }

    /**
     * @param array $data
     * @return string
     */
    protected function convertDataArrayToMessageBodyJson($data = [])
    {
        //if isset $data['params'];
        if (isset($data['params']) && (is_array($data['params']) || is_object($data['params']))) {
            $return = json_encode($data['params']);
        } else {
            $return = json_encode($data);
        }
        return $return;
    }

    /**
     * @param array $data
     * @return array
     */
    protected function convertDataArrayToMessageAttributes($data = [])
    {
        $return = [];
        foreach ($data as $key => $value) {
            if (!is_null($value) && !empty($value)) {
                if (is_numeric($value)) {
                    $return[$key] = [
                        "DataType" => "Number",
                        "StringValue" => $value,
                    ];
                } elseif (is_string($value)) {
                    $return[$key] = [
                        "DataType" => "String",
                        "StringValue" => $value,
                    ];
                }
            }
        }
        return $return;
    }

    /**
     * @return bool
     */
    protected function getQueueUrlSQS()
    {
        try {
            $result = $this->client->getQueueUrl([
                'QueueName' => $this->queueName // REQUIRED
            ]);

            if ($result instanceof \Aws\Result) {
                if ($result->get('@metadata')['statusCode'] == HttpStatusCode::HTTP_OK) {
                    return $result->get('QueueUrl');
                }
            }
            return $result;
        } catch (SqsException $e) {
            error_log($e->getMessage());
            echo("[QUEUE-NAME]" . $this->queueName);
            if ($e->getAwsErrorCode() == self::QUEUE_NOT_EXIST_ERROR) {
                if ($this->createQueue()) {
                    return true;
                } else {
                    return false;
                }
            }
            return false;
        } catch (AwsException $e) {
            error_log($e->getMessage());
            echo("[QUEUE-NAME]" . $this->queueName);
            // output error message if fails
            return false;
        } catch (Exception $e) {
            error_log($e->getMessage());
            echo("[QUEUE-NAME]" . $this->queueName);
            // output error message if fails
            return false;
        }
    }

    /**
     * @return mixed
     */
    protected function getQueueArnSQS()
    {
        return $this->client->getQueueArn($this->getQueueUrl());
    }

    /**
     * @return bool
     */
    public function receiveMessageSQS()
    {
        try {

            if ($this->queueUrl == '') {
                $this->queueUrl = $this->getQueueUrlSQS();
            }

            if ($this->queueUrl && Helpers::__isUrl($this->queueUrl)) {
                $result = $this->client->receiveMessage(array(
                    'AttributeNames' => ['SentTimestamp'],
                    'MaxNumberOfMessages' => 1,
                    'MessageAttributeNames' => ['All'],
                    'QueueUrl' => $this->queueUrl, // REQUIRED
                    'WaitTimeSeconds' => 0,
                ));
                if (is_array($result->get('Messages')) && count($result->get('Messages')) > 0) {
                    $this->message = ($result->get('Messages')[0]);
                    return $result;
                } else {
                    $this->message = null;
                    return false;
                }
            } else {
                $this->message = null;
                return false;
            }
        } catch (SqsException $e) {
            $this->message = null;
            //error_log($e->getMessage());
            return false;
        } catch (AwsException $e) {
            $this->message = null;
            // output error message if fails
            // error_log($e->getMessage());
            return false;
        } catch (\Exception $e) {
            $this->message = null;
        }
    }

    /**
     *
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * delete message
     * @param $receiptHandle
     * @return bool
     */
    public function deleteMessage($receiptHandle = '')
    {

        if ($receiptHandle == '') {
            $receiptHandle = $this->message['ReceiptHandle'];
        }
        try {

            if ($this->queueUrl && Helpers::__isUrl($this->queueUrl) && $receiptHandle != '') {
                $result = $this->client->deleteMessage([
                    'QueueUrl' => $this->queueUrl, // REQUIRED
                    'ReceiptHandle' => $receiptHandle // REQUIRED
                ]);
                if ($result instanceof \Aws\Result) {
                    if ($result->get('@metadata')['statusCode'] == HttpStatusCode::HTTP_OK) {
                        return true;
                    }
                }
                return false;
            } else {
                return false;
            }

        } catch (AwsException $e) {
            // output error message if fails
            error_log($e->getMessage());
            return false;
        }
    }

    /**
     * @return array
     */
    public function parseMessageAttributesToArray()
    {
        $return = [];
        if (is_array($this->message) && !is_null($this->message)) {
            if (isset($this->message['MessageAttributes']) &&
                is_array($this->message['MessageAttributes']) &&
                count($this->message['MessageAttributes']) > 0) {
                foreach ($this->message['MessageAttributes'] as $key => $valItem) {
                    $return[$key] = isset($valItem['StringValue']) ? $valItem['StringValue'] : null;
                }
            }
            if (isset($this->message['Body'])) {
                try {
                    $json_decode = json_decode($this->message['Body'], true);
                    $return['params'] = $json_decode;
                    if (isset($json_decode['to']) && !isset($return['to'])) {
                        $return['to'] = $json_decode['to'];
                    }
                } catch (\Exception $e) {
                    $json_decode = [];
                }
            }
        }
        return $return;
    }

    /**
     *
     */
    public function createQueue()
    {
        try {
            $result = $this->client->createQueue([
                'QueueName' => $this->queueName, // REQUIRED
                'Attributes' => array(
                    'DelaySeconds' => self::DELAY_5_SECONDES,
                    'MaximumMessageSize' => self::SIZE_64KB, // 4 KB
                ),
            ]);
            if ($result instanceof \Aws\Result) {
                if ($result->get('@metadata')['statusCode'] == HttpStatusCode::HTTP_OK) {
                    $this->queueUrl = $result->get('QueueUrl');
                    return true;
                }
            }
            return false;
        } catch (SqsException $e) {
            error_log($e->getMessage());
            return false;
        } catch (AwsException $e) {
            // output error message if fails
            error_log($e->getMessage());
            return false;
        }
    }

    /**
     * @return string
     */
    public function getQueueUrl()
    {
        return $this->queueUrl;
    }

    /**
     * @return string
     */
    public function getQueueName()
    {
        return $this->queueName;
    }

    /**
     * @return string
     */
    public function getQueueArn()
    {
        return $this->queueArn;
    }
}