(function () {
    'use strict';

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.content : '';

    async function request(url, options) {
        let response;
        try {
            response = await fetch(url, { credentials: 'same-origin', ...options });
        } catch {
            throw new Error("You're offline. Check your connection.");
        }

        let payload;
        try {
            payload = await response.json();
        } catch {
            payload = { success: false, error: 'Unexpected server response.' };
        }

        if (!response.ok || !payload.success) {
            throw new Error(payload.error || 'Something went wrong. Try again.');
        }

        return payload.data;
    }

    function post(url, fields) {
        const body = fields instanceof FormData ? fields : new URLSearchParams(fields || {});

        return request(url, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
            body
        });
    }

    function get(url) {
        return request(url, { headers: { Accept: 'application/json' } });
    }

    function confirmAction(options) {
        const dialog = document.getElementById('confirm-dialog');
        if (!dialog || typeof dialog.showModal !== 'function') {
            return Promise.resolve(window.confirm(options.text || 'Are you sure?'));
        }

        dialog.querySelector('[data-confirm-title]').textContent = options.title || 'Are you sure?';
        dialog.querySelector('[data-confirm-text]').textContent = options.text || '';
        dialog.querySelector('[data-confirm-button]').textContent = options.button || 'Confirm';
        dialog.returnValue = '';
        dialog.showModal();

        return new Promise((resolve) => {
            dialog.addEventListener('close', () => resolve(dialog.returnValue === 'confirm'), { once: true });
        });
    }

    function toast(message, type) {
        let stack = document.querySelector('.toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'toast-stack';
            document.body.appendChild(stack);
        }

        const item = document.createElement('div');
        item.className = 'alert alert--' + (type || 'info') + ' toast';
        item.setAttribute('role', 'status');
        item.textContent = message;
        stack.appendChild(item);

        setTimeout(() => {
            item.classList.add('is-leaving');
            setTimeout(() => item.remove(), 300);
        }, 4000);
    }

    function setBadge(name, count) {
        document.querySelectorAll('[data-badge="' + name + '"]').forEach((badge) => {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.hidden = count < 1;
        });
    }

    async function refreshBadges() {
        if (!document.querySelector('[data-badge]')) {
            return;
        }
        try {
            const counts = await get('api/unread_count.php');
            setBadge('messages', counts.messages);
            setBadge('notifications', counts.notifications);
        } catch {
            // badges are best effort, the next poll tries again
        }
    }

    window.Vendove = {
        post,
        get,
        confirm: confirmAction,
        toast,
        refreshBadges
    };

    const navToggle = document.querySelector('[data-nav-toggle]');
    const nav = document.querySelector('[data-nav]');
    if (navToggle && nav) {
        navToggle.addEventListener('click', () => {
            const open = nav.classList.toggle('is-open');
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    document.addEventListener('click', (event) => {
        document.querySelectorAll('details.user-menu[open], details.menu[open]').forEach((menu) => {
            if (!menu.contains(event.target)) {
                menu.removeAttribute('open');
            }
        });
    });

    document.querySelectorAll('[data-dismissible]').forEach((alert) => {
        function close() {
            alert.classList.add('is-leaving');
            setTimeout(() => alert.remove(), 300);
        }
        alert.querySelector('[data-dismiss]').addEventListener('click', close);
        if (alert.classList.contains('alert--success')) {
            setTimeout(close, 6000);
        }
    });

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = button.parentElement.querySelector('input');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            button.classList.toggle('is-active', show);
        });
    });

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const confirmed = await confirmAction({
                title: form.dataset.confirmTitle,
                text: form.dataset.confirm,
                button: form.dataset.confirmButton || 'Delete'
            });
            if (confirmed) {
                form.querySelectorAll('button').forEach((button) => {
                    button.disabled = true;
                });
                HTMLFormElement.prototype.submit.call(form);
            }
        });
    });

    document.querySelectorAll('form[data-loading-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented) {
                return;
            }
            const button = event.submitter || form.querySelector('button[type="submit"]');
            // disabling synchronously would drop the button's name/value from the submitted form
            setTimeout(() => {
                if (button) {
                    button.disabled = true;
                    button.classList.add('is-loading');
                }
            }, 0);
        });
    });

    document.querySelectorAll('[data-gallery]').forEach((gallery) => {
        const main = gallery.querySelector('[data-gallery-main]');
        const thumbs = gallery.querySelectorAll('[data-gallery-thumb]');
        thumbs.forEach((thumb) => {
            thumb.addEventListener('click', () => {
                main.src = thumb.dataset.galleryThumb;
                thumbs.forEach((other) => other.classList.toggle('is-active', other === thumb));
            });
        });
    });

    if (document.querySelector('[data-badge]')) {
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                refreshBadges();
            }
        }, 20000);
    }
})();
