<?php

namespace ivankff\yii2UploadImages\behaviors;

use yii\base\Behavior;
use yii\base\Event;
use yii\db\ActiveRecord;
use yii\db\AfterSaveEvent;
use yii\helpers\ArrayHelper;
use ivankff\yii2UploadImages\FilesInterface;

/**
 * @property ActiveRecord $owner
 *
 * ```php
 * property-read FilesInterface $images
 * property-read FilesInterface $gallery
 *
 * 'images' => [
 *     'class' => 'ivankff\yii2UploadFiles\behaviors\ImagesBehavior',
 *     'dir' => '@image/product',
 *     'maxCount' => 1,
 *     'widen' => ArrayHelper::getValue(Yii::$app->params, 'images.widen', 0),
 * ],
 * 'gallery' => [
 *     'class' => 'ivankff\yii2UploadFiles\behaviors\ImagesBehavior',
 *     'filesConfig' => [ "class" => "ivankff\yii2UploadFiles\Images", "trim" => FilesInterface::TRIM_START, "suffix" => "gallery" ],
 *     'attributeName' => 'gallery',
 *     'dir' => '@image/product',
 *     'widen' => ArrayHelper::getValue(Yii::$app->params, 'images.widen', 0),
 * ],
 * ```
 */
class ImagesBehavior extends Behavior
{

    /** @var array|string */
    public $filesConfig = [ "class" => "ivankff\yii2UploadImages\Images" ];
    /** @var string Название атрибута в owner, по которому можно будет обратиться к FilesInterface, как правило должно совпадать с behaviorName */
    public $attributeName = 'images';
    /** @var string "@images/product" */
    public $dir;
    /** @var int обрезка по большей стороне */
    public $widen = 2000;
    /** @var int максимальное кол-во файлов */
    public $maxCount = 20;

    /** @var FilesInterface */
    private $_files;

    /**
     * {@inheritdoc}
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            ActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
        ];
    }

    /**
     * @param AfterSaveEvent $event
     */
    public function afterSave($event)
    {
        $this->_getFiles()->save($this->owner->primaryKey);
    }

    /**
     * @param Event $event
     */
    public function afterDelete($event)
    {
        $this->_getFiles()->clear();
        $this->_getFiles()->save($this->owner->primaryKey);
    }

    /**
     * @inheritdoc
     */
    public function __get($name)
    {
        if ($name === $this->attributeName)
            return $this->_getFiles();

        return parent::__get($name);
    }

    /**
     * @inheritdoc
     */
    public function canGetProperty($name, $checkVars = true)
    {
        if ($name === $this->attributeName)
            return true;

        return parent::canGetProperty($name, $checkVars);
    }

    /**
     * @return FilesInterface
     */
    private function _getFiles()
    {
        if (null === $this->_files) {
            $config = is_array($this->filesConfig) ? $this->filesConfig : ['class' => $this->filesConfig];
            $config = ArrayHelper::merge($config, ['dir' => \Yii::getAlias($this->dir), 'widen' => $this->widen, 'maxCount' => $this->maxCount]);
            $this->_files = \Yii::createObject($config);

            if (! $this->owner->isNewRecord)
                $this->_files->load($this->owner->primaryKey);
        }

        return $this->_files;
    }

}
