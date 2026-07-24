define(['jquery', 'mage/translate'], function ($, $t) {
    'use strict';

    return function (config, element) {
        var $button = $(element);
        var $result = $('#' + config.resultId);

        $button.on('click', function () {
            $result.html('<span style="color:#666;">' + $t('Testing…') + '</span>');

            var payload = {
                user_id: $('#' + config.userIdField).val() || '',
                security_id: $('#' + config.securityIdField).val() || '',
                password: $('#' + config.passwordField).val() || '',
                endpoint_mode: $('#' + config.endpointModeField).val() || '',
                endpoint_url_override: $('#' + config.endpointOverrideField).val() || '',
                form_key: window.FORM_KEY
            };

            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: payload,
                showLoader: true
            }).done(function (response) {
                if (response && response.success) {
                    $result.html(
                        '<span style="color:#79a22e;font-weight:600;">✓ ' + $t('Connected') + '</span> ' +
                        '<span style="color:#666;">(' + response.latency_ms + ' ms)</span>'
                    );
                } else {
                    var msg = (response && response.message) ? response.message : $t('Unknown error');
                    $result.html('<span style="color:#e02b27;font-weight:600;">✗ ' + msg + '</span>');
                }
            }).fail(function () {
                $result.html('<span style="color:#e02b27;font-weight:600;">✗ ' + $t('Request failed') + '</span>');
            });
        });
    };
});
