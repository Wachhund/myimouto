<?php

namespace MyImouto\Assets;

class JsCompressor
{
    private static ?string $toolPath = null;
    private static bool $checked = false;

    public static function run($contents)
    {
        $tool = self::findTool();
        if ($tool === null) {
            return (string) $contents;
        }

        $tmpIn = tempnam(sys_get_temp_dir(), 'js_min_');
        file_put_contents($tmpIn, $contents);

        if (str_contains($tool, 'terser')) {
            $cmd = escapeshellarg($tool)
                 . ' ' . escapeshellarg($tmpIn)
                 . ' --compress --mangle 2>&1';
        } else {
            $cmd = escapeshellarg($tool)
                 . ' --bundle ' . escapeshellarg($tmpIn)
                 . ' --minify 2>&1';
        }

        $output = shell_exec($cmd);
        unlink($tmpIn);

        if ($output === null || trim($output) === '') {
            error_log('[PROJ-32] JS minification produced empty output, returning original');
            return (string) $contents;
        }

        return $output;
    }

    private static function findTool(): ?string
    {
        if (self::$checked) {
            return self::$toolPath;
        }
        self::$checked = true;

        $projectRoot = defined('RAILS_ROOT') ? RAILS_ROOT : dirname(__DIR__, 3);
        $npxLocal = $projectRoot . '/node_modules/.bin/terser';
        if (is_file($npxLocal) || is_file($npxLocal . '.cmd')) {
            self::$toolPath = is_file($npxLocal . '.cmd') ? $npxLocal . '.cmd' : $npxLocal;
            return self::$toolPath;
        }

        $global = trim((string) shell_exec('which terser 2>/dev/null'));
        if ($global !== '') {
            self::$toolPath = $global;
            return self::$toolPath;
        }

        $globalEsbuild = trim((string) shell_exec('which esbuild 2>/dev/null'));
        if ($globalEsbuild !== '') {
            self::$toolPath = $globalEsbuild;
            return self::$toolPath;
        }

        error_log('[PROJ-32] No JS minification tool found (terser/esbuild). Falling back to no compression.');
        self::$toolPath = null;
        return null;
    }
}
