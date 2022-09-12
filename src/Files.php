<?php

namespace ivankff\yii2UploadImages;

use Yii;
use Assert\Assertion;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;

/**
 */
class Files extends BaseObject implements FilesInterface
{

    /** @var string папка для хранения файлов */
    public $dir;
    /** @var int максимальное кол-во файлов */
    public $maxCount = 20;
    /** @var string суффикс для имен файлов */
    public $suffix = '';
    /** @var string какие файлы удалять, если кол-во превышает максимальное: из начала, из конца, со второго файла */
    public $trim = FilesInterface::TRIM_START;
    /** @var string[] возможные расширения для поиска файлов в папке */
    public $extensions = ['pdf', 'csv', 'xls', 'xlsx', 'txt', 'doc', 'docx', 'rar', 'zip'];

    /** @var array */
    protected $_files = [];
    /**
     * @var bool|null сохранять или нет оригинальный файл при _save
     * @see _replace()
     * @see _save()
     */
    protected $_keepOriginal;

    /**
     * {@inheritdoc}
     * @throws
     */
    public function init()
    {
        $this->dir = Yii::getAlias($this->dir);
        Assertion::directory($this->dir);
        Assertion::integerish($this->maxCount);
        Assertion::greaterOrEqualThan($this->maxCount, 1);
        Assertion::notEmpty($this->extensions);
        Assertion::allString($this->extensions);
        parent::init();
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
        $this->_files = $this->_load($id);
        return true;
    }

    /**
     * @return bool
     */
    public function clear()
    {
        $this->_files = [];
        return true;
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->_files);
    }

    /**
     * @return string
     */
    public function getDir()
    {
        return empty($this->dir);
    }

    /**
     * @param int $i - номер файла >= 1
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
        $files = $this->_files;
        ArrayHelper::remove($files, 1);
        return $files;
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
     * Сохранение файлов
     * @param string|int $id
     * @return bool
     * @throws
     */
    protected function _save($id)
    {
        Assertion::notBlank($id);

        $result = true;
        $oldFiles = $this->_load($id, 100);

        $filesToDiff = [];
        foreach ($this->_files as $i => $file)
            if (is_string($file))
                $filesToDiff[$i] = $file;

        $toDelete = array_diff($oldFiles, $filesToDiff);

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
                if (is_array($file)) {
                    list($tempName, $fileName) = $file;
                    $path_info = pathinfo($fileName);
                    $ext = $path_info['extension'];
                    $newFile = $this->_getFilePath($id, $i, $ext);
                    $file = $tempName;
                } else {
                    $path_info = pathinfo($file);
                    $ext = $path_info['extension'];
                    $newFile = $this->_getFilePath($id, $i, $ext);
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

    /**
     * @param string|int $id
     * @param null|int $max
     * @return array
     * @throws
     */
    protected function _load($id, $max = null)
    {
        Assertion::notBlank($id);
        $maxT = $max ?: $this->maxCount;

        $files = [];
        $c = 0;

        for ($i=1; $i<=$maxT; $i++)
            foreach ($this->extensions as $extension)
                if (file_exists($file = $this->_getFilePath($id, $i, $extension)))
                    $files[++$c] = $file;

        return $files;
    }

    /**
     * @param int $i - номер файла >= 1
     * @return string|null
     */
    private function _get($i = 1)
    {
        return isset($this->_files[$i]) ? $this->_files[$i] : null;
    }

    /**
     * @return array
     */
    private function _getAll()
    {
        return $this->_files ?: [];
    }

    /**
     * @param string[] $files
     * @param bool $keepOriginal
     * @return bool
     */
    private function _add(array $files, $keepOriginal)
    {
        $this->_keepOriginal = $keepOriginal;

        if (empty($this->_files)) $this->_files = [];
        $this->_files = array_merge(array_values($this->_files), array_values($files));
        $this->_trim();

        return true;
    }

    /**
     * @param string[] $files
     * @param bool $keepOriginal сохранить или нет переносимый файл
     * @return bool была ли замена или нет
     */
    private function _replace(array $files, $keepOriginal)
    {
        $old = $this->_getAll();
        $this->_files = [];
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
    protected function _getFilePath($id, $num, $extension)
    {
        return $this->_getDirToId($id) . "/" . $id . '-' . ($this->suffix ?: null) . $num . '.' . $extension;
    }

    /**
     * Создает директорию при необходимости
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
        $av = array_values($this->_files);

        if ($this->trim === FilesInterface::TRIM_SECOND) {
            if ($first = ArrayHelper::remove($av, 0))
                $values[] = $first;

            if ($other = array_slice($av, -$this->maxCount+1))
                $values = array_merge($values, $other);
        } elseif ($this->trim === FilesInterface::TRIM_END) {
            $values = array_slice($av, 0, $this->maxCount);
        } else {
            $values = array_slice($av, -$this->maxCount);
        }

        if ($values) {
            $keys = range(1, sizeof($values));
            $this->_files = array_combine($keys, $values);
        } else {
            $this->_files = [];
        }
    }

}
