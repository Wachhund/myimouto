<?php
namespace MyImouto;

class Application extends \Rails\Application\Base
{
    private $booruConfig;
    
    public function booruConfig()
    {
        return $this->booruConfig;
    }
    
    protected function init()
    {
        require __DIR__ . '/default_config.php';
        require __DIR__ . '/config.php';
        require __DIR__ . '/../lib/functions.php';
        $this->booruConfig = new LocalConfig();
        
        $this->I18n()->loadLocale('my-imouto');
    }
    
    protected function initConfig($config)
    {
        $config->assets->enabled = true;

        // Keep asset compilation fully local and deterministic.
        // This avoids the legacy assetic/remote-closure minification chain.
        $noopCompressor = [
            'file' => \Rails::root() . '/lib/MyImouto/Assets/NoopCompressor.php',
            'class_name' => 'MyImouto\\Assets\\NoopCompressor',
            'method' => 'run',
            'static' => true,
        ];
        $config->assets->css_compressor = $noopCompressor;
        $config->assets->js_compressor = $noopCompressor;
        
        $config->action_view->layout = 'default';
        
        $config->plugins = [
            'Rails\WillPaginate'
        ];
    }
}
