<?php

namespace ivankff\yii2UploadImages\actions;

use yii\base\Model;
use yii\helpers\ArrayHelper;

/**
 */
class FileActionRequest extends Model
{

    public $id;
    public $i;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['i'], 'integer', 'min' => 1],
            [['id'], 'string'],
        ];
    }

    /** @return int */
    public function getI() { return (string)$this->i === "" ? 1 : (int)$this->i; }

    /**
     * @param array $excludeParams
     * @return array
     */
    public function getRequestParams($excludeParams = [])
    {
        $params = ArrayHelper::toArray($this);
        ArrayHelper::removeValue($params, null);

        foreach ($excludeParams as $param)
            ArrayHelper::remove($params, $param);

        foreach ($params as &$param)
            $param = (string) $param;

        return $params;
    }

}
