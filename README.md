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
    ...
    'image' => [
        'class' => 'yii\image\ImageDriver',
        'driver' => 'GD',  //GD or Imagick
    ],
    ...
],
```

Application params
------------------------------
```php
[
    ...
    'images.widen' => 2000,
    'images.securityKey' => 'some.security.key',
    ...
],
```

Active Record Entity
------------------------------
```php
/**
 * @property-read PluralImages $images
 */
class Product extends ActiveRecord implements ImagesEntityInterface
{
...

    public function behaviors()
    {
        return [
            ...
            'images' => [
                'class' => 'ivankff\yii2UploadImages\EntityImagesBehavior',
                'imagesClass' => 'ivankff\yii2UploadImages\PluralImages',
                'dir' => static::getImagesDir(),
                'widen' => static::getImagesWiden(),
            ],
            ...
        ];
    }
    
    /** @return string */
    public static function getImagesDir() { return Yii::getAlias('@image/product'); }

    /** @return int */
    public static function getImagesWiden() { return (int)ArrayHelper::getValue(Yii::$app->params, 'image.widen', 0); }

...
}
```

Backend controller
------------------------------
```php
public function actions()
{
    return [
        ...
        'delete-image' => 'ivankff\yii2UploadImages\DeleteImageAction',
        ...
    ];
}
```

Backend form model
------------------------------
```php
/**
 * @method string|null|string[] getFiles($attribute)
 */
class ProductForm extends Model {

    /** @var UploadedFile */
    public $mainImage;
    
    /** @var UploadedFile[] */
    public $dopImages;
    
    ...
    
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'upload' => [
                'class' => 'ivankff\yii2UploadImages\UploadBehavior',
                'single' => [
                    'mainImage' => $this->_ar->images->getMain(),
                    'dopImages' => $this->_ar->images->getDop(),
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mainImage_keys', 'dopImages_keys'], 'string'],
            [['mainImage'], 'image'],
            [['dopImages'], 'image', 'maxFiles' => 10],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'mainImage' => 'Основное изображение',
            'dopImages' => 'Дополнительные изображения',
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

        $this->product->replaceMainImage($this->getFiles('mainImage'));
        $this->product->replaceDopImages($this->getFiles('dopImages'));

        return $this->product->save($runValidation);
    }
}
```

Backend _form view
------------------------------
```php
$mainImages = $dopImages = [];

if ($model->getFiles('mainImage'))
    $mainImages[] = Yii::$app->router->productThumbnailMain($model->product);

foreach ($model->getFiles('dopImages') as $i => $image)
    $dopImages[] = Yii::$app->router->productThumbnailDop($model->product, $i+1);

echo $form->field($model, 'mainImage')->widget('ivankff\yii2UploadImages\FileInputWidget', [
    'pluginOptions' => [
        'initialPreview' => $mainImages,
        'overwriteInitial' => true,
    ]
]);

echo $form->field($model, 'dopImages[]')->widget('ivankff\yii2UploadImages\FileInputWidget', [
    'options'=>[
        'multiple' => true,
    ],
    'pluginOptions' => [
        'initialPreview' => $dopImages,
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
    /**
     * @var string компонент-менеджер фронтенда
     */
    public $urlManagerBackend = 'urlManagerBackend';

    /** @var UrlManager */
    private $_frontendManager;

    /** @var UrlManager */
    private $_backendManager;
    
    ...
    
    /**
     * @param Product $product
     * @param array $params
     * @return null|string
     */
    public function productThumbnailMain(Product $product, $params = [])
    {
        $req = $this->_getImageRequest($params, $product->id, PluralImages::TYPE_MAIN);

        return $this->_frontendManager->createUrl(ArrayHelper::merge(
            ['/catalog/product/picture-main', 'name' => Inflector::slug($product->title)],
            $req->getRequestParams(['type'])
        ));
    }

    /**
     * @param Product $product
     * @param array $params
     * @param int $i
     * @return null|string
     */
    public function productThumbnailDop(Product $product, int $i, $params = [])
    {
        $req = $this->_getImageRequest($params, $product->id, PluralImages::TYPE_DOP, $i);

        return $this->_frontendManager->createUrl(ArrayHelper::merge(
            ['/catalog/product/picture-dop', 'name' => Inflector::slug($product->title)],
            $req->getRequestParams(['type'])
        ));
    }
    
    /**
     * @param array $params
     * @param int $id
     * @param string $type
     * @param int|null $i
     * @return ImageActionRequest
     * @throws
     */
    private function _getImageRequest($params, $id, $type, $i = null)
    {
        $params['type'] = $type;
        $params['id'] = $id;
        $params['i'] = $i;
        $params['class'] = ImageActionRequest::class;

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
        'picture-main' => [
            'class' => 'ivankff\yii2UploadImages\ImageAction',
            'imagesClass' => 'ivankff\yii2UploadImages\PluralImages',
            'dir' => Product::getImagesDir(),
            'widen' => Product::getImagesWiden(),
            'type' => PluralImages::TYPE_MAIN,
        ],
        'picture-dop' => [
            'class' => 'ivankff\yii2UploadImages\ImageAction',
            'imagesClass' => 'ivankff\yii2UploadImages\PluralImages',
            'dir' => Product::getImagesDir(),
            'widen' => Product::getImagesWiden(),
            'type' => PluralImages::TYPE_DOP,
        ],
    ];
}
```
