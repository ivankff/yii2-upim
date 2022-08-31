<?php

namespace ivankff\yii2UploadImages\actions;

use ivankff\yii2UploadImages\events\ActionFileEvent;
use Yii;
use yii\base\Action;
use yii\base\InvalidArgumentException;
use yii\db\ActiveRecord;
use yii\helpers\FileHelper;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use ivankff\yii2UploadImages\Files;
use ivankff\yii2UploadImages\FilesInterface;

/**
 */
class FileAction extends Action
{

    /**
     * @var string класс ActiveRecord \common\entities\Product\Product
     */
    public $activeRecordClass;
    /**
     * @var string атрибут ActiveRecord для обращения к FilesInterface
     */
    public $attributeName = 'documents';
    /**
     * имя файла может быть по русски (перед сохранением переводится в латиницу)
     * ВНИМАНИЕ!!! имя файла должно быть без расширения
     * null - имя файла при сохранении берется из urlManager
     * @var string|callable|null
     * @see Inflector::slug()
     */
    public $fileName;

    /** @var FileActionRequest */
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

        if (empty($this->attributeName))
            throw new InvalidArgumentException('"attributeName" must be configurated');

        parent::init();
    }

    /**
     * @return mixed
     */
    public function run()
    {
        $filePath = FileHelper::normalizePath($this->getFilePath());
        $fileName = $this->getFileName();

        Yii::$app->response->headers->add('Content-Type', mime_content_type($filePath));
        return Yii::$app->response->sendFile($filePath, $fileName, StringHelper::endsWith($fileName, '.pdf') ? ['inline' => true] : []);
    }

    /**
     * @return string
     */
    public function getFileName()
    {
        $filePath = FileHelper::normalizePath($this->getFilePath());
        $fileName = null;

        if (is_string($this->fileName))
            $fileName = $this->fileName;
        elseif (is_callable($this->fileName))
            $fileName = call_user_func($this->fileName, $this);

        if (! $fileName)
            if (preg_match("/([^\/]+)$/", Yii::$app->request->pathInfo, $m))
                $fileName = $m[1];

        if (! $fileName)
            $fileName = StringHelper::basename($filePath);

        $fileName = trim($fileName);
        $fileName = preg_replace("/\s+/", " ", $fileName);
        $fileName = Inflector::slug($fileName, '_');

        /** @var Files $filesComponent */
        $ar = new $this->activeRecordClass;
        $filesComponent = $ar->{$this->attributeName};

        foreach ($filesComponent->extensions as $extension)
            if (StringHelper::endsWith($fileName, ".{$extension}"))
                $fileName = mb_substr($fileName, 0, -mb_strlen($extension)-1);

        $pathInfo = pathinfo($filePath);
        return "{$fileName}.{$pathInfo['extension']}";
    }

    /**
     * @return FileActionRequest
     */
    public function getParams()
    {
        if (null === $this->_params) {
            $this->_params = new FileActionRequest();
            $this->_params->load(Yii::$app->request->get(), "");

            if (! $this->_params->validate())
                throw new BadRequestHttpException("Invalid request");
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
            /** @var FilesInterface $files */
            $params = $this->getParams();
            $ar = new $this->activeRecordClass;
            $files = $ar->{$this->attributeName};
            $files->load($params->id);
            $this->_filePath = $files->getOne($params->getI());

            if (! $this->_filePath)
                throw new NotFoundHttpException("File not found");

            $this->_filePath = realpath($this->_filePath);
        }

        return $this->_filePath;
    }

}
