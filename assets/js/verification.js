(function () {
    'use strict';

    if (!document.querySelector('[data-verification-poll]')) {
        return;
    }

    const timer = setInterval(async () => {
        if (document.visibilityState !== 'visible') {
            return;
        }
        try {
            const status = await Vendove.get('api/check_verification.php');
            if (status.verified) {
                clearInterval(timer);
                window.location.href = status.redirect || 'index.php';
            }
        } catch {
            // keep polling, a dropped request is not worth showing
        }
    }, 4000);
})();
