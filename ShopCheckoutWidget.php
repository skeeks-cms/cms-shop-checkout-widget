<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 14.10.2016
 */
namespace skeeks\cms\shopCheckout;

use skeeks\cms\helpers\RequestResponse;
use skeeks\cms\shop\models\ShopBuyer;
use skeeks\cms\shop\models\ShopFuser;
use skeeks\cms\shop\models\ShopOrder;
use yii\base\Exception;
use yii\base\Widget;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

/**
 * @property string formId
 * @property bool shopIsReady
 * @property ShopBuyer shopBuyer
 *
 * Class ShopCheckoutSimpleWidget
 * @package skeeks\cms\shopCheckout
 */
class ShopCheckoutWidget extends Widget
{
    public static $autoIdPrefix = 'shopCheckout';

    public $viewFile = 'default';

    public $options = [];
    public $clientOptions = [];

    public $btnSubmitWrapperOptions     = [];
    public $btnSubmitName               = '';
    public $btnSubmitOptions            = [
        'class' => 'btn btn-primary',
        'type' => 'submit',
    ];

    /**
     * @var ShopFuser
     */
    public $shopFuser = null;

    /**
     * @var ShopBuyer
     */
    public $shopBuyer = null;

    public $shopErrors = [];

    public $notSubmitParam = 'sx-not-submit';

    public function init()
    {
        parent::init();
        static::registerTranslations();

        $this->options['id'] = $this->id;

        if (!$this->shopFuser)
        {
            $this->shopFuser = \Yii::$app->shop->shopFuser;
            $this->shopFuser->loadDefaultValues();
        }
        //Покупателя никогда нет
        $this->shopFuser->buyer_id = null;

        $this->clientOptions = ArrayHelper::merge($this->clientOptions, [
            'formid'    => $this->formId,
            'notsubmit' => $this->notSubmitParam,
        ]);

        if (!$this->btnSubmitName)
        {
            $this->btnSubmitName = \Yii::t('skeeks/shop-checkout', 'Submit');
        }
    }

    public function run()
    {
        $rr = new RequestResponse();
        $errors = [];

        if ($post = \Yii::$app->request->post())
        {
            $this->shopFuser->load($post);
            if (!$this->shopFuser->save())
            {
                \Yii::error("Error widget: " . Json::encode($this->shopFuser->errors), static::class);
            }
        }

        $this->shopBuyer = $this->shopFuser->personType->createModelShopBuyer();

        $shopBuyer = $this->shopBuyer;
        if ($shopBuyer)
        {
            if ($post = \Yii::$app->request->post())
            {
                $this->shopBuyer->load($post);
                $this->shopBuyer->relatedPropertiesModel->load($post);
            }
        }

        if ($rr->isRequestPjaxPost())
        {
            if (!\Yii::$app->request->post($this->notSubmitParam))
            {
                if ($this->shopFuser->validate() && $this->shopBuyer->validate() && $this->shopBuyer->relatedPropertiesModel->validate())
                {
                    if ($this->shopBuyer->isNewRecord)
                    {
                        if (!$this->shopBuyer->save())
                        {
                            throw new Exception('Not save buyer');
                        }
                    }

                    if (!$this->shopBuyer->relatedPropertiesModel->save())
                    {
                        throw new Exception('Not save buyer data');
                    }

                    $this->shopFuser->buyer_id = $this->shopBuyer->id;

                    $newOrder = ShopOrder::createOrderByFuser($this->shopFuser);
                    $orderUrl = $newOrder->publicUrl;
                    $this->view->registerJs(<<<JS
location.href='{$orderUrl}';
JS
);

                } else
                {
                    /*print_r($this->shopFuser->firstErrors);
                    print_r($this->shopBuyer->firstErrors);
                    print_r($this->shopBuyer->relatedPropertiesModel->firstErrors);*/
                }
            }
        }

        return $this->render($this->viewFile);
    }


    /**
     * @return bool
     */
    public function getShopIsReady()
    {
        $this->shopErrors = [];

        if (!\Yii::$app->shop->shopPersonTypes)
        {
            $this->shopErrors[] = 'Не заведены типы профилей покупателей';
        }

        if ($this->shopErrors)
        {
            return false;
        }

        return true;
    }

    /**
     * @return string
     */
    public function getFormId()
    {
        return $this->id . "-form";
    }



    static public $isRegisteredTranslations = false;

    static public function registerTranslations()
    {
        if (self::$isRegisteredTranslations === false)
        {
            \Yii::$app->i18n->translations['skeeks/shop-checkout'] = [
                'class' => 'yii\i18n\PhpMessageSource',
                'sourceLanguage' => 'en',
                'basePath' => '@skeeks/cms/shopCheckout/messages',
                'fileMap' => [
                    'skeeks/shop-checkout' => 'main.php',
                ],
            ];
            self::$isRegisteredTranslations = true;
        }
    }
}
