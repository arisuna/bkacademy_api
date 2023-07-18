<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;

class NeedAssessmentItems extends \Reloday\Application\Models\NeedAssessmentItemsExt {

    /**
     * @param array $params
     */
    public function initialize($params = [])
    {
        parent::initialize();
        $this->belongsTo('need_assessment_id','\Reloday\Gms\Models\NeedAssessments','id', ['alias' => 'NeedAssessments']);
        $this->hasMany('id','\Reloday\Gms\Models\NeedAssessmentAnswer','questionnaire_id', ['alias' => 'NeedAssessmentAnswer']);
    }

}