# yii2-upim

Application bootstrap
------------------------------
```php
Yii::setAlias('@cache-image', dirname(dirname(dirname(__DIR__))) . '/alias/cache/image');
Yii::setAlias('@image', dirname(dirname(dirname(__DIR__))) . '/alias/image');
```

Application config
------------------------------
```php
'components' => [
    'image' => [
        'class' => 'yii\image\ImageDriver',
        'driver' => 'GD',  //GD or Imagick
    ],
],
```

Application params
------------------------------
```php
[
    'images.widen' => 2000,
    'images.securityKey' => 'some.security.key',
],
```

Active Record Entity
------------------------------

```php
/**
 * @property-read ImagesInterface $images
 */
class Product extends ActiveRecord
{

    public function behaviors()
    {
        return [
            'images' => [
                'class' => 'ivankff\yii2UploadImages\ImagesBehavior',
                'dir' => '@image/product',
                'widen' => (int)ArrayHelper::getValue(Yii::$app->params, 'image.widen', 0),
            ],
        ];
    }
    
}
```

Backend controller
------------------------------
```php
public function actions()
{
    return [
        'delete-image' => 'ivankff\yii2UploadImages\DeleteImageAction',
    ];
}
```

Backend form model
------------------------------
```php
/**
 * @method string[] getFiles($attribute)
 */
class ProductForm extends Model {

    /** @var UploadedFile */
    public $uploadImages;
    
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'upload' => [
                'class' => 'ivankff\yii2UploadImages\UploadBehavior',
                'formAttribute' => 'uploadImages',
                'initFiles' => $this->_ar->images->getAll(),
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['uploadImages_keys'], 'string'],
            [['uploadImages'], 'image', 'maxFiles' => 20],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'uploadImages' => 'Изображения',
        ];
    }

    /**
     * @param bool $runValidation
     * @return bool
     */
    public function save($runValidation = true)
    {
        if ($runValidation && !$this->validate())
            return false;

        $this->_ar->images->replace($this->getFiles('uploadImages'));

        return $this->_ar->save($runValidation);
    }
}
```

Backend _form view
------------------------------
```php
$uploadImages = [];

foreach ($model->getFiles('uploadImages') as $i => $image)
    $uploadImages[] = Yii::$app->router->productThumbnailDop($model->product, $i);

echo $form->field($model, 'uploadImages[]')->widget('ivankff\yii2UploadImages\FileInputWidget', [
    'options' => [
        'multiple' => true,
    ],
    'pluginOptions' => [
        'initialPreview' => $uploadImages,
        'overwriteInitial' => false,
    ]
]);
```

Router component
------------------------------
```php
class Router extends Component
{

    /**
     * @var string компонент-менеджер фронтенда
     */
    public $urlManagerFrontend = 'urlManagerFrontend';

    /** @var UrlManager */
    private $_frontendManager;
    
    /**
     * @param Product $product
     * @param array $params
     * @param int $i
     * @return null|string
     */
    public function productThumbnail(Product $product, int $i, $params = [])
    {
        $req = $this->_getImageRequest($params, $product->id, $i);

        return $this->_frontendManager->createUrl(ArrayHelper::merge(
            ['/catalog/product/picture', 'name' => Inflector::slug($product->title)],
            $req->getRequestParams()
        ));
    }
    
    /**
     * @param array $params
     * @param int $id
     * @param int|null $i
     * @return \ivankff\yii2UploadImages\ImageActionRequest
     * @throws
     */
    private function _getImageRequest($params, $id, $i = null)
    {
        $params['class'] = 'ivankff\yii2UploadImages\ImageActionRequest';
        $params['id'] = $id;
        $params['i'] = $i;

        return Yii::createObject($params);
    }
    
}
```

Frontend controller
------------------------------
```php
public function actions()
{
    return [
        'picture' => [
            'class' => 'ivankff\yii2UploadImages\ImageAction',
            'activeRecordClass' => 'common\entities\Product\Product',
        ],
    ];
}
```

_urlManager.php config
------------------------------
```php
['pattern' => 'picture/<name>__p<id:\d+>m.jpg', 'route' => 'catalog/product/picture', 'suffix' => false, 'defaults' => ['i' => 1]],
['pattern' => 'picture/<name>__p<id:\d+>d<i:\d+>.jpg', 'route' => 'catalog/product/picture', 'suffix' => false],
```
