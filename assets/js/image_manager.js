(function () {
    'use strict';

    const MAX_SOURCE_BYTES = 20 * 1024 * 1024;
    const MAX_DIMENSION = 1600;
    const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    function readAsDataUrl(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = () => reject(new Error('Could not read ' + file.name));
            reader.readAsDataURL(file);
        });
    }

    function loadImage(src) {
        return new Promise((resolve, reject) => {
            const image = new Image();
            image.onload = () => resolve(image);
            image.onerror = () => reject(new Error('That file is not a valid image.'));
            image.src = src;
        });
    }

    // phone photos are often 10+ MB; scale down before upload so they fit the 5 MB server limit
    async function shrink(file) {
        const image = await loadImage(await readAsDataUrl(file));
        const scale = Math.min(1, MAX_DIMENSION / Math.max(image.naturalWidth, image.naturalHeight));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(image.naturalWidth * scale);
        canvas.height = Math.round(image.naturalHeight * scale);
        const context = canvas.getContext('2d');
        // jpeg has no alpha, so paint transparent pngs onto white instead of black
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(image, 0, 0, canvas.width, canvas.height);

        return canvas.toDataURL('image/jpeg', 0.88);
    }

    function setupManager(root) {
        const max = parseInt(root.dataset.max, 10) || 5;
        const hiddenInput = root.querySelector('[data-image-input]');
        const fileInput = root.querySelector('[data-image-file]');
        const grid = root.querySelector('[data-image-grid]');
        const addButton = root.querySelector('[data-image-add]');
        const dialog = document.querySelector('[data-crop-dialog]');
        const cropImage = dialog.querySelector('[data-crop-image]');
        let cropper = null;
        let cropIndex = null;
        let dragIndex = null;

        // saved photos are sent back by id, new or cropped ones as data urls
        const photos = JSON.parse(root.dataset.existing || '[]').map((entry) =>
            entry.data ? { id: null, src: entry.data, data: entry.data } : { id: entry.id, src: entry.url, data: null }
        );

        function sync() {
            hiddenInput.value = JSON.stringify(
                photos.map((photo) => (photo.data ? { data: photo.data } : { id: photo.id }))
            );
            addButton.hidden = photos.length >= max;
            root.dispatchEvent(
                new CustomEvent('images:change', {
                    bubbles: true,
                    detail: { cover: photos.length ? photos[0].src : null }
                })
            );
        }

        function move(from, to) {
            if (from === to || from === null) {
                return;
            }
            const [moved] = photos.splice(from, 1);
            photos.splice(to, 0, moved);
            render();
        }

        function render() {
            grid.innerHTML = '';
            photos.forEach((photo, index) => {
                const tile = document.createElement('div');
                tile.className = 'image-tile';
                tile.draggable = true;

                const preview = document.createElement('button');
                preview.type = 'button';
                preview.className = 'image-tile__preview';
                preview.setAttribute('aria-label', 'Crop photo ' + (index + 1));
                const img = document.createElement('img');
                img.src = photo.src;
                img.alt = '';
                preview.appendChild(img);
                preview.addEventListener('click', () => openCrop(index));
                tile.appendChild(preview);

                if (index === 0) {
                    const badge = document.createElement('span');
                    badge.className = 'image-tile__badge';
                    badge.textContent = 'Cover';
                    tile.appendChild(badge);
                } else {
                    const cover = document.createElement('button');
                    cover.type = 'button';
                    cover.className = 'image-tile__action image-tile__action--left';
                    cover.textContent = 'Make cover';
                    cover.addEventListener('click', () => move(index, 0));
                    tile.appendChild(cover);
                }

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'image-tile__remove';
                remove.setAttribute('aria-label', 'Remove photo ' + (index + 1));
                remove.textContent = '×';
                remove.addEventListener('click', () => {
                    photos.splice(index, 1);
                    render();
                });
                tile.appendChild(remove);

                tile.addEventListener('dragstart', (event) => {
                    dragIndex = index;
                    event.dataTransfer.effectAllowed = 'move';
                    tile.classList.add('is-dragging');
                });
                tile.addEventListener('dragend', () => tile.classList.remove('is-dragging'));
                tile.addEventListener('dragover', (event) => event.preventDefault());
                tile.addEventListener('drop', (event) => {
                    event.preventDefault();
                    move(dragIndex, index);
                    dragIndex = null;
                });

                grid.appendChild(tile);
            });
            sync();
        }

        function openCrop(index) {
            cropIndex = index;
            cropImage.src = photos[index].src;
            dialog.showModal();
            // cropper measures the image, so wait until the dialog is laid out
            requestAnimationFrame(() => {
                if (cropper) {
                    cropper.destroy();
                }
                cropper = new Cropper(cropImage, { aspectRatio: 1, viewMode: 1, autoCropArea: 1, background: false });
            });
        }

        function closeCrop() {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            cropIndex = null;
            if (dialog.open) {
                dialog.close();
            }
        }

        dialog.querySelector('[data-crop-cancel]').addEventListener('click', closeCrop);
        dialog.addEventListener('cancel', closeCrop);
        dialog.querySelector('[data-crop-apply]').addEventListener('click', () => {
            if (!cropper || cropIndex === null) {
                return;
            }
            const canvas = cropper.getCroppedCanvas({
                maxWidth: MAX_DIMENSION,
                maxHeight: MAX_DIMENSION,
                fillColor: '#ffffff',
                imageSmoothingQuality: 'high'
            });
            const cropped = canvas.toDataURL('image/jpeg', 0.9);
            photos[cropIndex] = { id: null, src: cropped, data: cropped };
            closeCrop();
            render();
        });

        addButton.addEventListener('click', () => fileInput.click());

        fileInput.addEventListener('change', async () => {
            const files = Array.from(fileInput.files).slice(0, max - photos.length);
            fileInput.value = '';
            addButton.classList.add('is-loading');

            for (const file of files) {
                if (!ACCEPTED_TYPES.includes(file.type)) {
                    Vendove.toast(file.name + ' is not a JPG, PNG or WebP image.', 'error');
                    continue;
                }
                if (file.size > MAX_SOURCE_BYTES) {
                    Vendove.toast(file.name + ' is larger than 20 MB.', 'error');
                    continue;
                }
                try {
                    const shrunk = await shrink(file);
                    photos.push({ id: null, src: shrunk, data: shrunk });
                } catch (error) {
                    Vendove.toast(error.message, 'error');
                }
            }

            addButton.classList.remove('is-loading');
            render();
        });

        render();
    }

    function setupPreview(form) {
        function update(field) {
            const target = document.querySelector('[data-preview="' + field.dataset.previewSource + '"]');
            if (!target) {
                return;
            }
            let value = field.value.trim();
            if (field.tagName === 'SELECT') {
                value = field.value ? field.options[field.selectedIndex].text : '';
            }
            if (field.dataset.previewSource === 'price' && value !== '') {
                const price = parseFloat(value);
                value = isNaN(price) ? '' : '$' + price.toFixed(2);
            }
            target.textContent = value || target.dataset.empty || '';
            target.classList.toggle('is-empty', value === '');
        }

        form.querySelectorAll('[data-preview-source]').forEach((field) => {
            update(field);
            field.addEventListener('input', () => update(field));
            field.addEventListener('change', () => update(field));
        });

        const cover = document.querySelector('[data-preview-image]');
        const placeholder = cover ? cover.getAttribute('src') : '';
        form.addEventListener('images:change', (event) => {
            if (cover) {
                cover.src = event.detail.cover || placeholder;
            }
        });
    }

    document.querySelectorAll('[data-item-form]').forEach(setupPreview);
    document.querySelectorAll('[data-image-manager]').forEach(setupManager);
})();
