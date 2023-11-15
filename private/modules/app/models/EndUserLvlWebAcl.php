<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace SMXD\App\Models;

class EndUserLvlWebAcl extends \SMXD\Application\Models\EndUserLvlWebAclExt
{
	/**
	 * [getAllPrivilegiesGroup description]
	 * @param  [type] $user_group_id    [id of user group]
	 * @return [type]                   [object phalcon : collection of all data in user group acl company table]
	 */
	public static function getAllPrivilegiesLvl( $lvl ){
		return self::find([
			'conditions' => 'lvl = :lvl:',
			'bind'		 => [
				'lvl' => $lvl,
			]
		]);
	}
}