<?php

namespace ivankff\yii2UploadImages;

use Yii;
use Assert\Assertion;
use yii\base\BaseObject;
use yii\base\ErrorException;
use yii\image\drivers\Kohana_Image_GD;

/**
 * TODO: функции move
 */
abstract class BaseImages extends BaseObject implements ImagesInterface
{

    /**
     * @var string папка для хранения изображений
     */
    public $dir;
    /**
     * @var int максимальный размер по большей стороне
     */
    public $widen = 0;
    /**
     * @var string[] возможные расширения для поиска файлов в папке
     */
    public $extensions = ['jpg', 'png'];

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
        foreach ($this->types() as $type => $options) {
            $this->_images[$type] = [];
        }

        return true;
    }

    /**
     * @param string $type
     * @param int $i - номер фоты >=1
     * @return null
     */
    public function get($type, $i = 1)
    {
        return $this->_get($type, $i);
    }

    /**
     * @param string $type
     * @return null
     */
    public function getAll($type)
    {
        return $this->_getAll($type);
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

        foreach ($this->types() as $type => $options) {
            $toDelete = array_diff($oldImages[$type], $this->_images[$type]);

            foreach ($toDelete as $file){
                if (file_exists($file)){
                    if (!unlink($file)) {
                        \Yii::warning("Файл {$file} не был удален", __FUNCTION__);
                        $result = false;
                    }
                }
            }

            $ftmp = [];

            foreach ($this->_images[$type] as $i => $file) {
                try {
                    /** @var Kohana_Image_GD $image */
                    $image = Yii::$app->image->load($file);

                    $ext = image_type_to_extension($image->type, false);
                    if ('jpeg' === $ext) $ext = 'jpg';
                    $newFile = $this->_getImagePath($id, $type, $i, $ext);

                    if (!in_array($file, $oldImages[$type])) {
                        if (!empty($options['widen'])) {
                            if ($image->width > $options['widen'] || $image->height > $options['widen']) {
                                $image->resize($options['widen'], $options['widen']);
                                $save = false;

                                try {
                                    $save = $image->save($newFile, 100);
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
            }

        }

        $this->_keepOriginal = null;
        $this->_images = $this->_load($id);
        return $result;
    }

    /**
     * @param string $type
     * @param int $i - номер фоты >=1
     * @return string|null
     */
    protected function _get($type, $i = 1)
    {
        return isset($this->_images[$type][$i]) ? $this->_images[$type][$i] : null;
    }

    /**
     * @param string $type
     * @return array
     */
    protected function _getAll($type)
    {
        return isset($this->_images[$type]) ? $this->_images[$type] : [];
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

        $images = [];

        foreach ($this->types() as $type => $options) {
            $images[$type] = [];
            $maxT = $max !== null ? $max : $this->types()[$type]['max'];
            $c = 0;

            for ($i=1; $i<=$maxT; $i++) {
                foreach ($this->extensions as $extension) {
                    $file = $this->_getImagePath($id, $type, $i, $extension);
                    if (file_exists($file)) {
                        $images[$type][++$c] = $file;
                    }
                }
            }
        }
        return $images;
    }

    /**
     * @param string $type
     * @param int $i - порядковый номер 1,2,3....
     * @return bool
     */
    protected function _delete($type, $i)
    {
        Assertion::inArray($type, array_keys($this->types()));
        if (isset($this->_images[$type][$i])) {
            unset($this->_images[$type][$i]);
            return true;
        }
        return false;
    }

    /**
     * @param string $type
     * @return bool
     */
    protected function _truncate($type)
    {
        Assertion::inArray($type, array_keys($this->types()));
        $this->_images[$type] = [];
        return false;
    }

    /**
     * @param string $type
     * @param string[] $files
     * @return bool
     */
    protected function _add($type, array $files)
    {
        Assertion::allFile($files);
        Assertion::inArray($type, array_keys($this->types()));

        if (empty($this->_images[$type])) $this->_images[$type] = [];
        $this->_images[$type] += $files;
        $this->_slice($type);

        return true;
    }

    /**
     * @param string $type
     * @param string[] $files
     * @param bool $keepOriginal сохранить или нет переносимый файл
     * @return bool была ли замена или нет
     */
    protected function _replace($type, array $files, $keepOriginal = false)
    {
        Assertion::allFile($files);
        Assertion::inArray($type, array_keys($this->types()));

        $this->_keepOriginal = $keepOriginal;

        $old = $this->_get($type);
        unset($this->_images[$type]);
        $this->_add($type, $files);
        $new = $this->_get($type);

        return $old !== $new;
    }

    /**
     * @param string|int $id - идентификатор сущности
     * @param string $type - тип изображения
     * @param int $num - порядковый номер 1,2,3 ...
     * @param string $extension
     * @return string
     */
    private function _getImagePath($id, $type, $num, $extension)
    {
        return $this->_getDirToId($id) . DIRECTORY_SEPARATOR . $id . '-' . $type . $num . '.' . $extension;
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
        if (is_numeric($id)) $dir = $dir . DIRECTORY_SEPARATOR . floor($id/1000);
        else $dir = $dir . DIRECTORY_SEPARATOR . mb_substr($id, 0, 1);
        if (!file_exists($dir)) mkdir($dir, 0755, true);
        return $dir;
    }

    /**
     * @param string $type
     */
    private function _slice($type)
    {
        if ($values = array_slice(array_values($this->_images[$type]), -$this->types()[$type]['max'])) {
            $keys = range(1, sizeof($values));
            $this->_images[$type] = array_combine($keys, $values);
        } else {
            $this->_images[$type] = [];
        }
    }

}