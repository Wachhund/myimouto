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

        $config->assets->js_compressor = [
            'file' => \Rails::root() . '/lib/MyImouto/Assets/JsCompressor.php',
            'class_name' => 'MyImouto\\Assets\\JsCompressor',
            'method' => 'run',
            'static' => true,
        ];
        $config->assets->css_compressor = [
            'file' => \Rails::root() . '/lib/MyImouto/Assets/CssCompressor.php',
            'class_name' => 'MyImouto\\Assets\\CssCompressor',
            'method' => 'run',
            'static' => true,
        ];
        
        $config->action_view->layout = 'default';
        
        $config->plugins = [
            'Rails\WillPaginate'
        ];
    }
}
