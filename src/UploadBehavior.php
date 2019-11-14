<?php

namespace ivankff\yii2UploadImages;

use yii\base\Behavior;
use yii\base\Event;
use yii\base\InvalidArgumentException;
use yii\base\InvalidCallException;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\base\ModelEvent;
use yii\db\ActiveRecord;
use yii\helpers\StringHelper;
use yii\web\UploadedFile;

/**
 * @property Model|ActiveRecord $owner
 */
class UploadBehavior extends Behavior
{

    /**
     * @var string[] список атрибутов, где возможна только одно изображение
     */
    public $single = [];
    /**
     * @var array[] список атрибутов, где возможно несколько изображений
     */
    public $multiple = [];

    /**
     * @var array
     */
    protected $_files;
    /**
     * @var array
     */
    protected $_keys = [];


    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_FIND => 'afterFind',
            Model::EVENT_BEFORE_VALIDATE => 'beforeValidate',
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
     * @param $attribute
     * @return string|null|string[]
     */
    public function getFiles($attribute)
    {
        if (null === $this->_files)
            throw new InvalidCallException('Method can call after validate()');

        if (!isset($this->_files[$attribute]))
            throw new InvalidArgumentException();

        return $this->_files[$attribute]['type'] === 'multiple' ? $this->_files[$attribute]['files'] : ($this->_files[$attribute]['files'] ? reset($this->_files[$attribute]['files']) : null);
    }

    /**
     * @param ModelEvent $event
     */
    public function beforeValidate($event)
    {
        foreach ($this->_files as $attribute => $files) {
            if ($files['type'] === 'multiple') {
                $this->owner->{$attribute} = UploadedFile::getInstances($this->owner, $attribute);
            } else {
                $this->owner->{$attribute} = UploadedFile::getInstance($this->owner, $attribute);
            }
        }
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
        foreach ($this->_files as $attribute => $files) {
            $keys = StringHelper::explode($this->owner->{"{$attribute}_keys"}, ',', true, true);

            if ($this->_files[$attribute]['type'] === 'multiple') {
                foreach ($this->owner->{$attribute} as $tmpFile) {
                    $key = max(array_keys($this->_files[$attribute]['files']) + [0]) + 1;
                    $this->_files[$attribute]['files'][$key] = $tmpFile->tempName;
                    $keys[] = $key;
                }
            } elseif ($this->owner->{$attribute}) {
                $this->_files[$attribute]['files'] = [1 => $this->owner->{$attribute}->tempName];
                $keys[] = 1;
            }

            $this->_files[$attribute]['files'] = array_filter($this->_files[$attribute]['files'], function($item, $i) use ($keys){
                return in_array($i, $keys);
            }, ARRAY_FILTER_USE_BOTH);

            uksort($this->_files[$attribute]['files'], function ($a, $b) use ($keys) {
                $ka = array_search($a, $keys);
                $kb = array_search($b, $keys);

                if ($ka === $kb)
                    return 0;

                return ($ka < $kb) ? -1 : 1;
            });
        }
    }

    /**
     * @inheritdoc
     */
    public function __get($name)
    {
        if (false !== $attribute = $this->_getAttributeForKey($name)){
            if (! isset($this->_keys[$attribute]))
                $this->_keys[$attribute] = implode(',', array_keys($this->_files[$attribute]['files']));

            return $this->_keys[$attribute];
        }

        return parent::__get($name);
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        if (false !== $attribute = $this->_getAttributeForKey($name)) {
            $this->_keys[$attribute] = $value;
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

            if (array_key_exists($attribute, $this->single))
                return $attribute;

            if (array_key_exists($attribute, $this->multiple))
                return $attribute;
        }

        return false;
    }

    /**
     * @throws
     */
    private function _fillFiles()
    {
        $this->_files = [];

        $images = null;
        $this->owner->ensureBehaviors();

        foreach ($this->owner->getBehaviors() as $key => $behavior) {
            if ($behavior instanceof EntityImagesBehavior) {
                $images = $behavior->getImages();

                if (! $this->owner->isNewRecord)
                    $images->load($this->owner->primaryKey);
            }
        }

        foreach ($this->single as $attribute => $file) {
            if ($images && in_array($file, array_keys($images->types()))) {
                $file = $images->get($file);
            }

            $this->_files[$attribute] = ['type' => 'single', 'files' => $file ? [1 => $file] : []];
        }
        foreach ($this->multiple as $attribute => $files) {
            foreach ($files as &$file) {
                if ($images && in_array($file, array_keys($images->types()))) {
                    $file = $images->get($file);
                }
            }

            $this->_files[$attribute] = ['type' => 'multiple', 'files' => $files];
        }

        foreach ($this->_files as $attribute => $files) {
            if (! $this->owner->isAttributeSafe($attribute))
                throw new InvalidConfigException("Attribute {$attribute} should be safe");

            if (! $this->owner->isAttributeSafe("{$attribute}_keys"))
                throw new InvalidConfigException("Attribute {$attribute}_keys should be safe");
        }
    }

}
