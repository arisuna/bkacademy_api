<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */

namespace Reloday\Gms\Models;

class UserGroupAcl extends \Reloday\Application\Models\UserGroupAclExt
{
	/**
	 * [getAllPrivilegiesGroup description]
	 * @param  [type] $user_group_id    [id of user group]
	 * @return [type]                   [object phalcon : collection of all data in user group acl company table]
	 */
	public static function getAllPrivilegiesGroup( $user_group_id ){
		return self::find([
			'conditions' => 'user_group_id = :user_group_id:',
			'bind'		 => [
				'user_group_id' => $user_group_id,
			]
		]);
	}
}