<?php
/**
 * Created by PhpStorm.
 * User: macbook
 * Date: 4/23/20
 * Time: 4:01 PM
 */

namespace SMXD\Media\Models;


use SMXD\Application\Models\RelodayObjectAvatarExt;

class RelodayObjectAvatar extends RelodayObjectAvatarExt
{
    /** @var [varchar] [url of thumbnail] */
    public $url_thumb;

    /**
     * [getUrlThumb description]
     * @return [type] [description]
     */
    public function getUrlThumb()
    {
        $this->url_thumb = $this->getPresignedS3Url();
        return $this->url_thumb;
    }

    /**
     *
     */
    public function setAllUrl()
    {
        $this->getUrlThumb();
    }

    /**
     * @return string
     */
    public function getTokenKey64()
    {
        $token64 = ModuleModel::$user_login_token ? base64_encode(ModuleModel::$user_login_token) : "";
        return $token64;
    }

    /**
     * @return bool
     */
    public function belongsToCompany()
    {
        return $this->getCompanyUuid() == ModuleModel::$company->getUuid();
    }

    /**
     * @return bool
     */
    public function belongsToCurrentUser()
    {
        return $this->getUserUuid() == ModuleModel::$user->getUuid();
    }

    /**
     * @return bool
     */
    public function canEditMedia()
    {
        if ($this->belongsToCurrentUser() == true) {
            return true;
        }
        if (ModuleModel::$user->isAdminOrManager() && $this->belongsToCompany()) {
            return true;
        }
        return false;
    }

    /**
     * @return mixed
     */
    public function getParsedData()
    {
        $item = $this->toArray();
        $item['uuid'] = $this->getUuid();
        $item['name'] = $this->getName();
        $item['file_type'] = $this->getFileType();
        $item['file_extension'] = $this->getFileExtension();

        $item['company_uuid'] = $this->getCompanyUuid();
        $item['user_uuid'] = $this->getUserUuid();
        $item['is_owner'] = $this->belongsToCurrentUser();
        $item['is_company_owner'] = $this->belongsToCompany();

        $item['file_size'] = intval($this->getSize());
        $item['file_size_human_format'] = $this->getSizeHumainFormat();
        $item['s3_full_path'] = $this->getRealFilePath();

        $item['image_data'] = [
            "url_thumb" => $this->getUrlThumb(),
            "name" => $this->getFilename()
        ];
        return $item;
    }


    /**
     * @param $uuid
     * @return array|null
     */
    public static function __getAvatar($uuid, $type = self::USER_AVATAR, $returnType = 'array')
    {
        $avatar = self::__getLastestImage($uuid, $type, $returnType);
        if ($avatar) {
            return $avatar;
        } else {
            return null;
        }
    }

    /**
     * @param $uuid
     * @return array|null
     */
    public static function __getLogo($uuid, $type = self::USER_LOGO, $returnType = 'array')
    {
        $logo = self::__getLastestImage($uuid, $type, $returnType);
        if ($logo) {
            return $logo;
        } else {
            return null;
        }
    }

    /**
     * @param $uuid
     * @return array|null
     */
    public static function __getImageByObjectUuid($uuid, $returnType = 'object')
    {
        $image = self::__getLastestImage($uuid, '', $returnType);
        if ($image) {
            return $image;
        } else {
            return null;
        }
    }
}
