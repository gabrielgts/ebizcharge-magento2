define([
    'Magento_Vault/js/view/payment/method-renderer/vault'
], function (VaultComponent) {
    'use strict';

    return VaultComponent.extend({
        defaults: {
            template: 'Gtstudio_Ebizcharge/payment/ach-vault'
        },

        getData: function () {
            return {
                method: this.code,
                additional_data: {
                    public_hash: this.publicHash
                }
            };
        },

        getMaskedAccount: function () {
            return this.details.maskedAccount || '';
        },

        getAccountType: function () {
            return this.details.accountType || '';
        }
    });
});
