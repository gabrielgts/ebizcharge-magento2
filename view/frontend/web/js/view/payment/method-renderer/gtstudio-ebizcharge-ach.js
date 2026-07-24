define([
    'jquery',
    'ko',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Customer/js/model/customer'
], function ($, ko, Component, customer) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Gtstudio_Ebizcharge/payment/gtstudio-ebizcharge-ach',
            achAccount: '',
            achRouting: '',
            achType: 'checking',
            saveAccount: false
        },

        initObservable: function () {
            this._super().observe(['achAccount', 'achRouting', 'achType', 'saveAccount']);
            return this;
        },

        getCode: function () {
            return 'gtstudio_ebizcharge_ach';
        },

        isSandbox: function () {
            var cfg = window.checkoutConfig.payment[this.getCode()];
            return Boolean(cfg && cfg.isSandbox);
        },

        canOfferSaveAccount: function () {
            return Boolean(customer.isLoggedIn());
        },

        getAccountTypeOptions: function () {
            return [
                { value: 'checking', label: $.mage.__('Checking') },
                { value: 'savings', label: $.mage.__('Savings') }
            ];
        },

        getData: function () {
            return {
                method: this.item.method,
                additional_data: {
                    ach_account: this.achAccount(),
                    ach_routing: this.achRouting(),
                    ach_type: this.achType(),
                    cc_save: this.canOfferSaveAccount() && this.saveAccount() ? '1' : '0'
                }
            };
        },

        validate: function () {
            var $form = $('#' + this.getCode() + '-form');

            // validation() with no args initialises the widget; without that call, asking it for
            // 'isValid' throws "cannot call methods on validation prior to initialization".
            return $form.validation() && $form.validation('isValid');
        }
    });
});
