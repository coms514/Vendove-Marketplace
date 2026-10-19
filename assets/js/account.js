(function () {
    'use strict';

    const form = document.querySelector('[data-avatar-form]');
    const dialog = document.querySelector('[data-avatar-dialog]');

    if (form && dialog) {
        const fileInput = form.querySelector('[data-avatar-file]');
        const hiddenInput = form.querySelector('[data-avatar-input]');
        const preview = form.querySelector('[data-avatar-preview]');
        const cropImage = dialog.querySelector('[data-avatar-crop-image]');
        let cropper = null;

        function close() {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            if (dialog.open) {
                dialog.close();
            }
        }

        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];
            fileInput.value = '';
            if (!file) {
                return;
            }
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                Vendove.toast('Please choose a JPG, PNG or WebP image.', 'error');
                return;
            }

            const reader = new FileReader();
            reader.onload = () => {
                cropImage.src = reader.result;
                dialog.showModal();
                // cropper measures the image, so wait until the dialog is laid out
                requestAnimationFrame(() => {
                    cropper = new Cropper(cropImage, {
                        aspectRatio: 1,
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 1,
                        background: false
                    });
                });
            };
            reader.readAsDataURL(file);
        });

        dialog.querySelector('[data-avatar-cancel]').addEventListener('click', close);
        dialog.addEventListener('cancel', close);
        dialog.querySelector('[data-avatar-apply]').addEventListener('click', () => {
            if (!cropper) {
                return;
            }
            const avatar = cropper
                .getCroppedCanvas({ width: 400, height: 400, fillColor: '#ffffff', imageSmoothingQuality: 'high' })
                .toDataURL('image/jpeg', 0.9);
            hiddenInput.value = avatar;
            preview.src = avatar;
            close();
            Vendove.toast('Photo ready. Click "Save profile" to keep it.', 'info');
        });
    }

    const phone = document.querySelector('[data-phone-input]');
    if (phone) {
        phone.addEventListener('input', () => {
            const digits = phone.value.replace(/\D/g, '').slice(0, 10);
            let formatted = digits;
            if (digits.length > 6) {
                formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
            } else if (digits.length > 3) {
                formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
            } else if (digits.length > 0) {
                formatted = '(' + digits;
            }
            phone.value = formatted;
        });
    }
})();
