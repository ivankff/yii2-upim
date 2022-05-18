<?php

namespace ivankff\yii2UploadImages;

use Yii;
use yii\base\Action;
use yii\base\InvalidArgumentException;
use yii\db\ActiveRecord;
use yii\helpers\FileHelper;
use yii\image\drivers\Image;
use yii\image\drivers\Image_GD;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 */
class ImageAction extends Action
{

    /**
     * @var string класс ActiveRecord \common\entities\Product\Product
     */
    public $activeRecordClass;
    /**
     * @var string ключ поведения для ImagesBehavior
     */
    public $behaviorKey = 'images';

    /** @var bool check security hash */
    public $checkHash = true;
    /** @var string $cachePath path alias relative with webroot where the cache files are kept  */
    public $cachePath = '@cache-image';
    /** @var int $cacheExpire */
    public $cacheExpire = 3600 * 24 * 7;
    /** @var int $imageQuality */
    public $imageQuality = 80;

    /** @var ImageActionRequest */
    private $_params;
    /** @var string */
    private $_filePath;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        if (! class_exists($this->activeRecordClass))
            throw new InvalidArgumentException('"activeRecordClass" must be configurated');

        if (empty($this->behaviorKey))
            throw new InvalidArgumentException('"behaviorKey" must be configurated');

        if ($this->controller && $this->cacheExpire > 0) {
            $this->controller->attachBehavior("{$this->id}-cache", [
                'class' => 'yii\filters\HttpCache',
                'only' => [$this->id],
                'cacheControlHeader' => "public, max-age={$this->cacheExpire}",
                'lastModified' => function ($action, $params) {
                    /** @var static $action */
                    return filemtime($action->getFilePath());
                },
                'etagSeed' => function ($action, $params) {
                    /** @var static $action */
                    return sha1_file($action->getFilePath());
                },
            ]);
        }

        parent::init();
    }

    /**
     * @return mixed
     */
    public function run()
    {
        $params = $this->getParams();
        $filePath = $this->getFilePath();

        if (! $params->w && ! $params->h)
            return $this->_render($filePath, $params);

        $cacheFilename = $params->getCacheFilename($filePath);
        $cachePath = Yii::getAlias($this->cachePath . "/" . mb_substr($cacheFilename, 0, 2) . "/" . $cacheFilename);

        if ($this->cacheExpire > 0 && file_exists($cachePath) && (filemtime($cachePath) + $this->cacheExpire) < time())
            unlink($cachePath);

        if (! is_file($cachePath)) {
            $cacheDir = dirname($cachePath);

            if (! is_dir($cacheDir))
                FileHelper::createDirectory($cacheDir);

            /** @var Image_GD $image */
            $image = \Yii::$app->image->load($filePath);

            if ($image->mime === 'image/gif')
                return $this->_render($filePath, $params);

            $image->resize(min($params->w, $image->width), min($params->h, $image->height), $params->getMasterDimension());
            $image->save($cachePath, $this->imageQuality);
        }

        return $this->_render($cachePath, $params);
    }

    /**
     * @return ImageActionRequest
     */
    public function getParams()
    {
        if (null === $this->_params) {
            $this->_params = new ImageActionRequest();

            if (! ($this->_params->load(Yii::$app->request->get(), '') && $this->_params->validate()))
                throw new BadRequestHttpException('Invalid request');

            if ($this->checkHash && ! $this->_params->checkHash())
                throw new BadRequestHttpException('Invalid security hash');
        }

        return $this->_params;
    }

    /**
     * @return string
     */
    public function getFilePath()
    {
        if (null === $this->_filePath) {
            /** @var ActiveRecord $ar */
            /** @var ImagesInterface $images */
            $params = $this->getParams();
            $ar = new $this->activeRecordClass;
            $images = $ar->getBehavior($this->behaviorKey)->getImages();
            $images->load($params->id);
            $this->_filePath = $images->getOne($params->getI());

            if (! $this->_filePath)
                throw new NotFoundHttpException('Image not found');

            $this->_filePath = realpath($this->_filePath);
        }

        return $this->_filePath;
    }

    /**
     * @param Image|string $image
     * @param ImageActionRequest $params
     * @return string
     */
    private function _render($image, $params)
    {
        \Yii::$app->response->format = Response::FORMAT_RAW;

        if ($image instanceof Image) {
            \Yii::$app->response->headers->set('Content-Type', $image->mime);
            return $image->render($params->f, $this->imageQuality);
        }

        $info = getimagesize($image);
        \Yii::$app->response->headers->set('Content-Type', image_type_to_mime_type($info[2]));
        return file_get_contents($image);
    }

}
