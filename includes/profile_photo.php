<?php

function uploadProfilePhoto(
    array $uploadedFile,
    int $userID,
    ?string $currentPhoto = null
): string {

    if (
        !isset($uploadedFile['error']) ||
        is_array($uploadedFile['error'])
    ) {
        throw new RuntimeException(
            'Invalid profile photo upload.'
        );
    }

    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE =>
            'The uploaded image exceeds the server upload limit.',
        UPLOAD_ERR_FORM_SIZE =>
            'The uploaded image is too large.',
        UPLOAD_ERR_PARTIAL =>
            'The image was only partially uploaded.',
        UPLOAD_ERR_NO_FILE =>
            'Select a profile image.',
        UPLOAD_ERR_NO_TMP_DIR =>
            'The server temporary folder is missing.',
        UPLOAD_ERR_CANT_WRITE =>
            'The server could not save the uploaded image.',
        UPLOAD_ERR_EXTENSION =>
            'The upload was stopped by a server extension.'
    ];

    if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(
            $uploadErrors[$uploadedFile['error']] ??
            'Unable to upload the profile image.'
        );
    }

    $maximumSize = 2 * 1024 * 1024;

    if (
        !isset($uploadedFile['size']) ||
        (int) $uploadedFile['size'] > $maximumSize
    ) {
        throw new RuntimeException(
            'The profile image must not exceed 2 MB.'
        );
    }

    if (
        empty($uploadedFile['tmp_name']) ||
        !is_uploaded_file($uploadedFile['tmp_name'])
    ) {
        throw new RuntimeException(
            'The uploaded profile image is invalid.'
        );
    }

    $fileInfo =
        new finfo(FILEINFO_MIME_TYPE);

    $mimeType =
        $fileInfo->file(
            $uploadedFile['tmp_name']
        );

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowedTypes[$mimeType])) {
        throw new RuntimeException(
            'Only JPG, PNG and WEBP images are allowed.'
        );
    }

    $imageInformation =
        @getimagesize(
            $uploadedFile['tmp_name']
        );

    if ($imageInformation === false) {
        throw new RuntimeException(
            'The selected file is not a valid image.'
        );
    }

    $width =
        (int) $imageInformation[0];

    $height =
        (int) $imageInformation[1];

    if ($width < 100 || $height < 100) {
        throw new RuntimeException(
            'The image must be at least 100 by 100 pixels.'
        );
    }

    if ($width > 5000 || $height > 5000) {
        throw new RuntimeException(
            'The image dimensions are too large.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Physical upload folder
    |--------------------------------------------------------------------------
    |
    | profile_photo.php is inside includes/.
    | dirname(__DIR__) therefore returns the project root.
    |
    */

    $uploadDirectory =
        dirname(__DIR__) .
        '/assets/uploads/profile_photos/';

    if (!is_dir($uploadDirectory)) {

        if (
            !mkdir(
                $uploadDirectory,
                0775,
                true
            ) &&
            !is_dir($uploadDirectory)
        ) {
            throw new RuntimeException(
                'The profile photo folder could not be created.'
            );
        }
    }

    if (!is_writable($uploadDirectory)) {
        throw new RuntimeException(
            'The profile photo folder is not writable: ' .
            $uploadDirectory
        );
    }

    try {
        $randomText =
            bin2hex(random_bytes(8));

    } catch (Throwable $exception) {
        $randomText =
            str_replace(
                '.',
                '',
                uniqid('', true)
            );
    }

    $extension =
        $allowedTypes[$mimeType];

    $filename =
        'user_' .
        $userID .
        '_' .
        time() .
        '_' .
        $randomText .
        '.' .
        $extension;

    $destination =
        $uploadDirectory .
        $filename;

    if (
        !move_uploaded_file(
            $uploadedFile['tmp_name'],
            $destination
        )
    ) {
        throw new RuntimeException(
            'The profile image could not be saved.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Path saved in the database
    |--------------------------------------------------------------------------
    */

    $databasePath =
        'assets/uploads/profile_photos/' .
        $filename;

    /*
     * Delete the previous image only after the new
     * image has been saved successfully.
     */
    deleteProfilePhotoFile(
        $currentPhoto
    );

    return $databasePath;
}

function deleteProfilePhotoFile(
    ?string $profilePhoto
): void {

    if (empty($profilePhoto)) {
        return;
    }

    $expectedPrefix =
        'assets/uploads/profile_photos/';

    if (
        strpos(
            $profilePhoto,
            $expectedPrefix
        ) !== 0
    ) {
        return;
    }

    $projectRoot =
        dirname(__DIR__);

    $uploadDirectory =
        $projectRoot .
        '/assets/uploads/profile_photos';

    $fullFilePath =
        $projectRoot .
        '/' .
        ltrim($profilePhoto, '/');

    $realUploadDirectory =
        realpath($uploadDirectory);

    $realFilePath =
        realpath($fullFilePath);

    if (
        $realUploadDirectory === false ||
        $realFilePath === false
    ) {
        return;
    }

    if (
        strpos(
            $realFilePath,
            $realUploadDirectory .
            DIRECTORY_SEPARATOR
        ) !== 0
    ) {
        return;
    }

    if (is_file($realFilePath)) {
        @unlink($realFilePath);
    }
}