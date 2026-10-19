(function () {
    'use strict';

    const root = document.querySelector('[data-chat]');
    if (!root) {
        return;
    }

    const POLL_INTERVAL = 3000;
    const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    const MAX_PHOTO_BYTES = 5 * 1024 * 1024;
    const conversationId = root.dataset.conversationId;
    const otherName = root.dataset.otherName;
    const maxImages = parseInt(root.dataset.maxImages, 10) || 3;

    const list = root.querySelector('[data-chat-messages]');
    const form = root.querySelector('[data-chat-form]');
    const input = root.querySelector('[data-chat-input]');
    const fileInput = root.querySelector('[data-chat-files]');
    const previews = root.querySelector('[data-chat-previews]');
    const sendButton = root.querySelector('[data-chat-send]');
    const notice = root.querySelector('[data-chat-notice]');
    const blockButton = root.querySelector('[data-block-toggle]');
    const menu = document.querySelector('[data-message-menu]');
    const editDialog = document.querySelector('[data-edit-dialog]');
    const editInput = editDialog.querySelector('[data-edit-input]');
    const lightbox = document.querySelector('[data-lightbox]');
    const lightboxImage = lightbox.querySelector('[data-lightbox-image]');

    const state = {
        messages: [],
        signature: '',
        blockedByMe: root.dataset.blockedByMe === '1',
        blockedMe: root.dataset.blockedMe === '1',
        files: [],
        menuMessage: null,
        loading: false
    };

    const timeFormat = new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' });
    const dayFormat = new Intl.DateTimeFormat(undefined, { weekday: 'long', month: 'long', day: 'numeric' });
    const fullFormat = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' });

    function dayLabel(date) {
        const today = new Date();
        const yesterday = new Date();
        yesterday.setDate(today.getDate() - 1);
        if (date.toDateString() === today.toDateString()) {
            return 'Today';
        }
        if (date.toDateString() === yesterday.toDateString()) {
            return 'Yesterday';
        }
        return dayFormat.format(date);
    }

    function isNearBottom() {
        return list.scrollHeight - list.scrollTop - list.clientHeight < 120;
    }

    function scrollToBottom() {
        list.scrollTop = list.scrollHeight;
    }

    function buildBubble(message, isLastMine) {
        const bubble = document.createElement('div');
        bubble.className = 'bubble ' + (message.mine ? 'bubble--mine' : 'bubble--theirs');
        if (message.pending) {
            bubble.classList.add('is-pending');
        }
        if (message.failed) {
            bubble.classList.add('is-failed');
        }
        bubble.dataset.messageId = message.id;

        if (message.images && message.images.length) {
            const gallery = document.createElement('div');
            gallery.className = 'bubble__images bubble__images--' + Math.min(message.images.length, 3);
            message.images.forEach((src) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'bubble__image';
                const img = document.createElement('img');
                img.src = src;
                img.alt = 'Photo';
                img.loading = 'lazy';
                // photos change the height after render, so re-pin to the bottom if we were there
                img.addEventListener('load', () => {
                    if (bubble.dataset.stick === '1') {
                        scrollToBottom();
                    }
                });
                button.appendChild(img);
                button.addEventListener('click', () => openLightbox(src));
                gallery.appendChild(button);
            });
            bubble.appendChild(gallery);
        }

        if (message.text) {
            const text = document.createElement('p');
            text.className = 'bubble__text';
            text.textContent = message.text;
            bubble.appendChild(text);
        }

        const meta = document.createElement('div');
        meta.className = 'bubble__meta';
        const sent = message.sent_at ? new Date(message.sent_at) : new Date();
        const time = document.createElement('time');
        time.dateTime = message.sent_at || '';
        time.title = fullFormat.format(sent);
        if (message.pending) {
            time.textContent = 'Sending…';
        } else if (message.failed) {
            time.textContent = 'Not sent';
        } else {
            time.textContent = timeFormat.format(sent);
        }
        meta.appendChild(time);
        if (message.edited) {
            meta.appendChild(document.createTextNode(' · edited'));
        }
        if (isLastMine && message.read) {
            meta.appendChild(document.createTextNode(' · Seen'));
        }
        bubble.appendChild(meta);

        if (!message.pending && !message.failed) {
            const more = document.createElement('button');
            more.type = 'button';
            more.className = 'bubble__more';
            more.setAttribute('aria-label', 'Message options');
            more.textContent = '⋯';
            more.addEventListener('click', (event) => {
                event.stopPropagation();
                openMenu(message, event.clientX, event.clientY);
            });
            bubble.appendChild(more);
            bubble.addEventListener('contextmenu', (event) => {
                event.preventDefault();
                openMenu(message, event.clientX, event.clientY);
            });
        }

        return bubble;
    }

    function render(messages, forceScroll) {
        const stick = forceScroll || isNearBottom();
        let lastMineIndex = -1;
        messages.forEach((message, index) => {
            if (message.mine && !message.pending) {
                lastMineIndex = index;
            }
        });

        const fragment = document.createDocumentFragment();
        let lastDay = '';

        if (!messages.length) {
            const empty = document.createElement('div');
            empty.className = 'chat__empty';
            empty.textContent = 'No messages yet. Say hello to ' + otherName + '!';
            fragment.appendChild(empty);
        }

        messages.forEach((message, index) => {
            const date = message.sent_at ? new Date(message.sent_at) : new Date();
            const day = date.toDateString();
            if (day !== lastDay) {
                const separator = document.createElement('div');
                separator.className = 'chat__day';
                separator.textContent = dayLabel(date);
                fragment.appendChild(separator);
                lastDay = day;
            }
            const bubble = buildBubble(message, index === lastMineIndex);
            bubble.dataset.stick = stick ? '1' : '0';
            fragment.appendChild(bubble);
        });

        list.replaceChildren(fragment);
        if (stick) {
            scrollToBottom();
        }
    }

    function applyBlockState() {
        const disabled = state.blockedByMe || state.blockedMe;
        input.disabled = disabled;
        sendButton.disabled = disabled;
        fileInput.disabled = disabled;
        form.classList.toggle('is-disabled', disabled);

        if (state.blockedByMe) {
            notice.textContent = 'You blocked ' + otherName + '. Unblock them to send messages.';
        } else if (state.blockedMe) {
            notice.textContent = 'You can no longer reply to this conversation.';
        }
        notice.hidden = !disabled;

        if (blockButton) {
            blockButton.textContent = (state.blockedByMe ? 'Unblock ' : 'Block ') + otherName;
        }
    }

    async function load(forceScroll) {
        if (state.loading) {
            return;
        }
        state.loading = true;
        try {
            const thread = await Vendove.post('api/fetch_messages.php', { conversation_id: conversationId });
            // skip the re-render when nothing changed so scroll position and open menus survive polling
            const signature = JSON.stringify(thread.messages);
            state.blockedByMe = thread.blocked_by_me;
            state.blockedMe = thread.blocked_me;
            applyBlockState();

            if (signature !== state.signature || forceScroll) {
                state.signature = signature;
                state.messages = thread.messages;
                render(state.messages, forceScroll);
            }
        } catch (error) {
            if (!state.messages.length) {
                const empty = document.createElement('div');
                empty.className = 'chat__empty';
                empty.textContent = error.message;
                list.replaceChildren(empty);
            }
        } finally {
            state.loading = false;
        }
    }

    function renderPreviews() {
        previews.innerHTML = '';
        previews.hidden = state.files.length === 0;
        state.files.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'attachment';
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.alt = file.name;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'attachment__remove';
            remove.setAttribute('aria-label', 'Remove ' + file.name);
            remove.textContent = '×';
            remove.addEventListener('click', () => {
                state.files.splice(index, 1);
                renderPreviews();
            });
            item.appendChild(img);
            item.appendChild(remove);
            previews.appendChild(item);
        });
    }

    function autosize() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 160) + 'px';
    }

    async function send() {
        const body = input.value.trim();
        if ((!body && !state.files.length) || input.disabled) {
            return;
        }

        const files = state.files.slice();
        const pending = {
            id: 'pending-' + Date.now(),
            mine: true,
            text: body,
            images: files.map((file) => URL.createObjectURL(file)),
            pending: true,
            sent_at: new Date().toISOString()
        };

        input.value = '';
        autosize();
        state.files = [];
        renderPreviews();
        render(state.messages.concat([pending]), true);

        const upload = new FormData();
        upload.append('conversation_id', conversationId);
        upload.append('body', body);
        files.forEach((file) => upload.append('images[]', file));

        try {
            await Vendove.post('api/send_message.php', upload);
            await load(true);
        } catch (error) {
            pending.pending = false;
            pending.failed = true;
            render(state.messages.concat([pending]), true);
            input.value = body;
            autosize();
            Vendove.toast(error.message, 'error');
        }
    }

    function openMenu(message, x, y) {
        state.menuMessage = message;
        menu.querySelector('[data-message-action="edit"]').hidden = !(message.mine && message.text);
        menu.querySelector('[data-message-action="delete-everyone"]').hidden = !message.mine;
        menu.hidden = false;
        const rect = menu.getBoundingClientRect();
        menu.style.left = Math.min(x, window.innerWidth - rect.width - 8) + 'px';
        menu.style.top = Math.min(y, window.innerHeight - rect.height - 8) + 'px';
    }

    function closeMenu() {
        menu.hidden = true;
    }

    function openLightbox(src) {
        lightboxImage.src = src;
        lightbox.showModal();
    }

    function editMessage(message) {
        editInput.value = message.text || '';
        editDialog.returnValue = '';
        editDialog.showModal();
        editInput.focus();
        editDialog.addEventListener(
            'close',
            async () => {
                const text = editInput.value.trim();
                if (editDialog.returnValue !== 'save' || !text || text === message.text) {
                    return;
                }
                try {
                    await Vendove.post('api/edit_message.php', { message_id: message.id, body: text });
                    await load(false);
                } catch (error) {
                    Vendove.toast(error.message, 'error');
                }
            },
            { once: true }
        );
    }

    async function deleteMessage(message, everyone) {
        const confirmed = await Vendove.confirm({
            title: 'Delete message',
            text: everyone
                ? 'This removes the message for both of you.'
                : 'This hides the message from your view only.',
            button: 'Delete'
        });
        if (!confirmed) {
            return;
        }
        try {
            await Vendove.post('api/delete_message.php', {
                message_id: message.id,
                scope: everyone ? 'everyone' : 'me'
            });
            await load(false);
        } catch (error) {
            Vendove.toast(error.message, 'error');
        }
    }

    menu.addEventListener('click', (event) => {
        const action = event.target.dataset.messageAction;
        const message = state.menuMessage;
        closeMenu();
        if (!action || !message) {
            return;
        }

        if (action === 'edit') {
            editMessage(message);
        } else {
            deleteMessage(message, action === 'delete-everyone');
        }
    });

    document.addEventListener('click', (event) => {
        if (!menu.contains(event.target)) {
            closeMenu();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMenu();
        }
    });
    list.addEventListener('scroll', closeMenu);

    lightbox.addEventListener('click', (event) => {
        if (event.target === lightbox || event.target.closest('[data-lightbox-close]')) {
            lightbox.close();
        }
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        send();
    });

    input.addEventListener('keydown', (event) => {
        // isComposing: don't send while an IME is still building the character
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            send();
        }
    });
    input.addEventListener('input', autosize);

    fileInput.addEventListener('change', () => {
        const chosen = Array.from(fileInput.files);
        fileInput.value = '';
        chosen.forEach((file) => {
            if (!ACCEPTED_TYPES.includes(file.type)) {
                Vendove.toast(file.name + ' is not a JPG, PNG or WebP image.', 'error');
            } else if (file.size > MAX_PHOTO_BYTES) {
                Vendove.toast(file.name + ' is larger than 5 MB.', 'error');
            } else if (state.files.length >= maxImages) {
                Vendove.toast('You can attach up to ' + maxImages + ' photos.', 'error');
            } else {
                state.files.push(file);
            }
        });
        renderPreviews();
        input.focus();
    });

    if (blockButton) {
        blockButton.addEventListener('click', async () => {
            blockButton.closest('details').removeAttribute('open');
            const blocking = !state.blockedByMe;
            if (blocking) {
                const confirmed = await Vendove.confirm({
                    title: 'Block ' + otherName + '?',
                    text: 'Neither of you will be able to send messages in this conversation until you unblock them.',
                    button: 'Block'
                });
                if (!confirmed) {
                    return;
                }
            }
            try {
                const { blocked } = await Vendove.post('api/block_user.php', {
                    conversation_id: conversationId,
                    blocked: blocking ? 1 : 0
                });
                state.blockedByMe = blocked;
                applyBlockState();
                Vendove.toast(otherName + (blocked ? ' is blocked.' : ' is unblocked.'), 'success');
            } catch (error) {
                Vendove.toast(error.message, 'error');
            }
        });
    }

    document.querySelectorAll('[data-delete-conversation]').forEach((button) => {
        button.addEventListener('click', async () => {
            button.closest('details').removeAttribute('open');
            const everyone = button.dataset.deleteConversation === 'everyone';
            const confirmed = await Vendove.confirm({
                title: 'Delete conversation',
                text: everyone
                    ? 'This permanently deletes the conversation and all photos for both of you.'
                    : 'This hides the conversation from your list. It comes back if a new message arrives.',
                button: 'Delete'
            });
            if (!confirmed) {
                return;
            }
            try {
                await Vendove.post('api/delete_conversation.php', {
                    conversation_id: conversationId,
                    scope: everyone ? 'everyone' : 'me'
                });
                window.location.href = 'messages.php';
            } catch (error) {
                Vendove.toast(error.message, 'error');
            }
        });
    });

    function loadIfVisible() {
        if (document.visibilityState === 'visible') {
            load(false);
        }
    }

    async function start() {
        applyBlockState();
        await load(true);
        // opening the thread marks it read, so the header badge is stale now
        Vendove.refreshBadges();
    }

    start();
    setInterval(loadIfVisible, POLL_INTERVAL);
    document.addEventListener('visibilitychange', loadIfVisible);

    if (!input.disabled) {
        input.focus();
    }
})();
