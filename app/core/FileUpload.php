<?php
namespace Core;

class FileUpload
{
    private array $cfg;

    public function __construct()
    {
        $app = require CONFIG_ROOT . '/app.php';
        $this->cfg = $app['upload'];
    }

    public function upload(array $file, string $subDir = '', bool $imageOnly = false): string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException($this->uploadErrorMessage((int)$file['error']));
        }
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException('Invalid upload. Please try again.');
        }

        $maxSize = $imageOnly ? $this->cfg['photo_size'] : $this->cfg['max_size'];
        if ($file['size'] > $maxSize) {
            throw new \RuntimeException('File too large. Max ' . round($maxSize / 1024 / 1024) . ' MB allowed.');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = $imageOnly ? $this->cfg['img_only'] : $this->cfg['allowed'];
        if (!in_array($ext, $allowed, true)) {
            throw new \RuntimeException('File type not allowed. Allowed: ' . implode(', ', $allowed));
        }

        // Validate image mime for images
        if ($imageOnly) {
            $mime = $this->detectImageMime($file['tmp_name']);
            if (!$mime || !str_starts_with($mime, 'image/')) {
                throw new \RuntimeException('File is not a valid image.');
            }
        }

        $dir = rtrim($this->cfg['path'] . $subDir, '/') . '/';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Upload directory is not writable.');
        }
        if (!is_writable($dir)) {
            throw new \RuntimeException('Upload directory is not writable.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException('Failed to move uploaded file.');
        }

        return '/' . ltrim(rtrim($this->cfg['url'], '/') . '/' . ltrim($subDir . '/' . $filename, '/'), '/');
    }

    public function delete(string $relativePath): void
    {
        $abs = PUBLIC_ROOT . '/' . ltrim($relativePath, '/');
        if (file_exists($abs)) unlink($abs);
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE => 'Uploaded file is too large.',
            UPLOAD_ERR_PARTIAL   => 'File upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_FILE   => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server temp directory is missing.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded file.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
            default               => 'File upload failed (code ' . $code . ').',
        };
    }

    private function detectImageMime(string $tmpPath): ?string
    {
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($tmpPath);
            if (is_string($mime) && $mime !== '') return $mime;
        }
        if (function_exists('finfo_open')) {
            $f = @finfo_open(FILEINFO_MIME_TYPE);
            if ($f) {
                $mime = @finfo_file($f, $tmpPath);
                @finfo_close($f);
                if (is_string($mime) && $mime !== '') return $mime;
            }
        }
        // Fallback: getimagesize works without the fileinfo extension.
        $info = @getimagesize($tmpPath);
        if (is_array($info) && !empty($info['mime'])) return $info['mime'];
        return null;
    }
}
