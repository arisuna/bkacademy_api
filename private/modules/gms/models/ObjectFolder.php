<?php

namespace Reloday\Gms\Models;

use Elasticsearch\Endpoints\Cat\Help;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Behavior\SoftDelete;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Application\Lib\Helpers;

class ObjectFolder extends \Reloday\Application\Models\ObjectFolderExt
{

    const STATUS_ARCHIVED = -1;
    const STATUS_DRAFT = 0;
    const STATUS_ACTIVE = 1;

    public function initialize()
    {
        parent::initialize();
        $this->belongsTo('assignment_id', 'Reloday\Gms\Models\Assignment', 'id', [
            'alias' => 'Assignment'
        ]);
    }

    /**
     * @param String $objectUuid
     * @param String $sourceName
     * @return array
     */
    public static function __createMyDspFolder(String $objectUuid, String $sourceName)
    {
        $object_folder_dsp = new self();
        $object_folder_dsp->setUuid(Helpers::__uuid());
        $object_folder_dsp->setObjectUuid($objectUuid);
        $object_folder_dsp->setObjectName($sourceName);
        $object_folder_dsp->setDspCompanyId(ModuleModel::$company->getId());
        $result = $object_folder_dsp->__quickCreate();
        return $result;
    }
}
