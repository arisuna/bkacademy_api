<?php
/**
 * Created by PhpStorm.
 * User: binhnt
 * Date: 12/12/14
 * Time: 2:04 PM
 */
namespace SMXD\App\Models;

class UserRequestToken extends \SMXD\Application\Models\UserRequestTokenExt {

	public $data_raw;

	public static function isTokenValid($token){
		$userTokenRequest = self::findFirstByHash($token);
        if (!$userTokenRequest instanceof self) {
            return false;
        } else {
            // Check expired time
            if (strtotime($userTokenRequest->getEndedAt()) < time() || (int)$userTokenRequest->getStatus() !== (int)self::STATUS_ACTIVE ) {
                return false;
            } else {
                return true;
            }
        }
	}

    /**
     * @return mixed
     */
	public function getDataFromJson(){
		$this->data_raw = [];
		if( $this->data != ""){
			$this->data_row = json_decode( $this->data );
			return $this->data_row;
		}
	}
}