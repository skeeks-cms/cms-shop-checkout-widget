Widget shop checkout for SkeekS CMS
===================================

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist skeeks/cms-shop-checkout-widget "*"
```

or add

```
"skeeks/cms-shop-checkout-widget": "*"
```

Example
----------

```php

<? $checkout = \skeeks\cms\shopCheckout\ShopCheckoutWidget::begin([
    'btnSubmitWrapperOptions' =>
    [
        'style' => 'display: none;'
    ]
]); ?>
<? \skeeks\cms\shopCheckout\ShopCheckoutWidget::end(); ?>

```


##Links
* [Web site](https://cms.skeeks.com)
* [Author](https://skeeks.com)

___

> [![skeeks!](https://gravatar.com/userimage/74431132/13d04d83218593564422770b616e5622.jpg)](https://skeeks.com)  
<i>SkeekS CMS (Yii2) — quickly, easily and effectively!</i>  
[skeeks.com](https://skeeks.com) | [cms.skeeks.com](https://cms.skeeks.com)


