/*!
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 29.12.2016
 */
(function(sx, $, _)
{
    sx.classes.CheckoutWidget = sx.classes.Component.extend({

        _init: function()
        {},

        _onDomReady: function()
        {
            var self = this;

            $("[data-form-reload=true]").on('change', function()
            {
                self.update();
            });

            $("[data-form-reload=true] input[type=radio]").on('change', function()
            {
                self.update();
            });

        },

        update: function()
        {
            var self = this;

            _.delay(function()
            {
                var jForm = $("#" + self.get('formid'));
                jForm.append($('<input>', {'type': 'hidden', 'name' : self.get('notsubmit'), 'value': 'true'}));
                jForm.submit();
            }, 50);
        }

    });
})(sx, sx.$, sx._);