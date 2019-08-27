<?php

namespace ivankff\yii2UploadImages;

use yii\base\Action;
use yii\web\Response;

/**
 */
class DeleteImageAction extends Action
{

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;
        return [];
    }

}