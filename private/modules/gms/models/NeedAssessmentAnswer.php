<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;

class NeedAssessmentAnswer extends \Reloday\Application\Models\NeedAssessmentAnswerExt {

    public function initialize($params = [])
    {
        parent::initialize($params);
        $this->belongsTo('questionnaire_id','\Reloday\Gms\Models\NeedAssessmentItems','id', ['alias' => 'NeedAssessmentItems']);
    }

}