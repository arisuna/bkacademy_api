<?php
use \Mailgun\Mailgun;

class Sendmail{

    /**
     * @param array $params from, to = [], subject, body
     * @return array
     */
    static function sendMailByApi(array $params)
    {

        $domain = getenv('MAILGUN_DOMAIN');

        $mgObject = Mailgun::create(getenv('MAILGUN_KEY'));

        if( getenv('ENVIR') == 'DEV' &&  getenv('SYS_TEST_EMAIL') != ''){
            if( in_array( $params['to'] , self::__getExceptEmailAdd() ) ){

            }else{
                $params['to'] = getenv('SYS_TEST_EMAIL');
            }
        }

        $result = $mgObject->messages()->send($domain, [
            'from'      => getenv('MAILGUN_SENDER'),
            'to'        => $params['to'],
            'subject'   => $params['subject'],
            'html'      => $params['body'],
        ]);


        if (is_object($result) && $result instanceof  \Mailgun\Model\Message\SendResponse ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return array
     */
    public static function __getExceptEmailAdd(){
        if( getenv('RELO_EMAILS_EXCEPTIONS') != '' ){
            return explode(';', getenv('RELO_EMAILS_EXCEPTIONS') );
        }else{
            return [];
        }
    }
}