<?php

namespace ivankff\yii2UploadImages;

use Yii;
use Assert\Assertion;
use yii\base\BaseObject;
use yii\base\ErrorException;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\image\drivers\Kohana_Image_GD;

/**
 * TODO: функции move
 */
class Images extends BaseObject implements ImagesInterface
{

    /** @var string папка для хранения изображений */
    public $dir;
    /** @var int максимальный размер по большей стороне */
    public $widen = 0;
    /** @var int максимальное кол-во фоток */
    public $maxCount = 20;
    /** @var string суффикс для имет файлов */
    public $suffix = '';
    /** @var string какие фотки удалять, если кол-во превышает максимальное: из начала, из конца, со второй фотки */
    public $trim = ImagesInterface::TRIM_SECOND;
    /** @var string[] возможные расширения для поиска файлов в папке */
    public $extensions = ['jpg', 'png', 'jpeg', 'gif', 'webp'];

    /** @var array */
    private $_images = [];
    /**
     * @var bool|null сохранять или нет оригинальное изображение при _save
     * @see _replace()
     * @see _save()
     */
    private $_keepOriginal;

    /**
     * {@inheritdoc}
     * @throws
     */
    public function init()
    {
        $this->dir = Yii::getAlias($this->dir);
        Assertion::directory($this->dir);
        Assertion::integer($this->widen);
        Assertion::notEmpty($this->extensions);
        Assertion::allString($this->extensions);
        Assertion::isInstanceOf(Yii::$app->get('image'), 'yii\image\ImageDriver');
    }

    /**
     * @param string|int $id
     * @return bool
     */
    public function save($id)
    {
        return $this->_save($id);
    }

    /**
     * @param string|int $id
     * @return bool
     */
    public function load($id)
    {
        $this->_images = $this->_load($id);
        return true;
    }

    /**
     * @return bool
     */
    public function clear()
    {
        $this->_images = [];
        return true;
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->_images);
    }

    /**
     * @param int $i - номер фоты >= 1
     * @return string|null
     */
    public function getOne($i)
    {
        return $this->_get($i);
    }

    /**
     * @return string|null
     */
    public function getFirst()
    {
        return $this->_get(1);
    }

    /**
     * @return string[]
     */
    public function getDop()
    {
        $images = $this->_images;
        ArrayHelper::remove($images, 1);
        return $images;
    }

    /**
     * @param string $type
     * @return null
     */
    public function getAll()
    {
        return $this->_getAll();
    }

    /**
     * @param string|array $files один или несколько файлов
     * @param bool $keepOriginal копировать или перемещать оригинал
     * @return bool
     */
    public function add($files, $keepOriginal = false)
    {
        return $this->_add((array)$files, $keepOriginal);
    }

    /**
     * @param string|array $files один или несколько файлов
     * @param bool $keepOriginal копировать или перемещать оригинал
     * @return null
     */
    public function replace($files, $keepOriginal = false)
    {
        return $this->_replace((array)$files, $keepOriginal);
    }

    /**
     * Сохранение фоток
     * @param string|int $id
     * @return bool
     * @throws
     */
    private function _save($id)
    {
        Assertion::notBlank($id);

        $result = true;
        $oldImages = $this->_load($id, 100);
        $toDelete = array_diff($oldImages, $this->_images);

        foreach ($toDelete as $file){
            if (file_exists($file)){
                if (! unlink($file)) {
                    \Yii::warning("Файл {$file} не был удален", __FUNCTION__);
                    $result = false;
                }
            }
        }

        $ftmp = [];

        foreach ($this->_images as $i => $file) {
            try {
                /** @var Kohana_Image_GD $image */
                $image = Yii::$app->image->load($file);

                $ext = image_type_to_extension($image->type, false);
                if ('jpeg' === $ext) $ext = 'jpg';
                $newFile = $this->_getImagePath($id, $i, $ext);

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
        $this->_images = $this->_load($id);
        return $result;
    }

    /**
     * @param int $i - номер фоты >= 1
     * @return string|null
     */
    protected function _get($i = 1)
    {
        return isset($this->_images[$i]) ? $this->_images[$i] : null;
    }

    /**
     * @return array
     */
    protected function _getAll()
    {
        return $this->_images ?: [];
    }

    /**
     * @param string|int $id
     * @param null|int $max
     * @return array
     * @throws
     */
    private function _load($id, $max = null)
    {
        Assertion::notBlank($id);
        $maxT = $max ?: $this->maxCount;

        $images = [];
        $c = 0;

        for ($i=1; $i<=$maxT; $i++)
            foreach ($this->extensions as $extension)
                if (file_exists($file = $this->_getImagePath($id, $i, $extension)))
                    $images[++$c] = $file;

        return $images;
    }

    /**
     * @param int $i - порядковый номер 1,2,3....
     * @return bool
     */
    protected function _delete($i)
    {
        if (isset($this->_images[$i])) {
            unset($this->_images[$i]);
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    protected function _truncate()
    {
        $this->_images = [];
        return false;
    }

    /**
     * @param string[] $files
     * @param bool $keepOriginal
     * @return bool
     */
    protected function _add(array $files, $keepOriginal)
    {
        $this->_keepOriginal = $keepOriginal;
        Assertion::allFile($files);

        if (empty($this->_images)) $this->_images = [];
        $this->_images += $files;
        $this->_trim();

        return true;
    }

    /**
     * @param string[] $files
     * @param bool $keepOriginal сохранить или нет переносимый файл
     * @return bool была ли замена или нет
     */
    protected function _replace(array $files, $keepOriginal)
    {
        Assertion::allFile($files);

        $old = $this->_getAll();
        $this->_images = [];
        $this->_add($files, $keepOriginal);
        $new = $this->_getAll();

        return $old !== $new;
    }

    /**
     * @param string|int $id - идентификатор сущности
     * @param int $num - порядковый номер 1,2,3 ...
     * @param string $extension
     * @return string
     */
    private function _getImagePath($id, $num, $extension)
    {
        return $this->_getDirToId($id) . "/" . $id . '-' . ($this->suffix ?: null) . $num . '.' . $extension;
    }

    /**
     * Создает категорию при необходимости
     * @param string|int $id - идентификатор сущности
     * @return string
     */
    private function _getDirToId($id)
    {
        $dir = $this->dir;
        $id = basename($id);
        if (is_numeric($id)) $dir = $dir . "/" . floor($id/1000);
        else $dir = $dir . "/" . mb_substr($id, 0, 1);
        FileHelper::createDirectory($dir);
        return $dir;
    }

    /**
     */
    private function _trim()
    {
        $values = [];
        $av = array_values($this->_images);

        if ($this->trim === ImagesInterface::TRIM_SECOND) {
            if ($first = ArrayHelper::remove($av, 0))
                $values[] = $first;

            if ($other = array_slice($av, -$this->maxCount+1))
                $values = array_merge($values, $other);
        } elseif ($this->trim === ImagesInterface::TRIM_END) {
            $values = array_slice($av, 0, $this->maxCount);
        } else {
            $values = array_slice($av, -$this->maxCount);
        }

        if ($values) {
            $keys = range(1, sizeof($values));
            $this->_images = array_combine($keys, $values);
        } else {
            $this->_images = [];
        }
    }

}
