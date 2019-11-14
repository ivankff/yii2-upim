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
                    'onSort' => new \yii\web\JsExpression("function(event) {
                            var keys = String(jQuery('#{$keysInputId}').val()).split(',');
                            var buff = keys[event.oldIndex];
                            keys.splice(event.oldIndex, 1);
                            keys.splice(event.newIndex, 0, buff);
                            jQuery('#{$keysInputId}').val(keys.join(','));
                        }"),
                ],
            ],
        ]);

        parent::init();
        echo Html::activeHiddenInput($this->model, "{$attribute}_keys", ['data-keys' => "{$this->model->{"{$attribute}_keys"}}"]);
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
                            var keys = String(jQuery('#{$keysInputId}').data('keys')).split(',');
                            var value = String(jQuery('#{$keysInputId}').val()).split(',');
                            var idx = value.indexOf(keys[key]);
                            value.splice(idx, 1);
                            jQuery('#{$keysInputId}').val(value.join(','));
});"));
    }

}