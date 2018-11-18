<?php

namespace ivankff\yii2UploadImages;

class PluralImages extends BaseImages
{

    const TYPE_MAIN = 'main';
    const TYPE_DOP = 'dop';

    /**
     * @return null|string
     */
    public function getMain()
    {
        return $this->_get(self::TYPE_MAIN);
    }

    /**
     * @return array
     */
    public function getDop()
    {
        return $this->_getAll(self::TYPE_DOP);
    }

    /**
     * @param int $i >=1
     * @return null|string
     */
    public function getDopOne(int $i)
    {
        return $this->_get(self::TYPE_DOP, $i);
    }

    /**
     * @param string|null $file
     * @return bool
     */
    public function replaceMain(string $file = null)
    {
        $files = !empty($file) ? [$file] : [];
        return $this->_replace(self::TYPE_MAIN, $files);
    }

    /**
     * @param array $files
     * @return bool
     */
    public function replaceDop(array $files)
    {
        return $this->_replace(self::TYPE_DOP, $files);
    }

    /**
     * @return array
     */
    public function types()
    {
        return [
            self::TYPE_MAIN => ['max' => 1, 'widen' => $this->widen],
            self::TYPE_DOP => ['max' => 10, 'widen' => $this->widen],
        ];
    }

}