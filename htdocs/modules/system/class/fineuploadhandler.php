<?php

use Xmf\Request;

/**
 * Base SystemFineUploadHandler class to work with ajaxfineupload.php endpoint
 *
 * Upload files as specified
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

abstract class SystemFineUploadHandler
{
    public $allowedExtensions = [];
    public $allowedMimeTypes = ['(none)']; // must specify!
    public $sizeLimit = null;
    public $inputName = 'qqfile';
    public $chunksFolder = 'chunks';

    public $chunksCleanupProbability = 0.001; // Once in 1000 requests on avg
    public $chunksExpireIn = 604800; // One week

    public $uploadName;
    public $claims;

    /**
     * XoopsFineUploadHandler constructor.
     * @param stdClass $claims claims passed in JWT header
     */
    public function __construct(\stdClass $claims)
    {
        $this->claims = $claims;
    }

    /**
     * Confirm a client-supplied chunk identifier is a single safe path segment.
     *
     * @param string $uuid
     * @return string the validated identifier
     * @throws \RuntimeException when the value is not a plain identifier
     */
    protected function safeUuid($uuid)
    {
        $uuid = (string) $uuid;
        if (1 !== preg_match('/^[A-Za-z0-9_-]{1,64}$/', $uuid)) {
            throw new \RuntimeException('Invalid upload identifier.');
        }
        return $uuid;
    }

    /**
     * Reduce a client-supplied filename to a single safe leaf name and, when an
     * extension allowlist is configured, confirm the extension is permitted. This
     * is applied on the chunk-combine path as well as on normal upload, so a
     * combine request cannot introduce a path segment or a disallowed type.
     *
     * @param string $name
     * @return string the validated leaf name
     * @throws \RuntimeException when the value is empty, hidden, or disallowed
     */
    protected function safeLeafName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        if ('' === $name || '.' === $name[0]
            || strlen($name) > 255
            || preg_match('/[\x00-\x1F\x7F]/', $name)) {
            throw new \RuntimeException('Invalid file name.');
        }
        if (!empty($this->allowedExtensions)) {
            $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, array_map('strtolower', $this->allowedExtensions), true)) {
                throw new \RuntimeException('File has an invalid extension.');
            }
        }
        return $name;
    }

    /**
     * Defence-in-depth check that a built path stays under $root. The UUID and
     * leaf name are already validated, so this performs a filesystem-independent
     * lexical check (no realpath) that rejects residual parent traversal and
     * confirms the root prefix - it never rejects a directory that does not exist
     * yet, which would otherwise break the first chunk of an upload.
     *
     * @param string $path
     * @param string $root
     * @return string the validated path
     * @throws \RuntimeException when the path would fall outside $root
     */
    protected function assertWithin($path, $root)
    {
        $normalizedPath = str_replace('\\', '/', (string) $path);
        $normalizedRoot = rtrim(str_replace('\\', '/', (string) $root), '/') . '/';
        if (false !== strpos($normalizedPath, '../')
            || str_ends_with($normalizedPath, '/..')
            || 0 !== strncmp($normalizedPath, $normalizedRoot, strlen($normalizedRoot))) {
            throw new \RuntimeException('Path escapes upload root.');
        }
        return $path;
    }

    /**
     * Get the original filename
     */
    public function getName(): string
    {
        if (Request::hasVar('qqfilename', 'REQUEST')) {
            return Request::getString('qqfilename', '', 'REQUEST');
        }

        if (Request::hasVar($this->inputName, 'FILES')) {
            $file = Request::getArray($this->inputName, [], 'FILES');
            return (is_array($file) && isset($file['name']) && is_string($file['name'])) ? $file['name'] : '';
        }

        return '';
    }

    /**
     * Get the name of the uploaded file
     * @return string
     */
    public function getUploadName()
    {
        return $this->uploadName;
    }

    /**
     * Combine chunks into a single file
     *
     * @param string      $uploadDirectory upload directory
     * @param string|null $name            name
     * @return array response to be json encoded and returned to client
     */
    public function combineChunks($uploadDirectory, $name = null)
    {
        $uuid = $this->safeUuid(Request::getString('qquuid', '', 'REQUEST'));
        if (null === $name || '' === $name) {
            $name = $this->getName();
        }
        $name = $this->safeLeafName($name);
        $targetFolder = $this->assertWithin(
            $this->chunksFolder . DIRECTORY_SEPARATOR . $uuid,
            $this->chunksFolder
        );
        $totalParts = Request::getInt('qqtotalparts', 1, 'REQUEST');
        if ($totalParts < 1) {
            throw new \RuntimeException('Invalid chunk metadata.');
        }

        $targetPath = $this->assertWithin(
            implode(DIRECTORY_SEPARATOR, [$uploadDirectory, $uuid, $name]),
            $uploadDirectory
        );
        $this->uploadName = $name;

        // Confirm every expected chunk is present before writing the final file,
        // so a combine request cannot produce an empty or partial target.
        for ($i = 0; $i < $totalParts; $i++) {
            if (!is_readable($targetFolder . DIRECTORY_SEPARATOR . $i)) {
                throw new \RuntimeException('Missing upload chunk.');
            }
        }

        if (!file_exists($targetPath)) {
            if (!mkdir($concurrentDirectory = dirname($targetPath), 0775, true) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }
        $target = fopen($targetPath, 'wb');

        for ($i = 0; $i < $totalParts; $i++) {
            $chunk = fopen($targetFolder . DIRECTORY_SEPARATOR . $i, 'rb');
            stream_copy_to_stream($chunk, $target);
            fclose($chunk);
        }

        // Success
        fclose($target);

        for ($i = 0; $i < $totalParts; $i++) {
            unlink($targetFolder . DIRECTORY_SEPARATOR . $i);
        }

        rmdir($targetFolder);

        if (null !== $this->sizeLimit && filesize($targetPath) > $this->sizeLimit) {
            unlink($targetPath);
            //http_response_code(413);
            header('HTTP/1.0 413 Request Entity Too Large');
            return ['success' => false, 'uuid' => $uuid, 'preventRetry' => true];
        }

        return ['success' => true, 'uuid' => $uuid];
    }

    /**
     * Process the upload.
     * @param string $uploadDirectory Target directory.
     * @param string $name Overwrites the name of the file.
     * @return array response to be json encoded and returned to client
     */
    public function handleUpload($uploadDirectory, $name = null)
    {
        if (is_writable($this->chunksFolder) &&
            1 == mt_rand(1, 1 / $this->chunksCleanupProbability)) {
            // Run garbage collection
            $this->cleanupChunks();
        }

        // Check that the max upload size specified in class configuration does not
        // exceed size allowed by server config
        if ($this->toBytes(ini_get('post_max_size')) < $this->sizeLimit ||
            $this->toBytes(ini_get('upload_max_filesize')) < $this->sizeLimit) {
            $neededRequestSize = max(1, $this->sizeLimit / 1024 / 1024) . 'M';
            return [
                'error' => 'Server error. Increase post_max_size and upload_max_filesize to ' . $neededRequestSize,
            ];
        }

        if ($this->isInaccessible($uploadDirectory)) {
            return ['error' => "Server error. Uploads directory isn't writable"];
        }

        $type = $_SERVER['HTTP_CONTENT_TYPE'] ?? $_SERVER['CONTENT_TYPE'];

        if (!isset($type)) {
            return ['error' => "No files were uploaded."];
        }

        if (strpos(strtolower($type), 'multipart/') !== 0) {
            return [
                'error' => "Server error. Not a multipart request. Please set forceMultipart to default value (true).",
            ];
        }

        // Get size and name
        $file = Request::getArray($this->inputName, [], 'FILES');
        $size = $file['size'] ?? 0;
        // Pin the declared total size to POST so a GET/cookie value cannot
        // understate the size and slip past the size-limit check below.
        if (Request::hasVar('qqtotalfilesize', 'POST')) {
            $size = Request::getInt('qqtotalfilesize', 0, 'POST');
        }

        if (null === $name) {
            $name = $this->getName();
        }

        // check file error
        if (!empty($file['error'])) {
            return ['error' => 'Upload Error #' . $file['error']];
        }

        // Validate name
        if (null === $name || '' === $name) {
            return ['error' => 'File name empty.'];
        }

        // Validate file size
        if (0 == $size) {
            return ['error' => 'File is empty.'];
        }

        if (null !== $this->sizeLimit && $size > $this->sizeLimit) {
            return ['error' => 'File is too large.', 'preventRetry' => true];
        }

        // Validate file extension
        $pathinfo = pathinfo((string) $name);
        $ext = isset($pathinfo['extension']) ? strtolower($pathinfo['extension']) : '';

        if ($this->allowedExtensions
            && !in_array(strtolower($ext), array_map('strtolower', $this->allowedExtensions))) {
            $these = implode(', ', $this->allowedExtensions);
            return [
                'error' => 'File has an invalid extension, it should be one of ' . $these . '.',
                'preventRetry' => true,
            ];
        }

        $mimeType = '';
        if (!empty($this->allowedMimeTypes)) {
            $fileArr = Request::getArray($this->inputName, [], 'FILES');
            $tmpName = $fileArr['tmp_name'] ?? '';
            if ('' === $tmpName || !is_string($tmpName) || !is_readable($tmpName)) {
                return ['error' => 'File is empty.', 'preventRetry' => true];
            }
            $mimeType = mime_content_type($tmpName);
            if (false === $mimeType || !in_array($mimeType, $this->allowedMimeTypes, true)) {
                return ['error' => 'File is of an invalid type.', 'preventRetry' => true];
            }
        }

        // Save a chunk
        $totalParts = 1;
        if (Request::hasVar('qqtotalparts', 'REQUEST')) {
            $totalParts = Request::getInt('qqtotalparts', 1, 'REQUEST');
        }
        if ($totalParts < 1) {
            throw new \RuntimeException('Invalid chunk metadata.');
        }

        // FineUploader sends its qq* identifiers where the REQUEST hash finds them
        // (query string or body depending on the client); do NOT pin these to POST
        // or uploads break. safeUuid() validates the value regardless of source.
        // Only qqtotalfilesize stays POST-only (above), where the source matters.
        $uuid = $this->safeUuid(Request::getString('qquuid', '', 'REQUEST'));
        if ($totalParts > 1) {
            # chunked upload

            $chunksFolder = $this->chunksFolder;
            $partIndex = Request::getInt('qqpartindex', -1, 'REQUEST');
            if ($partIndex < 0 || $partIndex >= $totalParts) {
                throw new \RuntimeException('Invalid chunk metadata.');
            }

            if ($this->isInaccessible($chunksFolder)) {
                return ['error' => "Server error. Chunks directory isn't writable or executable."];
            }

            $targetFolder = $this->assertWithin(
                $this->chunksFolder . DIRECTORY_SEPARATOR . $uuid,
                $this->chunksFolder
            );

            if (!file_exists($targetFolder)) {
                if (!mkdir($targetFolder, 0775, true) && !is_dir($targetFolder)) {
                    throw new \RuntimeException(sprintf('Directory "%s" was not created', $targetFolder));
                }
            }

            $target = $targetFolder . '/' . $partIndex;

            $storeResult = $this->storeUploadedFile($target, $mimeType, $uuid);
            if (false !== $storeResult) {
                return $storeResult;
            }
        } else {
            # non-chunked upload

            $name = $this->safeLeafName($name);
            $target = $this->assertWithin(
                implode(DIRECTORY_SEPARATOR, [$uploadDirectory, $uuid, $name]),
                $uploadDirectory
            );

            if ($target) {
                $this->uploadName = basename($target);

                $storeResult = $this->storeUploadedFile($target, $mimeType, $uuid);
                if (false !== $storeResult) {
                    return $storeResult;
                }
            }

            return ['error' => 'Could not save uploaded file.' .
                               'The upload was cancelled, or server error encountered',
            ];
        }
    }

    protected function storeUploadedFile($target, $mimeType, $uuid)
    {
        if (!is_dir(dirname((string) $target))) {
            if (!mkdir($concurrentDirectory = dirname((string) $target), 0775, true) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }
        $file = Request::getArray($this->inputName, null, 'FILES');
        if (null !== $file && move_uploaded_file($file['tmp_name'], $target)) {
            return ['success' => true, 'uuid' => $uuid];
        }
        return false;
    }

    /**
     * Process a delete.
     * @param string      $uploadDirectory Target directory.
     * @param string|null $name            Overwrites the name of the file.
     * @return array response to be json encoded and returned to client
     */
    public function handleDelete($uploadDirectory, $name = null)
    {
        if ($this->isInaccessible($uploadDirectory)) {
            return [
                'error' => "Server error. Uploads directory isn't writable"
                           . ((!$this->isWindows()) ? ' or executable.' : '.'),
            ];
        }

        $uuid = false;
        $url  = '';
        $method = Request::getString('REQUEST_METHOD', 'GET', 'SERVER');

        if ('DELETE' === $method) {
            $url = Request::getString('REQUEST_URI', '', 'SERVER');
            if ('' !== $url) {
                $url    = parse_url($url, PHP_URL_PATH);
                $tokens = explode('/', $url);
                $uuid = $this->safeUuid($tokens[count($tokens) - 1]);
            }
        } elseif ('POST' === $method) {
            $uuid = $this->safeUuid(Request::getString('qquuid', '', 'REQUEST'));
        } else {
            return ['success' => false,
                'error'   => 'Invalid request method! ' . $method,
            ];
        }

        // Refuse a missing identifier: without it the target resolves to the
        // upload root itself and removeDir() would wipe the whole directory.
        if (false === $uuid || '' === $uuid) {
            return ['success' => false, 'error' => 'Missing upload identifier.'];
        }

        $target = $this->assertWithin(
            implode(DIRECTORY_SEPARATOR, [$uploadDirectory, $uuid]),
            $uploadDirectory
        );

        if (is_dir($target)) {
            $this->removeDir($target);
            return ['success' => true, 'uuid' => $uuid];
        } else {
            return [
                'success' => false,
                'error'   => 'File not found! Unable to delete.' . $url,
                'path'    => $uuid,
            ];
        }
    }

    /**
     * Returns a path to use with this upload. Check that the name does not exist,
     * and appends a suffix otherwise.
     * @param string $uploadDirectory Target directory
     * @param string $filename The name of the file to use.
     *
     * @return string|false path or false if path could not be determined
     */
    protected function getUniqueTargetPath($uploadDirectory, $filename)
    {
        // Allow only one process at the time to get a unique file name, otherwise
        // if multiple people would upload a file with the same name at the same time
        // only the latest would be saved.

        if (function_exists('sem_acquire')) {
            $lock = sem_get(ftok(__FILE__, 'u'));
            sem_acquire($lock);
        }

        $pathinfo = pathinfo($filename);
        $base = $pathinfo['filename'];
        $ext = $pathinfo['extension'] ?? '';
        $ext = '' == $ext ? $ext : '.' . $ext;

        $unique = $base;
        $suffix = 0;

        // Get unique file name for the file, by appending random suffix.

        while (file_exists($uploadDirectory . DIRECTORY_SEPARATOR . $unique . $ext)) {
            $suffix += random_int(1, 999);
            $unique = $base . '-' . $suffix;
        }

        $result =  $uploadDirectory . DIRECTORY_SEPARATOR . $unique . $ext;

        // Create an empty target file
        if (!touch($result)) {
            // Failed
            $result = false;
        }

        if (function_exists('sem_acquire')) {
            sem_release($lock);
        }

        return $result;
    }

    /**
     * Deletes all file parts in the chunks folder for files uploaded
     * more than chunksExpireIn seconds ago
     *
     * @return void
     */
    protected function cleanupChunks()
    {
        foreach (scandir($this->chunksFolder) as $item) {
            if ('.' == $item || '..' == $item) {
                continue;
            }

            $path = $this->chunksFolder . DIRECTORY_SEPARATOR . $item;

            if (!is_dir($path)) {
                continue;
            }

            if (time() - filemtime($path) > $this->chunksExpireIn) {
                $this->removeDir($path);
            }
        }
    }

    /**
     * Removes a directory and all files contained inside
     * @param string $dir
     * @return void
     */
    protected function removeDir($dir)
    {
        foreach (scandir($dir) as $item) {
            if ('.' == $item || '..' == $item) {
                continue;
            }

            // Build the full child path so recursion and deletion act on the
            // intended entry rather than a bare name resolved against the CWD.
            $child = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($child)) {
                $this->removeDir($child);
            } else {
                unlink($child);
            }
        }
        rmdir($dir);
    }

    /**
     * Converts a given size with units to bytes.
     * @param string $str
     * @return int
     */
    protected function toBytes($str)
    {
        $str = trim($str);
        $last = strtolower($str[strlen($str) - 1]);
        if(is_numeric($last)) {
            $val = (int) $str;
        } else {
            $val = (int) substr($str, 0, -1);
        }
        switch ($last) {
            case 'g':
                $val *= 1024; // fall thru
                // no break
            case 'm':
                $val *= 1024; // fall thru
                // no break
            case 'k':
                $val *= 1024; // fall thru
        }
        return $val;
    }

    /**
     * Determines whether a directory can be accessed.
     *
     * is_executable() is not reliable on Windows prior PHP 5.0.0
     *  (https://www.php.net/manual/en/function.is-executable.php)
     * The following tests if the current OS is Windows and if so, merely
     * checks if the folder is writable;
     * otherwise, it checks additionally for executable status (like before).
     *
     * @param string $directory The target directory to test access
     * @return bool true if directory is NOT accessible
     */
    protected function isInaccessible($directory)
    {
        // A directory must be writable to create files in, and (on non-Windows)
        // executable to traverse; lacking either makes it unusable. is_executable()
        // is unreliable for directories on Windows, so it is only checked elsewhere.
        if (!is_writable($directory)) {
            return true;
        }
        return !$this->isWindows() && !is_executable($directory);
    }

    /**
     * Determines is the OS is Windows or not
     *
     * @return bool
     */

    protected function isWindows()
    {
        $isWin = (stripos(PHP_OS, 'WIN') === 0);
        return $isWin;
    }
}
