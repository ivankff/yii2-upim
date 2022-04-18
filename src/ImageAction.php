<?php

namespace ivankff\yii2UploadImages;

use Yii;
use yii\base\Action;
use yii\base\InvalidArgumentException;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
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
    public $cacheExpire = 0;
    /** @var int $imageQuality */
    public $imageQuality = 80;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        if (! class_exists($this->activeRecordClass))
            throw new InvalidArgumentException('"activeRecordClass" must be configurated');

        if (empty($this->behaviorKey))
            throw new InvalidArgumentException('"behaviorKey" must be configurated');

        parent::init();
    }

    /**
     * @return string
     * @throws
     */
    public function run()
    {
        $params = new ImageActionRequest();

        if (! ($params->load(ArrayHelper::filter(Yii::$app->request->get(), $params->safeAttributes()), '') && $params->validate()))
            throw new BadRequestHttpException('There is an error in request');

        /** @var ActiveRecord $ar */
        /** @var ImagesInterface $images */
        $ar = new $this->activeRecordClass;
        $images = $ar->getBehavior($this->behaviorKey)->getImages();
        $images->load($params->id);

        if ($filePath = $images->getOne(max($params->i, 1))) {
            $filePath = realpath($filePath);

            if ($this->checkHash && ! $params->checkHash())
                throw new BadRequestHttpException('Invalid security hash');

            if (! $params->w && ! $params->h)
                return $this->_render($filePath, $params);

            $cacheFilename = $params->getCacheFilename($filePath);
            $cachePath = Yii::getAlias($this->cachePath . '/' . substr($cacheFilename, 0, 2) . '/' . $cacheFilename);

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

        throw new NotFoundHttpException('Image not found');
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
        \Yii::$app->response->headers->set('Content-Type', $info[2]);
        return file_get_contents($image);
    }

}