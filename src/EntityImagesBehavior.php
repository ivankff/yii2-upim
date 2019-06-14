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
class EntityImagesBehavior extends Behavior
{

    /**
     * @var array|string
     */
    public $imagesClass;
    /**
     * @var string
     */
    public $dir;
    /**
     * @var int
     */
    public $widen = 0;

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
     * @throws
     */
    private function _getImages()
    {
        if (null === $this->_images) {
            $config = is_array($this->imagesClass) ? $this->imagesClass : ['class' => $this->imagesClass];
            $config = ArrayHelper::merge(['dir' => $this->dir, 'widen' => $this->widen], $config);

            $this->_images = \Yii::createObject($config);

            if (! $this->owner->isNewRecord)
                $this->_images->load($this->owner->primaryKey);
        }
        return $this->_images;
    }

}