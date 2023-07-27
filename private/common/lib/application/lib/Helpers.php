<?php

/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 10/7/16
 * Time: 13:29
 */

namespace SMXD\Application\Lib;

use \Mailgun\Mailgun;
use Phalcon\Di;
use Phalcon\Http\Client\Header;
use Phalcon\Http\Client\Provider\Exception;
use Phalcon\Security;
use Phalcon\Utils\Slug as PhpSlug;
use Phalcon\Http\Request;

use Phalcon\Validation;
use Phalcon\Validation\Validator\Email as EmailValidator;
use Phalcon\Validation\Validator\Url as UrlValidator;
use Phalcon\Filter;
use SMXD\Api\Models\Company;
use SMXD\Application\Lib\SMXDFilter as SMXDFilter;
use SMXD\Application\Models\ApplicationModel;
use SMXD\Application\Models\AssignmentExt;
use SMXD\Application\Models\DataUserMemberExt;
use SMXD\Application\Models\EntityProgressEXt;
use SMXD\Application\Models\FilterConfigExt;
use SMXD\Application\Models\FilterConfigItemExt;
use SMXD\Application\Models\RelocationExt;
use SMXD\Application\Models\RelocationServiceCompanyExt;
use SMXD\Application\Models\SequenceExt;
use SMXD\Application\Models\UserExt;
use SMXD\Application\Validator\CurrencyValidator;
use SMXD\Application\Validator\DomainPrefixValidator;
use Phalcon\Validation\Validator\Date as DateValidator;
use Phalcon\Validation\Validator\PasswordStrength as PasswordStrengthValidator;
use Phalcon\Validation\Validator\StringLength as StringLengthValidator;

class Helpers
{
    /**
     * variable list
     */
    const TOKEN = 'token';
    const TOKEN_KEY = 'token-key';
    const REFRESH_TOKEN = 'refresh-token';
    const EMPLOYEE_TOKEN_KEY = 'ee-token-key';
    const BACKEND_TOKEN_KEY = 'backend-token-key';
    const LANGUAGE_KEY = 'language-key';
    const TIMEZONE = 'timezone';
    const TIMEZONE_OFFSET = 'timezone-offset';
    const CURRENT_ASSIGNMENT = 'current-assignment';
    const YES = 1;
    const NO = 0;
    const API_ACCESS_KEY = 'api-key';
    const CLIENT_ID = 'client-id';
    const CLIENT_SECRET = 'client-secret';
    const API_GATEWAY_ID = 'x-amzn-apigateway-api-id';
    const AUTHORIZATION = 'Authorization';

    const DATE_FORMAT_YMDHIS = 'Y-m-d H:i:s';
    const DATE_FORMAT_YMD = 'Y-m-d';

    /**
     * @param array $params from, to = [], subject, body
     * @param array $params
     * @param bool $truncate_file
     * @return array
     */
    static function sendMail(array $params, $truncate_file = false)
    {
        if (!isset($params['to'])) {
            return [
                'success' => false,
                'msg' => 'Parameter "to" required'
            ];
        }

        if (getenv('ENVIR') == 'DEV' && getenv('SYS_TEST_EMAIL') != '') {
            if (in_array($params['to'], self::__getExceptEmailAdd())) {
                //don't try to edit params to if params['to'] are in the list if Exception Emails
            } else {
                $params['to'] = getenv('SYS_TEST_EMAIL');
            }
        }

        // Create the Transport
        $transport = \Swift_SmtpTransport::newInstance('smtp.mailgun.org', 25)
            ->setUsername('postmaster@digitalexpat.com')
            ->setPassword('c6b3753faec13c12763eda279981121b');

        // Create the Mailer using your created Transport
        $mailer = \Swift_Mailer::newInstance($transport);

        // Create a message
        $message = \Swift_Message::newInstance(isset($params['subject']) ? $params['subject'] : '')
            ->setFrom(isset($params['from']) ? $params['from'] : ['contact@digitalexpat.com' => 'Contact'])
            ->setTo($params['to'])
            ->setBody(isset($params['body']) ? $params['body'] : '', 'text/html');

        // Attach file
        $attach_type = isset($params['attach_type']) ? $params['attach_type'] : 'file';
        if (isset($params['files'])) {
            if (is_array($params['files'])) {
                foreach ($params['files'] as $file) {
                    switch ($attach_type) {
                        case 'file':
                            if (is_file($file))
                                $message->attach(\Swift_Attachment::fromPath($file));
                            break;
                        case 'string':
                            $message->attach(\Swift_Attachment::newInstance(
                                $file['data'],
                                isset($params['file_name']) ? $params['file_name'] : 'data',
                                isset($params['mime']) ? $params['mime'] : 'application/pdf'
                            ));
                            break;
                        default:
                            break;
                    }
                }
            } else
                switch ($attach_type) {
                    case 'file':
                        if (is_file($params['files']))
                            $message->attach(\Swift_Attachment::fromPath($params['files']));
                        break;
                    case 'string':
                        $message->attach(\Swift_Attachment::newInstance(
                            $params['files'],
                            isset($params['file_name']) ? $params['file_name'] : 'data',
                            isset($params['mime']) ? $params['mime'] : 'application/pdf'
                        ));
                        break;
                    default:
                        break;
                }
        }

        // Send the message
        $result = $mailer->send($message);

        // Check option delete file after send
        if ($truncate_file & $attach_type == 'file') {
            if (isset($params['files'])) {
                if (is_array($params['files'])) {
                    foreach ($params['files'] as $file) {
                        if (is_file($file))
                            unlink($file);
                    }
                } else if (is_file($params['files'])) {
                    unlink($params['files']);
                }
            }
        } // end if

        return [
            'success' => $result,
            'msg' => $result ? 'Send mail success' : 'Send mail fail'
        ];

    }

    /**
     * @param array $params from, to = [], subject, body
     * @return array
     */
    static function sendMailByApi(array $params)
    {
        $mg = new Mailgun(getenv('MAILGUN_KEY'));
        $domain = getenv('MAILGUN_DOMAIN');


        if (getenv('ENVIR') == 'DEV' && getenv('SYS_TEST_EMAIL') != '') {

            if (in_array($params['to'], self::__getExceptEmailAdd())) {
                //don't try to edit params to if params['to'] are in the list if Exception Emails
            } else {
                $params['to'] = getenv('SYS_TEST_EMAIL');
            }

        }

        $loader = new \Twig_Loader_Filesystem(__DIR__ . '/../../../../../resources/templates/emails/');
        $twig = new \Twig_Environment($loader, array(
            'cache' => __DIR__ . '/../../../../../app/cache/emails/',
        ));

        /** @var  $body_html */
        $body_html = $twig->render('default.html', [
            'subject' => isset($params['subject_html']) ? $params['subject_html'] : $params['subject'],
            'datatime' => date('Y-m-d H:i:s'),
            'body' => $params['body']
        ]);


        /** @var  $from */
        $from = isset($params['from']) ? $params['from'] : getenv('MAILGUN_SENDER');
        /** @var $sending_params */
        $sending_params = [
            'from' => $from,
            'to' => $params['to'],
            'subject' => $params['subject'],
            'html' => $body_html,
        ];

        /**
         * reply to
         */
        if (isset($params['replyto'])) {
            $sending_params['h:Reply-To'] = $params['replyto'];
        }
        /**
         * custom variables
         */
        if (isset($params['user_uuid'])) {
            $sending_params['v:user_uuid'] = $params['user_uuid'];
        }

        $result = $mg->sendMessage($domain, $sending_params);

        $httpResponseCode = $result->http_response_code;
        if ($httpResponseCode == 200) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param array
     */
    static function addToMailQueue(array $params)
    {

    }

    static function sort_menu($item1, $item2)
    {
        return $item1['order'] > $item2['order'];
    }

    /**
     * [formatdateDMY description]
     * @param  [type] $originalDate [description]
     * @return [type]               [description]
     */
    static function formatdateDMY($originalDate)
    {
        return date("d/m/Y", strtotime($originalDate));
    }

    /**
     * @param int $length
     * @param bool $add_dashes
     * @param string $available_sets
     * @return string
     */
    static function __password($length = 10, $add_dashes = false, $available_sets = 'luds')
    {
        return self::password();
    }

    /**
     * @return string generate password
     */
    static function password($length = 10, $add_dashes = false, $available_sets = 'luds')
    {
        $sets = array();
        if (strpos($available_sets, 'l') !== false)
            $sets[] = 'abcdefghjkmnpqrstuvwxyz';
        if (strpos($available_sets, 'u') !== false)
            $sets[] = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        if (strpos($available_sets, 'd') !== false)
            $sets[] = '23456789';
        if (strpos($available_sets, 's') !== false)
            $sets[] = '!@#$%&*?';
        $all = '';
        $password = '';
        foreach ($sets as $set) {
            $password .= $set[array_rand(str_split($set))];
            $all .= $set;
        }
        $all = str_split($all);
        for ($i = 0; $i < $length - count($sets); $i++)
            $password .= $all[array_rand($all)];
        $password = str_shuffle($password);
        if (!$add_dashes)
            return $password;
        $dash_len = floor(sqrt($length));
        $dash_str = '';
        while (strlen($password) > $dash_len) {
            $dash_str .= substr($password, 0, $dash_len) . '-';
            $password = substr($password, $dash_len);
        }
        $dash_str .= $password;
        return $dash_str;
    }

    /**
     * @param $text
     * @param $limit
     * @return string
     */
    static function limit_text($text, $limit)
    {
        if (str_word_count($text, 0) > $limit) {
            $words = str_word_count($text, 2);
            $pos = array_keys($words);
            $text = substr($text, 0, $pos[$limit]) . '...';
        }
        return $text;
    }

    /**
     * @param $text
     * @param $limit
     * @return string
     */
    static function limit_text_length($text, $limit = 250)
    {
        if (mb_strwidth($text, 'UTF-8') <= $limit) {
            return $text;
        }
        return rtrim(mb_strimwidth($text, 0, $limit, '', 'UTF-8')) . "...";
    }

    /**
     * @param $url
     */
    static function getTemporaryUrlNotification($url, $server_name)
    {
        $matches = null;
        if (preg_match('#^http\\:\\/\\/([a-z0-9-\\.]+)\\/gms\\/(.*)$#', $url, $matches)) {
            if (isset($matches[1]) && $matches[1] != $server_name) {
                $url = str_replace($matches[1], $server_name, $url);
            }
        }
        return $url;
    }

    /**
     * @param $params
     * @param $string
     */
    static function isIntPositive($params = array(), $string)
    {
        if (isset($params[$string]) && is_numeric($params[$string]) && $params[$string] > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param array $params
     * @param $string
     * @return bool|mixed
     */
    static function getIntPositive($params = array(), $string)
    {
        if (isset($params[$string]) && is_numeric($params[$string]) && $params[$string] > 0) {
            return intval($params[$string]);
        } else {
            return false;
        }
    }

    /**
     * @param $string
     * @param $limit
     * @return string
     */
    public static function getWords($string, $limit)
    {
        $str = "";
        $words = str_word_count($string, 1);
        if (is_array($words) && count($words) > 0 && count($words) > $limit) {
            for ($i = 0; $i < $limit; $i++) {
                if (isset($words[$i])) {
                    $str .= $words[$i] . ' ';
                }
            }
        } else {
            $str = $string;
        }
        return $str;
    }

    /**
     * @param $string
     */
    public static function getShortkeyFromWord($string)
    {

        $shortname = "";
        if (strlen($string) <= 4) {
            $shortname = strtoupper($string);
        } else {
            $array = [];
            $arrayWord = [];
            $words = str_word_count(strtoupper($string), 1);
            if (is_array($words) && count($words) > 0) {
                for ($i = 0; $i < count($words); $i++) {
                    if (isset($words[$i])) {
                        $array = array_merge($array, str_split($words[$i]));
                        $arrayWord[$i] = $words[$i];
                    }
                }
            }
            $array = array_values(array_unique($array));

            if (count($arrayWord) >= 3) {
                foreach ($arrayWord as $key => $aryWord) {
                    if ($key < 1) {
                        $shortname .= $aryWord[0] . (isset($aryWord[1]) ? $aryWord[1] : "");
                    }
                    if ($key == 1) {
                        $shortname .= $aryWord[0];
                    }
                    if ($key == 2) {
                        $shortname .= $aryWord[0];
                    }
                }
            } elseif (count($arrayWord) == 2) {
                foreach ($arrayWord as $key => $aryWord) {
                    if ($key <= 1) {
                        $shortname .= $aryWord[0] . (isset($aryWord[1]) ? $aryWord[1] : "");
                    }
                }
            } elseif (count($arrayWord) == 1) {
                foreach ($arrayWord as $key => $aryWord) {
                    if ($key <= 1) {
                        $shortname .= $aryWord[0] . (isset($aryWord[1]) ? $aryWord[1] : "");
                    }
                }
            }
        }
        return $shortname;
    }

    /**
     *
     */
    public static function __getHeaderValue($name)
    {
        $request = new Request();
        $headers = array_change_key_case($request->getHeaders());
        if (array_key_exists($name, $headers)) return $headers[$name];
        return null;
    }

    /**
     * @param $name
     */
    public static function __getRequestValue($name)
    {
        if (self::__existInputJsonValue($name)) {
            return self::__getJsonInput($name);
        } else {
            return self::__getPostPut($name);
        }
    }

    /**
     * get property of object filled by JSON request
     * @param String $objectName
     * @param String $fieldName
     * @return mixed|null
     */
    public static function __getRequestProperty(string $objectName, string $propertyName)
    {
        $object = self::__getRequestValueAsArray($objectName);
        if ($object && in_array($propertyName, $object)) {
            return $object[$propertyName];
        }
        return null;
    }

    /**
     * @param $name
     * @return mixed
     */
    public static function __getPostPut($name)
    {
        $name = trim($name);
        $request = self::__getSharedRequest();
        if ($request->isPost()) {
            $valFormRequest = $request->getPost($name);
        } elseif ($request->isPut()) {
            $valFormRequest = $request->getPut($name);
        } elseif ($request->isGet()) {
            $valFormRequest = $request->getQuery($name);
        }
        if (isset($valFormRequest) && !is_null($valFormRequest)) {
            return $valFormRequest;
        }
    }

    /**
     * @param $name
     * @return null
     */
    public static function __getJsonInput($name)
    {
        $name = trim($name);
        $request = self::__getSharedRequest();
        $dataPayload = $request->getJsonRawBody();
        $valPayload = isset($dataPayload->$name) && !is_null($dataPayload->$name) ? $dataPayload->$name : null;
        return $valPayload;
    }

    /**
     * @param $name
     * @return null
     */
    public static function __getJsonInputArray($name)
    {
        $name = trim($name);
        $request = self::__getSharedRequest();
        $dataPayload = $request->getJsonRawBody(true);
        $valPayload = isset($dataPayload[$name]) && !is_null($dataPayload[$name]) ? $dataPayload[$name] : null;
        return $valPayload;
    }

    /**
     * @return Request
     */
    public static function __getSharedRequest()
    {
        try {
            $request = Di::getDefault()->getRequest();
            if (!$request) return new Request();
            else return $request;
        } catch (\Exception $e) {
            return new Request();
        }
    }

    /**
     * @param $name
     */
    public static function __existInputJsonValue($name)
    {
        $request = self::__getSharedRequest();
        $name = trim($name);
        $dataPayload = $request->getJsonRawBody(true);
        if (is_array($dataPayload)) {
            if (array_key_exists($name, $dataPayload)) return true;
        }
        return false;
    }


    /**
     * @param $name
     */
    public static function __existRequestValue($name)
    {
        $request = self::__getSharedRequest();
        $name = trim($name);
        $dataPayload = $request->getJsonRawBody(true);
        if (is_array($dataPayload)) {
            if (array_key_exists($name, $dataPayload)) return true;
        }

        if ($request->isPost()) {
            if ($request->hasPost($name)) return true;
        } elseif ($request->isPut()) {
            if ($request->hasPut($name)) return true;
        }
        return false;
    }

    /**
     * @param $name
     */
    public static function __existQueryValue($name)
    {
        $request = self::__getSharedRequest();
        $name = trim($name);
        if ($request->isGet()) {
            if ($request->hasQuery($name)) return true;
        }
        return false;
    }

    /**
     * @param String $name
     * @param array $custom
     * @return bool
     */
    public static function __existCustomValue(string $name, $custom = [])
    {
        return is_array($custom) && (isset($custom[$name]) || array_key_exists($name, $custom));
    }

    /**
     * @param $name
     * @return bool
     */
    public static function __existHeaderValue(string $name)
    {
        $request = new Request();
        $headers = array_change_key_case($request->getHeaders());
        if (array_key_exists($name, $headers)) return true;
        return false;
    }

    /**
     * @param $name
     */
    public static function __getRequestValueAsArray($name)
    {
        $val = self::__getJsonInputArray($name);
        if (isset($val) && !is_null($val)) {
            if (is_object($val)) $val = (array)$val;
            return $val;
        }

        $val = self::__getPostPut($name);
        if (isset($val) && !is_null($val)) {
            if (is_object($val)) $val = (array)$val;
            return $val;
        }
    }


    /**
     * @param null $request
     */
    public static function __getRequestValues()
    {
        $request = self::__getSharedRequest();
        $dataPayload = $request->getJsonRawBody();
        if (is_array($dataPayload) && count($dataPayload) > 0) {
            return $dataPayload;
        }
        if (is_object($dataPayload) && !empty($dataPayload)) {
            return $dataPayload;
        }
        if ($request->isPost()) {
            return $request->getPost();
        } elseif ($request->isPut()) {
            return $request->getPut();
        }
    }

    /**
     * @param null $request
     */
    public static function __getRequestValuesObject()
    {
        $request = self::__getSharedRequest();
        $dataPayload = $request->getJsonRawBody(); //tra ve OBJECT
        if (is_array($dataPayload) && count($dataPayload) > 0) {
            return (object)$dataPayload;
        }
        if (is_object($dataPayload) && !empty($dataPayload) && count($dataPayload) > 0) {
            return $dataPayload;
        }
        if ($request->isPost()) {
            return (object)$request->getPost();
        } elseif ($request->isPut()) {
            return (object)$request->getPut();
        }
    }

    /**
     * @param null $request
     */
    public static function __getRequestValuesArray()
    {
        $request = self::__getSharedRequest();
        $dataPayload = $request->getJsonRawBody(true);
        if (count($dataPayload) > 0) {
            return $dataPayload;
        }
        if ($request->isPost()) {
            return $request->getPost();
        } elseif ($request->isPut()) {
            return $request->getPut();
        }
    }

    /**
     * @param $name
     * @param array $custom
     * @param null $request
     */
    public static function __getRequestValueWithCustom($name, $custom = array())
    {
        //Custom Exist
        if (!is_array($custom) || is_null($custom)) return false;
        if (array_key_exists($name, $custom)) {
            $val = self::__getCustomValue($name, $custom);
            if (!Helpers::__isNull($val)) return $val;
        }
        //else
        return self::__getRequestValue($name);
    }

    /**
     * @param $name
     * @param array $custom
     * @param null $request
     */
    public static function __getRequestValueFromCustom($name, $custom = array())
    {
        $valCustom = isset($custom[$name]) && !is_null($custom[$name]) ? $custom[$name] : null;
        return $valCustom;
    }

    /**
     * @param $name
     * @param array $custom
     * @param null $request
     */
    public static function __getCustomValue($name, $custom = array())
    {
        $custom = (array)$custom;
        $valCustom = isset($custom[$name]) && !is_null($custom[$name]) ? $custom[$name] : null;
        return $valCustom;
    }


    /**
     * @param $uuid
     * @return bool
     */
    public static function __isValidUuid($uuid)
    {
        if (!is_string($uuid)) return false;
        if (is_null($uuid)) return false;
        if (self::__isSingleUuid($uuid) == true) {
            return true;
        } else {
            return self::__isMultipleUuid($uuid);
        }
        return true;
    }

    /**
     * @param $uuids
     */
    public static function __isMultipleUuid(string $uuidList)
    {
        if ($uuidList == "") {
            return false;
        }
        $uuids = preg_split('#_#', $uuidList);
        if (is_array($uuids) && count($uuids) > 0) {
            foreach ($uuids as $uuid) {
                if (self::__isSingleUuid($uuid) == false) return false;
            }
        } else {
            return false;
        }
        return true;
    }

    /**
     * @param $uuid
     * @return bool
     */
    public static function __isSingleUuid(string $uuid)
    {
        if (is_numeric($uuid) || !is_string($uuid) || (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid) !== 1)) {
            return false;
        }
        return true;
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
     * @param array $custom
     * @param null $request
     */
    public static function __existRequestValueWithCustom($name, $custom = array(), $request = null)
    {
        //@todo $returnCustom = array_key_exists($name, $custom) && !is_null($custom[$name]) ? true : false;
        $returnCustom = array_key_exists($name, $custom) ? true : false;
        if (is_null($request)) {
            $request = new Request();
        }
        $name = trim($name);
        $dataPayload = $request->getJsonRawBody();
        $returnPayload = isset($dataPayload->$name) && !is_null($dataPayload->$name) ? true : false;

        if ($request->isPost()) {
            $returnPostPut = $request->hasPost($name);
        } elseif ($request->isPut()) {
            $returnPostPut = $request->hasPut($name);
        } else {
            $returnPostPut = false;
        }
        return $returnCustom || $returnPayload || $returnPostPut;
    }

    /**
     *
     */
    public static function __notNull($value)
    {
        return !is_null($value);
    }

    /***
     * @return mixed
     */
    public static function __coalesce()
    {
        $array = array_filter(func_get_args(), function ($value) use (&$data) {
            return !is_null($value);
        });
        return array_shift($array);
    }


    /**
     * @param $uuid
     * @return bool
     */
    public static function __isEmail($email)
    {
        $validation = new Validation();
        $validation->add('field', new EmailValidator(['message' => "The e-mail is not valid"]));
        $messages = $validation->validate(['field' => $email]);
        if (count($messages) > 0) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param $var
     * @return bool
     */
    public static function __isAlphaNumeric($var)
    {
        $validation = new Validation();
        $validation->add('field', new Validation\Validator\AlphaNumericValidator(['message' => "NOT ALPHANUMERIC"]));
        $messages = $validation->validate(['field' => $var]);
        if (count($messages) > 0) {
            return false;
        } else {
            return true;
        }
    }


    /**
     * @param array $params
     */
    public static function __parseDynamodbObjectParams($params = [])
    {
        if (is_array($params) && count($params) > 0) {
            foreach ($params as $key => $object) {
                if (is_array($object)) {
                    if (isset($object['S'])) $params[$key] = $object['S'];
                    if (isset($object['N'])) $params[$key] = $object['N'];
                }
            }
        }
        return $params;
    }

    /**
     * @param array $params
     */
    public static function __transformDynamodbObject($params = [])
    {
        $params = (array)$params;
        foreach ($params as $key => $object) {
            if (is_numeric($object)) {
                $params[$key] = ['N' => strval($object)];
            } elseif (is_string($object)) {
                $params[$key] = ['S' => $object];
            } elseif (is_array($object)) {
                foreach ($object as $kk => $item) {
                    if ($item != '') {
                        $object[$kk] = ['S' => $item];
                    } else {
                        unset($object[$kk]);
                    }
                }
                $params[$key] = ['M' => $object];
            }
        }
        return $params;
    }

    /**
     * @param $params
     */
    public static function __parseArrayToDynamoParams($params = [])
    {
        if (is_array($params)) {
            foreach ($params as $kk => $item) {
                if ($item && is_string($item)) {
                    $params[$kk] = ['S' => (string)$item];
                } elseif ($item && is_bool($item)) {
                    $params[$kk] = ['BOOL' => $item];
                } elseif ($item && is_numeric($item)) {
                    $params[$kk] = ['N' => (string)$item];
                } else {
                    unset($params[$kk]);
                }
            }
            return $params;
        }
    }

    /**
     * @param $value
     */
    public static function __sanitizeText($value)
    {
        $filter = SMXDFilter::__getFilter();
        return $filter->sanitize($value, 'text');
    }

    /**
     * @param $value
     */
    public static function __sanitizeInteger($value)
    {
        $filter = new Filter();
        return $filter->sanitize($value, 'int');
    }

    /**
     * @param $value
     */
    public static function __sanitizeEmail($value)
    {
        if (is_string($value)) {
            $filter = new Filter();
            $value = strtolower($value);
            return $filter->sanitize($value, 'email');
        } else {
            return null;
        }
    }

    /**
     * @param $value
     */
    public static function __sanitizeFloat($value)
    {
        $filter = new Filter();
        return $filter->sanitize($value, 'float');
    }

    /**
     * @param $value
     */
    public static function __sanitizeAlphanum($value)
    {
        $filter = new Filter();
        return $filter->sanitize($value, 'alphanum');
    }

    /**
     * @param $value
     */
    public static function __sanitizeAlphanumSpace($value)
    {
        $filter = SMXDFilter::__getFilter();
        return $filter->sanitize($value, 'alphanum_space');
    }

    /**
     * @param $value
     * @return bool
     */
    public static function __isJsonValid($value)
    {
        if ($value == '' || is_null($value)) return false;
        try {
            json_decode($value);
            return json_last_error() === JSON_ERROR_NONE;
        } catch (\Exception $e) {
            return false;
        }

    }

    /**
     * @param $uuid
     * @return bool
     */
    public static function __checkUuid($uuid)
    {
        return !is_null($uuid) && is_string($uuid) && $uuid != "" && self::__isValidUuid($uuid);
    }

    /**
     * @param $id
     * @return bool
     */
    public static function __checkId($id)
    {
        return is_numeric($id) && $id > 0;
    }

    /**
     * @param $id
     * @return bool
     */
    public static function __isValidId($id)
    {
        return is_numeric($id) && $id > 0;
    }

    /**
     * @param $string
     * @return bool
     */
    public static function __checkString($string)
    {
        return is_string($string) && $string != '';
    }

    /**
     * @param $status
     */
    public static function __checkStatus($status)
    {
        return is_numeric($status) && is_int((int)$status) && (int)$status >= -2;
    }

    /**
     * @param $status
     * @return bool
     */
    public static function __checkYesNo($status)
    {
        return is_bool((int)$status);
    }

    /**
     * @param $url
     * @return bool
     */
    public static function __isUrl($url)
    {
        $validation = new Validation();
        $validation->add('field', new UrlValidator(['message' => "The url is not valid"]));
        $messages = $validation->validate(['field' => $url]);
        if (count($messages) > 0) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param $url
     * @return bool
     */
    public static function __isDomainPrefix($subdomain)
    {
        $validation = new Validation();
        $validation->add('field', new DomainPrefixValidator([
            'min' => 2,
            'max' => 128,
            'hyphen' => true,
            'message' => 'APP_URL_ALPHANUMERIC_TEXT'
        ]));
        $messages = $validation->validate(['field' => $subdomain]);
        if (count($messages) > 0) {
            return false;
        } else {
            return true;
        }
    }
    /*
    static function checkUuid($value)
    {
        if (!preg_match("/^\{?[A-Z0-9]{8}-[A-Z0-9]{4}-[1-5][A-Z0-9]{3}-[A-Z0-9]{4}-[A-Z0-9]{12}\}?$/i", $value)) {
            return false;
        } else {
            return true;
        }
    }
    */
    /**
     * @param $url
     * @return bool|mixed|null|string
     */
    public static function __urlGetContent($url)
    {
        if (function_exists('curl_exec')) {
            try {
                $conn = curl_init($url);
                curl_setopt($conn, CURLOPT_URL, $url);
                curl_setopt($conn, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($conn, CURLOPT_FRESH_CONNECT, true);
                curl_setopt($conn, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($conn, CURLOPT_HEADER, false);
                curl_setopt($conn, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
                $url_get_contents_data = (curl_exec($conn));

                if (!curl_errno($conn)) {
                    switch ($http_code = curl_getinfo($conn, CURLINFO_HTTP_CODE)) {
                        case 200:  # OK
                            break;
                        default:
                            try {
                                $url_get_contents_data = file_get_contents($url);
                            } catch (\Exception $e) {
                                $url_get_contents_data = false;
                            }
                            break;
                    }
                }
                curl_close($conn);

            } catch (\Exception $e) {
                return null;
            }
        } elseif (function_exists('file_get_contents')) {
            try {
                $url_get_contents_data = file_get_contents($url);
            } catch (\Exception $e) {
                return false;
            }
        } elseif (function_exists('fopen') && function_exists('stream_get_contents')) {
            $handle = fopen($url, "r");
            $url_get_contents_data = stream_get_contents($handle);
        } else {
            $url_get_contents_data = false;
        }
        return $url_get_contents_data;
    }

    public static function __urlGetFileSize($url)
    {
        try {
            $conn = curl_init($url);
            curl_setopt($conn, CURLOPT_URL, $url);
            curl_setopt($conn, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($conn, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($conn, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($conn, CURLOPT_HEADER, true);
            curl_setopt($conn, CURLOPT_NOBODY, true);
            curl_setopt($conn, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($conn, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
            $data = (curl_exec($conn));

            $size = curl_getinfo($conn, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
            curl_close($conn);

            return $size;

        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * @param $date
     * @param string $format
     * @return bool
     */
    public static function __isDate($date, $format = "Y-m-d H:i:s")
    {
        $validation = new Validation();
        $validation->add('field', new DateValidator([
            'format' => $format,
            'message' => "The date is not valid"
        ]));
        $messages = $validation->validate(['field' => $date]);
        if (count($messages) > 0) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param $date
     * @return bool
     */
    public static function __isUniversalDate($date)
    {
        return self::__isDate($date, 'Y-m-d H:i:s') || self::__isDate($date, 'Y-m-d');
    }

    /**
     * @param $date
     */
    public static function __getDateBegin($date = null)
    {
        if ($date == null) {
            $dateInt = time();
        } else if (is_numeric($date) && self::__isTimeSecond($date)) {
            $dateInt = $date;
        } else {
            $dateInt = strtotime($date);
        }
        return date('Y-m-d 00:00:00', mktime(0, 0, 0, date("m", $dateInt), date("d", $dateInt), date("Y", $dateInt)));
    }

    /**
     * @param $date
     */
    public static function __getDateEnd($date)
    {
        $dateTime = date('Y-m-d', strtotime($date));
        return date('Y-m-d 23:59:59', mktime(23, 59, 59, date("m", strtotime($date)), date("d", strtotime($date)), date("Y", strtotime($date))));
    }

    /**
     * @param $date
     */
    public static function __getTimeBegin($date = null)
    {
        if ($date == null) {
            $dateInt = time();
        } else if (is_numeric($date) && self::__isTimeSecond($date)) {
            $dateInt = $date;
        } else {
            $dateInt = strtotime($date);
        }
        return mktime(0, 0, 0, date("m", $dateInt), date("d", $dateInt), date("Y", $dateInt));
    }

    /**
     * @param null $secondDate
     * @return bool
     */
    public static function __isTimeBegin($secondDate = null)
    {
        return self::__getTimeBegin($secondDate) == $secondDate;
    }

    /**
     * @param $date
     */
    public static function __getNextDate($date)
    {
        $dateTime = date('Y-m-d', strtotime($date));
        $nextDay = strtotime($dateTime) + 86400;
        return date('Y-m-d 00:00:00', $nextDay);
    }

    /**
     * @param $time
     */
    public static function __getStartTimeOfDay($dateTime = '')
    {
        if ($dateTime == '') $dateTime = time();
        if (is_numeric($dateTime) && $dateTime > 0) {
            if (Helpers::__isTimeMiliSecond($dateTime)) {
                $dateTime = self::__convertToSecond($dateTime);
            }
            return mktime(0, 0, 0, date("m", $dateTime), date("d", $dateTime), date("Y", $dateTime));
        } else {
            return mktime(0, 0, 0, date("m", strtotime($dateTime)), date("d", strtotime($dateTime)), date("Y", strtotime($dateTime)));
        }
    }

    /**
     * @param $time
     * @return false|string
     */
    public static function __convertSecondToDate($time, $format = "Y-m-d")
    {
        return date($format, $time);
    }

    /**
     * @param $time
     */
    public static function __getEndTimeOfDay($dateTime = '')
    {
        if ($dateTime == '') $dateTime = time();
        if (is_numeric($dateTime) && $dateTime > 0) {
            if (Helpers::__isTimeMiliSecond($dateTime)) {
                $dateTime = self::__convertToSecond($dateTime);
            }
            return mktime(23, 59, 59, date("m", $dateTime), date("d", $dateTime), date("Y", $dateTime));
        } else {
            return mktime(23, 59, 59, date("m", strtotime($dateTime)), date("d", strtotime($dateTime)), date("Y", strtotime($dateTime)));
        }
    }

    /**
     * @param $time
     */
    public static function __convertToSecond($time)
    {
        if (is_numeric($time)) {
            if (strlen($time) >= 13) {
                return round($time / 1000);
            } else {
                return $time;
            }
        }
    }

    /**
     * @param $time
     */
    public static function __convertDateToSecond($date)
    {
        if (self::__isTimeSecond($date)) {
            return $date;
        }
        return strtotime($date);
    }

    /**
     * @param $cc
     * @return array
     */
    public static function __parListEmailFromInput($cc)
    {
        if (is_string($cc)) {
            if (self::__isEmail($cc)) {
                return [$cc];
            }
        } elseif (is_array($cc) && count($cc) > 0) {
            $return = [];
            foreach ($cc as $ccItemValue) {

                if (is_string($ccItemValue) && self::__isEmail($ccItemValue)) {
                    $return[] = $ccItemValue;
                } elseif (is_object($ccItemValue) && property_exists($ccItemValue, 'text') && self::__isEmail($ccItemValue->text)) {
                    $return[] = $ccItemValue->text;
                } elseif (is_object($ccItemValue) && property_exists($ccItemValue, 'value') && self::__isEmail($ccItemValue->value)) {
                    $return[] = $ccItemValue->value;
                }

            }
            return $return;
        }
    }

    /**
     * @param $password
     */
    public static function __validatePassword($password)
    {
        $validation = new Validation();
        $validation->add('field', new StringLengthValidator([
            'min' => 8,
            'max' => 32,
            'messageMinimum' => 'PASSWORD_MIN_CHARACTERS_TEXT',
            'messageMaximum' => 'PASSWORD_MAX_CHARACTERS_TEXT',
        ]));
        $validation->add('field', new PasswordStrengthValidator([
            'minScore' => 3,
            'message' => 'PASSWORD_INVALID_TEXT'
        ]));
        $messages = $validation->validate(['field' => $password]);
        if (count($messages) > 0) {
            return ['success' => false, 'msg' => $messages];
        } else {
            return ['success' => true];
        }
    }

    /**
     * @param $email
     * @return array
     */
    public static function __parseEmailString($email)
    {
        $recipients = [];
        if (function_exists('mailparse_rfc822_parse_addresses')) {
            $addresses = mailparse_rfc822_parse_addresses($email);
            if (count($addresses) > 0) {
                foreach ($addresses as $add) {
                    $recipients[] = ['mail' => $add['address'], 'name' => $add['display']];
                }
            }
        } elseif (class_exists('Mail_RFC822')) {
            $Mail_RFC822 = new \Mail_RFC822();
            $addresses = $Mail_RFC822->parseAddressList($email);
            if (\PEAR::isError($addresses)) return $recipients;

            if (is_array($addresses)) {
                foreach ($addresses as $ob) {
                    $recipients[] = [
                        'mail' => isset($ob->mailbox) ? $ob->mailbox . '@' . $ob->host : '',
                        'name' => $ob->personal,
                    ];
                }
            }
        }
        return $recipients;
    }

    /**
     * @param $emailContent
     * @return string
     */
    public static function __removeSignatureFromHTML($emailContent)
    {
        $re = "#(.*)--(\s+)\<br\>#";
        if (preg_match($re, $emailContent)) {
            $arrParts = preg_split($re, $emailContent, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_pop($arrParts);
            array_pop($arrParts);
            $emailContent = implode('', $arrParts);
        }
        return $emailContent;
    }

    /**
     * @param $uuid
     * @return bool
     */
    public static function __isLabelConstant($value)
    {
        if (!is_string($value) || (preg_match('/^([0-9A-Z\_]+)$/', $value) !== 1)) {
            return false;
        }
        return true;
    }

    /**
     * @param $string
     * @return string
     */
    public static function __randomUsername($string, $randLimit = 0)
    {
        $username_parts = array_filter(explode(" ", strtolower($string)));
        //explode and lowercase name

        if (count($username_parts) >= 3) {
            $username_parts = array_slice($username_parts, 0, 3);
        } else {
            $username_parts = array_slice($username_parts, 0, 2);
        }
        //return only first two arry part
        $part1 = (!empty($username_parts[0])) ? substr($username_parts[0], 0, strlen($username_parts[0]) < 12 ? strlen($username_parts[0]) : 8) : ""; //cut first name to 8 letters
        $part2 = (!empty($username_parts[1])) ? substr($username_parts[1], 0, strlen($username_parts[1]) < 8 ? strlen($username_parts[1]) : 5) : ""; //cut second name to 5 letters
        if (isset($username_parts[2]) && $username_parts[2] != '') {
            $part3 = (!empty($username_parts[2])) ? substr($username_parts[2], 0, 5) : ""; //cut second name to 5 letters
        } else {
            $part3 = "";
        }
        $part4 = "";

        if ($randLimit > 0) {
            $part4 = rand(0, $randLimit);
        }

        if (count($username_parts) >= 3) {
            return $part1 . "." . ($part2) . "." . str_shuffle($part3);
        } else {
            return $part1 . "." . str_shuffle($part2) . $part4;
        }


        /*
        $name = preg_split("#[ .-]#", $string);
        $firstname = $name[0];
        $lastname = $name[1];

        $firstname = strtolower($firstname);
        $lastname = strtolower(substr($lastname, 0, 2));
        if ($randLimit > 0) {
            $nrRand = rand(0, 100);
            return $firstname . $lastname . $nrRand;
        }
        return $firstname . $lastname;
        */

    }

    /**
     * @param $firstname
     * @param $lastname
     * @param $company_id
     */
    public static function __createUserNickName($firstname, $lastname, $company_id)
    {
        $User = new UserExt();
        $User->setFirstname($firstname);
        $User->setLastname($lastname);
        $User->setCompanyId($company_id);
        $nickname = $User->generateNickName();
        unset($User);
        return $nickname;
    }

    /**
     * @param $uuid
     * @return bool
     */
    public static function __isCurrency($currency)
    {
        $validation = new Validation();
        $validation->add('field', new CurrencyValidator(['message' => "The currency is not valid"]));
        $messages = $validation->validate(['field' => $currency]);
        if (count($messages) > 0) {
            return false;
        } else {
            return true;
        }
    }


    /**
     * @param $number
     * @return string
     */
    public static function __numberLeadingZero($number)
    {
        if ($number <= 9999) {
            return str_pad($number, 4, '0', STR_PAD_LEFT);
        } else {
            return str_pad($number, 6, '0', STR_PAD_LEFT);
        }

    }

    /**
     * @return array
     */
    public static function __getDataTableOrderConfig()
    {
        $columns = self::__getRequestValue('columns');
        $orders = self::__getRequestValue('orders');
        $ordersConfig = [];
        /****** orders ****/
        if (is_array($orders) && count($orders) > 0) {
            foreach ($orders as $order) {
                $order = (array)$order;
                if (isset($columns[$order['column']])) {
                    $ordersConfig[] = ['field' => $columns[$order['column']]->data, "order" => $order['dir']];
                }
            }
        }
        return $ordersConfig;
    }

    /**
     * @return array
     */
    public static function __getApiOrderConfig($orders = [])
    {
        $ordersConfig = [];
        /****** orders ****/
        if (is_array($orders) && count($orders) > 0) {
            foreach ($orders as $orderConfig) {
                $orderConfig = (array)$orderConfig;
                $orderConfig['column'] = isset($orderConfig['column']) ? $orderConfig['column'] : (isset($orderConfig['field']) ? $orderConfig['field'] : null);
                if (isset($orderConfig['sort'])) {
                    $orderConfig['order'] = isset($orderConfig['sort']) ? $orderConfig['sort'] : (isset($orderConfig['order']) ? $orderConfig['order'] : null);
                }
                if (isset($orderConfig['descending'])) {
                    if (is_bool($orderConfig['descending']) && $orderConfig['descending'] == true) {
                        $orderConfig['order'] = 'desc';
                    }
                    if (is_bool($orderConfig['descending']) && $orderConfig['descending'] == false) {
                        $orderConfig['order'] = 'asc';
                    }
                }
                $ordersConfig[] = [
                    "field" => strtolower($orderConfig['column']),
                    "order" => isset($orderConfig['order']) && ($orderConfig['order'] == 'desc' || $orderConfig['order'] == 'descending') ? 'desc' : 'asc'
                ];
            }
        }
        return $ordersConfig;
    }

    public static function __getFrontendState()
    {

    }


    /** Format Size of file */
    public static function __formatBytes($size, $precision = 2)
    {
        $base = log($size, 1024);
        $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];
        return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
    }


    /**
     * @param $firstname
     * @param $lastname
     * @param $companyId
     */
    public static function __generateNickname($firstname, $lastname, $companyName = '', $companyId = 0)
    {
        //$firstname = str_replace(['-', '_', '.'], ' ', $firstname);
        $firstList = preg_split("/\s+/", $firstname);
        $firstname = reset($firstList);
        //$lastname = str_replace(['-', '_', '.'], ' ', $lastname);
        $lastList = preg_split("/\s+/", $lastname);
        $lastname = end($lastList);

        $stringName = $firstname . " " . $lastname . " " . ($companyName != '' ? $companyName : '');
        $stringName = TextHelper::__stripAccents($stringName);
        $stringName = str_replace('-', ' ', \Phalcon\Utils\Slug::generate($stringName));

        if ($companyId > 0) {
            $sequence = SequenceExt::PREFIX . "_user_nickname_" . $companyId;
            $count = SequenceHelper::getSeqNextVal($sequence);
        }
        $nickName = self::__randomUsername($stringName, 1) . (isset($count) && $count > 0 ? $count : '');
        $nickName = str_replace('-', '.', $nickName);

        return $nickName;
    }

    /**
     * @param $var
     * @return bool
     */
    public static function __isNull($var)
    {
        if (is_numeric($var) && $var == 0) return false;

        if (is_object($var)) {
            if (method_exists($var, 'getValue') || $var instanceof \Phalcon\Db\RawValue) {
                return $var->getValue() == '' || $var->getValue() == null || $var->getValue() == 'NULL' || $var->getValue() == 'EMPTY';
            }
        }
        return $var == "" || is_null($var) || $var == "null" || empty($var);
    }


    /**
     * @param string $delimiter
     * @param $items
     * @return string
     */
    public static function __convertItemListToString($delimiter = ',', $items)
    {
        if (is_array($items) && count($items)) {
            return implode($delimiter, $items);
        }
    }

    /**
     * @return mixed
     */
    public static function __convertStringToArray($string)
    {
        if (self::__isJsonValid($string)) {
            return is_string($string) ? json_decode($string, true) : [];
        }
        return [];
    }

    /**
     * @return mixed
     */
    public static function __convertStringToObjectArray($string, $fieldName = "text")
    {
        $array = self::__convertStringToArray($string);
        $tagsObjectList = [];
        if (is_array($array) && count($array)) {
            foreach ($array as $item) {
                $tagsObjectList[] = [$fieldName => $item];
            }
        }
        return $tagsObjectList;
    }

    /**
     * @param $time
     * @param string $format
     * @return false|string
     */
    public static function __convertTimeToUTC($time, $format = 'Y-m-d H:i:s')
    {
        $time = self::__convertToSecond($time);
        if ($time > 0) {
            return date($format, $time);
        }
    }

    /**
     * @param $time
     * @param string $unit
     * @return bool
     */
    public static function __isTime($time, $unit = "milisecond")
    {
        if (is_numeric($time)) {
            if (strlen($time) >= 13 && $unit == "milisecond") {
                return true;
            }
            if (strlen($time) < 13 && strlen($time) >= 10 && $unit == "second") {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $time
     * @param string $unit
     * @return bool
     */
    public static function __isTimeSecond($time)
    {
        return self::__isTime($time, "second");
    }

    /**
     * @param $time
     * @param string $unit
     * @return bool
     */
    public static function __isTimeMiliSecond($time)
    {
        return self::__isTime($time, "milisecond");
    }

    /**
     * @param $filename
     * @return bool|string
     */
    public static function __getFileNameWithoutExtension($filename)
    {
        return substr($filename, 0, (strrpos($filename, ".")));
    }

    /**
     * @param $filename
     * @return bool|string
     */
    public static function __getExtensionFromFileName($filename)
    {
        return ltrim(substr($filename, strrpos($filename, '.', -1), strlen($filename)), ".");
    }

    /**
     * @param $string
     * @return bool
     */
    public static function __isBase64($string)
    {
        return (bool)preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $string);
    }

    public static function getClassFromString($string)
    {
        switch ($string) {
            case "relocation":
                return "\SMXD\Application\Models\RelocationExt";
            case "contract":
                return "\SMXD\Application\Models\ContractExt";
            case "assignment" :
                return "\SMXD\Application\Models\AssignmentExt";
            case "employee":
                return "\SMXD\Application\Models\EmployeeExt";
        }
        return "";
    }

    public static function convertToAscii()
    {

    }

    /**
     * @return array
     */
    public static function __getNotFoundResult()
    {
        return ['success' => true, 'message' => 'DATA_NOT_FOUND_TEXT'];
    }

    /**
     * @return mixed
     */
    public static function __getDynamoDbClient()
    {
        $di = Di::getDefault();
        $appConfig = $di->get('appConfig');
        if (SMXDDynamoORM::__isLocal() == true) {
            $dynamodbClient = $di->get('aws')->createDynamoDb([
                'region' => 'us-east-1',
                'version' => 'latest',
                'endpoint' => Di::getDefault()->getShared('appConfig')->aws->dynamoEndpointUrl,
            ]);
        } else {
            $dynamodbClient = $di->get('aws')->createDynamoDb();
        }

        return $dynamodbClient;
    }

    /**
     * @return mixed
     */
    public static function __uuid()
    {
        $random = new \Phalcon\Security\Random();
        return $random->uuid();
    }

    /**
     * @return mixed
     */
    public static function __passwordHash(string $string)
    {
        $security = new Security();
        return $security->hash($string);
    }

    /**
     * @return string
     */
    public static function __hash()
    {
        return strtoupper(md5(self::__uuid()));
    }

     /**
     * @return string
     */
    public static function __generateSecret()
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * @param $var
     * @return bool
     */
    public static function __isValidAwsArn($var)
    {
        if ($var == null || $var == '') return false;
        return true;
    }

    /**
     * @param $string
     * @return bool
     */
    public static function __isStringHasSpace($string)
    {
        if ($string != trim($string)) {
            return true;
        }
        if ($string == trim($string) && strpos($string, ' ') !== false) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get day-month format by company
     * EU : d-m
     * US : m-d
     */
    public static function __getDayMonthFormatByCompany($companyId)
    {
        $result = 'd-m';
        if ($companyId && $company = \SMXD\Application\Models\Company::findFirstById($companyId)) {
            if ($company->getDateFormat() && $company->getDateFormat() == 'MM/DD/YYYY') {
                $result = 'm-d';
            }
        }
        return $result;
    }

    /**
     * Get day-month format by company
     * EU : d-m
     * US : m-d
     */
    public static function __getDayMonthYearFormatByCompany($companyId)
    {
        $result = 'd-m-y';
        if ($companyId && $company = \SMXD\Application\Models\Company::findFirstById($companyId)) {
            if ($company->getDateFormat() && $company->getDateFormat() == 'MM/DD/YYYY') {
                $result = 'm-d-y';
            }
        }
        return $result;
    }

    /**
     * Get end convert company config date format for SQL query
     * DD/MM/YYYY -> dd/MM/yyyy
     * MM/DD/YYYY -> MM/dd/yyyy
     */
    public static function __getCompanyDateFormatSql($companyId)
    {
        $result = 'dd/MM/yyyy';
        if ($companyId && $company = \SMXD\Application\Models\Company::findFirstById($companyId)) {
            if ($company->getDateFormat() && $company->getDateFormat() == 'MM/DD/YYYY') {
                $result = 'MM/dd/yyyy';
            }
        }
        return $result;
    }

    /**
     * Converts an array to XML
     * @param array $array
     * @param SimpleXMLElement $xml
     * @param string $child_name
     *
     * @return SimpleXMLElement $xml
     */
    public static function __arrayToXML($array, $xml = false, $child_name = null)
    {
        if ($xml === false) {
            $xml = new \SimpleXMLElement('<result/>');
        }

        if ($child_name == null || $child_name == "") {
            $child_name = 'result';
        }

        if (is_array($child_name)) {
            $child_name = 'item';
        }

        foreach ($array as $k => $v) {

            if (is_object($v) && method_exists($v, 'toArray')) {
                $v = $v->toArray();
            }
            if (is_array($v)) {
                if (is_int($k)) {
                    self::__arrayToXML($v, $xml->addChild($child_name), $v);
                } else if (is_string($k)) {
                    self::__arrayToXML($v, $xml->addChild(strtolower($k)), $child_name);
                }
            } else {
                if (is_bool($v)) {
                    if ($v === true) $v = 'true';
                    if ($v === false) $v = 'false';
                }
                (is_int($k)) ? $xml->addChild($child_name, $v) : (strpos($k, 'url') !== false ? $xml->addChild(strtolower($k), htmlspecialchars($v)) : $xml->addChild(strtolower($k), $v));
            }
        }

        return $xml->asXML();
    }

    /**
     * @param $sex
     */
    public static function __getSexIntValue($sex)
    {
        if (is_numeric($sex)) {
            if ($sex === 1 || $sex == "1") return 1;
            if ($sex === 0 || $sex == "0") return 0;
            if ($sex === -1 || $sex == "-1") return -1;
            return null;
        }

        if (is_string($sex)) {
            if ($sex === 'M') return 1;
            if ($sex === 'F') return 0;
            if ($sex === 'O') return -1;
            return null;
        }

        return null;
    }

    /**
     * Clean array and remove empty item
     * @param $items
     * @return array
     */
    public static function __cleanArray($items, $compareFields = [])
    {
        return array_filter($items, function ($value) {
            return !is_null($value) && $value !== '';
        });

    }

    /**
     * Clean array and compare with followings fieldes
     * @param $items
     * @return array
     */
    public static function __cleanCompareArray($items, $compareFields = [])
    {
        if (is_array($compareFields) && count($compareFields) > 0) {
            foreach ($items as $itemFieldName => $itemFieldValue) {
                if (Helpers::__isNull($itemFieldValue) && Helpers::__existCustomValue($itemFieldName, $compareFields) == false) {
                    unset($items[$itemFieldName]);
                }
            }
        }
    }

    /**
     * @param array $array
     * @param bool $return_first
     * @param bool $return_by_key
     * @return array|mixed
     */
    public static function getDupKeys(array $array, $return_first = true, $return_by_key = true)
    {
        $seen = array();
        $dups = array();

        foreach ($array as $k => $v) {
            $vk = $return_by_key ? $v : 0;
            if (!array_key_exists($v, $seen)) {
                $seen[$v] = $k;
                continue;
            }
            if ($return_first && !array_key_exists($v, $dups)) {
                $dups[$vk][] = $seen[$v];
            }
            $dups[$vk][] = $k;
        }
        return $return_by_key ? $dups : $dups[0];
    }

    /**
     * @param $error
     */
    public static function __trackError($error)
    {
        \Sentry\captureException($error);
    }

    /**
     * @param $name
     * @return mixed
     */
    public static function __getQuery($name)
    {
        $name = trim($name);
        $request = self::__getSharedRequest();
        $valFormRequest = $request->getQuery($name);
        if (isset($valFormRequest) && !is_null($valFormRequest)) {
            return $valFormRequest;
        }
    }

    /**
     * Decrypt data from a CryptoJS json encoding string
     *
     * @param mixed $cipherText
     * @param mixed $password
     * @param mixed $method
     * @return mixed
     */
    public static function __cryptoDecrypt($cipherText, $password, $method = "AES-256-CBC")
    {
        if (!$password) {
            $password = getenv('CRYPTO_PASSWORD');
        }

        $iv_size = openssl_cipher_iv_length($method);
        $data = explode(":", $cipherText);
        if (!isset($data[0]) || !isset($data[1])) {
            return null;
        }
        $iv = hex2bin($data[0]);
        $ciphertext = hex2bin($data[1]);
        return openssl_decrypt($ciphertext, $method, $password, OPENSSL_RAW_DATA, $iv);
    }

    /**
     * Encrypt value to a cryptojs compatiable json encoding string
     *
     * @param mixed $message
     * @param mixed $password
     * @param mixed $method
     * @return string
     */
    public static function __cryptoEncrypt($message, $password = null, $method = "AES-128-CBC")
    {
        if (OPENSSL_VERSION_NUMBER <= 268443727) {
            throw new \RuntimeException('OpenSSL Version too old, vulnerability to Heartbleed');
        }

        if (!$password) {
            $password = getenv('CRYPTO_PASSWORD');
        }

        $iv_size = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($iv_size);
        $ciphertext = openssl_encrypt($message, $method, $password, OPENSSL_RAW_DATA, $iv);
        $ciphertext_hex = bin2hex($ciphertext);
        $iv_hex = bin2hex($iv);
        return "$iv_hex:$ciphertext_hex";
    }

    /**
     * Allow add conditions from filter config
     * Function set filter operator
     * @param &queryBuilder
     * @param int $filterConfigId
     * @param boolean $isTmp
     * @param string $target
     * @param array $tableField
     * @param array $dataType
     * @param string $user_uuid
     * @return mixed
     */

    static function __addFilterConfigConditions(&$queryBuilder, $filterConfigId, $isTmp = false, $target = null, $tableField = [], $dataType = [], $user_uuid = '')
    {
        if ($isTmp) {
            $di = \Phalcon\DI::getDefault();
            $cacheManager = $di->get('cache');
            $aFilterConfigItem = $cacheManager->get('TMP_ITEMS_FILTER_' . $filterConfigId);
        } else {
            $aOptions = [
                'filter_config_id' => $filterConfigId,
                'target' => $target
            ];
            $oFilterConfigItems = FilterConfigItemExt::listJoinByCriteria($aOptions);
            $filterConfigData = $oFilterConfigItems['data'];
            if (is_object($oFilterConfigItems['data'])) {
                $filterConfigData = $oFilterConfigItems['data']->toArray();
            }

            $aFilterConfigItem = $filterConfigData;
        }
        $aTableFieldByName = $tableField;

        if ($aFilterConfigItem == null || count($aFilterConfigItem) == 0) {
            return null;
        }

        foreach ($aFilterConfigItem as $item) {
            $sTableField = isset($aTableFieldByName[$item['field_name']]) ? $aTableFieldByName[$item['field_name']] : null;
            $sField = strtolower(str_replace(' ', '', $item['field_name']));

            if ($sField && $sTableField && $item['operator']) {
                switch ($item['operator']) {
                    case FilterConfigExt::OPERATOR_EQUAL :
                        if($item['field_name'] == 'INITIATION_STATUS_TEXT'){
                            if(is_string($item['value'])){
                                switch ($item['value']){
                                    case 'accepted':
                                        $queryBuilder->andwhere("AssignmentRequest.status = 2 AND AssignmentRequest.relocation_id is NULL");
                                        break;
                                    case 'cancelled':
                                        $queryBuilder->andwhere("AssignmentRequest.status = 2 AND AssignmentRequest.relocation_id > 0 AND Relocation.status = -1");
                                        break;
                                    case 'todo':
                                        $queryBuilder->andwhere("AssignmentRequest.status = 2 AND AssignmentRequest.relocation_id > 0 AND Relocation.status = 0");
                                        break;
                                    case 'in_progress':
                                        $queryBuilder->andwhere("AssignmentRequest.status = 2 AND AssignmentRequest.relocation_id > 0 AND Relocation.status = 2");
                                        break;
                                    case 'completed':
                                        $queryBuilder->andwhere("AssignmentRequest.status = 2 AND AssignmentRequest.relocation_id > 0 AND Relocation.status = 4");
                                        break;
                                    default:
                                        $queryBuilder->andWhere($sTableField . " = :" . $sField . ":", [
                                            $sField => $item['value']
                                        ]);
                                        break;
                                }
                            }elseif (is_array($item['value'])){
                                switch ($item['value']['name']){
                                    case 'PENDING_TEXT':
                                        $queryBuilder->andwhere("AssignmentRequest.status = 0");
                                        break;
                                    case 'REJECTED_TEXT':
                                        $queryBuilder->andwhere("AssignmentRequest.status = 1");
                                        break;
                                    case 'ACCEPTED_TEXT':
                                        $queryBuilder->andwhere("AssignmentRequest.status = 2 AND AssignmentRequest.relocation_id is NULL");
                                        break;
                                    case 'CANCELLED_TEXT':
                                        $queryBuilder->andwhere("AssignmentRequest.status = 2 AND AssignmentRequest.relocation_id > 0 AND Relocation.status = -1");
                                        break;
                                    case 'TODO_TEXT':
                                        $queryBuilder->andwhere("AssignmentRequest.status = 2 AND AssignmentRequest.relocation_id > 0 AND Relocation.status = 0");
                                        break;
                                    case 'IN_PROGRESS_TEXT':
                                        $queryBuilder->andwhere("AssignmentRequest.status = 2 AND AssignmentRequest.relocation_id > 0 AND Relocation.status = 2");
                                        break;
                                    case 'COMPLETED_TEXT':
                                        $queryBuilder->andwhere("AssignmentRequest.status = 2 AND AssignmentRequest.relocation_id > 0 AND Relocation.status = 4");
                                        break;
                                    default:
                                        break;
                                }
                            }

                        }else{
                            $queryBuilder->andWhere($sTableField . " = :" . $sField . ":", [
                                $sField => $item['value']
                            ]);
                        }
                        break;
                    case FilterConfigExt::OPERATOR_NOT_EQUAL :
                        $queryBuilder->andWhere($sTableField . " != :" . $sField . ":", [
                            $sField => $item['value']
                        ]);
                        break;
                    case FilterConfigExt::OPERATOR_BEFORE :
                        if (($item['field_name'] == 'START_DATE_TEXT' || $item['field_name'] == 'END_DATE_TEXT') && $target == FilterConfigExt::RELOCATION_SERVICE_FILTER_TARGET) {
                            $queryBuilder->leftjoin('\SMXD\Gms\Models\ServiceEventValue', 'RelocationServiceCompany.id = ServiceEventValueBefore.relocation_service_company_id', 'ServiceEventValueBefore');
                            $queryBuilder->leftjoin('\SMXD\Gms\Models\ServiceEvent', 'ServiceEventValueBefore.service_event_id = ServiceEventBefore.id', 'ServiceEventBefore');
                        }

                        if (count($dataType) > 0 && isset($dataType[strtoupper($sField)]) && ($dataType[strtoupper($sField)] == 'timestamp' || $dataType[strtoupper($sField)] == 'int')) {
                            $begin_of_day = strtotime("yesterday", time());
                            $endOfDay = strtotime("tomorrow", $begin_of_day) - 1;
                            $date = strtotime('-' . $item['value'] . ' days', $endOfDay);

                            if (($item['field_name'] == 'START_DATE_TEXT' || $item['field_name'] == 'END_DATE_TEXT') && $target == FilterConfigExt::RELOCATION_SERVICE_FILTER_TARGET) {
                                $queryBuilder->andwhere('ServiceEventBefore.code = :code: AND ServiceEventValueBefore.value >= :begin_date: AND ServiceEventValueBefore.value <= :end_date:', [
                                    'code' => $item['field_name'] == 'START_DATE_TEXT' ? 'SERVICE_START' : 'SERVICE_END',
                                    'begin_date' => $date,
                                    'end_date' => $endOfDay
                                ]);
                            } else {
                                $queryBuilder->andWhere($sTableField . " >= '" . $date . "' AND " . $sTableField . " <= '" . $endOfDay . "'");
                            }
                        } else {
                            $begin_of_day = strtotime("yesterday", time());
                            $endOfDay = date('Y-m-d', strtotime("tomorrow", $begin_of_day) - 1);
                            $date = date('Y-m-d', strtotime('-' . strval($item['value']) . ' days', strtotime($endOfDay)));

                            if (($item['field_name'] == 'START_DATE_TEXT' || $item['field_name'] == 'END_DATE_TEXT') && $target == FilterConfigExt::RELOCATION_SERVICE_FILTER_TARGET) {
                                $queryBuilder->andwhere('ServiceEventBefore.code = :code: AND ServiceEventValueBefore.value >= :begin_date: AND ServiceEventValueBefore.value <= :end_date:', [
                                    'code' => $item['field_name'] == 'START_DATE_TEXT' ? 'SERVICE_START' : 'SERVICE_END',
                                    'begin_date' => $date,
                                    'end_date' => Helpers::__getDateEnd($endOfDay)
                                ]);
                            } else {
                                $queryBuilder->andWhere($sTableField . " >= '" . $date . "' AND " . $sTableField . " <= '" . Helpers::__getDateEnd($endOfDay) . "'");

                            }
                        }
                        break;
                    case FilterConfigExt::OPERATOR_AFTER :
                        if (($item['field_name'] == 'START_DATE_TEXT' || $item['field_name'] == 'END_DATE_TEXT') && $target == FilterConfigExt::RELOCATION_SERVICE_FILTER_TARGET) {
                            $queryBuilder->leftjoin('\SMXD\Gms\Models\ServiceEventValue', 'RelocationServiceCompany.id = ServiceEventValueAfter.relocation_service_company_id', 'ServiceEventValueAfter');
                            $queryBuilder->leftjoin('\SMXD\Gms\Models\ServiceEvent', 'ServiceEventValueAfter.service_event_id = ServiceEventAfter.id', 'ServiceEventAfter');
                        }
                        if (count($dataType) > 0 && isset($dataType[strtoupper($sField)]) && ($dataType[strtoupper($sField)] == 'timestamp' || $dataType[strtoupper($sField)] == 'int')) {
                            $begin_of_day = strtotime("yesterday", time());
                            $endOfDay = strtotime("tomorrow", $begin_of_day) - 1;
                            $date = strtotime('+' . $item['value'] . ' days', $endOfDay);
                            if (($item['field_name'] == 'START_DATE_TEXT' || $item['field_name'] == 'END_DATE_TEXT') && $target == FilterConfigExt::RELOCATION_SERVICE_FILTER_TARGET) {
                                $queryBuilder->andwhere('ServiceEventAfter.code = :code: AND ServiceEventValueAfter.value >= :begin_date: AND ServiceEventValueAfter.value <= :end_date:', [
                                    'code' => $item['field_name'] == 'START_DATE_TEXT' ? 'SERVICE_START' : 'SERVICE_END',
                                    'begin_date' => $begin_of_day,
                                    'end_date' => $date
                                ]);
                            } else {
                                $queryBuilder->andWhere($sTableField . " >= '" . $begin_of_day . "' AND " . $sTableField . " <= '" . $date . "'");
                            }
                        } else {
                            $begin_of_day = strtotime("yesterday", time());
                            $endOfDay = strtotime("tomorrow", $begin_of_day) - 1;
                            $date = strtotime('+' . $item['value'] . ' days', $endOfDay);

                            if (($item['field_name'] == 'START_DATE_TEXT' || $item['field_name'] == 'END_DATE_TEXT') && $target == FilterConfigExt::RELOCATION_SERVICE_FILTER_TARGET) {
                                $queryBuilder->andwhere('ServiceEventAfter.code = :code: AND ServiceEventValueAfter.value >= :begin_date: AND ServiceEventValueAfter.value <= :end_date:', [
                                    'code' => $item['field_name'] == 'START_DATE_TEXT' ? 'SERVICE_START' : 'SERVICE_END',
                                    'begin_date' => date('Y-m-d', $begin_of_day),
                                    'end_date' => Helpers::__getDateEnd(date('Y-m-d', $date))
                                ]);
                            } else {
                                $queryBuilder->andWhere($sTableField . " >= '" . date('Y-m-d', $begin_of_day) . "' AND " . $sTableField . " <= '" . Helpers::__getDateEnd(date('Y-m-d', $date)) . "'");
                            }

                        }
                        break;
                    case FilterConfigExt::OPERATOR_OLDER_THAN :
                        if (($item['field_name'] == 'START_DATE_TEXT' || $item['field_name'] == 'END_DATE_TEXT') && $target == FilterConfigExt::RELOCATION_SERVICE_FILTER_TARGET) {
                            $queryBuilder->leftjoin('\SMXD\Gms\Models\ServiceEventValue', 'RelocationServiceCompany.id = ServiceEventValueBefore.relocation_service_company_id', 'ServiceEventValueBefore');
                            $queryBuilder->leftjoin('\SMXD\Gms\Models\ServiceEvent', 'ServiceEventValueBefore.service_event_id = ServiceEventBefore.id', 'ServiceEventBefore');
                        }

                        if (count($dataType) > 0 &&  isset($dataType[strtoupper($sField)]) && ($dataType[strtoupper($sField)] == 'timestamp' || $dataType[strtoupper($sField)] == 'int')) {
                            $begin_of_day = strtotime('-' . $item['value'] . ' days', time());

                            if (($item['field_name'] == 'START_DATE_TEXT' || $item['field_name'] == 'END_DATE_TEXT') && $target == FilterConfigExt::RELOCATION_SERVICE_FILTER_TARGET) {
                                $queryBuilder->andwhere('ServiceEventBefore.code = :code: AND ServiceEventValueBefore.value < :begin_of_day:', [
                                    'code' => $item['field_name'] == 'START_DATE_TEXT' ? 'SERVICE_START' : 'SERVICE_END',
                                    'begin_of_day' => $begin_of_day,
                                ]);
                            } else {
                                $queryBuilder->andWhere($sTableField . " < '" . $begin_of_day . "'");
                            }
                        } else {
                            $begin_of_day = strtotime('-' . strval($item['value']) . ' days', time());
                            $date = date('Y-m-d', $begin_of_day - 1);

                            if (($item['field_name'] == 'START_DATE_TEXT' || $item['field_name'] == 'END_DATE_TEXT') && $target == FilterConfigExt::RELOCATION_SERVICE_FILTER_TARGET) {
                                $queryBuilder->andwhere('ServiceEventBefore.code = :code: AND ServiceEventValueBefore.value < :begin_date', [
                                    'code' => $item['field_name'] == 'START_DATE_TEXT' ? 'SERVICE_START' : 'SERVICE_END',
                                    'begin_date' => $date,
                                ]);
                            } else {
                                $queryBuilder->andWhere($sTableField . " < '" . $date . "'");

                            }
                        }
                        break;
                    case FilterConfigExt::OPERATOR_TODAY :
                        $offset = ApplicationModel::__getTimezoneOffset();
                        if (!is_numeric($offset)) {
                            $offset = 0;
                        }
                        if (($item['field_name'] == 'START_DATE_TEXT' || $item['field_name'] == 'END_DATE_TEXT') && $target == FilterConfigExt::RELOCATION_SERVICE_FILTER_TARGET) {
                            $queryBuilder->leftjoin('\SMXD\Gms\Models\ServiceEventValue', 'RelocationServiceCompany.id = ServiceEventValueBefore.relocation_service_company_id', 'ServiceEventValueBefore');
                            $queryBuilder->leftjoin('\SMXD\Gms\Models\ServiceEvent', 'ServiceEventValueBefore.service_event_id = ServiceEventBefore.id', 'ServiceEventBefore');
                        }

                        if (count($dataType) > 0 && isset($dataType[strtoupper($sField)]) && ($dataType[strtoupper($sField)] == 'timestamp' || $dataType[strtoupper($sField)] == 'int')) {
                            $currentTime = time() + ($offset * 60);
                            $currentDate = date('Y-m-d', $currentTime);
                            $begin_of_day = strtotime($currentDate);
                            $endOfDay = Helpers::__getEndTimeOfDay($begin_of_day);

                            if (($item['field_name'] == 'START_DATE_TEXT' || $item['field_name'] == 'END_DATE_TEXT') && $target == FilterConfigExt::RELOCATION_SERVICE_FILTER_TARGET) {
                                $queryBuilder->andwhere('ServiceEventBefore.code = :code: AND ServiceEventValueBefore.value >= :begin_date: AND ServiceEventValueBefore.value <= :end_date:', [
                                    'code' => $item['field_name'] == 'START_DATE_TEXT' ? 'SERVICE_START' : 'SERVICE_END',
                                    'begin_date' => $begin_of_day,
                                    'end_date' => $endOfDay
                                ]);
                            } else {
                                $queryBuilder->andWhere($sTableField . " >= '" . $begin_of_day . "' AND " . $sTableField . " <= '" . $endOfDay . "'");
                            }
                        } else {
                            $currentTime = time() + ($offset * 60);
                            $currentDate = date('Y-m-d', $currentTime);
                            if (($item['field_name'] == 'START_DATE_TEXT' || $item['field_name'] == 'END_DATE_TEXT') && $target == FilterConfigExt::RELOCATION_SERVICE_FILTER_TARGET) {
                                $queryBuilder->andwhere('ServiceEventBefore.code = :code: AND ServiceEventValueBefore.value >= :begin_date: AND ServiceEventValueBefore.value <= :end_date:', [
                                    'code' => $item['field_name'] == 'START_DATE_TEXT' ? 'SERVICE_START' : 'SERVICE_END',
                                    'begin_date' => Helpers::__getDateBegin($currentTime),
                                    'end_date' => Helpers::__getDateEnd($currentDate)
                                ]);
                            } else {
                                $queryBuilder->andWhere($sTableField . " >= '" . Helpers::__getDateBegin($currentTime) . "' AND " . $sTableField . " <= '" . Helpers::__getDateEnd($currentDate) . "'");

                            }
                        }
                        break;
                    case FilterConfigExt::OPERATOR_CONTAIN :
                        if ($item['field_name'] == 'TAG_TEXT') {
                            $data = $item['value'];
                            if (!is_array($item['value'])) {
                                $data = explode(',', $item['value']);
                            }
                            $tagCondition = '';
                            $tagBinding = [];
                            $tagFirst = true;
                            foreach ($data as $key => $tag) {
                                $tagCondition .= $tagFirst ? "$sTableField LIKE :{$key}:" : " OR $sTableField LIKE :{$key}:";
                                $tagBinding[$key] = '%' . $tag . '%';
                                $tagFirst = false;
                            }
                            $queryBuilder->andwhere($tagCondition, $tagBinding);
                        } else {
                            $queryBuilder->andWhere($sTableField . ' LIKE "%' . $item['value'] . '%"');
                        }
                        break;
                    case FilterConfigExt::OPERATOR_IS_TEXT :
                        $queryBuilder->andWhere($sTableField);
                        break;
                    case FilterConfigExt::OPERATOR_IS_NULL :

                        if (isset($dataType[strtoupper($sField)]) && $dataType[strtoupper($sField)] == 'int') {
                            $queryBuilder->andWhere($sTableField . ' = 0');
                        } else {
                            $queryBuilder->andWhere($sTableField . ' IS NULL');
                        }
                        break;
                    case FilterConfigExt::OPERATOR_NOT_NULL :
                        if (isset($dataType[strtoupper($sField)]) && $dataType[strtoupper($sField)] == 'int') {
                            $queryBuilder->andWhere($sTableField . ' = 1');
                        } else {
                            $queryBuilder->andWhere($sTableField . ' IS NOT NULL');
                        }
                        break;
                    case FilterConfigExt::OPERATOR_IN :
                        $data = isset($item['value']) ? $item['value'] : [];
                        if (!is_array($item['value'])) {
                            $data = explode(',', $item['value']);
                        }

                        if ($item['field_name'] == 'SERVICE_ARRAY_TEXT' && $target == FilterConfigExt::TASK_FILTER_TARGET) {
//                            $queryBuilder->leftjoin('\SMXD\Application\Models\RelocationServiceCompanyExt', 'RelocationServiceCompany.uuid = Task.object_uuid', 'RelocationServiceCompany');
                            $queryBuilder->andwhere('RelocationServiceCompany.status = :relocation_service_active:', [
                                'relocation_service_active' => RelocationServiceCompanyExt::STATUS_ACTIVE
                            ]);
                        }

                        if ($item['field_name'] == 'SERVICE_ARRAY_TEXT' && $target == FilterConfigExt::RELOCATION_FILTER_TARGET) {
                            $queryBuilder->leftjoin('\SMXD\Application\Models\RelocationServiceCompanyExt', 'RelocationServiceCompanyFilter.relocation_id = Relocation.id', 'RelocationServiceCompanyFilter');
                            $queryBuilder->andwhere('RelocationServiceCompanyFilter.status = :relocation_service_active:', [
                                'relocation_service_active' => RelocationServiceCompanyExt::STATUS_ACTIVE
                            ]);
                        }

                        if ($item['field_name'] == 'STATUS_ARRAY_TEXT' && $target == FilterConfigExt::RELOCATION_SERVICE_FILTER_TARGET && count($data) > 0 && in_array((string)RelocationExt::STATUS_CANCELED, $data)) {
                            $queryBuilder->andwhere('Relocation.status = :relocation_cancelled: OR RelocationServiceCompany.progress IN ({custom_progresses:array})', [
                                'custom_progresses' => $data,
                                'relocation_cancelled' => RelocationExt::STATUS_CANCELED
                            ]);
                            goto end_operator_in;
                        }

                        if ($item['field_name'] == 'STATUS_ARRAY_TEXT' && $target == FilterConfigExt::RELOCATION_SERVICE_FILTER_TARGET && count($data) > 0 && !in_array(RelocationExt::STATUS_CANCELED, $data)) {
                            $queryBuilder->andwhere('Relocation.status != :relocation_cancelled: AND RelocationServiceCompany.progress IN ({custom_progresses:array})', [
                                'custom_progresses' => $data,
                                'relocation_cancelled' => RelocationExt::STATUS_CANCELED
                            ]);
                        }

                        if ($item['field_name'] == 'ACCOUNT_ARRAY_TEXT' && $target == FilterConfigExt::TASK_FILTER_TARGET) {
//                            $queryBuilder->innerJoin('\SMXD\Application\Models\AssignmentExt', 'Assignment.id = Task.assignment_id', 'Assignment');
                            $queryBuilder->andwhere('Assignment.archived = :assignment_not_archived:', [
                                'assignment_not_archived' => AssignmentExt::ARCHIVED_NO
                            ]);
                        }

                        if ($item['field_name'] == 'OWNER_ARRAY_TEXT' && isset($tableField['PREFIX_DATA_OWNER_TYPE']) && $tableField['PREFIX_DATA_OWNER_TYPE']) {
                            $queryBuilder->innerJoin('\SMXD\Application\Models\DataUserMemberExt', $tableField['PREFIX_OBJECT_UUID'] . ' = ' . 'Owner.object_uuid', 'Owner');
                            $queryBuilder->andWhere($tableField['PREFIX_DATA_OWNER_TYPE'] . ' = ' . DataUserMemberExt::MEMBER_TYPE_OWNER);
                        }

                        if ($item['field_name'] == 'REPORTER_ARRAY_TEXT' && isset($tableField['PREFIX_DATA_REPORTER_TYPE']) && $tableField['PREFIX_DATA_REPORTER_TYPE']) {
                            $queryBuilder->innerJoin('\SMXD\Application\Models\DataUserMemberExt', $tableField['PREFIX_OBJECT_UUID'] . ' = ' . 'Reporter.object_uuid', 'Reporter');
                            $queryBuilder->andWhere($tableField['PREFIX_DATA_REPORTER_TYPE'] . ' = ' . DataUserMemberExt::MEMBER_TYPE_REPORTER);
                        }

                        $queryBuilder->andWhere($sTableField . ' IN ({' . $sField . ':array})', [
                            $sField => $data
                        ]);
                        end_operator_in:
                        break;
                    case FilterConfigExt::OPERATOR_BETWEEN :
                        if (($item['field_name'] == 'START_DATE_TEXT' || $item['field_name'] == 'END_DATE_TEXT') && $target == FilterConfigExt::RELOCATION_SERVICE_FILTER_TARGET) {
                            $queryBuilder->leftjoin('\SMXD\Gms\Models\ServiceEventValue', 'RelocationServiceCompany.id = ServiceEventValueBetween.relocation_service_company_id', 'ServiceEventValueBetween');
                            $queryBuilder->leftjoin('\SMXD\Gms\Models\ServiceEvent', 'ServiceEventValueBetween.service_event_id = ServiceEventBetween.id', 'ServiceEventBetween');
                        }

                        $dateRange = $item['value'];
                        if (is_string($dateRange)) {
                            if ($dateRange === 'TODAY') {
                                if (count($dataType) > 0 && isset($dataType[strtoupper($sField)]) && ($dataType[strtoupper($sField)] == 'timestamp' || $dataType[strtoupper($sField)] == 'int')) {
                                    $begin_of_day = strtotime("yesterday", time());
                                    $endOfDay = strtotime("tomorrow", $begin_of_day) - 1;

                                    if (($item['field_name'] == 'START_DATE_TEXT' || $item['field_name'] == 'END_DATE_TEXT') && $target == FilterConfigExt::RELOCATION_SERVICE_FILTER_TARGET) {
                                        $queryBuilder->andwhere('ServiceEventBetween.code = :code: AND ServiceEventValueBetween.value >= :begin_date: AND ServiceEventValueBetween.value < :end_date:', [
                                            'code' => $item['field_name'] == 'START_DATE_TEXT' ? 'SERVICE_START' : 'SERVICE_END',
                                            'begin_date' => $begin_of_day,
                                            'end_date' => $date
                                        ]);
                                    } else {
                                        $queryBuilder->andWhere($sTableField . " >= " . $begin_of_day);
                                        $queryBuilder->andWhere($sTableField . " < " . $endOfDay);
                                    }

                                } else {
                                    if (($item['field_name'] == 'START_DATE_TEXT' || $item['field_name'] == 'END_DATE_TEXT') && $target == FilterConfigExt::RELOCATION_SERVICE_FILTER_TARGET) {
                                        $queryBuilder->andwhere('ServiceEventBetween.code = :code: AND ServiceEventValueBetween.value >= :begin_date: AND ServiceEventValueBetween.value < :end_date:', [
                                            'code' => $item['field_name'] == 'START_DATE_TEXT' ? 'SERVICE_START' : 'SERVICE_END',
                                            'begin_date' => Helpers::__getDateBegin(date('Y-m-d')),
                                            'end_date' => Helpers::__getDateEnd(date('Y-m-d'))
                                        ]);

                                    } else {
                                        $queryBuilder->andWhere($sTableField . " >= '" . Helpers::__getDateBegin(date('Y-m-d')) . "'");
                                        $queryBuilder->andWhere($sTableField . " < '" . Helpers::__getDateEnd(date('Y-m-d')) . "'");
                                    }
                                }
                                break;
                            } else {
                                $dateRange = json_decode($dateRange, true);
                            }

                        }
                        if (count($dataType) > 0 && isset($dataType[strtoupper($sField)]) && ($dataType[strtoupper($sField)] == 'timestamp' || $dataType[strtoupper($sField)] == 'int')) {
                            $User = UserExt::findFirstByUuid($user_uuid);
                            if ($User) {
                                $time_zone = $User->getCompany()->getTimeZoneConfig() ? $User->getCompany()->getTimeZoneConfig()->getZoneName() : '';
                                date_default_timezone_set($time_zone);
                            }
                            if (($item['field_name'] == 'START_DATE_TEXT' || $item['field_name'] == 'END_DATE_TEXT') && $target == FilterConfigExt::RELOCATION_SERVICE_FILTER_TARGET) {
                                $queryBuilder->andwhere('ServiceEventBetween.code = :code: AND ServiceEventValueBetween.value >= :begin_date: AND ServiceEventValueBetween.value < :end_date:', [
                                    'code' => $item['field_name'] == 'START_DATE_TEXT' ? 'SERVICE_START' : 'SERVICE_END',
                                    'begin_date' => Helpers::__getStartTimeOfDay(doubleval($dateRange['startDate'])),
                                    'end_date' => Helpers::__getEndTimeOfDay(doubleval($dateRange['endDate']))
                                ]);
                            } else {
                                $queryBuilder->andWhere($sTableField . " >= " . Helpers::__getStartTimeOfDay(doubleval($dateRange['startDate'])));
                                $queryBuilder->andWhere($sTableField . " < " . Helpers::__getEndTimeOfDay(doubleval($dateRange['endDate'])));
                            }
                        } else {
                            if (($item['field_name'] == 'START_DATE_TEXT' || $item['field_name'] == 'END_DATE_TEXT') && $target == FilterConfigExt::RELOCATION_SERVICE_FILTER_TARGET) {
                                $queryBuilder->andwhere('ServiceEventBetween.code = :code: AND ServiceEventValueBetween.value >= :begin_date: AND ServiceEventValueBetween.value < :end_date:', [
                                    'code' => $item['field_name'] == 'START_DATE_TEXT' ? 'SERVICE_START' : 'SERVICE_END',
                                    'begin_date' => Helpers::__getDateBegin(Helpers::__convertTimeToUTC($dateRange['startDate'], 'Y-m-d')),
                                    'end_date' => Helpers::__getDateEnd(Helpers::__convertTimeToUTC($dateRange['endDate'], 'Y-m-d'))
                                ]);
                            } else {
                                $queryBuilder->andWhere($sTableField . " >= '" . Helpers::__getDateBegin(Helpers::__convertTimeToUTC($dateRange['startDate'], 'Y-m-d')) . "'");
                                $queryBuilder->andWhere($sTableField . " < '" . Helpers::__getDateEnd(Helpers::__convertTimeToUTC($dateRange['endDate'], 'Y-m-d')) . "'");
                            }
                        }
                        break;
                }
            }
        }
    }

    /**
     * @param $filterConfigId
     * @param bool $isTmp
     * @param null $target
     * @return array
     */
    static function __getFilterConfigs($filterConfigId, $isTmp = false, $target = null)
    {
        if ($isTmp) {
            $di = \Phalcon\DI::getDefault();
            $cacheManager = $di->get('cache');
            $aFilterConfigItem = $cacheManager->get('TMP_ITEMS_FILTER_' . $filterConfigId);
        } else {
            $aOptions = [
                'filter_config_id' => $filterConfigId,
                'target' => $target
            ];
            $oFilterConfigItems = FilterConfigItemExt::listJoinByCriteria($aOptions);
            $aFilterConfigItem = $oFilterConfigItems['data']->toArray();
        }

        return $aFilterConfigItem;
    }


    /**
     * Allow add conditions from filter config
     * Function set filter operator
     * @param &queryBuilder
     * @param String $queryString
     * @param int $filterConfigId
     * @param boolean $isTmp
     * @param string $target
     * @param array $tableField
     * @param array $dataType
     * @return mixed
     */

    static function __addFilterConfigConditionsQueryString(&$queryString, $filterConfigId, $isTmp = false, $target = null, $tableField = [], $dataType = [])
    {
        if ($isTmp) {
            $di = \Phalcon\DI::getDefault();
            $cacheManager = $di->get('cache');
            $aFilterConfigItem = $cacheManager->get('TMP_ITEMS_FILTER_' . $filterConfigId);
        } else {
            $aOptions = [
                'filter_config_id' => $filterConfigId,
                'target' => $target
            ];
            $oFilterConfigItems = FilterConfigItemExt::listJoinByCriteria($aOptions);
            $filterConfigData = $oFilterConfigItems['data'];
            if (is_object($oFilterConfigItems['data'])) {
                $filterConfigData = $oFilterConfigItems['data']->toArray();
            }

            $aFilterConfigItem = $filterConfigData;
        }
        $aTableFieldByName = $tableField;

        if ($aFilterConfigItem == null || count($aFilterConfigItem) == 0) {
            return null;
        }

        foreach ($aFilterConfigItem as $item) {

            $sTableField = isset($aTableFieldByName[$item['field_name']]) ?
                $aTableFieldByName[$item['field_name']] : null;
            $sField = strtolower(str_replace(' ', '', $item['field_name']));

            if ($sField && $sTableField && $item['operator']) {
                switch ($item['operator']) {
                    case FilterConfigExt::OPERATOR_EQUAL :
                        if (is_numeric($item['value'])) {
                            if (isset($dataType[strtoupper($sField)]) && $dataType[strtoupper($sField)] == 'boolean') {
                                if ($item['value'] == 0) {
                                    $queryString .= " AND $sTableField = " . 'false' . "";
                                } else if ($item['value'] == 1) {
                                    $queryString .= " AND $sTableField = " . 'true' . "";
                                } else {
                                    $queryString .= " AND $sTableField = " . $item['value'] . "";
                                }
                            } else {
                                $queryString .= " AND $sTableField = " . $item['value'] . "";
                            }

                        } else {
                            $queryString .= " AND $sTableField = '" . $item['value'] . "'";
                        }
                        break;
                    case FilterConfigExt::OPERATOR_NOT_EQUAL :
                        if (is_numeric($item['value'])) {
                            if (isset($dataType[strtoupper($sField)]) && $dataType[strtoupper($sField)] == 'boolean') {
                                if ($item['value'] == 0) {
                                    $queryString .= " AND $sTableField != " . 'false' . "";
                                } else if ($item['value'] == 1) {
                                    $queryString .= " AND $sTableField != " . 'true' . "";
                                } else {
                                    $queryString .= " AND $sTableField != " . $item['value'] . "";
                                }
                            } else {
                                $queryString .= " AND $sTableField != " . $item['value'] . "";
                            }

                        } else {
                            $queryString .= " AND $sTableField != '" . $item['value'] . "'";
                        }
                        break;
                    case FilterConfigExt::OPERATOR_BEFORE :
                        if (count($dataType) > 0 && isset($dataType[strtoupper($sField)]) && ($dataType[strtoupper($sField)] == 'timestamp' || $dataType[strtoupper($sField)] == 'int')) {
                            $begin_of_day = strtotime("yesterday", time());
                            $endOfDay = strtotime("tomorrow", $begin_of_day) - 1;
                            $date = strtotime('-' . $item['value'] . ' days', $begin_of_day);
                            $queryString .= " AND $sTableField >= $date AND $sTableField <= $endOfDay";

                        } else {
                            $begin_of_day = date('Y-m-d');
                            $endOfDay = date('Y-m-d', strtotime('-' . $item['value'] . ' days'));
                            $queryString .= " AND $sTableField >= DATE('$endOfDay') AND $sTableField <= DATE('$begin_of_day')";
                        }
                        break;
                    case FilterConfigExt::OPERATOR_AFTER :
                        if (count($dataType) > 0 && isset($dataType[strtoupper($sField)]) && ($dataType[strtoupper($sField)] == 'timestamp' || $dataType[strtoupper($sField)] == 'int')) {
                            $begin_of_day = strtotime("yesterday", time());
                            $endOfDay = strtotime("tomorrow", $begin_of_day) - 1;
                            $date = strtotime('+' . $item['value'] . ' days', $endOfDay);
                            $queryString .= " AND $sTableField >= $begin_of_day AND $sTableField <= $date";
                        } else {
                            $begin_of_day = date('Y-m-d');
                            $endOfDay = date('Y-m-d', strtotime('+' . $item['value'] . ' days'));
                            $queryString .= " AND $sTableField >= DATE('$begin_of_day') AND $sTableField <= DATE('$endOfDay')";
                        }
                        break;
                    case FilterConfigExt::OPERATOR_OLDER_THAN :
                        if (count($dataType) > 0 && isset($dataType[strtoupper($sField)]) && ($dataType[strtoupper($sField)] == 'timestamp' || $dataType[strtoupper($sField)] == 'int')) {
                            $begin_of_day = strtotime('-' . $item['value'] . ' days', time());
                            $queryString .= " AND $sTableField <= $begin_of_day";

                        } else {
                            $begin_of_day = date('Y-m-d', strtotime('-' . $item['value'] . ' days'));
                            $queryString .= " AND $sTableField <= DATE('$begin_of_day')";
                        }
                        break;
                    case FilterConfigExt::OPERATOR_TODAY :
                        $offset = ApplicationModel::__getTimezoneOffset();
                        if (!is_numeric($offset)) {
                            $offset = 0;
                        }
                        if (count($dataType) > 0 && isset($dataType[strtoupper($sField)]) && ($dataType[strtoupper($sField)] == 'timestamp' || $dataType[strtoupper($sField)] == 'int')) {
                            $currentTime = time() + ($offset * 60);
                            $currentDate = date('Y-m-d', $currentTime);
                            $begin_of_day = strtotime($currentDate);
                            $endOfDay = Helpers::__getEndTimeOfDay($begin_of_day);

                            $queryString .=  " AND ". $sTableField . " >= '" . $begin_of_day . "' AND " . $sTableField . " <= '" . $endOfDay;
                        } else {

                            $currentTime = time() + ($offset * 60);
                            $currentDate = date('Y-m-d', $currentTime);
                            $endDate = date('Y-m-d', $currentTime + 86400); // 86400second = 1day

                            $queryString .= " AND ". $sTableField . " >= DATE('" . ($currentDate) . "')". " AND " . $sTableField . " < DATE('" . ($endDate) . "')";
                        }
                        break;
                    case FilterConfigExt::OPERATOR_CONTAIN :
                        if ($item['field_name'] == 'TAG_TEXT') {
                            $data = $item['value'];
                            if (!is_array($item['value'])) {
                                $data = explode(',', $item['value']);
                            }
                            $_index = 0;
                            foreach ($data as $key => $tag) {
                                if ($_index == 0) {
                                    $queryString .= " AND ($sTableField LIKE " . "'%" . $tag . "%'";
                                } else {
                                    $queryString .= " OR $sTableField LIKE " . "'%" . $tag . "%'";
                                }
                                $_index++;
                            }
                            if ($_index > 0) {
                                $queryString .= ") ";
                            }
                        } else {
                            $queryString .= " AND $sTableField LIKE" . "'%" . $item['value'] . "%'";;
                        }
                        break;
                    case FilterConfigExt::OPERATOR_IS_TEXT :
                        break;
                    case FilterConfigExt::OPERATOR_IS_NULL :
                        if ($target == FilterConfigExt::RELOCATION_EXTRACT_FILTER_TARGET && $item['field_name'] == 'ARCHIVED_TEXT') {
                            $queryString .= " AND $sTableField = 1";
                        } else {
                            $queryString .= " AND $sTableField IS NULL";
                        }
                        break;
                    case FilterConfigExt::OPERATOR_NOT_NULL :
                        if ($target == FilterConfigExt::RELOCATION_EXTRACT_FILTER_TARGET && $item['field_name'] == 'ARCHIVED_TEXT') {
                            $queryString .= " AND $sTableField = -1";
                        } else {
                            $queryString .= " AND $sTableField IS NOT NULL";
                        }
                        break;
                    case FilterConfigExt::OPERATOR_IN :
                        if (isset($item['value'])) {
                            $data = $item['value'];
                            if (!is_array($item['value'])) {
                                $data = explode(',', $item['value']);
                            }
                            $_index = 0;
                            foreach ($data as $key => $value) {
                                if ($_index == 0) {
                                    $queryString .= " AND ($sTableField = $value";
                                } else {
                                    $queryString .= " OR $sTableField = $value";
                                }
                                $_index++;
                            }
                            if ($_index > 0) {
                                $queryString .= ") ";
                            }
                        }
                        break;
                    case FilterConfigExt::OPERATOR_BETWEEN :
                        $dateRange = $item['value'];
                        if (is_string($dateRange)) {
                            $dateRange = json_decode($dateRange, true);
                        }
                        if (count($dataType) > 0 && isset($dataType[strtoupper($sField)]) && ($dataType[strtoupper($sField)] == 'timestamp' || $dataType[strtoupper($sField)] == 'int')) {
                            $queryString .= " AND $sTableField >= " . doubleval($dateRange['startDate']) . " AND $sTableField <= " . doubleval($dateRange['endDate']) . "";
                        } else {
                            if ($dateRange['startDate'] == $dateRange['endDate']) {
                                $queryString .= " AND $sTableField = DATE('" . Helpers::__convertTimeToUTC($dateRange['startDate'], 'Y-m-d') . "')";
                            } else {
                                $queryString .= " AND $sTableField > DATE('" . Helpers::__convertTimeToUTC($dateRange['startDate'], 'Y-m-d') . "')" . " AND $sTableField <= DATE('" . Helpers::__convertTimeToUTC($dateRange['endDate'], 'Y-m-d') . "')";
                            }
                        }

                        break;
                }
            }
        }
    }

    /**
     * @param $monthNum
     * @return false|string
     */
    public static function __getMonthName($monthNum)
    {
        return date('M', mktime(0, 0, 0, $monthNum, 10));
    }

    /**
     * Get Pagination array
     * @param $data
     * @param $totalCount
     * @param $pageNumber
     * @param $limit
     * @return mixed
     */
    public static function getPagination($data, $totalCount, $pageNumber = 1, $limit = 10)
    {

        if (!$data && !is_array($data)) {
            return [
                'success' => false,
            ];
        };

        $roundedTotal = $totalCount / floatval($limit);
        $totalPages = intval($roundedTotal);
        if ($totalPages != $roundedTotal) {
            $totalPages++;
        }


        $page = new \stdClass();
        $page->items = $data;


        //Fix next
        if ($pageNumber < $totalPages) {
            $next = $pageNumber + 1;
        } else {
            $next = $totalPages;
        }
        if ($pageNumber > 1) {
            $before = $pageNumber - 1;
        } else {
            $before = 1;
        }

        $page->next = $next;
        $page->first = 1;
        $page->before = $before;
        $page->current = $pageNumber;
        $page->last = $totalPages;
        $page->total_pages = $totalPages;
        $page->total_count = $totalCount;

        return [
            'success' => true,
            'data' => $page
        ];
    }

    public static function groupBy($key, $data)
    {
        $result = array();

        foreach ($data as $val) {
            if (array_key_exists($key, $val)) {
                $result[$val[$key]][] = $val;
            } else {
                $result[""][] = $val;
            }
        }

        return $result;
    }

    /**
     * @param $key
     * @param $keyVal
     * @param $data
     * @param bool $sort
     * @return array
     */
    public static function groupGetKeyValue($key, $keyVal, $data, $sort = false)
    {
        $result = array();

        foreach ($data as $val) {
            if (array_key_exists($key, $val)) {
                $result[$val[$key]] = $val[$keyVal];
            }
        }
        if ($sort == true) {
            asort($result, SORT_NATURAL);
            $arr = [];
            foreach ($result as $key => $val) {
                $arr = array($key => $val) + $arr;
            }
            return $arr;
        }

        return $result;
    }

    /**
     * @param $array
     * @param $order
     * @param $key
     * @return array
     */
    public static function __reArrangeArray(&$array, $order, $key = 'id')
    {
        return usort($array, function ($a, $b) use ($order, $key) {
            $pos_a = array_search($a[$key], $order);
            $pos_b = array_search($b[$key], $order);
            return $pos_a - $pos_b;
        });
    }

    /**
     * @param array $options
     * @return array|mixed
     */
    public static function __getSecretManagerData($options = [])
    {
        try {
            $di = Di::getDefault();
            $awsSecretManager = $di->get('aws')->createClient('SecretsManager');
            $value = $awsSecretManager->getSecretValue([
                'SecretId' => $options['name']
            ]);

            return $value['SecretString'];
        } catch (\Aws\Exception\AwsException $e) {
            \Sentry\captureException($e);
            return [];
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return [];
        }

    }

    /**
     * @param $message
     * @return string
     */
    public static function __createException($message)
    {
        try {
            throw new Exception($message);
        } catch (Exception $error) {
            self::__trackError($error);
        } finally {
            return true;
        }
    }

    /**
     * @param $string
     * @return string
     * @throws \Phalcon\Exception
     */
    public static function __generateSlug($string)
    {
        return PhpSlug::generate($string);
    }

    /**
     * @param $date
     * @return mixed
     */
    public static function __convertUnixDateLocalToUtc($date)
    {
        //FORMAT TO HOUR 00, 01, 02 ... 23, 24.
        $dateHour = date('H', $date);

        if ($dateHour === 0) {
            //Already UTC 0
            return $date;
        }

        //CALCULATE
        if ($dateHour > 12) {
            // math: $date + (24 - $dateHour) * 3600
            $result = $date + (24 - $dateHour) * 3600;
        } else {
            // math: $date  - $dateHour * 3600
            $result = $date - $dateHour * 3600;
        }
        return $result;
    }

//   /**
//    * Function CountPdf
//    */
//   public static function __countPdf($path) {
//        $pdf = file_get_contents($path);
//        $number = preg_match_all("/[<|>][\r\n|\r|\n]*\/Type\s*\/Page\W/", $pdf, $dummy);
//        return $number;
//    }

    public static function __countPdf($path = NULL)
    {
        $image = new \Imagick();
        $image->pingImage($path);
        return $image->getNumberImages();
    }

    /**
     * @param $date
     */
    public static function __escapeJsonString($value)
    {
        $escapers = array("\\", "/", "\"", "\n", "\r", "\t", "\x08", "\x0c");
        $replacements = array("\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b");
        $result = str_replace($escapers, $replacements, $value);
        return $result;
    }

    /**
     * @param $URL
     * @return void
     */
    public static function __urlGetContents($URL){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $URL);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    /**
     * @param $dob
     * @param $isYearAfter
     * @return false|int
     */
    public static function __convertBirthDateToCurrent($dob, $isYearAfter = true){
        if(!is_numeric($dob)){
            $dob = strtotime($dob);
        }

        $dob = intval($dob);

        $today = time();
        $currentBirth = date('Y-m-d', mktime(0, 0, 0, date("m", $dob), date("d", $dob), date("Y", $today)));
        $currentTime = strtotime($currentBirth);
        $todayStartTime = Helpers::__getTimeBegin($today);

        //Get year after if birthdate less than now.
        if($todayStartTime >= $currentTime && $isYearAfter){
            $currentTime = strtotime('+1 year', $currentTime);
        }

        return $currentTime;
    }

    /**
     * [get format date base on company date format description]
     * @param  [type] $originalDate [description]
     * @return [type]               [description]
     */
    static function __convertCompanyDateFormatToPhpDateFormat($companyDateFormat = 'DD/MM/YYYY', $isHourFormat = false, $hourFormat = 'H:i:s', $stripDate = '/')
    {
        switch ($companyDateFormat){
            case 'DD/MM/YYYY':
                if($isHourFormat){
                    return 'd'. $stripDate .'m'. $stripDate. 'Y' . ' ' . $hourFormat;
                }else{
                    return 'd'. $stripDate .'m'. $stripDate. 'Y';
                }
            case 'MM/DD/YYYY':
                if($isHourFormat){
                    return 'm'. $stripDate .'d'. $stripDate. 'Y' . ' ' . $hourFormat;
                }else{
                    return 'm'. $stripDate .'d'. $stripDate. 'Y';
                }
            case 'YYYY/MM/DD':
                if($isHourFormat){
                    return 'Y'. $stripDate .'m'. $stripDate. 'd' . ' ' . $hourFormat;
                }else{
                    return 'Y'. $stripDate .'m'. $stripDate. 'd';
                }
            default:
                if($isHourFormat){
                    return 'd'. $stripDate .'m'. $stripDate. 'Y' . ' ' . $hourFormat;
                }else{
                    return 'd'. $stripDate .'m'. $stripDate. 'Y';
                }
        }
    }

    /**
     * [get format date base on company date format description]
     * @param  [type] $originalDate [description]
     * @return [type]               [description]
     */
    static function __convertCompanyDateFormatToAthenaDateFormat($companyDateFormat = 'DD/MM/YYYY', $isHourFormat = false, $hourFormat = 'H:i:s', $stripDate = '/')
    {
        switch ($companyDateFormat){
            case 'DD/MM/YYYY':
                if($isHourFormat){
                    return '%d'. $stripDate .'%m'. $stripDate. '%Y' . ' ' . $hourFormat;
                }else{
                    return '%d'. $stripDate .'%m'. $stripDate. '%Y';
                }
            case 'MM/DD/YYYY':
                if($isHourFormat){
                    return '%m'. $stripDate .'%d'. $stripDate. '%Y' . ' ' . $hourFormat;
                }else{
                    return '%m'. $stripDate .'%d'. $stripDate. '%Y';
                }
            case 'YYYY/MM/DD':
                if($isHourFormat){
                    return '%Y'. $stripDate .'%m'. $stripDate. '%d' . ' ' . $hourFormat;
                }else{
                    return '%Y'. $stripDate .'%m'. $stripDate. '%d';
                }
            default:
                if($isHourFormat){
                    return '%d'. $stripDate .'%m'. $stripDate. '%Y' . ' ' . $hourFormat;
                }else{
                    return '%d'. $stripDate .'%m'. $stripDate. '%Y';
                }
        }
    }

    /**
     * @return mixed
     */
    static function __removeStringQuote($str){
        $str = str_replace('"', '', $str);
        $str = str_replace("'", '', $str);
        return $str;
    }
}
