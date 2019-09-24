<?php

namespace ivankff\yii2UploadImages;

interface ImagesInterface
{

    /**
     * @return array
     */
    public function types();
    /**
     * @param string|int $id идентификатор AR
     * @return bool
     */
    public function save($id);
    /**
     * @param string|int $id идентификатор AR
     * @return bool
     */
    public function load($id);
    /**
     * @return bool
     */
    public function clear();
    /**
     * @param string $type
     * @param int $i - номер фоты >=0
     * @return string|null
     */
    public function get($type, $i = 0);
    /**
     * @param string $type
     * @return string[]
     */
    public function getAll($type);
    /**
     * @param string $type
     * @param string|array $files один или несколько файлов
     * @param bool $keepOriginal копировать или перемещать оригинал
     * @return bool
     */
    public function add($type, $files, $keepOriginal = false);
    /**
     * @param string $type
     * @param string|array $files один или несколько файлов
     * @param bool $keepOriginal копировать или перемещать оригинал
     * @return null
     */
    public function replace($type, $files, $keepOriginal = false);

}