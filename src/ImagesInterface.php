<?php

namespace ivankff\yii2UploadImages;

/**
 */
interface ImagesInterface
{

    const TRIM_START = 'start';
    const TRIM_SECOND = 'second';
    const TRIM_END = 'end';

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
     * @return bool
     */
    public function isEmpty();
    /**
     * @param int $i - номер фоты >= 1
     * @return string|null
     */
    public function getOne($i);
    /**
     * @return string|null - первая фотка
     */
    public function getFirst();
    /**
     * @return string[] - все остальные фотки, начиная со второй
     * индексы сохранены, т.е. начинается с 2
     */
    public function getDop();
    /**
     * @return string[] - все фотки
     */
    public function getAll();
    /**
     * @param string|array $files один или несколько файлов
     * @param bool $keepOriginal копировать или перемещать оригинал
     * @return bool
     */
    public function add($files, $keepOriginal = false);
    /**
     * @param string|array $files один или несколько файлов
     * @param bool $keepOriginal копировать или перемещать оригинал
     * @return null
     */
    public function replace($files, $keepOriginal = false);

}
