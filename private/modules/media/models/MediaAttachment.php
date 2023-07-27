<?php


namespace SMXD\Media\Models;

class MediaAttachment extends \SMXD\Application\Models\MediaAttachmentExt
{

    /**
     * [initialize description]
     * @return [type] [description]
     */
    public function initialize()
    {
        $this->belongsTo('media_id', 'SMXD\Media\Models\Media', 'id', ['alias' => 'Media']);
    }


    /**
     * @param $uuid
     * @return array|null
     */
    public static function __getAvatar($uuid, $type = self::MEDIA_GROUP_AVATAR, $returnType = 'array')
    {
        if (!in_array($type, self::$media_groups)) {
            $type = self::MEDIA_GROUP_AVATAR;
        }
        $avatar = self::__getLastAttachment($uuid, $type);
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
    public static function __getAvatarObject($uuid, $type = self::MEDIA_GROUP_AVATAR)
    {
        if (!in_array($type, self::$media_groups)) {
            $type = self::MEDIA_GROUP_AVATAR;
        }
        $avatar = self::__getLastAttachment($uuid, $type, "object");
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
    public static function __getAvatarFromDynamoDb($uuid)
    {
        $avatar = self::__getLastAttachment($uuid, "avatar");
        if ($avatar) {
            return $avatar;
        } else {
            return null;
        }
    }


    /**
     * @return mixed
     * @throws \Exception
     */
    public function getMedia(){
        return Media::findFirstByUuid($this->getMediaUuid());
    }
}
