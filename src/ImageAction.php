<?php

namespace ivankff\yii2UploadImages;

use Yii;
use yii\base\Action;
use yii\base\InvalidArgumentException;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\image\drivers\Image;
use yii\image\drivers\Image_GD;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ImageAction extends Action
{

    /**
     * @var string class is extended by \ivankff\yii2UploadImages\BaseImages
     */
    public $imagesClass;
    /**
     * @var string директория с фото
     * @see \ivankff\yii2UploadImages\BaseImages::$dir
     */
    public $dir;
    /**
     * @var int обрезка по большей стороне
     * @see \ivankff\yii2UploadImages\BaseImages::$widen
     */
    public $widen;
    /**
     * @var string type of image. Example: mail, dop
     */
    public $type;

    /** @var bool check security hash */
    public $checkHash = true;
    /** @var string $cachePath path alias relative with webroot where the cache files are kept  */
    public $cachePath = '@cache-image';
    /** @var int $cacheExpire */
    public $cacheExpire = 0;
    /** @var int $imageQuality */
    public $imageQuality = 80;

    public function init()
    {
        if (! class_exists($this->imagesClass))
            throw new InvalidArgumentException('"imagesClass" must be configurated');

        if (empty($this->dir))
            throw new InvalidArgumentException('"dir" must be configurated');

        if (empty($this->type))
            throw new InvalidArgumentException('"type" must be configurated');

        parent::init();
    }

    /**
     * @return string
     * @throws
     */
    public function run()
    {
        try {
            /** @var ImageActionRequest $params */
            $h = new ImageActionRequest;
            $keys = array_keys(ArrayHelper::toArray($h));

            $get = ArrayHelper::filter(Yii::$app->request->get(), $keys);
            $get['type'] = $this->type;
            $get['class'] = ImageActionRequest::class;

            $params = Yii::createObject($get);
        } catch (\Exception $e) {
            throw new BadRequestHttpException('There is error in request');
        }

        /** @var ImagesInterface $images */
        $images = Yii::createObject([
            'class' => $this->imagesClass,
            'dir' => $this->dir,
            'widen' => $this->widen,
        ]);
        $images->load($params->id);

        if ($filePath = realpath($images->get($params->type, $params->getI()))) {
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
                $image->resize(min($params->w, $image->width), min($params->h, $image->height), $params->getMasterDimension());
                $image->save($cachePath, $this->imageQuality);
            }

            return $this->_render($cachePath, $params);
        }

        throw new NotFoundHttpException;
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