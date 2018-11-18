<?php

namespace ivankff\yii2UploadImages;

use yii\base\Behavior;
use yii\base\InvalidArgumentException;
use yii\base\InvalidCallException;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\base\ModelEvent;
use yii\helpers\ArrayHelper;
use yii\helpers\StringHelper;
use yii\web\UploadedFile;

class UploadBehavior extends Behavior
{

    /**
     * @var Model
     */
    public $owner;
    /**
     * @var string[] список атрибутов, где возможна только одно изображение
     */
    public $single = [];
    /**
     * @var array[] список атрибутов, где возможно несколько изображений
     */
    public $multiple = [];

    protected $files;
    protected $keys = [];


    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
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

        $this->files = [];

        foreach ($this->single as $attribute => $file) {
            $this->files[$attribute] = ['type' => 'single', 'files' => $file ? [$file] : []];
        }
        foreach ($this->multiple as $attribute => $files) {
            $this->files[$attribute] = ['type' => 'multiple', 'files' => array_merge($files, [])];
        }

        foreach ($this->files as $attribute => $files) {
            if (! $this->owner->isAttributeSafe($attribute))
                throw new InvalidConfigException("Attribute {$attribute} should be safe");

            if (! $this->owner->isAttributeSafe("{$attribute}_keys"))
                throw new InvalidConfigException("Attribute {$attribute}_keys should be safe");
        }
    }


    /**
     * @param $attribute
     * @return string|null|string[]
     */
    public function getFiles($attribute)
    {
        if (null === $this->files)
            throw new InvalidCallException('Method can call after validate()');

        if (!isset($this->files[$attribute]))
            throw new InvalidArgumentException();

        return $this->files[$attribute]['type'] === 'multiple' ? $this->files[$attribute]['files'] : ($this->files[$attribute]['files'] ? reset($this->files[$attribute]['files']) : null);
    }


    /**
     * @param ModelEvent $event
     */
    public function beforeValidate($event)
    {
        foreach ($this->files as $attribute => $files) {
            if ($files['type'] === 'multiple') {
                $this->owner->{$attribute} = UploadedFile::getInstances($this->owner, $attribute);
            } else {
                $this->owner->{$attribute} = UploadedFile::getInstance($this->owner, $attribute);
            }
        }
    }


    /**
     * @param ModelEvent $event
     */
    public function afterValidate($event)
    {
        foreach ($this->files as $attribute => $files) {
            $keys = StringHelper::explode($this->owner->{"{$attribute}_keys"}, ',', true, true);

            if ($this->files[$attribute]['type'] === 'multiple') {
                foreach ($this->owner->{$attribute} as $tmpFile) {
                    $this->files[$attribute]['files'][] = $tmpFile->tempName;
                    $keys[] = sizeof($this->files[$attribute]['files']);
                }
            } elseif ($this->owner->{$attribute}) {
                $this->files[$attribute]['files'] = [$this->owner->{$attribute}->tempName];
                $keys[] = sizeof($this->files[$attribute]['files']);
            }

            $this->files[$attribute]['files'] = array_filter($this->files[$attribute]['files'], function($item, $i) use ($keys){
                return in_array($i+1, $keys);
            }, ARRAY_FILTER_USE_BOTH);

            uksort($this->files[$attribute]['files'], function ($a, $b) use ($keys) {
                $ka = array_search($a+1, $keys);
                $kb = array_search($b+1, $keys);

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
            if (! isset($this->keys[$attribute])) {
                $plus1 = function ($item) {
                    return $item + 1;
                };

                $this->keys[$attribute] = implode(',', array_map($plus1, array_keys($this->files[$attribute]['files'])));
            }

            return $this->keys[$attribute];
        }

        return parent::__get($name);
    }


    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        if (false !== $attribute = $this->_getAttributeForKey($name)) {
            $this->keys[$attribute] = $value;
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

            if (isset($this->files[$attribute]))
                return $attribute;
        }

        return false;
    }

}