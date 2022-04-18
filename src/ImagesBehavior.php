<?php

namespace ivankff\yii2UploadImages;

use yii\base\Behavior;
use yii\base\Event;
use yii\db\ActiveRecord;
use yii\db\AfterSaveEvent;
use yii\helpers\ArrayHelper;

/**
 * @property ActiveRecord $owner
 */
class ImagesBehavior extends Behavior
{

    /** @var array|string */
    public $imagesClass = "ivankff\yii2UploadImages\Images";
    /** @var string @images/product */
    public $dir;
    /** @var int обрезка по большей стороне */
    public $widen = 2000;
    /** @var int максимальное кол-во фоток */
    public $maxCount = 20;
    /** @var string суффикс для имет файлов */
    public $suffix = '';
    /** @var string какие фотки удалять, если кол-во превышает максимальное: из начала, из конца, со второй фотки */
    public $trim = ImagesInterface::TRIM_SECOND;

    /** @var ImagesInterface */
    private $_images;

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
        $this->_getImages()->save($this->owner->primaryKey);
    }

    /**
     * @param Event $event
     */
    public function afterDelete($event)
    {
        $this->_getImages()->clear();
        $this->_getImages()->save($this->owner->primaryKey);
    }

    /**
     * @return ImagesInterface
     */
    public function getImages()
    {
        return $this->_getImages();
    }

    /**
     * @return ImagesInterface
     */
    private function _getImages()
    {
        if (null === $this->_images) {
            $config = is_array($this->imagesClass) ? $this->imagesClass : ['class' => $this->imagesClass];
            $config = ArrayHelper::merge($config, ['dir' => \Yii::getAlias($this->dir), 'widen' => $this->widen, 'suffix' => $this->suffix, 'maxCount' => $this->maxCount, 'trim' => $this->trim]);

            $this->_images = \Yii::createObject($config);

            if (! $this->owner->isNewRecord)
                $this->_images->load($this->owner->primaryKey);
        }

        return $this->_images;
    }

}
