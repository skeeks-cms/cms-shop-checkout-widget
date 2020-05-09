<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 14.10.2016
 */
/* @var $this yii\web\View */
/* @var $widget \skeeks\cms\shopCheckout\ShopCheckoutWidget */
\skeeks\cms\shopCheckout\assets\ShopCheckoutWidgetAsset::register($this);

$widget = $this->context;
$shopOrder = $widget->shopOrder;
$clientOptions = \yii\helpers\Json::encode($widget->clientOptions);
?>
<?= \yii\helpers\Html::beginTag('div', $widget->options); ?>

<? /* if (\Yii::$app->user->isGuest) : */ ?><!--
        <div class="panel panel-default">
            <div class="panel-body">
                <strong>Вы не авторизованы на сайте.</strong><br />
                Для постоянных покупателей у нас действует система бонусов и скидок.<br />
                Если у вас уже есть аккаунт, то вы можете <a href="<? /*= \skeeks\cms\helpers\UrlHelper::construct('cms/auth/login')->setCurrentRef(); */ ?>" data-pjax="0">войти на сайт</a>. <br />
            </div>
        </div>
    <!-- /EMPTY CART -->
<? /* endif; */ ?>

<? if ($widget->shopIsReady) : ?>
    <?php $form = \yii\bootstrap\ActiveForm::begin([
        'id'                     => $widget->formId,
        'enableAjaxValidation'   => false,
        'enableClientValidation' => false,
        'options'                => [
            'data-pjax' => 'true',
        ],
    ]); ?>

    <? $this->registerJs(<<<JS
    (function(sx, $, _)
    {
        new sx.classes.CheckoutWidget({$clientOptions});
    })(sx, sx.$, sx._);
JS
    ); ?>

    <? if (count(\Yii::$app->shop->shopPersonTypes) <= 1) : ?>
        <div style="display: none;">
    <? endif; ?>
    <?= $form->field($shopOrder, 'shop_person_type_id')->radioList(
        \yii\helpers\ArrayHelper::map(\Yii::$app->shop->shopPersonTypes, 'id', 'name'),
        [
            'data-form-reload' => 'true',
        ]
    )->label(false); ?>
    <? if (count(\Yii::$app->shop->shopPersonTypes) <= 1) : ?>
        </div>
    <? endif; ?>

    <? $widget->shopBuyer->relatedPropertiesModel->toArray(); ?>
    <? foreach ($widget->shopBuyer->relatedProperties as $property) : ?>
        <?= $property->renderActiveForm($form, $widget->shopBuyer) ?>
    <? endforeach; ?>

    <? if ($deliveries = \skeeks\cms\shop\models\ShopDelivery::getAllowForOrder()) : ?>
        <div class="sx-delivery-wrapper">
            <?= $form->field($widget->shopOrder, 'shop_delivery_id')
                ->label('Доставка')
                ->radioList(
                    \yii\helpers\ArrayHelper::map(
                        $deliveries, 'id', 'name'), [
                            'data-form-reload' => 'true',
                            'item' => function ($i, $label, $name, $checked, $value) {
                                /**
                                 * @var $delivery \skeeks\cms\shop\models\ShopDelivery
                                 */
                                $delivery = \skeeks\cms\shop\models\ShopDelivery::findOne($value);

                                $options = [
                                    'id' => $name . $value,
                                    'class' => 'custom-control-input',
                                    'value' => $value
                                ];
                                $wrapperOptions = \yii\helpers\ArrayHelper::remove($options, 'wrapperOptions', ['class' => ['custom-control', 'custom-radio']]);
                                /*if ($this->inline) {
                                    \yii\helpers\Html::addCssClass($wrapperOptions, 'custom-control-inline');
                                }*/

                                $html = \yii\helpers\Html::beginTag('div', $wrapperOptions) . "\n" .
                                    \yii\helpers\Html::radio($name, $checked, $options) . "\n" .
                                    \yii\helpers\Html::label($label, $name . $value, ['class' => 'custom-control-label']) . "\n" .
                                    "<div class='float-right sx-delivery-price'>" . $delivery->money . "</div>";
                                $html .= \yii\helpers\Html::endTag('div') . "\n";

                                return $html;
                            }
                            /*'item' => function($index, $label, $name, $checked, $value) {
                                return \yii\helpers\Html::radio($name, $checked, array_merge([
                                    'value' => $value,
                                    'label' => $label,
                                    'template' => "<div class=\"custom-control custom-radio\">\n{input}\n{label}\n{error}\n{hint}\n</div>"
                                ]));
                            }*/
                        ])
            ?>
        </div>
    <? endif; ?>


    <? if ($widget->shopOrder->paySystems) : ?>
        <div class="sx-pay-systems-wrapper">
            <?= $form->field($widget->shopOrder, 'shop_pay_system_id')
                ->label('Оплата')
                ->radioList(
                    \yii\helpers\ArrayHelper::map($widget->shopOrder->paySystems, 'id', 'name'), [
                        'data-form-reload' => 'true',
                    ]
                ); ?>
        </div>
    <? endif; ?>


    <div style="display: none;">
        <?= \yii\helpers\Html::hiddenInput($widget->id, $widget->id); ?>
    </div>
    <?= \yii\helpers\Html::beginTag('div', $widget->btnSubmitWrapperOptions); ?>
    <?=
    \yii\helpers\Html::button($widget->btnSubmitName, $widget->btnSubmitOptions)
    ?>
    <?= \yii\helpers\Html::endTag('div'); ?>

    <? if ($error) : ?>
        <? $alert = \yii\bootstrap\Alert::begin([
            'options' =>
                [
                    'class' => 'alert-danger',
                    'style' => 'margin-top: 20px;',
                ],
        ]); ?>
        <?= $error; ?>
        <? $alert::end(); ?>

    <? endif; ?>
    <?= $form->errorSummary([$widget->shopOrder, $widget->shopBuyer, $widget->shopBuyer->relatedPropertiesModel]); ?>
    <? $form::end(); ?>
<? else : ?>
    Магазин не настроен
<? endif; ?>
<?= \yii\helpers\Html::endTag('div'); ?>