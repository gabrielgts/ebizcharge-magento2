define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    // Only the non-vault methods are registered here. Magento_Vault/js/view/payment/vault
    // registers one renderer per saved token from checkoutConfig.payment.vault, passing the
    // token's details/publicHash as config. Listing the vault codes here as well would build a
    // second, token-less instance whose `details` is undefined.
    rendererList.push(
        {
            type: 'gtstudio_ebizcharge',
            component: 'Gtstudio_Ebizcharge/js/view/payment/method-renderer/gtstudio-ebizcharge'
        },
        {
            type: 'gtstudio_ebizcharge_ach',
            component: 'Gtstudio_Ebizcharge/js/view/payment/method-renderer/gtstudio-ebizcharge-ach'
        }
    );

    return Component.extend({});
});
