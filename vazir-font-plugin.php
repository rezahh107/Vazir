<?php
/**
 * Plugin Name: Vazir Font for WordPress
 * Plugin URI: https://github.com/your-username/vazir-font-wp
 * Description: اضافه کردن فونت وزیر به تمام بخش‌های وردپرس شامل فرانت، ادمین و گرویتی فرمز
 * Version: 1.1.0
 * Author: Your Name
 * Author URI: https://yoursite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vazir-font-wp
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.5
 * Requires PHP: 7.4
 * Network: false
 */

defined('ABSPATH') || exit;

// Define constants
define('VAZIR_FONT_VERSION', '1.1.0');
define('VAZIR_FONT_PLUGIN_FILE', __FILE__);
define('VAZIR_FONT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VAZIR_FONT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VAZIR_FONT_ASSETS_URL', VAZIR_FONT_PLUGIN_URL . 'assets/');
define('VAZIR_FONT_FONTS_URL', VAZIR_FONT_ASSETS_URL . 'fonts/');

// Autoload classes
spl_autoload_register(function ($class) {
    $prefix = 'VazirFont_';
    $base_dir = VAZIR_FONT_PLUGIN_DIR . 'includes/';
    
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    
    $relative_class = substr($class, strlen($prefix));
    $file = $base_dir . 'class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Main plugin class
 */
final class VazirFontPlugin
{
    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->initHooks();
    }

    private function initHooks()
    {
        register_activation_hook(VAZIR_FONT_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(VAZIR_FONT_PLUGIN_FILE, [$this, 'deactivate']);
        add_action('plugins_loaded', [$this, 'init']);
    }

    public function init()
    {
        load_plugin_textdomain('vazir-font-wp', false, dirname(plugin_basename(VAZIR_FONT_PLUGIN_FILE)) . '/languages');

        // Initialize components
        VazirFont_Loader::getInstance();
        
        if (is_admin()) {
            VazirFont_Admin_Settings::getInstance();
        }

        if (class_exists('GFForms')) {
            VazirFont_GravityForms_Integration::getInstance();
        }
    }

    public function activate()
    {
        $defaults = [
            'enable_frontend' => true,
            'enable_admin' => true,
            'enable_gravity_forms' => true,
            'font_weights' => ['300', '400', '500', '700', '900'],
            'exclude_selectors' => [
                '.dashicons',
                '.dashicons-before:before',
                '[class*="dashicons"]:before',
                '.wp-menu-image',
                'i.fa',
                '[class*="icon-"]:before',
                '.material-icons',
                '[data-icon]:before'
            ]
        ];

        add_option('vazir_font_options', $defaults);
        
        if (!wp_next_scheduled('vazir_font_clear_cache')) {
            wp_schedule_event(time(), 'weekly', 'vazir_font_clear_cache');
        }
    }

    public function deactivate()
    {
        wp_clear_scheduled_hook('vazir_font_clear_cache');
    }

    public static function getOptions()
    {
        static $options = null;
        
        if ($options === null) {
            $options = get_option('vazir_font_options', []);
            
            // Backward compatibility
            if (!isset($options['font_weights'])) {
                $options['font_weights'] = ['400'];
            }
            
            if (!isset($options['exclude_selectors'])) {
                $options['exclude_selectors'] = [];
            }
        }
        
        return $options;
    }

    public static function updateOptions($options)
    {
        $old_options = self::getOptions();
        $new_options = wp_parse_args($options, $old_options);
        
        update_option('vazir_font_options', $new_options);
        
        // Clear cache
        self::clearCache();
        
        return $new_options;
    }

    public static function clearCache()
    {
        // Clear any caching mechanism that might be in place
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Trigger action for other caching plugins
        do_action('vazir_font_clear_cache');
    }
}

// Initialize
VazirFontPlugin::getInstance();