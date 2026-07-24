define([
    'jquery',
    'Magento_Ui/js/modal/confirm'
], function ($, confirmation) {
    'use strict';

    return function (config, element) {
        $(element).on('click', function () {
            confirmation({
                title: $.mage.__('Verify EBizCharge Customer'),
                content: config.confirmation,
                actions: {
                    confirm: function () {
                        $('<form>', {
                            method: 'POST',
                            action: config.url
                        })
                            .append($('<input>', {
                                type: 'hidden',
                                name: 'form_key',
                                value: window.FORM_KEY
                            }))
                            .append($('<input>', {
                                type: 'hidden',
                                name: 'customer_id',
                                value: config.customerId
                            }))
                            .appendTo(document.body)
                            .trigger('submit');
                    }
                }
            });
        });
    };
});
