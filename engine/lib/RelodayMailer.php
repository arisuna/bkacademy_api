<?php

use Phalcon\Mailer\Manager;
use Phalcon\Mvc\View;
use Phalcon\Mvc\User\Component;
use Swift_Message as Message;
use Mailgun\Mailgun;
use Reloday\Application\Lib\Helpers as RelodayHelpers;

class RelodayMailer extends Component
{


    /**
     * @var array
     */
    protected $attachments = [];

    /**
     * @var string
     */
    protected $templateName = 'default';

    /**
     * @var bool
     */
    protected $withTemplate = true;
    /**
     * @var bool
     */
    protected $sendDirect = false;

    const DRIVER_MAILGUN = 'mailgun';
    const DRIVER_SES = 'ses';
    const MAIL_ADDRESS_SEPARATOR = ',';

    /**
     * RelodayMailer constructor.
     */
    public function __construct()
    {
        $this->registerMailerObject();
    }

    /**
     *
     */
    public function registerMailerObject()
    {
        $driver = $this->getConfig('driver');

        switch ($driver) {
            case self::DRIVER_MAILGUN:
                $this->registerMailGun();
                break;
            case self::DRIVER_SES:
                $this->registerAwsSES();
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Driver-mail "%s" is not supported', $driver));
        }
    }

    /**
     *
     */
    protected function registerMailGun()
    {
        $this->mailer = Mailgun::create($this->getConfig('mailgun')->key);
    }

    /**
     *
     */
    protected function registerAwsSES()
    {

    }

    /**
     * @param $name
     */
    public function setTemplateName($name)
    {
        $this->templateName = $name;
    }


    /**
     * Applies a template to be used in the e-mail
     *
     * @param string $name
     * @param array $params
     * @return string
     */
    public function getTemplate($name, $params)
    {
        $parameters = array_merge([
            'publicUrl' => $this->config->application->publicUrl
        ], $params);

        return $this->view->getRender('emailTemplates', $name, $parameters, function ($view) {
            $view->setRenderLevel(View::LEVEL_LAYOUT);
        });
        return $view->getContent();
    }

    /**
     * @param $name
     * @param $params
     */
    public function getTemplateWithName($name, $params)
    {
        $view = new \Phalcon\Mvc\View\Simple();
        $di = \Phalcon\DI::getDefault();
        $view->setDi($di);
        $view->registerEngines(array(
            ".volt" => function ($view, $di) {
                $volt = new \Phalcon\Mvc\View\Engine\Volt($view, $di);
                $volt->setOptions(
                    array(
                        'compiledPath' => $di->getShared('appConfig')->application->cacheDir,
                        'compiledSeparator' => '_',
                        'compileAlways' => false,
                    )
                );
                return $volt;
            }
        ));

        $view->setViewsDir($di->getShared('appConfig')->application->templatesVoltDir);
        try {
            $html = $view->render('emails/default', [
                'subject' => $this->getSubjectTemplate($name, $params),
                'body' => $this->getBodyTemplate($name, $params),
                'datetime' => date('d M Y - H:i:s'),
            ]);
            return $html;
        } catch (Phalcon\Mvc\View\Engine\Volt\Exception $e) {
            return null;
        } catch (Phalcon\Mvc\View\Exception $e) {
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param $name
     * @param $params
     * @return string
     */
    public function getTemplateDefault($params)
    {
        $view = new \Phalcon\Mvc\View\Simple();
        $di = \Phalcon\DI::getDefault();
        $view->setDi($di);
        $view->registerEngines(array(
            ".volt" => function ($view, $di) {
                $volt = new \Phalcon\Mvc\View\Engine\Volt($view, $di);
                $volt->setOptions(
                    array(
                        'compiledPath' => $di->getShared('appConfig')->application->cacheDir,
                        'compiledSeparator' => '_',
                        'compileAlways' => false,
                    )
                );
                return $volt;
            }
        ));
        $view->setViewsDir($di->getShared('appConfig')->application->templatesVoltDir);
        try {
            $body_html = $view->render('emails/default', [
                'subject' => $params['subject'],
                'subject_html' => $params['subject_html'],
                'body' => $params['body'],
                'datetime' => date('d M Y - H:i:s'),
            ]);
        } catch (Phalcon\Mvc\View\Engine\Volt\Exception $e) {
            return null;
        } catch (Phalcon\Mvc\View\Exception $e) {
            return null;
        } catch (Exception $e) {
            return null;
        }
        return $body_html;
    }

    /**
     * @param $name
     * @param $params
     */
    public function getBodyTemplate($name, $params = [])
    {
        $template = EngineHelpers::__getTemplateEmail($name, $params['language']);
        $text = '';
        if ($template) {
            $text = $template['text'];
            foreach ($params as $key => $value) {
                if (is_string($value)) {
                    $text = str_replace("{#" . $key . "}", $value, $text);
                }
            }
        }
        return $text;
    }

    /**
     * @param $name
     * @param $params
     * @return mixed|string
     */
    public function getSubjectTemplate($name, $params)
    {
        if (isset($params['language'])) {
            $template = EngineHelpers::__getTemplateEmail($name, $params['language']);
        } else {
            $template = EngineHelpers::__getTemplateEmail($name);
        }
        $subject = '';
        if ($template) {
            $subject = $template['subject'];
            foreach ($params as $key => $value) {
                if (is_string($value)) {
                    $subject = str_replace("{#" . $key . "}", $value, $subject);
                }
            }
        }
        return $subject;
    }

    /**
     * @param $params
     */
    public function send($params)
    {
        if (!isset($params['to']) && isset($params['email'])) {
            $params['to'] = $params['email'];
        }

        switch ($driver = $this->getConfig('driver')) {
            case self::DRIVER_MAILGUN:
                return $this->sendByMailGun($params);
                break;
            case self::DRIVER_SES:
                return $this->sendByAwsSES($params);
                break;
        }

    }

    /**
     * @param $params
     */
    public function parseParamsMailgun($params)
    {

        if (!isset($params['subject']) || $params['subject'] == '') {
            $subject = $this->getSubjectTemplate($this->templateName, $params);
        } else {
            $subject = $params['subject'];
        }

        if ($this->withTemplate == true) {
            if (!isset($params['body'])) {
                $body = $this->getTemplateWithName($this->templateName, $params);
            } else {
                $body = $this->getTemplateDefault($params);
            }
        } else {
            //if body raw text
            $body = $params['body'];
        }

        if (isset($params['from']) && $params['from'] != '') {
            $from = $params['from'];
        } else {
            $from = $this->getConfig('mailgun')->sender;
        }

        if (isset($params['to']) && is_string($params['to']) && $params['to'] != '') {
            $to = $params['to'];
        } elseif (is_array($params['to']) && count($params['to'])) {
            $to = implode(self::MAIL_ADDRESS_SEPARATOR, $params['to']);
        } else {
            $to = null;
        }


        if (isset($params['from_name']) && $params['from_name'] != '' && is_string($params['from_name'])) {
            $from = $params['from_name'] . "<" . $from . ">";
        } elseif (isset($params['sender_name']) && $params['sender_name'] != '' && is_string($params['sender_name'])) {
            $from = $params['sender_name'] . "<" . $from . ">";
        }

        $di = \Phalcon\DI::getDefault();
        if ($di->getShared('appConfig')->application->isProd == false) {
            $subject = "[" . $di->getShared('appConfig')->application->environment . "]" . $subject . "[TO : " . $params['to'] . "]";
        }

        $sendArray = [
            'from' => $from,
            'to' => $to,
            'subject' => $subject,
            'html' => $body,
        ];

        /** @var CC $ccData */
        $ccData = [];
        if (isset($params['cc'])) {
            if (is_array($params['cc']) && count($params['cc']) > 0) {
                $ccData = [];
                foreach ($params['cc'] as $ccItem) {
                    if (RelodayHelpers::__isEmail($ccItem)) {
                        $ccData[] = $ccItem;
                    }
                }
            } elseif (is_string($params['cc'])) {
                if (RelodayHelpers::__isEmail($params['cc'])) {
                    $ccData[] = $params['cc'];
                }
            }
        }

        if (!is_null($ccData) && is_array($ccData) && count($ccData) > 0) {
            $sendArray['cc'] = implode(self::MAIL_ADDRESS_SEPARATOR, $ccData);
        }

        /**** BCC **/

        $bccData = [];
        if (isset($params['bcc'])) {
            if (is_array($params['bcc']) && count($params['bcc']) > 0) {
                $bccData = [];
                foreach ($params['bcc'] as $bccItem) {
                    if (RelodayHelpers::__isEmail($bccItem)) {
                        $bccData[] = $bccItem;
                    }
                }
            } elseif (is_string($params['bcc'])) {
                if (RelodayHelpers::__isEmail($bccData)) {
                    $bccData[] = $bccData;
                }
            }
        }

        if (!is_null($bccData) && is_array($ccData) && count($bccData) > 0) {
            $sendArray['bcc'] = implode(self::MAIL_ADDRESS_SEPARATOR, $bccData);
        }

        if (isset($params['replyto']) && $params['replyto'] != '') {
            $sendArray['h:Reply-To'] = $params['replyto'];
        }


        if (count($this->getAttachments()) > 0) {
            $sendArray['attachment'] = $this->getAttachments();
        }
        $count = count($this->getAttachments());
        return $sendArray;
    }

    /**
     * @param $params
     * @return bool
     */
    public function sendByMailGun($params)
    {
        $sendArray = $this->parseParamsMailgun($params);
        $sendArray['to'] = $this->checkEmail($sendArray['to']);
        try {
            $result = $this->mailer->messages()->send(
                $this->getConfig('mailgun')->domain,
                $sendArray
            );
            return true;
        } catch (Mailgun\Exception\InvalidArgumentException $e) {
            \Reloday\Application\Lib\Helpers::__trackError($e);
            return false;
        } catch (Exception $e) {
            $errorDetails = ([
                'array' => array_keys($sendArray),
                'key' => $this->getConfig('mailgun')->key,
                'domain' => $this->getConfig('mailgun')->domain,
                'to' => isset($sendArray['to']) ? $sendArray['to'] : null,
                'from' => isset($sendArray['from']) ? $sendArray['from'] : null,
                'body' => isset($sendArray['body']) ? sizeof($sendArray['body']) : null,
                'html' => isset($sendArray['html']) ? sizeof($sendArray['html']) : null,
                'subject' => isset($sendArray['subject']) ? $sendArray['subject'] : null,
            ]);
            \Reloday\Application\Lib\Helpers::__trackError($e);
            return false;
        }
    }

    /**
     *
     */
    public function sendByAwsSES($params)
    {
        $params['to'] = $this->checkEmail($params['to']);
    }

    /**
     * @return mixed
     */
    public function getAttachments()
    {
        return $this->attachments;
    }

    /**
     * @param $data
     */
    public function addAttachementData($data)
    {
        if (isset($data['fileContent']) && isset($data['filename'])) {
            $this->attachments[] = ['fileContent' => $data['fileContent'], 'filename' => $data['filename']];
        }
    }

    /**
     * @param $data
     */
    public function addAttachementPath($data)
    {
        if (isset($data['filePath']) && isset($data['filename'])) {
            $this->attachments[] = ['filePath' => $data['filePath'], 'filename' => $data['filename']];
        }
    }

    /**
     * @return array
     */
    public static function __getExceptEmailAdd()
    {
        if (getenv('RELO_EMAILS_EXCEPTIONS') != '') {
            return explode(';', getenv('RELO_EMAILS_EXCEPTIONS'));
        } else {
            return [];
        }
    }

    /**
     * @param $name
     */
    public function getConfig($name)
    {
        if (isset($this->config->$name)) return $this->config->$name;
        else return false;
    }

    /**
     * @param bool $check
     */
    public function setWithTemplate($check = true)
    {
        $this->withTemplate = $check;
    }


    /**
     * @param bool $check
     */
    public function setSendDirect($check = true)
    {
        $this->sendDirect = $check;
    }

    /**
     * @param $email
     * @return array|false|string
     */
    public function checkEmail($emails)
    {
        if ($this->sendDirect == true) {
            return $emails;
        } else {
            if (is_string($emails)) {

                //il email end by relotest.com
                //il email end by relotest.com
                if (\Reloday\Application\Lib\Helpers::__isEmail($emails) && preg_match('/relotest\.com$/', $emails)) {
                    return $emails;
                }

                if (\Reloday\Application\Lib\Helpers::__isEmail($emails) && preg_match('/relotalent\.com$/', $emails)) {
                    return $emails;
                }

                if (\Reloday\Application\Lib\Helpers::__isEmail($emails) && preg_match('/expatfinder\.com$/', $emails)) {
                    return $emails;
                }

                if (\Reloday\Application\Lib\Helpers::__isEmail($emails) && preg_match('/reloday\.com$/', $emails)) {
                    return $emails;
                }

                if (getenv('SYS_TEST_EMAIL') != '' &&
                    getenv('SYS_TEST_EMAIL') != false &&
                    getenv('RELO_EMAILS_EXCEPTIONS') != '' &&
                    getenv('RELO_EMAILS_EXCEPTIONS') != false
                ) {
                    if (in_array($emails, self::__getExceptEmailAdd())) {
                        return $emails;
                    } else {
                        $emails = getenv('SYS_TEST_EMAIL');
                        return $emails;
                    }
                } else {
                    return $emails;
                }
            } elseif (is_array($emails) && count($emails) > 0) {
                $return = [];

                if (getenv('SYS_TEST_EMAIL') != '' &&
                    getenv('SYS_TEST_EMAIL') != false &&
                    getenv('RELO_EMAILS_EXCEPTIONS') != '' &&
                    getenv('RELO_EMAILS_EXCEPTIONS') != false
                ) {
                    foreach ($emails as $emailItemValue) {
                        if (in_array($emailItemValue, self::__getExceptEmailAdd())) {
                            $return[] = $emailItemValue;
                        } else {
                            $return[] = getenv('SYS_TEST_EMAIL');
                        }
                    }
                } else {
                    $return = $emails;
                }
                return $return;
            }
        }
    }

    /**
     * @TODO delete it when Mailgun go with 3.0
     * @param $url
     * @return mixed
     */
    public function getMailgunAttachmentByMailer($url)
    {
        return $this->mailer->getAttachment($url);
    }

    /**
     * @param $url
     */
    public function getMailgunAttachment($url)
    {
        try {
            $httpClient = new \GuzzleHttp\Client();
            $response = $httpClient->get($url, [
                'auth' => ['api', $this->getConfig('mailgun')->key],
            ]);
            return ['success' => true, 'data' => (string)$response->getBody()];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }


    /**
     * @param $url
     */
    public function getMailgunMessage($url)
    {
        try {
            $httpClient = new \GuzzleHttp\Client();
            $response = $httpClient->get($url, [
                'auth' => ['api', $this->getConfig('mailgun')->key],
            ]);
            return ['success' => true, 'data' => json_decode($response->getBody(), true)];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }
}