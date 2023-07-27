<?php
/**
 * Created by PhpStorm.
 * User: macbook
 * Date: 12/9/19
 * Time: 4:48 PM
 */

namespace SMXD\Media\Models;



use SMXD\Application\Models\MediaExt;

class Media extends MediaExt
{
    /** @var [varchar] [url of token] */
    public $url_token;
    /** @var [varchar] [url of full load] */
    public $url_full;
    /** @var [varchar] [url of thumbnail] */
    public $url_thumb;
    /** @var [var char] [url of download] */
    public $url_download;

    /**
     * [afterFetch description]
     * @return [type] [description]
     */
    public function afterFetch()
    {
        $this->url_token = $this->getUrlToken($this->getTokenKey64());
        $this->url_thumb = $this->getUrlThumb($this->getTokenKey64());
        $this->url_full = $this->getUrlFull($this->getTokenKey64());
        $this->url_download = $this->getUrlDownload($this->getTokenKey64());
    }

    /**
     * [getTUrlToken description]
     * @return [type] [description]
     */
    public function getUrlToken($token = '')
    {
        $this->url_token = parent::getUrlToken($this->getTokenKey64());
        return $this->url_token;
    }

    /**
     * [getUrlFull description]
     * @return [type] [description]
     */
    public function getUrlFull($token = '')
    {
        $this->url_full = parent::getUrlFull($this->getTokenKey64());
        return $this->url_full;
    }

    /**
     * [getUrlThumb description]
     * @return [type] [description]
     */
    public function getUrlThumb($token = '')
    {
        $this->url_thumb = parent::getUrlThumb($this->getTokenKey64());
        return $this->url_thumb;
    }

    /**
     * [getUrlDownload description]
     * @return [type] [description]
     */
    public function getUrlDownload($token = '')
    {
        $this->url_download = parent::getUrlDownload($this->getTokenKey64());
        return $this->url_download;
    }

    /**
     *
     */
    public function getTokenKey64()
    {
        $token64 = ModuleModel::$user_login_token ? base64_encode(ModuleModel::$user_login_token) : "";
        return $token64;
    }

    /** return all url */
    public function setAllUrl()
    {
        $this->getUrlToken();
        $this->getUrlThumb();
        $this->getUrlFull();
        $this->getUrlDownload();
    }
}
