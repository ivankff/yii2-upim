<?php

namespace ivankff\yii2UploadImages;

use Yii;
use yii\base\BaseObject;

/**
 */
class Gluer extends BaseObject
{

    /** @var string папка для хранения изображений */
    private $_dir;

    /**
     * @param string $dir
     * @param array $config
     */
    public function __construct($dir, $config = [])
    {
        $this->_dir = Yii::getAlias($dir);
        parent::__construct($config);
    }

    /**
     */
    public function glue()
    {
        $ids = [];

        foreach (scandir($this->_dir) as $item) {
            if ($item === "." || $item === "..")
                continue;

            $path = $this->_dir . "/" . $item;
            if (is_dir($path)) {
                foreach (scandir($path) as $subitem) {
                    if ($subitem === "." || $subitem === "..")
                        continue;

                    if (preg_match("/^([0-9]+)\-/", $subitem, $m)) {
                        if (! in_array($m[1], $ids)) {
                            if ($id = $this->_glue($path, $subitem)) {
                                $ids[] = $id;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @param string $dir
     * @param string $filename
     */
    private function _glue($dir, $filename)
    {
        $id = null;
        if (preg_match("/^([0-9]+)\-/", $filename, $m))
            $id = $m[1];

        if (! $id)
            return null;

        $main = glob("{$dir}/{$id}-main*");
        $dop = glob("{$dir}/{$id}-dop*");

        $c = 0;
        foreach (array_merge($main, $dop) as $filepath) {
            $c++;
            $pathinfo = pathinfo($filepath);
            rename($filepath, "{$dir}/{$id}-{$c}.{$pathinfo['extension']}");
        }

        return $id;
    }

}
