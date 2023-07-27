<?php

namespace SMXD\Application\Lib;

use Intervention\Image\ImageManager;
use SMXD\Application\Models\MediaExt;

class SMXDLetterImage
{
    /**
     * @var string
     */
    protected $name;


    /**
     * @var string
     */
    protected $name_initials;


    /**
     * @var string
     */
    protected $shape;


    /**
     * @var int
     */
    protected $size;

    /**
     * @var ImageManager
     */
    protected $image_manager;


    protected $color;

    protected $color_internal = false;

    protected $limit = 2;

    /**
     * SMXDLetterImage constructor.
     * @param $name
     * @param string $shape
     * @param string $size
     * @param string $color
     * @param int $limit
     */
    public function __construct($name, $shape = 'circle', $size = '48', $color = '', $limit = 2)
    {
        $this->setName($name);
        $this->setImageManager(new ImageManager());
        $this->setShape($shape);
        $this->setSize($size);
        $this->setColor($color);
        $this->setLimit($limit);
        $this->setColorInternal(true);
        $this->generateFileName();
    }

    /**
     *
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;
    }


    /**
     *
     */
    public function setColorInternal()
    {
        $this->color_internal = true;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return ImageManager
     */
    public function getImageManager()
    {
        return $this->image_manager;
    }

    /**
     * @param ImageManager $image_manager
     */
    public function setImageManager(ImageManager $image_manager)
    {
        $this->image_manager = $image_manager;
    }

    /**
     * @return string
     */
    public function getShape()
    {
        return $this->shape;
    }

    /**
     * @param string $shape
     */
    public function setShape($shape)
    {
        $this->shape = $shape;
    }

    /**
     * @return int
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @param int $size
     */
    public function setSize($size)
    {
        $this->size = $size;
    }

    /**
     * @param string $color
     */
    public function setColor($color)
    {
        if (ctype_xdigit($color)) $this->color = '#' . $color;
    }

    /**
     * @return \Intervention\Image\Image
     */
    public function generate()
    {
        $this->generateFileName();
        if ($this->color == '') {
            if ($this->color_internal == false) {
                $color = $this->stringToColor($this->name);
            } else {
                $color = $this->selectInternalColor();
            }
        } else {
            $color = $this->color;
        }
        if ($this->shape == 'circle') {
            $canvas = $this->image_manager->canvas(480, 480);
            $canvas->circle(480, 240, 240, function ($draw) use ($color) {
                $draw->background($color);
            });
        } else {
            $canvas = $this->image_manager->canvas(480, 480, $color);
        }

        $canvas->text($this->name_initials, 240, 240, function ($font) {
            $font->file(__DIR__ . '/../fonts/arial-bold.ttf');
            $font->size(220);
            $font->color('#ffffff');
            $font->valign('middle');
            $font->align('center');
        });

        return $canvas->resize($this->size, $this->size);
    }

    public function saveAs($path, $mimetype = 'image/png', $quality = 90)
    {
        if (empty($path) || empty($mimetype) || $mimetype != "image/png" && $mimetype != 'image/jpeg') {
            return false;
        }

        return @file_put_contents($path, $this->generate()->encode($mimetype, $quality));
    }

    public function __toString()
    {
        return (string)$this->generate()->encode('data-url');
    }

    public function break_words($name)
    {
        $temp_word_arr = explode(' ', $name);
        $final_word_arr = array();
        foreach ($temp_word_arr as $key => $word) {
            if ($word != "" && $word != ",") {
                $final_word_arr[] = $word;
            }
        }
        return $final_word_arr;
    }

    protected function stringToColor($string)
    {
        // random color
        $rgb = substr(dechex(crc32($string)), 0, 6);
        // make it darker
        $darker = 2;
        list($R16, $G16, $B16) = str_split($rgb, 2);
        $R = sprintf("%02X", floor(hexdec($R16) / $darker));
        $G = sprintf("%02X", floor(hexdec($G16) / $darker));
        $B = sprintf("%02X", floor(hexdec($B16) / $darker));
        return '#' . $R . $G . $B;
    }

    /**
     *
     */
    protected function selectInternalColor()
    {
        $colors = [
            "#F44336",
            "#3F51B5",
            "#3F51B5",
            "#03A9F4",
            "#009688",
            "#4CAF50",
            "#CDDC39",
            "#FBC02D",
            "#FF9800",
            "#795548"
        ];
        if (isset($this->name_initials[1])) {
            $char_index = ord($this->name_initials[0]) + ord($this->name_initials[1]);
        } else {
            $char_index = ord($this->name_initials[0]) * 2;
        }
        $color_index = $char_index % 10;
        return $colors[$color_index];
    }

    /**
     * @return array
     */
    public function pushToS3()
    {
        $di = \Phalcon\DI::getDefault();
        $bucketPublicName = $di->get('appConfig')->aws->bucket_public_name;
        $content = $this->__toString();
        $fileName = $this->getFileName();
        $fileContent = base64_decode(explode(',', $content)[1]);
        return RelodayS3Helper::__uploadSingleFile($fileName, $fileContent, $bucketPublicName, RelodayS3Helper::ACL_PUBLIC_READ, MediaExt::MIME_TYPE_PNG);
    }

    /**
     * @return string
     */
    public function getS3Url()
    {
        if (in_array($this->name_initials, $this->getRangeWords())) {
            $di = \Phalcon\DI::getDefault();
            return $di->get('appConfig')->aws->bucket_public_url . "/" . $this->getFileName();
        } else {
            $this->pushToS3();
            sleep(1);
            $di = \Phalcon\DI::getDefault();
            return $di->get('appConfig')->aws->bucket_public_url . "/" . $this->getFileName();
        }
    }

    /**
     *
     */
    public function generateFileName()
    {
        $words = $this->break_words($this->name);
        $number_of_word = 1;
        $this->name_initials = '';
        foreach ($words as $word) {
            if ($number_of_word > $this->limit)
                break;
            $this->name_initials .= mb_strtoupper(trim(mb_substr($word, 0, 1, 'UTF-8')));
            $number_of_word++;
        }
    }

    /**
     * @return string
     */
    public function getFileName()
    {
        return "avatar/" . $this->name_initials . ".png";
    }

    /**
     * @return array
     */
    public function getRangeWords()
    {
        $array = [];
        $characters = range('A', 'Z');
        foreach ($characters as $characterX) {
            foreach ($characters as $characterY) {
                $array[] = $characterX . $characterY;
            }
        }
        return $array;
    }
}