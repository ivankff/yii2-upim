<?php

namespace ivankff\yii2UploadImages;

use Yii;
use Assert\Assertion;
use yii\base\ErrorException;
use yii\image\drivers\Kohana_Image_GD;

/**
 */
class Images extends Files
{

    /** @var int максимальный размер по большей стороне */
    public $widen = 0;
    /** @var string какие файлы удалять, если кол-во превышает максимальное: из начала, из конца, со второго файла */
    public $trim = FilesInterface::TRIM_SECOND;
    /** @var string id компонента для ресайза изображений */
    public $imageComponent = 'image';
    /** @var string[] возможные расширения для поиска файлов в папке */
    public $extensions = ['jpg', 'png', 'jpeg', 'gif', 'webp'];

    /**
     * {@inheritdoc}
     * @throws
     */
    public function init()
    {
        Assertion::integer($this->widen);
        Assertion::notEmpty($this->imageComponent);
        Assertion::isInstanceOf(Yii::$app->get($this->imageComponent), 'yii\image\ImageDriver');
        parent::init();
    }

    /**
     * @param string|int $id
     * @return bool
     * @throws
     */
    protected function _save($id)
    {
        Assertion::notBlank($id);

        $result = true;
        $oldImages = $this->_load($id, 100);
        $toDelete = array_diff($oldImages, $this->_files);

        foreach ($toDelete as $file) {
            if (file_exists($file)) {
                if (! unlink($file)) {
                    \Yii::warning("Файл {$file} не был удален", __FUNCTION__);
                    $result = false;
                }
            }
        }

        $ftmp = [];

        foreach ($this->_files as $i => $file) {
            try {
                /** @var Kohana_Image_GD $image */
                $image = Yii::$app->get($this->imageComponent)->load($file);

                $ext = image_type_to_extension($image->type, false);
                if ('jpeg' === $ext) $ext = 'jpg';
                $newFile = $this->_getFilePath($id, $i, $ext);

                if (! in_array($file, $oldImages)) {
                    if (! empty($this->widen)) {
                        if ($image->width > $this->widen || $image->height > $this->widen) {
                            $image->resize($this->widen, $this->widen);
                            $save = false;

                            try {
                                $save = $image->save($newFile, 100);
                                touch($newFile);
                            } catch (ErrorException $e) {
                                \Yii::warning("Файл {$file} не был сохранен с ресайзом в {$newFile}", __FUNCTION__);
                            }

                            if ($save) {
                                unset($image);
                                if (!$this->_keepOriginal && !unlink($file)) {
                                    \Yii::info("Файл {$file} не был удален", __FUNCTION__);
                                }

                                continue;
                            } else {
                                $result = false;
                                \Yii::warning("Файл {$file} не был сохранен с ресайзом в {$newFile}", __FUNCTION__);
                            }
                        }
                    }
                }
                if ($file !== $newFile) {
                    if (! call_user_func($this->_keepOriginal ? 'copy' : 'rename', $file, $newFile . '.tmp')) {
                        $result = false;
                        \Yii::warning("Файл {$file} не был переименован в {$newFile}", __FUNCTION__);
                    } else {
                        $ftmp[$newFile . '.tmp'] = $newFile;
                    }
                }
            } catch (\Exception $e) {
                $result = false;
                \Yii::error($e->getMessage() . PHP_EOL . $e->getTraceAsString(), __METHOD__);
            }
        }

        foreach ($ftmp as $tmp => $file) {
            rename($tmp, $file);
            touch($file);
        }

        $this->_keepOriginal = null;
        $this->_files = $this->_load($id);
        return $result;
    }

}
