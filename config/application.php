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

        // PROJ-46 AC-2: Reject default password salt in production.
        if ($this->booruConfig->user_password_salt === 'choujin-steiner') {
            if (RAILS_ENV === 'production') {
                throw new \RuntimeException(
                    'SECURITY: Default password salt is still active. ' .
                    'Set a unique $user_password_salt in config/config.php before running in production.'
                );
            } else {
                error_log('[WARN] Default password salt "choujin-steiner" is active. Override in config/config.php for production use.');
            }
        }
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
