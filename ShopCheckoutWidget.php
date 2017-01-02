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
use skeeks\cms\shop\models\ShopFuser;
use skeeks\cms\shop\models\ShopOrder;
use skeeks\cms\shop\models\ShopPersonTypeProperty;
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
        $error = "";

        //Установка присланных данных текущему покупателю
        if ($post = \Yii::$app->request->post())
        {
            $this->shopFuser->load($post);
            if (!$this->shopFuser->save())
            {
                \Yii::error("Error widget: " . Json::encode($this->shopFuser->errors), static::class);
            }
        }

        //Создание покупателя в зависимости от выбранного типа
        $this->shopBuyer = $this->shopFuser->personType->createModelShopBuyer();

        //Установка данных покупателя
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
            //Если это не просто перестроение формы, то запускается процесс создания заказа
            if (!\Yii::$app->request->post($this->notSubmitParam))
            {
                if ($this->shopFuser->validate() && $this->shopBuyer->validate() && $this->shopBuyer->relatedPropertiesModel->validate())
                {
                    //Сохранение покупателя
                    $buyer = $this->shopBuyer;
                    if ($buyer->isNewRecord)
                    {
                        if ($buyerName = $this->getBuyerName())
                        {
                            $buyer->name = $buyerName;
                        }

                        if (!$buyer->save())
                        {
                            throw new Exception('Not save buyer');
                        }
                    }

                    //Сохранение данных покупателя
                    if (!$this->shopBuyer->relatedPropertiesModel->save())
                    {
                        throw new Exception('Not save buyer data');
                    }

                    //Текущий профиль покупателя присваивается текущей корзине
                    $this->shopFuser->buyer_id = $this->shopBuyer->id;

                    try
                    {
                        $user = $this->createUser();
                        if ($user)
                        {
                            $this->shopFuser->user_id = $user->id;
                            $this->shopFuser->save();
                        }

                        $newOrder = ShopOrder::createOrderByFuser($this->shopFuser);
                        $orderUrl = $newOrder->publicUrl;

                        $this->view->registerJs(<<<JS
location.href='{$orderUrl}';
JS
);
                    } catch (\Exception $e)
                    {
                        $error = \Yii::t('skeeks/shop-checkout', 'Error') . ": " . $e->getMessage();
                    }



                } else
                {
                    $error = \Yii::t('skeeks/shop-checkout', 'Check the correctness of filling the form fields');
                    /*print_r($this->shopFuser->firstErrors);
                    print_r($this->shopBuyer->firstErrors);
                    print_r($this->shopBuyer->relatedPropertiesModel->firstErrors);*/
                }
            }
        }

        return $this->render($this->viewFile, [
            'error' => $error
        ]);
    }


    /**
     * @return string
     */
    public function getBuyerName()
    {
        $rp = $this->shopBuyer->relatedPropertiesModel;
        $modelBuyerName = null;

        foreach ($rp->toArray() as $code => $value)
        {
            /**
             * @var $property ShopPersonTypeProperty
             */
            $property = $rp->getRelatedProperty($code);
            if ($property->is_buyer_name == Cms::BOOL_Y)
            {
                $modelBuyerName[] = $value;
            }
        }

        return $modelBuyerName ? implode(", ", $modelBuyerName) : $this->shopBuyer->shopPersonType->name . " (" . \Yii::$app->formatter->asDate(time(), 'medium') . ")";
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        $rp         = $this->shopBuyer->relatedPropertiesModel;
        $email      = "";

        foreach ($rp->toArray() as $code => $value)
        {
            /**
             * @var $property ShopPersonTypeProperty
             */
            $property = $rp->getRelatedProperty($code);
            if ($property->is_user_email == Cms::BOOL_Y)
            {
                $email = $value;
            }
        }

        return $email;

    }

    public function createUser()
    {
        //Пользователь уже авторизован
        if (!\Yii::$app->user->isGuest)
        {
            return \Yii::$app->user->identity;
        }

        //Нужно создать пользователя
        $userPhone = "";
        $userUsername = "";
        $userName = "";

        $rp = $this->shopBuyer->relatedPropertiesModel;
        //Проверка свойств
        foreach ($rp->toArray($rp->attributes()) as $code => $value)
        {
            $property = $rp->getRelatedProperty($code);
            /**
             * @var $property ShopPersonTypeProperty
             */
            if ($property->is_user_name == Cms::BOOL_Y)
            {
                $userName = $value;
            }

            if ($property->is_user_username == Cms::BOOL_Y)
            {
                $userUsername = $value;
            }

            if ($property->is_user_phone == Cms::BOOL_Y)
            {
                $userPhone = $value;
            }
        }


        $userEmail = $this->getEmail();
        if (!$userEmail)
        {
            return false;
        }

        if ($userExist = CmsUser::find()->where(['email' => $userEmail])->one())
        {
            return false;
        }

        $newUser             = new SignupForm();
        $newUser->scenario   = SignupForm::SCENARION_ONLYEMAIL;
        $newUser->email      = $userEmail;

        if (!$user = $newUser->signup())
        {
            return false;
        }

        if ($userUsername)
        {
            $user->username      = $userUsername;
            $user->save();
        }

        if ($userName)
        {
            $user->name      = $userName;
            $user->save();
        }

        if ($userPhone)
        {
            $user->phone      = $userPhone;
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
