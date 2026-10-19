(function () {
    'use strict';

    const form = document.querySelector('[data-checkout]');
    if (!form) {
        return;
    }

    const errorBox = form.querySelector('[data-payment-error]');
    const submit = form.querySelector('[data-payment-submit]');
    const submitLabel = submit.textContent;

    function showError(message) {
        errorBox.textContent = message;
        errorBox.hidden = !message;
    }

    if (typeof Stripe !== 'function') {
        showError("The payment form didn't load. Allow js.stripe.com in your blocker and refresh.");
        return;
    }

    const stripe = Stripe(form.dataset.publishableKey);
    const elements = stripe.elements({
        clientSecret: form.dataset.clientSecret,
        appearance: {
            theme: 'night',
            variables: {
                colorPrimary: '#8b5cf6',
                colorBackground: '#1a1726',
                borderRadius: '10px',
                fontFamily: 'Inter, system-ui, sans-serif'
            }
        }
    });

    const container = form.querySelector('[data-payment-element]');
    const paymentElement = elements.create('payment');
    paymentElement.on('ready', () => {
        const loader = container.querySelector('.loading-block');
        if (loader) {
            loader.remove();
        }
        submit.disabled = false;
    });
    paymentElement.mount(container);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        showError('');
        submit.disabled = true;
        submit.classList.add('is-loading');
        submit.textContent = 'Processing…';

        // on success stripe redirects to return_url, so only the error path comes back here
        const { error } = await stripe.confirmPayment({
            elements,
            confirmParams: { return_url: form.dataset.returnUrl }
        });

        if (error) {
            showError(error.message || "Your payment didn't go through.");
            submit.disabled = false;
            submit.classList.remove('is-loading');
            submit.textContent = submitLabel;
        }
    });
})();
