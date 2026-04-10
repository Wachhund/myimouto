<?php

namespace MyImouto\PostReplacement;

class StagingService
{
    public const STAGING_DIR = '/tmp/post_replacements';

    public static function stageUploadFromGlobals($field = 'post_replacement', $file_key = 'file')
    {
        $file = self::extractFileFromGlobals($field, $file_key);
        if (!$file) {
            return null;
        }

        return self::stageUploadedFile($file['tmp_name'], $file['name']);
    }

    public static function stageUploadedFile($tmp_path, $original_name = null)
    {
        if (!$tmp_path || !is_file($tmp_path)) {
            throw new \RuntimeException('No upload file available');
        }

        $extension = strtolower(pathinfo((string) $original_name, PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension);
        $basename = bin2hex(random_bytes(16));
        $filename = $extension ? ($basename . '.' . $extension) : $basename;
        $destination = self::stagingDir() . '/' . $filename;

        $moved = false;
        if (function_exists('is_uploaded_file') && is_uploaded_file($tmp_path)) {
            $moved = move_uploaded_file($tmp_path, $destination);
        } else {
            $moved = @rename($tmp_path, $destination);
            if (!$moved) {
                $moved = @copy($tmp_path, $destination);
            }
        }

        if (!$moved) {
            throw new \RuntimeException('Unable to stage upload file');
        }

        return [
            'path' => $destination,
            'name' => $original_name ?: $filename,
        ];
    }

    public static function downloadFromSource($source_url)
    {
        if (!self::isSafeSourceUrl($source_url)) {
            throw new \RuntimeException('Source URL is not allowed');
        }

        // Check upload whitelist
        $whitelist_result = \UploadWhitelist::is_allowed($source_url);
        if (!$whitelist_result['allowed']) {
            throw new \RuntimeException('Source URL is not on the upload whitelist: ' . $whitelist_result['reason']);
        }

        $content = \Danbooru::http_get_streaming($source_url, ['max_size' => \CONFIG()->max_image_size]);
        if (!$content) {
            throw new \RuntimeException('Source URL returned no data');
        }

        $path_part = (string) parse_url((string) $source_url, PHP_URL_PATH);
        $name = basename($path_part ?: 'replacement');
        if ($name === '' || $name === '/') {
            $name = 'replacement';
        }

        $staged = self::stageUploadedFile(self::writeTempContent($content, $name), $name);
        return $staged;
    }

    public static function cleanup($path)
    {
        if (!$path) {
            return;
        }

        $real_staging = realpath(self::stagingDir());
        $real_path = realpath((string) $path);
        if (!$real_staging || !$real_path) {
            return;
        }

        $staging_prefix = rtrim($real_staging, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($real_path !== $real_staging && strpos($real_path, $staging_prefix) !== 0) {
            return;
        }

        if (is_file($real_path)) {
            @unlink($real_path);
        }
    }

    public static function isSafeSourceUrl($source_url)
    {
        $parts = parse_url((string) $source_url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }

        $host = strtolower((string) $parts['host']);
        if (in_array($host, ['localhost', '127.0.0.1', '::1', 'ip6-localhost', 'ip6-loopback'], true)) {
            return false;
        }

        $host_for_ip = trim($host, '[]');
        if (filter_var($host_for_ip, FILTER_VALIDATE_IP)) {
            return self::isPublicIp($host_for_ip);
        }

        $resolved = self::resolveHostIps($host_for_ip);
        if (!$resolved) {
            return false;
        }

        foreach ($resolved as $ip) {
            if (!self::isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    private static function isPublicIp($ip)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        if (strlen($packed) === 16
            && substr($packed, 0, 10) === str_repeat("\x00", 10)
            && substr($packed, 10, 2) === "\xff\xff"
        ) {
            $parts = unpack('N', substr($packed, 12, 4));
            if (!is_array($parts) || !isset($parts[1])) {
                return false;
            }

            $ipv4 = long2ip((int) $parts[1]);
            if (!$ipv4) {
                return false;
            }

            return (bool) filter_var(
                $ipv4,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            );
        }

        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );
    }

    private static function resolveHostIps($host)
    {
        $ips = [];

        if (function_exists('dns_get_record')) {
            $types = 0;
            if (defined('DNS_A')) {
                $types |= DNS_A;
            }
            if (defined('DNS_AAAA')) {
                $types |= DNS_AAAA;
            }
            if ($types > 0) {
                $records = @dns_get_record($host, $types);
                if (is_array($records)) {
                    foreach ($records as $record) {
                        if (!empty($record['ip'])) {
                            $ips[] = $record['ip'];
                        }
                        if (!empty($record['ipv6'])) {
                            $ips[] = $record['ipv6'];
                        }
                    }
                }
            }
        }

        if (!$ips) {
            $resolved = @gethostbynamel($host);
            if (is_array($resolved)) {
                $ips = array_merge($ips, $resolved);
            }
        }

        $ips = array_values(array_unique(array_filter($ips, function ($ip) {
            return (bool) filter_var($ip, FILTER_VALIDATE_IP);
        })));

        return $ips;
    }

    private static function extractFileFromGlobals($field, $file_key)
    {
        if (!isset($_FILES[$field])) {
            return null;
        }

        $upload = $_FILES[$field];

        if (isset($upload['tmp_name'][$file_key])) {
            $error = isset($upload['error'][$file_key]) ? (int) $upload['error'][$file_key] : UPLOAD_ERR_OK;
            if ($error === UPLOAD_ERR_NO_FILE) {
                return null;
            }
            if ($error !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('Upload failed with code ' . $error);
            }

            return [
                'tmp_name' => $upload['tmp_name'][$file_key],
                'name' => isset($upload['name'][$file_key]) ? $upload['name'][$file_key] : $file_key,
            ];
        }

        if (isset($upload['tmp_name'])) {
            $error = isset($upload['error']) ? (int) $upload['error'] : UPLOAD_ERR_OK;
            if ($error === UPLOAD_ERR_NO_FILE) {
                return null;
            }
            if ($error !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('Upload failed with code ' . $error);
            }

            return [
                'tmp_name' => $upload['tmp_name'],
                'name' => isset($upload['name']) ? $upload['name'] : $file_key,
            ];
        }

        return null;
    }

    private static function writeTempContent($content, $original_name)
    {
        $temp = tempnam(self::stagingDir(), 'src_');
        if (!$temp) {
            throw new \RuntimeException('Unable to allocate temporary replacement file');
        }

        $extension = strtolower(pathinfo((string) $original_name, PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension);
        $target = $temp;
        if ($extension) {
            $target = $temp . '.' . $extension;
        }

        if (file_put_contents($target, $content) === false) {
            @unlink($temp);
            throw new \RuntimeException('Unable to write staged source file');
        }

        if ($target !== $temp && is_file($temp)) {
            @unlink($temp);
        }

        return $target;
    }

    private static function stagingDir()
    {
        $path = \Rails::root() . self::STAGING_DIR;
        if (!is_dir($path)) {
            @mkdir($path, 0700, true);
        }

        if (!is_dir($path)) {
            throw new \RuntimeException('Unable to initialize replacement staging directory');
        }

        return $path;
    }
}
