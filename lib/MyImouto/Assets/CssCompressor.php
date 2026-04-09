<?php

namespace MyImouto\Assets;

class CssCompressor
{
    private static ?string $toolPath = null;
    private static bool $checked = false;

    public static function run($contents)
    {
        $tool = self::findTool();
        if ($tool === null) {
            return (string) $contents;
        }

        $tmpIn = tempnam(sys_get_temp_dir(), 'css_min_');
        file_put_contents($tmpIn, $contents);

        $cmd = escapeshellarg($tool)
             . ' ' . escapeshellarg($tmpIn)
             . ' 2>&1';

        $output = shell_exec($cmd);
        unlink($tmpIn);

        if ($output === null || trim($output) === '') {
            error_log('[PROJ-32] CSS minification produced empty output, returning original');
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

        // Prefer clean-css (handles legacy CSS hacks like *display, zoom)
        $cleanCssLocal = $projectRoot . '/node_modules/.bin/cleancss';
        if (is_file($cleanCssLocal) || is_file($cleanCssLocal . '.cmd')) {
            self::$toolPath = is_file($cleanCssLocal . '.cmd') ? $cleanCssLocal . '.cmd' : $cleanCssLocal;
            return self::$toolPath;
        }

        $global = trim((string) shell_exec('which cleancss 2>/dev/null'));
        if ($global !== '') {
            self::$toolPath = $global;
            return self::$toolPath;
        }

        error_log('[PROJ-32] No CSS minification tool found (cleancss). Falling back to no compression.');
        self::$toolPath = null;
        return null;
    }
}
