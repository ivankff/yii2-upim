<?php

namespace ivankff\yii2UploadImages;

interface ImagesEntityInterface
{

    /**
     * @return string
     */
    public static function getImagesDir();

    /**
     * @return int
     */
    public static function getImagesWiden();

}