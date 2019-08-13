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
     * @param int $i - номер фоты >=1
     * @return null
     */
    public function get($type, $i = 1);

}