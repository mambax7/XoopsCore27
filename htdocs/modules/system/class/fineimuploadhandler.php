<?php

/**
 * SystemFineImUploadHandler class to work with ajaxfineupload.php endpoint
 * to facilitate uploads for the system image manager
 *
 * Do not use or reference this directly from your client-side code.
 * Instead, this should be required via the endpoint.php or endpoint-cors.php
 * file(s).
 *
 * @license   MIT License (MIT)
 * @copyright Copyright (c) 2015-present, Widen Enterprises, Inc.
 * @link      https://github.com/FineUploader/php-traditional-server
 *
 * The MIT License (MIT)
 *
 * Copyright (c) 2015-present, Widen Enterprises, Inc.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

class SystemFineImUploadHandler extends SystemFineUploadHandler
{
    /**
     * XoopsFineImUploadHandler constructor.
     * @param stdClass $claims claims passed in JWT header
     */
    public function __construct(\stdClass $claims)
    {
        parent::__construct($claims);
        $this->allowedMimeTypes = ['image/gif', 'image/jpeg', 'image/png'];
        $this->allowedExtensions = ['gif', 'jpeg', 'jpg', 'png'];

        // The category's byte limit used to reach only the client-side widget.
        // Cap it at what PHP itself accepts, so a category limit above the
        // php.ini limits does not make handleUpload() refuse every file.
        $imgcat = $this->category();
        $maxSize = (null === $imgcat) ? 0 : (int) $imgcat->getVar('imgcat_maxsize');
        if ($maxSize > 0) {
            foreach (['upload_max_filesize', 'post_max_size'] as $ini) {
                $value = trim((string) ini_get($ini));
                $bytes = ('' === $value) ? 0 : (int) $this->toBytes($value);
                if ($bytes > 0) {
                    $maxSize = min($maxSize, $bytes);
                }
            }
            $this->sizeLimit = $maxSize;
        }
    }

    /**
     * Mint the ajaxfineupload.php token that routes an upload to this handler.
     * Every caller uses this, so the claims cannot drift apart. The caller must
     * already have checked imgcat_write for the category.
     *
     * @param int $imgcatId target image category
     * @param int $uid      current user id, 0 for anonymous
     * @return string JWT, valid for 30 minutes
     *
     * @throws \DomainException          propagated from TokenFactory::build()
     * @throws \InvalidArgumentException propagated from TokenFactory::build()
     * @throws \UnexpectedValueException propagated from TokenFactory::build()
     */
    public static function uploadToken(int $imgcatId, int $uid): string
    {
        $payload = [
            'aud'     => 'ajaxfineupload.php',
            'cat'     => $imgcatId,
            'uid'     => $uid,
            'handler' => 'fineimuploadhandler',
            'moddir'  => 'system',
        ];

        return \Xmf\Jwt\TokenFactory::build('fineuploader', $payload, 60 * 30);
    }

    /**
     * The image category named in the token claims.
     *
     * @return XoopsImagecategory|null null when the category does not exist
     */
    protected function category(): ?XoopsImagecategory
    {
        /** @var XoopsImagecategoryHandler $imgcatHandler */
        $imgcatHandler = xoops_getHandler('imagecategory');
        $imgcat = $imgcatHandler->get((int) ($this->claims->cat ?? 0));

        return $imgcat ?: null;
    }

    /**
     * Check an uploaded file against the category's pixel limits. A limit of 0
     * means no limit.
     *
     * @param string             $file   path of the uploaded temp file
     * @param XoopsImagecategory $imgcat target category
     * @return string|null error message, or null when the image is acceptable
     */
    protected function dimensionError(string $file, XoopsImagecategory $imgcat): ?string
    {
        $size = getimagesize($file);
        if (false === $size) {
            return 'File is of an invalid type.';
        }
        $maxWidth  = (int) $imgcat->getVar('imgcat_maxwidth');
        $maxHeight = (int) $imgcat->getVar('imgcat_maxheight');
        if (($maxWidth > 0 && $size[0] > $maxWidth) || ($maxHeight > 0 && $size[1] > $maxHeight)) {
            return sprintf('Image is too large. The limit is %d x %d pixels.', $maxWidth, $maxHeight);
        }

        return null;
    }

    protected function storeUploadedFile($target, $mimeType, $uuid)
    {
        $imgcat = $this->category();
        if (null === $imgcat) {
            return ['error' => 'Invalid image category.', 'preventRetry' => true];
        }

        $dimensionError = $this->dimensionError((string) $_FILES[$this->inputName]['tmp_name'], $imgcat);
        if (null !== $dimensionError) {
            return ['error' => $dimensionError, 'preventRetry' => true];
        }

        $pathParts = pathinfo((string) $this->getName());

        $imageName = uniqid('img', false) . '.' . strtolower($pathParts['extension']);
        $imageNicename = str_replace(['_', '-'], ' ', $pathParts['filename']);
        $imagePath = XOOPS_ROOT_PATH . '/uploads/images/' . $imageName;

        $fbinary = null;
        if ($imgcat->getVar('imgcat_storetype') === 'db') {
            $fbinary = file_get_contents($_FILES[$this->inputName]['tmp_name']);
        } else {
            if (false === move_uploaded_file($_FILES[$this->inputName]['tmp_name'], $imagePath)) {
                return false;
            }
        }

        /** @var XoopsImageHandler $imageHandler */
        $imageHandler = xoops_getHandler('image');
        $image = $imageHandler->create();

        $image->setVar('image_nicename', $imageNicename);
        $image->setVar('image_mimetype', $mimeType);
        $image->setVar('image_created', time());
        $image->setVar('image_display', 1);
        $image->setVar('image_weight', 0);
        $image->setVar('imgcat_id', $this->claims->cat);
        if ($imgcat->getVar('imgcat_storetype') === 'db') {
            $image->setVar('image_body', $fbinary, true);
        } else {
            $image->setVar('image_name', 'images/' . $imageName);
        }
        if (!$imageHandler->insert($image)) {
            return [
                'error' => sprintf(_FAILSAVEIMG, $image->getVar('image_nicename')),
            ];
        }
        return [
            'success'  => true,
            'uuid'     => $uuid,
            'image_id' => (int) $image->getVar('image_id'),
            'name'     => $imageNicename,
        ];
    }
}
