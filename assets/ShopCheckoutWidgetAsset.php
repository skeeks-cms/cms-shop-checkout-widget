<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 28.12.2016
 */
namespace skeeks\cms\shopCheckout\assets;
use skeeks\cms\base\AssetBundle;

/**
 * Class ShopCheckoutWidgetAsset
 *
 * @package skeeks\cms\shopCheckoutSimple\assets
 */
class ShopCheckoutWidgetAsset extends AssetBundle
{
    public $sourcePath = '@skeeks/cms/shopCheckout/assets/src';

    public $css             = [];

    public $js              = [
        'checkout.js'
    ];

    public $depends         = [
        'skeeks\sx\assets\Core'
    ];
}
