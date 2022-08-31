<?php

namespace ivankff\yii2UploadImages\actions;

use Yii;
use yii\base\Action;
use yii\web\Response;

/**
 */
class DeleteFileAction extends Action
{

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        return [];
    }

}
