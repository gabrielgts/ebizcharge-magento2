define([
    'Magento_Vault/js/view/payment/method-renderer/vault'
], function (VaultComponent) {
    'use strict';

    return VaultComponent.extend({
        defaults: {
            template: 'Gtstudio_Ebizcharge/payment/vault'
        },

        getData: function () {
            return {
                method: this.code,
                additional_data: {
                    public_hash: this.publicHash
                }
            };
        },

        getMaskedCard: function () {
            return this.details.maskedCC || '';
        },

        getExpirationDate: function () {
            return this.details.expirationDate || '';
        },

        getCardType: function () {
            return this.details.type || '';
        }
    });
});
