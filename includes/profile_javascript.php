<script>
document.addEventListener('DOMContentLoaded', function () {

    const passwordButtons =
        document.querySelectorAll('.password-toggle');

    passwordButtons.forEach(function (button) {

        button.addEventListener('click', function () {

            const input =
                document.getElementById(
                    button.dataset.target
                );

            if (!input) {
                return;
            }

            if (input.type === 'password') {
                input.type = 'text';
                button.textContent = 'Hide';
            } else {
                input.type = 'password';
                button.textContent = 'Show';
            }
        });
    });

    const photoInput =
        document.getElementById('profilePhoto');

    const photoPreview =
        document.getElementById(
            'profilePhotoPreview'
        );

    const avatarLetter =
        document.getElementById(
            'profileAvatarLetter'
        );

    const photoActions =
        document.getElementById(
            'profilePhotoActions'
        );

    const photoName =
        document.getElementById(
            'profilePhotoName'
        );

    const cancelButton =
        document.getElementById(
            'cancelPhotoSelection'
        );

    let originalPhotoSource = '';

    if (
        photoPreview &&
        photoPreview.getAttribute('src')
    ) {
        originalPhotoSource =
            photoPreview.getAttribute('src');
    }

    function resetPhotoSelection() {

        if (photoInput) {
            photoInput.value = '';
        }

        if (photoActions) {
            photoActions.classList.remove('show');
        }

        if (photoName) {
            photoName.textContent = '';
        }

        if (!photoPreview) {
            return;
        }

        if (originalPhotoSource !== '') {

            photoPreview.src =
                originalPhotoSource;

            photoPreview.classList.remove('hidden');

            if (avatarLetter) {
                avatarLetter.style.display = 'none';
            }

        } else {

            photoPreview.src = '';

            photoPreview.classList.add('hidden');

            if (avatarLetter) {
                avatarLetter.style.display = 'flex';
            }
        }
    }

    if (photoInput) {

        photoInput.addEventListener(
            'change',
            function () {

                if (
                    !this.files ||
                    this.files.length === 0
                ) {
                    resetPhotoSelection();
                    return;
                }

                const file = this.files[0];

                const allowedTypes = [
                    'image/jpeg',
                    'image/png',
                    'image/webp'
                ];

                if (!allowedTypes.includes(file.type)) {

                    alert(
                        'Only JPG, PNG and WEBP images are allowed.'
                    );

                    resetPhotoSelection();
                    return;
                }

                if (
                    file.size >
                    2 * 1024 * 1024
                ) {
                    alert(
                        'The image must not exceed 2 MB.'
                    );

                    resetPhotoSelection();
                    return;
                }

                if (photoName) {
                    photoName.textContent = file.name;
                }

                if (photoActions) {
                    photoActions.classList.add('show');
                }

                const reader =
                    new FileReader();

                reader.addEventListener(
                    'load',
                    function () {

                        if (!photoPreview) {
                            return;
                        }

                        photoPreview.src =
                            reader.result;

                        photoPreview.classList.remove(
                            'hidden'
                        );

                        if (avatarLetter) {
                            avatarLetter.style.display =
                                'none';
                        }
                    }
                );

                reader.readAsDataURL(file);
            }
        );
    }

    if (cancelButton) {
        cancelButton.addEventListener(
            'click',
            resetPhotoSelection
        );
    }
});
</script>