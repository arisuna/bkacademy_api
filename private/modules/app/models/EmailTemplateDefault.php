<?php

namespace SMXD\App\Models;

use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use SMXD\App\Models\ModuleModel;
use SMXD\Application\Lib\Helpers;

class EmailTemplateDefault extends \SMXD\Application\Models\EmailTemplateDefaultExt
{	

	const STATUS_ARCHIVED = -1;
	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;

    public function initialize()
    {
		parent::initialize(); 
	}

    public function getTemplate( $language ){
        $emailTemplate = EmailTemplate::findFirst([
            'conditions' => 'email_template_default_id= :email_template_default_id: AND language = :language:',
            'bind' => [
                'email_template_default_id' => $this->getId(),
                'language' => $language
            ]
        ]);
        return $emailTemplate;
    }
}
