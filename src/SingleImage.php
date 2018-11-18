<?php

namespace ivankff\yii2UploadImages;

class SingleImage extends BaseImages
{

    const TYPE_MAIN = 'main';

    /**
     * @return null|string
     */
    public function getMain()
    {
        return $this->_get(self::TYPE_MAIN);
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
     * @return array
     */
    public function types()
    {
        return [
            self::TYPE_MAIN => ['max' => 1, 'widen' => $this->widen],
        ];
    }

}