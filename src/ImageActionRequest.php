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
            [['i', 'w', 'h'], 'integer', 'min' => 1],
            [['f'], 'in', 'range' => ['png', 'jpg', 'jpeg', 'gif', 'webp']],
            [['zc'], 'boolean'],
            [['id', 'hash'], 'string'],
        ];
    }

    /** @return int */
    public function getI() { return (string)$this->i === "" ? 1 : (int)$this->i; }

    /** @return bool */
    public function checkHash() { return $this->hash === $this->_getHash(); }

    /** @return int */
    public function getMasterDimension() { return $this->zc ? Image::CROP : Image::AUTO; }

    /**
     * @param string $filePath
     * @return null|string
     */
    public function getCacheFilename($filePath)
    {
        $params = ArrayHelper::toArray($this);

        if (!is_file($filePath))
            return null;

        $params['filemtime'] = filemtime($filePath);
        $aFileInfo = pathinfo($filePath);

        $cache = \Yii::$app->get('cache', false);
        if (! $cache instanceof CacheInterface)
            $cache = new DummyCache();

        return "{$cache->buildKey($params)}.{$aFileInfo['extension']}";
    }

    /**
     * @param array $excludeParams
     * @return array
     */
    public function getRequestParams($excludeParams = [])
    {
        if (! ArrayHelper::isIn('hash', $excludeParams))
            $this->hash = $this->_getHash();

        $params = ArrayHelper::toArray($this);
        ArrayHelper::removeValue($params, null);

        foreach ($excludeParams as $param)
            ArrayHelper::remove($params, $param);

        foreach ($params as &$param)
            $param = (string) $param;

        return $params;
    }

    /**
     * @return string
     */
    private function _getHash()
    {
        $params = ArrayHelper::toArray($this);
        ArrayHelper::remove($params, 'hash');

        foreach ($params as &$param)
            $param = (string) $param;

        $params['securityKey'] = ArrayHelper::getValue(\Yii::$app->params, 'images.securityKey');

        $cache = \Yii::$app->get('cache', false);
        if (! $cache instanceof CacheInterface)
            $cache = new DummyCache();

        return $cache->buildKey($params);
    }

}
