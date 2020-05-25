<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 14.10.2016
 */

namespace skeeks\cms\shopCheckout;

use skeeks\cms\components\Cms;
use skeeks\cms\helpers\RequestResponse;
use skeeks\cms\models\CmsUser;
use skeeks\cms\models\forms\SignupForm;
use skeeks\cms\shop\models\ShopBuyer;
use skeeks\cms\shop\models\ShopOrder;
use skeeks\cms\shop\models\ShopPersonTypeProperty;
use yii\base\Exception;
use yii\base\UserException;
use yii\base\Widget;
use yii\grid\GridViewAsset;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;

/**
 * @property string    formId
 * @property bool      shopIsReady
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

    public $btnSubmitWrapperOptions = [];
    public $btnSubmitName = '';
    public $btnSubmitOptions = [
        'class' => 'btn btn-primary',
        'type'  => 'submit',
    ];

    /**
     * @var ShopOrder
     */
    public $shopOrder = null;

    /**
     * @var ShopBuyer
     */
    public $shopBuyer = null;

    /**
     * @var bool
     */
    public $user_auto_create = true;

    /**
     * Показывать согласие на обработку персональных данных
     * @var bool 
     */
    public $is_show_personal_permission = true;

    /**
     * Автоматически регистрировать неавторизованного пользователя
     * @var bool
     */
    public $isAutoUserRegister = true;

    public $shopErrors = [];

    public $notSubmitParam = 'sx-not-submit';

    public function init()
    {
        parent::init();
        static::registerTranslations();

        $this->options['id'] = $this->id;
        Html::addCssClass($this->options, "sx-shop-checkout-widget");

        if (!$this->shopOrder) {
            $this->shopOrder = \Yii::$app->shop->cart->shopOrder;
            $this->shopOrder->loadDefaultValues();
        }
        //Покупателя никогда нет
        $this->shopOrder->shop_buyer_id = null;

        $this->clientOptions = ArrayHelper::merge($this->clientOptions, [
            'formid'    => $this->formId,
            'notsubmit' => $this->notSubmitParam,
        ]);

        if (!$this->btnSubmitName) {
            $this->btnSubmitName = \Yii::t('skeeks/shop-checkout', 'Submit');
        }
    }

    public function run()
    {
        GridViewAsset::register(\Yii::$app->view);

        $rr = new RequestResponse();
        $error = "";

        //Установка присланных данных текущему покупателю
        if ($post = \Yii::$app->request->post()) {

            $this->shopOrder->load($post);
            if (!$this->shopOrder->save()) {
                \Yii::error("Error widget: ".Json::encode($this->shopOrder->errors), static::class);
            }
        }
        $this->shopOrder->validate();
        //Создание покупателя в зависимости от выбранного типа
        $this->shopBuyer = $this->shopOrder->shopPersonType->createModelShopBuyer();

        //Установка данных покупателя
        $shopBuyer = $this->shopBuyer;
        if ($shopBuyer) {
            if ($post = \Yii::$app->request->post()) {
                $this->shopBuyer->load($post);
                $this->shopBuyer->relatedPropertiesModel->load($post);
            }
        }

        if ($rr->isRequestPjaxPost() && \Yii::$app->request->post($this->id)) {
            //Если это не просто перестроение формы, то запускается процесс создания заказа
            if (!\Yii::$app->request->post($this->notSubmitParam)) {
                if ($this->shopOrder->validate() && $this->shopBuyer->validate() && $this->shopBuyer->relatedPropertiesModel->validate()) {
                    try {
                        //Сохранение покупателя
                        $buyer = $this->shopBuyer;
                        if ($buyer->isNewRecord) {
                            if ($buyerName = $this->getBuyerName()) {
                                $buyer->name = $buyerName;
                            }

                            if (!$buyer->save()) {
                                throw new Exception('Not save buyer');
                            }
                        }

                        //Сохранение данных покупателя
                        if (!$this->shopBuyer->relatedPropertiesModel->save()) {
                            throw new Exception('Not save buyer data');
                        }

                        //Текущий профиль покупателя присваивается текущей корзине
                        $this->shopOrder->shop_buyer_id = $this->shopBuyer->id;

                        if ($this->user_auto_create) {
                            $user = $this->createUser();
                            if ($user) {
                                $buyer->cms_user_id = $user->id;
                                $buyer->save();
                            }

                        }

                        $this->shopOrder->is_created = true;
                        if (!$this->shopOrder->save()) {
                            throw new UserException(print_r($this->shopOrder->errors, true));
                        }


                        $this->shopOrder->shopCart->shop_order_id = null;
                        $this->shopOrder->shopCart->save();

                        $orderUrl = $this->shopOrder->url;

                        //$newOrder = ShopOrder::createOrderByFuser($this->shopFuser);


                        /*$this->view->registerJs(<<<JS
location.href='{$orderUrl}';
JS
);*/
                        \Yii::$app->response->redirect($orderUrl);
                        \Yii::$app->response->headers->set('X-Pjax-Url', $orderUrl);

                        \Yii::$app->end();

                    } catch (\Exception $e) {
                        /*throw $e;*/
                        $error = \Yii::t('skeeks/shop-checkout', 'Error').": ".$e->getMessage();
                    }


                } else {
                    $error = \Yii::t('skeeks/shop-checkout', 'Check the correctness of filling the form fields');
                    /*print_r($this->shopFuser->firstErrors);
                    print_r($this->shopBuyer->firstErrors);
                    print_r($this->shopBuyer->relatedPropertiesModel->firstErrors);*/
                }
            }
        }

        return $this->render($this->viewFile, [
            'error' => $error,
        ]);
    }


    /**
     * @return string
     */
    public function getBuyerName()
    {
        $rp = $this->shopBuyer->relatedPropertiesModel;
        $modelBuyerName = null;

        foreach ($rp->toArray() as $code => $value) {
            /**
             * @var $property ShopPersonTypeProperty
             */
            $property = $rp->getRelatedProperty($code);
            if ($property->is_buyer_name == Cms::BOOL_Y) {
                $modelBuyerName[] = $value;
            }
        }

        return $modelBuyerName ? implode(", ", $modelBuyerName) : $this->shopBuyer->shopPersonType->name." (".\Yii::$app->formatter->asDate(time(), 'medium').")";
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        $rp = $this->shopBuyer->relatedPropertiesModel;
        $email = "";

        foreach ($rp->toArray() as $code => $value) {
            /**
             * @var $property ShopPersonTypeProperty
             */
            $property = $rp->getRelatedProperty($code);
            if ($property->is_user_email == Cms::BOOL_Y) {
                $email = $value;
            }
        }

        return $email;

    }

    public function createUser()
    {
        //Пользователь уже авторизован
        if (!\Yii::$app->user->isGuest) {
            return \Yii::$app->user->identity;
        }

        if (!$this->isAutoUserRegister) {
            return false;
        }

        //Нужно создать пользователя
        $userPhone = "";
        $userUsername = "";
        $userName = "";

        $rp = $this->shopBuyer->relatedPropertiesModel;
        //Проверка свойств
        foreach ($rp->toArray($rp->attributes()) as $code => $value) {
            $property = $rp->getRelatedProperty($code);
            /**
             * @var $property ShopPersonTypeProperty
             */
            if ($property->is_user_name == Cms::BOOL_Y) {
                $userName = $value;
            }

            if ($property->is_user_username == Cms::BOOL_Y) {
                $userUsername = $value;
            }

            if ($property->is_user_phone == Cms::BOOL_Y) {
                $userPhone = $value;
            }
        }


        $userEmail = $this->getEmail();
        if (!$userEmail) {
            return false;
        }

        if ($userExist = CmsUser::find()->where(['email' => $userEmail])->one()) {
            return false;
        }

        $newUser = new SignupForm();
        $newUser->scenario = SignupForm::SCENARION_ONLYEMAIL;
        $newUser->email = $userEmail;

        if (!$user = $newUser->signup()) {
            return false;
        }

        if ($userUsername) {
            $user->username = $userUsername;
            $user->save();
        }

        if ($userName) {
            $user->first_name = $userName;
            $user->save();
        }

        if ($userPhone) {
            $user->phone = $userPhone;
            $user->save();
        }

        return $user;
    }

    /**
     * @return bool
     */
    public function getShopIsReady()
    {
        $this->shopErrors = [];

        if (!\Yii::$app->shop->shopPersonTypes) {
            $this->shopErrors[] = 'Не заведены типы профилей покупателей';
        }

        if ($this->shopErrors) {
            return false;
        }

        return true;
    }

    /**
     * @return string
     */
    public function getFormId()
    {
        return $this->id."-form";
    }


    static public $isRegisteredTranslations = false;

    static public function registerTranslations()
    {
        if (self::$isRegisteredTranslations === false) {
            \Yii::$app->i18n->translations['skeeks/shop-checkout'] = [
                'class'          => 'yii\i18n\PhpMessageSource',
                'sourceLanguage' => 'en',
                'basePath'       => '@skeeks/cms/shopCheckout/messages',
                'fileMap'        => [
                    'skeeks/shop-checkout' => 'main.php',
                ],
            ];
            self::$isRegisteredTranslations = true;
        }
    }
}
