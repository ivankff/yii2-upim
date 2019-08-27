<?php

namespace ivankff\yii2UploadImages;

use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\JsExpression;

class FileInputWidget extends \kartik\widgets\FileInput
{

    /**
     * {@inheritdoc}
     */
    public function __construct(array $config = [])
    {
        $previews = ArrayHelper::getValue($config, 'pluginOptions.initialPreview');

        $keys = [];
        foreach ($previews as $k=>$v) $keys[] = ['key' => $k];

        $config = ArrayHelper::merge([
            'options'=>[
                'accept' => 'image/*'
            ],
            'pluginOptions' => [
                'initialPreviewConfig' => $keys,
                'overwriteInitial' => true,
                'initialPreviewAsData' => true,
                'showRemove' => false,
                'showUpload' => false,
                'showCancel' => false,
                'showClose' => false,
                'deleteUrl' => Url::to(['delete-image']),
            ]
        ], $config);

        parent::__construct($config);
    }

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        $attribute = Html::getAttributeName($this->attribute);
        $keysInputId = Html::getInputId($this->model, "{$attribute}_keys");

        $this->pluginOptions = ArrayHelper::merge($this->pluginOptions, [
            'fileActionSettings' => [
                'dragSettings' => [
                    'onSort' => new \yii\web\JsExpression("function(event){
                            var keys = console.log(event);
                            var keys = jQuery('#{$keysInputId}').val().split(',');
                            var buff = keys[event.oldIndex];
                            keys[event.oldIndex] = keys[event.newIndex];
                            keys[event.newIndex] = buff;
                            jQuery('#{$keysInputId}').val(keys.join(','));
                        }"),
                ],
            ],
        ]);

        parent::init();
        echo Html::activeHiddenInput($this->model, "{$attribute}_keys");
    }

    /**
     * {@inheritdoc}
     */
    public function registerAssets()
    {
        parent::registerAssets();
        $attribute = Html::getAttributeName($this->attribute);
        $inputId = Html::getInputId($this->model, $this->attribute);
        $keysInputId = Html::getInputId($this->model, "{$attribute}_keys");

        $this->getView()->registerJs(new JsExpression("jQuery('#{$inputId}').on('filedeleted', function(event, key, jqXHR, data) {
                            var keys = console.log(event);
                            var keys = jQuery('#{$keysInputId}').val().split(',');
                            var idx = keys.indexOf(String(key));
                            console.log(keys);
                            console.log(toString(key));
                            console.log(idx);
                            keys.splice(idx, 1);
                            jQuery('#{$keysInputId}').val(keys.join(','));
                            console.log(keys);
});"));
    }

}