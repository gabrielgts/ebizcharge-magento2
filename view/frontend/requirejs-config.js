var config = {
    paths: {
        // Vendored copy of jessepollak/card 2.5.4 — see js/lib/VENDORED.md
        'gtstudioCardLib': 'Gtstudio_Ebizcharge/js/lib/card'
    },
    shim: {
        'gtstudioCardLib': {
            exports: 'card'
        }
    }
};
