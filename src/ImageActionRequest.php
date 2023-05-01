<?php

namespace ivankff\yii2UploadImages;

use yii\base\Model;
use yii\caching\CacheInterface;
use yii\caching\DummyCache;
use yii\helpers\ArrayHelper;
use yii\image\drivers\Image;

/**
 */
class ImageActionRequest extends Model
{

    public $id;
    public $i;
    public $t;
    public $type;

    /* PhpThumb parameters */
    public $w;
    public $h;
    public $zc;
    public $f;
    public $hash;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['i'], 'default', 'value' => 1, 'when' => function($model) {
                /** @var self $model */
                return $model->type === PluralImages::TYPE_MAIN;
            }],
            [['i', 'w', 'h'], 'integer', 'min' => 1],
            [['f'], 'in', 'range' => ['png', 'jpg', 'jpeg', 'gif', 'webp']],
            [['zc'], 'boolean'],
            [['id', 'type', 'hash', 't'], 'string'],
        ];
    }

    /** @return bool */
    public function checkHash() { return $this->hash === $this->_getHash(); }

    /** @return int */
    public function getMasterDimension() { return $this->zc ? Image::CROP : Image::AUTO; }

    /**
     * @param $filePath
     * @return null|string
     */
    public function getCacheFilename($filePath)
    {
        if (!is_file($filePath))
            return null;

        $params = $this->_toArray(['t', 'hash', 'type']);
        $params['filemtime'] = filemtime($filePath);

        $cache = \Yii::$app->get('cache', false);
        if (!$cache instanceof CacheInterface)
            $cache = new DummyCache();

        return "{$cache->buildKey($params)}." . pathinfo($filePath, PATHINFO_EXTENSION);
    }

    /**
     * @param array $excludeParams
     * @return array
     */
    public function getRequestParams($excludeParams = [])
    {
        if (!ArrayHelper::isIn('hash', $excludeParams))
            $this->hash = $this->_getHash();

        return $this->_toArray($excludeParams);
    }

    /**
     * @return string
     */
    private function _getHash()
    {
        $params = $this->_toArray();
        $params['securityKey'] = ArrayHelper::getValue(\Yii::$app->params, 'images.securityKey');

        $cache = \Yii::$app->get('cache', false);
        if (!$cache instanceof CacheInterface)
            $cache = new DummyCache();

        return $cache->buildKey($params);
    }

    /**
     * @param string[] $except
     * @param bool $removeNull
     * @return array
     */
    private function _toArray($except = ['t', 'hash'], $removeNull = true)
    {
        $params = $this->getAttributes(null, $except);

        if ($removeNull)
            ArrayHelper::removeValue($params, null);

        foreach ($params as &$param)
            $param = (string)$param;

        return $params;
    }
}