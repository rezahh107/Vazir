<?php
class VazirFont_Loader
{
    private static $instance = null;
    private $fontWeights = [
        '300' => 'Light',
        '400' => 'Regular',
        '500' => 'Medium',
        '700' => 'Bold',
        '900' => 'Black'
    ];

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
        // Frontend
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendFonts'], 5);
        add_action('wp_head', [$this, 'addFontPreload'], 1);
        add_action('wp_head', [$this, 'addFrontendStyles'], 20);

        // Admin
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminFonts'], 5);
        add_action('admin_head', [$this, 'addFontPreload'], 1);
        add_action('admin_head', [$this, 'addAdminStyles'], 20);

        // Login
        add_action('login_enqueue_scripts', [$this, 'enqueueLoginFonts'], 5);
        add_action('login_head', [$this, 'addFontPreload'], 1);
        add_action('login_head', [$this, 'addLoginStyles'], 20);

        // Block Editor
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorFonts'], 5);
        
        // Cache clearing
        add_action('vazir_font_clear_cache', [$this, 'clearCache']);
    }

    public function enqueueFontFiles($context)
    {
        $options = VazirFontPlugin::getOptions();
        
        // Check if this context is enabled
        if (($context === 'frontend' && empty($options['enable_frontend'])) ||
            ($context === 'admin' && empty($options['enable_admin']))) {
            return;
        }

        $weights = $options['font_weights'] ?? ['400'];
        $version = VAZIR_FONT_VERSION . '.' . implode('', $weights);
        
        wp_enqueue_style(
            "vazir-font-{$context}",
            VAZIR_FONT_ASSETS_URL . 'css/vazir-fonts.css',
            [],
            $version
        );
        
        // Add inline styles for the selected weights
        $css = self::generateFontFaces($weights);
        wp_add_inline_style("vazir-font-{$context}", $css);
    }

    public function addFontPreload()
    {
        $options = VazirFontPlugin::getOptions();
        $weights = $options['font_weights'] ?? ['400'];
        
        // Preload most common weights first
        $preloadOrder = array_intersect(['400', '700', '500', '300', '900'], $weights);
        
        foreach ($preloadOrder as $weight) {
            $fontName = $weight === '400' ? 'vazir' : "vazir-{$weight}";
            echo sprintf(
                '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin="anonymous">' . "\n",
                esc_url(VAZIR_FONT_FONTS_URL . "{$fontName}.woff2")
            );
        }
    }

    private function outputCustomStyles($context)
    {
        $options = VazirFontPlugin::getOptions();
        $excludeSelectors = $options['exclude_selectors'] ?? [];
        
        ob_start();
        ?>
        <style id='vazir-font-<?php echo esc_attr($context); ?>-styles'>
        <?php
        switch ($context) {
            case 'frontend':
                echo $this->getFrontendCSS($excludeSelectors);
                break;
            case 'admin':
                echo $this->getAdminCSS($excludeSelectors);
                break;
            case 'login':
                echo $this->getLoginCSS($excludeSelectors);
                break;
        }
        ?>
        </style>
        <?php
        echo ob_get_clean();
    }

    public static function generateFontFaces($weights)
    {
        $css = '';
        $formats = [
            'woff2' => 'format("woff2")',
            'woff' => 'format("woff")',
            'ttf' => 'format("truetype")'
        ];
        
        foreach ($weights as $weight) {
            $fontName = $weight === '400' ? 'vazir' : "vazir-{$weight}";
            $src = [];
            
            foreach ($formats as $ext => $format) {
                $src[] = "url('" . VAZIR_FONT_FONTS_URL . "{$fontName}.{$ext}') {$format}";
            }
            
            $css .= "@font-face {\n";
            $css .= "    font-family: 'Vazir';\n";
            $css .= "    font-style: normal;\n";
            $css .= "    font-weight: {$weight};\n";
            $css .= "    font-display: swap;\n";
            $css .= "    src: " . implode(",\n         ", $src) . ";\n";
            $css .= "    unicode-range: U+0600-06FF, U+200C-200E, U+2010-2011, U+204F, U+2E41, U+FB50-FDFF, U+FE80-FEFC;\n";
            $css .= "}\n\n";
        }
        
        return $css;
    }
    
    public function clearCache()
    {
        // Clear any cached CSS files
        $this->regenerateFontFiles();
    }
    
    private function regenerateFontFiles()
    {
        // This would regenerate any cached CSS files
        // Implementation depends on your caching mechanism
    }
}