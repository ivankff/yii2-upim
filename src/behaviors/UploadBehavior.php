<?php

namespace ivankff\yii2UploadImages\behaviors;

use yii\base\Behavior;
use yii\base\Event;
use yii\base\InvalidCallException;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\base\ModelEvent;
use yii\db\ActiveRecord;
use yii\helpers\StringHelper;
use yii\web\UploadedFile;

/**
 * @property Model|ActiveRecord $owner
 *
 * ActiveRecord behavior element:
 * ```php
 * property-read array $uploadImageFiles
 * 'upload' => [
 *     'class' => 'ivankff\yii2UploadFiles\behaviors\UploadBehavior',
 *     'formAttribute' => 'uploadImage',
 *     'attributeName' => 'images',
 * ],
 * ```
 *
 * Not ActiveRecord (Model) behavior element:
 * property-read array $uploadImageFiles
 * ```php
 * 'upload' => [
 *     'class' => 'ivankff\yii2UploadFiles\behaviors\UploadBehavior',
 *     'formAttribute' => 'uploadImage',
 *     'initFiles' => $this->_ar->images->getAll(),
 * ],
 * ```
 */
class UploadBehavior extends Behavior
{

    /** @var string атрибут формы, отвечающий за закачку */
    public $formAttribute;
    /** @var string|null атрибут для обращения к файлам, ТОЛЬКО для ActiveRecord */
    public $attributeName;
    /** @var string[] уже закаченные фотки, ТОЛЬКО не для ActiveRecord */
    public $initFiles = [];

    /** @var array */
    protected $_files;
    /** @var array */
    protected $_keys;

    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_FIND => 'afterFind',
            Model::EVENT_AFTER_VALIDATE => 'afterValidate',
        ];
    }

    /**
     * @inheritdoc
     */
    public function attach($owner)
    {
        parent::attach($owner);
        $this->_fillFiles();
    }

    /**
     * @param Event $event
     * @throws
     */
    public function afterFind($event)
    {
        $this->_fillFiles();
    }

    /**
     * @param ModelEvent $event
     */
    public function afterValidate($event)
    {
        $keys = StringHelper::explode($this->owner->{"{$this->formAttribute}_keys"}, ',', true, true);
        $uploadedFiles = UploadedFile::getInstances($this->owner, $this->formAttribute);

        if ($uploadedFiles instanceof UploadedFile)
            $uploadedFiles = [$uploadedFiles];

        foreach ($uploadedFiles as $tmpFile) {
            $key = max(array_keys($this->_files) + [0]) + 1;
            $this->_files[$key] = [$tmpFile->tempName, $tmpFile->name];
            $keys[] = $key;
        }

        $this->_files = array_filter($this->_files, function($item, $i) use ($keys) {
            return in_array($i, $keys);
        }, ARRAY_FILTER_USE_BOTH);

        uksort($this->_files, function ($a, $b) use ($keys) {
            $ka = array_search($a, $keys);
            $kb = array_search($b, $keys);

            if ($ka === $kb)
                return 0;

            return ($ka < $kb) ? -1 : 1;
        });
    }

    /**
     * @inheritdoc
     */
    public function __get($name)
    {
        if (false !== $attribute = $this->_getAttributeForKey($name)) {
            if (null === $this->_keys)
                $this->_keys = implode(',', array_keys($this->_files));

            return $this->_keys;
        }

        if (false !== $attribute = $this->_getAttributeForFiles($name)) {
            if (null === $this->_files)
                throw new InvalidCallException('Method can be called after validate()');

            $files = [];
            foreach ($this->_files as $i => $file)
                $files[$i] = is_array($file) ? $file[0] : $file;

            return $files;
        }

        return parent::__get($name);
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        if (false !== $attribute = $this->_getAttributeForKey($name)) {
            $this->_keys = $value;
            return;
        }

        parent::__set($name, $value);
    }

    /**
     * @inheritdoc
     */
    public function canGetProperty($name, $checkVars = true)
    {
        if (false !== $attribute = $this->_getAttributeForKey($name))
            return true;

        if (false !== $attribute = $this->_getAttributeForFiles($name))
            return true;

        return parent::canGetProperty($name, $checkVars);
    }

    /**
     * @inheritdoc
     */
    public function canSetProperty($name, $checkVars = true)
    {
        if (false !== $attribute = $this->_getAttributeForKey($name))
            return true;

        return parent::canSetProperty($name, $checkVars);
    }

    /**
     * @param string $key
     * @return bool|string
     */
    private function _getAttributeForKey($key)
    {
        if (StringHelper::endsWith($key, '_keys')) {
            $attribute = mb_substr($key, 0, -5);

            if ($attribute === $this->formAttribute)
                return $attribute;
        }

        return false;
    }

    /**
     * @param string $key
     * @return bool|string
     */
    private function _getAttributeForFiles($key)
    {
        if (StringHelper::endsWith($key, 'Files')) {
            $attribute = mb_substr($key, 0, -5);

            if ($attribute === $this->formAttribute)
                return $attribute;
        }

        return false;
    }

    /**
     */
    private function _fillFiles()
    {
        $this->_files = [];
        $this->owner->ensureBehaviors();

        if ($this->owner instanceof ActiveRecord) {
            $filesComponent = $this->owner->{$this->attributeName};

            if (! $this->owner->isNewRecord)
                $filesComponent->load($this->owner->primaryKey);

            $this->_files = $filesComponent->getAll();
        } else {
            $this->_files = $this->initFiles;
        }

        if (! $this->owner->isAttributeSafe($this->formAttribute))
            throw new InvalidConfigException("Attribute `{$this->formAttribute}` should be safe");

        if (! $this->owner->isAttributeSafe("{$this->formAttribute}_keys"))
            throw new InvalidConfigException("Attribute `{$this->formAttribute}_keys` should be safe");
    }

}
