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
 * property-read FilesInterface $documents
 * property-read FilesInterface $instruction
 * property-read FilesInterface $warranty
 *
 * 'images' => [
 *     'class' => 'ivankff\yii2UploadFiles\behaviors\FilesBehavior',
 *     'dir' => '@files/product',
 * ],
 * 'instruction' => [
 *     'class' => 'ivankff\yii2UploadFiles\behaviors\FilesBehavior',
 *     'filesConfig' => [ "class" => "ivankff\yii2UploadFiles\Files", "suffix" => "instruction" ],
 *     'attributeName' => 'instruction',
 *     'dir' => '@image/product',
 *     'maxCount' => 1,
 * ],
 * 'warranty' => [
 *     'class' => 'ivankff\yii2UploadFiles\behaviors\FilesBehavior',
 *     'filesConfig' => [ "class" => "ivankff\yii2UploadFiles\Files", "suffix" => "warranty" ],
 *     'attributeName' => 'warranty',
 *     'dir' => '@image/product',
 *     'maxCount' => 1,
 * ],
 * ```
 */
class FilesBehavior extends Behavior
{

    /** @var array|string */
    public $filesConfig = [ "class" => "ivankff\yii2UploadImages\Files" ];
    /** @var string Название атрибута в owner, по которому можно будет обратиться к FilesInterface, как правило должно совпадать с behaviorName */
    public $attributeName = 'documents';
    /** @var string "@files/product" */
    public $dir;
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
            $config = ArrayHelper::merge($config, ['dir' => \Yii::getAlias($this->dir), 'maxCount' => $this->maxCount]);
            $this->_files = \Yii::createObject($config);

            if (! $this->owner->isNewRecord)
                $this->_files->load($this->owner->primaryKey);
        }

        return $this->_files;
    }

}
