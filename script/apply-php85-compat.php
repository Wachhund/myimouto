<?php
/**
 * Applies compatibility patches required to run the legacy stack on PHP 8.5.
 * This script is idempotent and is invoked by composer post-install/update hooks.
 */

$root = dirname(__DIR__);

$patches = [
    'vendor/railsphp/railsphp/lib/Rails/Rails.php' => [
        [
            'find' => 'static public function errorHandler($errno, $errstr, $errfile, $errline, $errargs)',
            'replace' => 'static public function errorHandler($errno, $errstr, $errfile, $errline, $errargs = null)',
        ],
        [
            'find' => <<<'TXT'
    static public function errorHandler($errno, $errstr, $errfile, $errline, $errargs = null)
    {
        $errtype = '';
TXT,
            'replace' => <<<'TXT'
    static public function errorHandler($errno, $errstr, $errfile, $errline, $errargs = null)
    {
        if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
            return true;
        }

        $errtype = '';
TXT,
        ],
    ],
    'vendor/railsphp/railsphp/lib/Rails/Yaml/Parser.php' => [
        [
            'find' => 'return SfYaml::parse($this->filepath);',
            'replace' => "return SfYaml::parse((string)file_get_contents(\$this->filepath));",
        ],
    ],
    'vendor/symfony/yaml/Symfony/Component/Yaml/Unescaper.php' => [
        [
            'find' => 'switch ($value{1}) {',
            'replace' => 'switch ($value[1]) {',
        ],
    ],
    'vendor/symfony/yaml/Symfony/Component/Yaml/Inline.php' => [
        [
            'find' => '$value = trim($value);',
            'replace' => '$value = trim((string) $value);',
        ],
    ],
    'vendor/railsphp/railsphp/lib/Rails/Routing/Mapper.php' => [
        [
            'find' => '$alias .= \'_\' . implode($this->resources, \'_\');',
            'replace' => '$alias .= \'_\' . implode(\'_\', $this->resources);',
        ],
    ],
    'vendor/railsphp/railsphp/lib/Rails/Routing/Router.php' => [
        [
            'find' => "\$useCache = Rails::env() == 'production';",
            'replace' => <<<'TXT'
        // Route cache serialization in this framework is not PHP 8 compatible.
        // Keep routing uncached to avoid runtime parse errors.
        $useCache = false;
TXT,
        ],
    ],
    'vendor/railsphp/railsphp/lib/Rails/ActionMailer/ActionMailer.php' => [
        [
            'find' => <<<'TXT'
        switch ($config['delivery_method']) {
TXT,
            'replace' => <<<'TXT'
        if ($config['delivery_method'] instanceof \Closure) {
            $transport = $config['delivery_method']();
            self::transport($transport);
            return;
        }

        switch ($config['delivery_method']) {
TXT,
        ],
    ],
    'vendor/railsphp/railsphp/lib/Rails/Config/Config.php' => [
        [
            'find' => "    function getIterator()\n",
            'replace' => "    #[\\ReturnTypeWillChange]\n    function getIterator()\n",
        ],
        [
            'find' => "    public function offsetSet(\$offset, \$value)\n",
            'replace' => "    #[\\ReturnTypeWillChange]\n    public function offsetSet(\$offset, \$value)\n",
        ],
        [
            'find' => "    public function offsetExists(\$offset)\n",
            'replace' => "    #[\\ReturnTypeWillChange]\n    public function offsetExists(\$offset)\n",
        ],
        [
            'find' => "    public function offsetUnset(\$offset)\n",
            'replace' => "    #[\\ReturnTypeWillChange]\n    public function offsetUnset(\$offset)\n",
        ],
        [
            'find' => "    public function offsetGet(\$offset)\n",
            'replace' => "    #[\\ReturnTypeWillChange]\n    public function offsetGet(\$offset)\n",
        ],
    ],
    'vendor/railsphp/railsphp/lib/Rails/ActionDispatch/Http/Parameters.php' => [
        [
            'find' => "    public function getIterator()\n",
            'replace' => "    #[\\ReturnTypeWillChange]\n    public function getIterator()\n",
        ],
    ],
    'vendor/railsphp/railsphp/lib/Rails/ActionDispatch/Http/Session.php' => [
        [
            'find' => "    public function getIterator()\n",
            'replace' => "    #[\\ReturnTypeWillChange]\n    public function getIterator()\n",
        ],
    ],
    'vendor/railsphp/railsphp/lib/Rails/Paths/Path.php' => [
        [
            'find' => 'public function basePaths(array $basePaths = null)',
            'replace' => 'public function basePaths(?array $basePaths = null)',
        ],
    ],
    'vendor/railsphp/railsphp/lib/Rails/Cache/Store/FileStore/Entry.php' => [
        [
            'find' => <<<'TXT'
        if (!is_dir($this->_path()))
            mkdir($this->_path(), 0777, true);
TXT,
            'replace' => <<<'TXT'
        $path = $this->_path();
        if (!is_dir($path) && !@mkdir($path, 0777, true) && !is_dir($path)) {
            return false;
        }
TXT,
        ],
        [
            'find' => <<<'TXT'
        $path = $this->_path();
        if (!is_dir($path) && !@mkdir($path, 0777, true) && !is_dir($path)) {
            return false;
        }
TXT,
            'replace' => <<<'TXT'
        $path = $this->_path();
        if (!is_dir($path)) {
            try {
                mkdir($path, 0777, true);
            } catch (Rails\Exception\PHPError\Warning $e) {
                if (!is_dir($path)) {
                    throw $e;
                }
            }
        }
TXT,
        ],
        [
            'find' => '            } catch (Rails\Exception\PHPError\Warning $e) {',
            'replace' => '            } catch (\Rails\Exception\PHPError\Warning $e) {',
        ],
    ],
    'vendor/railsphp/railsphp/lib/Rails/ActiveRecord/Base.php' => [
        [
            'find' => '                if ($primary_key && count($primary_key) == 1) {',
            'replace' => '                if ($primary_key && (!is_array($primary_key) || count($primary_key) == 1)) {',
        ],
    ],
    'vendor/railsphp/railsphp/lib/Rails/ActiveRecord/Migration/Migrator.php' => [
        [
            'find' => <<<'TXT'
        require $file;
        
        $classes = get_declared_classes();
        $className = array_pop($classes);
        unset($classes);
        
        $migrator = new $className();
        $migrator->up();
TXT,
            'replace' => <<<'TXT'
        $beforeClasses = get_declared_classes();
        require $file;
        $afterClasses = get_declared_classes();

        $newClasses = array_values(array_diff($afterClasses, $beforeClasses));
        $className = null;
        foreach (array_reverse($newClasses) as $candidate) {
            if ($candidate === __NAMESPACE__ . '\\Base') {
                continue;
            }
            if (is_subclass_of($candidate, __NAMESPACE__ . '\\Base')) {
                $className = $candidate;
                break;
            }
        }

        if (!$className) {
            throw new Exception\RuntimeException(
                sprintf("Migration class for version %s not found in %s", $version, $file)
            );
        }

        $migrator = new $className();
        $migrator->up();
TXT,
        ],
    ],
    'vendor/zendframework/zend-db/src/Sql/AbstractSql.php' => [
        [
            'find' => <<<'TXT'
                    $ppCount = count($multiParamsForPosition);
                    if (!isset($paramSpecs[$position][$ppCount])) {
                        throw new Exception\RuntimeException('A number of parameters (' . $ppCount . ') was found that is not supported by this specification');
                    }
                    $multiParamValues[] = vsprintf($paramSpecs[$position][$ppCount], $multiParamsForPosition);
TXT,
            'replace' => <<<'TXT'
                    if ($multiParamsForPosition instanceof StatementContainer) {
                        $multiParamsForPosition = array($multiParamsForPosition->getSql());
                    } elseif ($multiParamsForPosition instanceof \Traversable) {
                        $multiParamsForPosition = iterator_to_array($multiParamsForPosition);
                    } elseif (!is_array($multiParamsForPosition)) {
                        $multiParamsForPosition = array($multiParamsForPosition);
                    }

                    $ppCount = count($multiParamsForPosition);
                    if (!isset($paramSpecs[$position][$ppCount])) {
                        throw new Exception\RuntimeException('A number of parameters (' . $ppCount . ') was found that is not supported by this specification');
                    }
                    $multiParamValues[] = vsprintf($paramSpecs[$position][$ppCount], $multiParamsForPosition);
TXT,
        ],
        [
            'find' => <<<'TXT'
                $ppCount = count($paramsForPosition);
                if (!isset($paramSpecs[$position][$ppCount])) {
                    throw new Exception\RuntimeException('A number of parameters (' . $ppCount . ') was found that is not supported by this specification');
                }
                $topParameters[] = vsprintf($paramSpecs[$position][$ppCount], $paramsForPosition);
TXT,
            'replace' => <<<'TXT'
                if ($paramsForPosition instanceof StatementContainer) {
                    $paramsForPosition = array($paramsForPosition->getSql());
                } elseif ($paramsForPosition instanceof \Traversable) {
                    $paramsForPosition = iterator_to_array($paramsForPosition);
                } elseif (!is_array($paramsForPosition)) {
                    $paramsForPosition = array($paramsForPosition);
                }

                $ppCount = count($paramsForPosition);
                if (!isset($paramSpecs[$position][$ppCount])) {
                    throw new Exception\RuntimeException('A number of parameters (' . $ppCount . ') was found that is not supported by this specification');
                }
                $topParameters[] = vsprintf($paramSpecs[$position][$ppCount], $paramsForPosition);
TXT,
        ],
    ],
];

$changedFiles = 0;
$issues = [];

foreach ($patches as $relativePath => $filePatches) {
    $file = $root . '/' . $relativePath;
    if (!is_file($file)) {
        $issues[] = "missing: {$relativePath}";
        continue;
    }

    $original = file_get_contents($file);
    if ($original === false) {
        $issues[] = "unreadable: {$relativePath}";
        continue;
    }

    $eol = strpos($original, "\r\n") !== false ? "\r\n" : "\n";
    $content = str_replace("\r\n", "\n", $original);
    $fileChanged = false;

    foreach ($filePatches as $patch) {
        $find = str_replace("\r\n", "\n", $patch['find']);
        $replace = str_replace("\r\n", "\n", $patch['replace']);

        if (strpos($content, $replace) !== false) {
            continue;
        }

        if (strpos($content, $find) !== false) {
            $content = str_replace($find, $replace, $content);
            $fileChanged = true;
            continue;
        }

        $issues[] = "pattern-not-found: {$relativePath}";
    }

    if ($fileChanged) {
        $written = file_put_contents($file, str_replace("\n", $eol, $content));
        if ($written === false) {
            $issues[] = "write-failed: {$relativePath}";
            continue;
        }
        $changedFiles++;
    }
}

echo "[php85-compat] patched-files={$changedFiles}" . PHP_EOL;
if ($issues) {
    echo "[php85-compat] notes:" . PHP_EOL;
    foreach ($issues as $issue) {
        echo " - {$issue}" . PHP_EOL;
    }
}
