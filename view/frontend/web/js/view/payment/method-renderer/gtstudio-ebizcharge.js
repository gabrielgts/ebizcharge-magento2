define([
    'require',
    'jquery',
    'underscore',
    'ko',
    'Magento_Payment/js/view/payment/cc-form',
    'Magento_Vault/js/view/payment/vault-enabler',
    'gtstudioCardLib'
], function (require, $, _, ko, Component, VaultEnabler, CardLib) {
    'use strict';

    var CARD_CSS_LINK_ID = 'gtstudio-ebizcharge-card-css';
    var CARD_VISUAL_CSS_LINK_ID = 'gtstudio-ebizcharge-card-visual-css';
    var MAX_MOUNT_ATTEMPTS = 20;
    var MOUNT_RETRY_DELAY_MS = 100;
    var CARD_TYPE_CLASSES = [
        'jp-card-amex',
        'jp-card-dankort',
        'jp-card-dinersclub',
        'jp-card-discover',
        'jp-card-unionpay',
        'jp-card-jcb',
        'jp-card-laser',
        'jp-card-maestro',
        'jp-card-mastercard',
        'jp-card-troy',
        'jp-card-visa',
        'jp-card-visaelectron',
        'jp-card-elo',
        'jp-card-hipercard'
    ];
    var MAGENTO_TO_CARD_TYPE = {
        AE: 'amex',
        DI: 'discover',
        DN: 'dinersclub',
        JCB: 'jcb',
        MC: 'mastercard',
        MI: 'maestro',
        VI: 'visa',
        VE: 'visaelectron'
    };

    return Component.extend({
        defaults: {
            template: 'Gtstudio_Ebizcharge/payment/gtstudio-ebizcharge'
        },

        /**
         * Holds the Card.js instance so we can dispose it cleanly when the renderer is destroyed
         * (KO doesn't reliably tear down event listeners attached by external libs to the same
         * DOM nodes — without this, navigating away + back can leak handlers).
         */
        _cardInstance: null,
        _cardMountAttempts: 0,
        _cardMountTimer: null,

        initObservable: function () {
            this._super();
            return this;
        },

        initialize: function () {
            this._super();
            this.vaultEnabler = new VaultEnabler();
            this.vaultEnabler.setPaymentCode(this.getVaultCode());
            this.vaultEnabler.isActivePaymentTokenEnabler(false);
            this.creditCardType.subscribe(this.updateCardBrandVisual.bind(this));
            this.creditCardNumber.subscribe(this.updateCardBrandVisual.bind(this));
            this.creditCardExpMonth.subscribe(this.syncCardVisualExpiry.bind(this));
            this.creditCardExpYear.subscribe(this.syncCardVisualExpiry.bind(this));
            this.scheduleCardVisualMount();
            return this;
        },

        /**
         * Wires Card.js to the form once the DOM is ready. The library reads the inputs by selector
         * and paints a CSS-only animated card on the container div. No network calls.
         */
        mountCardVisual: function (container) {
            var code = this.getCode();
            var Card;

            container = this.isElement(container) ? container : document.querySelector('#' + code + '-card-visual');

            var form = document.querySelector('#' + code + '-form');

            if (this._cardInstance !== null) {
                return;
            }

            if (container === null || form === null) {
                this.scheduleCardVisualMount();
                return;
            }

            try {
                Card = this.getCardConstructor();

                this.ensureCardStylesheet();

                this._cardInstance = new Card({
                    form: form,
                    container: container,
                    formSelectors: {
                        numberInput: 'input[name="payment[cc_number]"]',
                        expiryInput: 'input[name="payment[cc_exp_month_year]"]',
                        cvcInput: 'input[name="payment[cc_cid]"]',
                        nameInput: 'input[name="payment[cc_owner]"]'
                    },
                    width: 280,
                    formatting: false,
                    placeholders: {
                        number: '•••• •••• •••• ••••',
                        name: 'CARDHOLDER NAME',
                        expiry: 'MM/YY',
                        cvc: '•••'
                    }
                });

                this.syncCardVisualExpiry();
                this.updateCardBrandVisual();
            } catch (e) {
                // The visual is optional UX — never let a render error block checkout.
                if (window.console && window.console.warn) {
                    window.console.warn('Gtstudio_Ebizcharge: card visual failed to mount.', e);
                }
            }
        },

        scheduleCardVisualMount: function () {
            if (this._cardInstance !== null || this._cardMountAttempts >= MAX_MOUNT_ATTEMPTS) {
                return;
            }

            this._cardMountAttempts += 1;
            clearTimeout(this._cardMountTimer);
            this._cardMountTimer = setTimeout(
                this.mountCardVisual.bind(this),
                this._cardMountAttempts === 1 ? 0 : MOUNT_RETRY_DELAY_MS
            );
        },

        ensureCardStylesheet: function () {
            this.appendStylesheet(
                CARD_CSS_LINK_ID,
                require.toUrl('Gtstudio_Ebizcharge/css/lib/card.css'),
                'Gtstudio_Ebizcharge/css/lib/card.css'
            );
            this.appendStylesheet(
                CARD_VISUAL_CSS_LINK_ID,
                require.toUrl('Gtstudio_Ebizcharge/css/card-visual.css'),
                'Gtstudio_Ebizcharge/css/card-visual.css'
            );
        },

        appendStylesheet: function (id, href, hrefNeedle) {
            var existing = document.getElementById(id);
            var link;

            if (existing !== null || document.querySelector('link[href*="' + hrefNeedle + '"]')) {
                return;
            }

            link = document.createElement('link');
            link.id = id;
            link.rel = 'stylesheet';
            link.type = 'text/css';
            link.href = href;
            document.head.appendChild(link);
        },

        getCardConstructor: function () {
            if (typeof CardLib === 'function') {
                return CardLib;
            }

            if (CardLib && typeof CardLib.Card === 'function') {
                return CardLib.Card;
            }

            if (CardLib && typeof CardLib.default === 'function') {
                return CardLib.default;
            }

            throw new TypeError('Card.js constructor is unavailable.');
        },

        isElement: function (value) {
            return value && value.nodeType === 1;
        },

        syncCardVisualExpiry: function () {
            var expiryInput = document.querySelector(
                '#' + this.getCode() + '-form input[name="payment[cc_exp_month_year]"]'
            );
            var month = this.creditCardExpMonth();
            var year = this.creditCardExpYear();

            if (expiryInput === null) {
                return;
            }

            expiryInput.value = month && year ? month + '/' + String(year).slice(-2) : '';
            $(expiryInput).trigger('change').trigger('keyup');
        },

        updateCardBrandVisual: function () {
            var card = document.querySelector('#' + this.getCode() + '-card-visual .jp-card');
            var type = this.resolveCardVisualType();

            if (card === null) {
                return;
            }

            $(card)
                .removeClass(CARD_TYPE_CLASSES.join(' '))
                .toggleClass('jp-card-unknown', type === 'unknown')
                .toggleClass('jp-card-identified', type !== 'unknown');

            if (type !== 'unknown') {
                $(card).addClass('jp-card-' + type);
            }
        },

        resolveCardVisualType: function () {
            var selectedType = this.creditCardType();

            if (selectedType && MAGENTO_TO_CARD_TYPE[selectedType]) {
                return MAGENTO_TO_CARD_TYPE[selectedType];
            }

            return this.detectCardTypeFromNumber(this.creditCardNumber());
        },

        detectCardTypeFromNumber: function (number) {
            var digits = String(number || '').replace(/\D/g, '');

            if (/^3[47]/.test(digits)) {
                return 'amex';
            }
            if (/^(5[1-5]|2[2-7])/.test(digits)) {
                return 'mastercard';
            }
            if (/^4/.test(digits)) {
                return 'visa';
            }
            if (/^(6011|65|64[4-9]|622)/.test(digits)) {
                return 'discover';
            }
            if (/^35/.test(digits)) {
                return 'jcb';
            }
            if (/^(36|38|30[0-5])/.test(digits)) {
                return 'dinersclub';
            }
            if (/^(50|5[6-9]|6)/.test(digits)) {
                return 'maestro';
            }

            return 'unknown';
        },

        getCode: function () {
            return 'gtstudio_ebizcharge';
        },

        /**
         * The card fields bind `enable: isActive($parents)`. Neither cc-form nor checkout's
         * default renderer defines this, so every method using that template supplies it.
         */
        isActive: function () {
            return this.getCode() === this.isChecked();
        },

        isSandbox: function () {
            return Boolean(window.checkoutConfig.payment[this.getCode()].isSandbox);
        },

        getVaultCode: function () {
            return window.checkoutConfig.payment[this.getCode()].ccVaultCode;
        },

        isVaultEnabled: function () {
            return this.vaultEnabler.isVaultEnabled();
        },

        getCcAvailableTypesValues: function () {
            var types = window.checkoutConfig.payment[this.getCode()].availableCardTypes || {};
            return _.map(types, function (label, code) {
                return { value: code, type: code, 'cc_type_label': label };
            });
        },

        getData: function () {
            var data = {
                method: this.item.method,
                additional_data: {
                    cc_number: this.creditCardNumber(),
                    cc_cid: this.creditCardVerificationNumber(),
                    cc_exp_month: this.creditCardExpMonth(),
                    cc_exp_year: this.creditCardExpYear(),
                    cc_type: this.creditCardType()
                }
            };

            this.vaultEnabler.visitAdditionalData(data);
            return data;
        },

        validate: function () {
            var $form = $('#' + this.getCode() + '-form');

            // validation() with no args initialises the widget; without that call, asking it for
            // 'isValid' throws "cannot call methods on validation prior to initialization".
            return $form.validation() && $form.validation('isValid');
        }
    });
});
