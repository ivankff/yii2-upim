<?php

namespace ivankff\yii2UploadImages;

/**
 * ```php
 * @property PluralImages $images
 * class Product extends ActiveRecord implements ImagesEntityInterface
 * {
 *
 *     // @return \ivankff\yii2UploadImages\PluralImages|null
 *     public function getImages()
 *     {
 *         if ($behavior = $this->getBehavior('images'))
 *             return $behavior->getImages();
 *
 *         return null;
 *     }
 *
 *     public function behaviors()
 *     {
 *         return [
 *             'images' => [
 *                 'class' => 'ivankff\yii2UploadImages\EntityImagesBehavior',
 *                 'imagesClass' => 'ivankff\yii2UploadImages\PluralImages',
 *                 'dir' => static::getImagesDir(),
 *                 'widen' => static::getImagesWiden(),
 *             ],
 *         ];
 *     }
 *
 *     public static function getImagesDir() { return \Yii::getAlias('@image/product'); }
 *     public static function getImagesWiden() { return ArrayHelper::getValue(Yii::$app->params, 'images.widen', 0); }
 *
 * }
 * ```
 */
interface ImagesEntityInterface
{

    /**
     * @return ImagesInterface
     */
    public function getImages();
    /**
     * @return string
     */
    public static function getImagesDir();
    /**
     * @return int
     */
    public static function getImagesWiden();

}