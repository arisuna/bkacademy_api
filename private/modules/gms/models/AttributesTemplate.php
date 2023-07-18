<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;

class AttributesTemplate extends \Reloday\Application\Models\AttributesTemplate {

	/**
	 * init model
	 * @return [type] [description]
	 */
	public function initialize()
    {
        parent::initialize();
        $this->hasMany('id', '\Reloday\Gms\Models\AttributesTemplateValue', 'attributes_template_id', array('alias' => 'values'));
        $this->hasMany('id', '\Reloday\Gms\Models\AttributesCompanyValue', 'attributes_template_id', array('alias' => 'CompanyValues'));
    }

    /**
     * list all values of an attributes by company
     * @param  [type] $company_id [description]
     * @return [type]             [description]
     */
    public function listValuesOfCompany( $company_id ){

    	return $this->getCompanyValues([
	        "company_id = :company_id:",
	        "bind" => [
	            "company_id" => $company_id
	        ]
	    ]);
    }

    public function saveValuesOfCompany( $company_id , $params ){

    }

}