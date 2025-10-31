<?php
class VazirFont_Admin_Settings
{
    private static $instance = null;
    private $pageSlug = 'vazir-font-settings';

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
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'initSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_filter('plugin_action_links_' . plugin_basename(VAZIR_FONT_PLUGIN_FILE), [$this, 'addSettingsLink']);
    }

    public function addAdminMenu()
    {
        add_options_page(
            __('تنظیمات فونت وزیر', 'vazir-font-wp'),
            __('فونت وزیر', 'vazir-font-wp'),
            'manage_options',
            $this->pageSlug,
            [$this, 'renderSettingsPage']
        );
    }

    public function initSettings()
    {
        register_setting(
            'vazir_font_settings',
            'vazir_font_options',
            [$this, 'sanitizeOptions']
        );

        // General Section
        add_settings_section(
            'vazir_font_general',
            __('تنظیمات عمومی', 'vazir-font-wp'),
            [$this, 'renderGeneralSectionDesc'],
            $this->pageSlug
        );

        add_settings_field(
            'enable_frontend',
            __('فعال‌سازی در فرانت‌اند', 'vazir-font-wp'),
            [$this, 'renderCheckboxField'],
            $this->pageSlug,
            'vazir_font_general',
            [
                'name' => 'enable_frontend',
                'label' => __('فونت وزیر در تمام صفحات سایت اعمال شود', 'vazir-font-wp')
            ]
        );

        add_settings_field(
            'enable_admin',
            __('فعال‌سازی در پنل مدیریت', 'vazir-font-wp'),
            [$this, 'renderCheckboxField'],
            $this->pageSlug,
            'vazir_font_general',
            [
                'name' => 'enable_admin',
                'label' => __('فونت وزیر در پنل مدیریت وردپرس اعمال شود', 'vazir-font-wp')
            ]
        );

        add_settings_field(
            'enable_gravity_forms',
            __('فعال‌سازی در گرویتی فرمز', 'vazir-font-wp'),
            [$this, 'renderCheckboxField'],
            $this->pageSlug,
            'vazir_font_general',
            [
                'name' => 'enable_gravity_forms',
                'label' => __('فونت وزیر در فرم‌های گرویتی فرمز اعمال شود', 'vazir-font-wp')
            ]
        );

        // Font Weights Section
        add_settings_section(
            'vazir_font_weights',
            __('وزن‌های فونت', 'vazir-font-wp'),
            [$this, 'renderWeightsSectionDesc'],
            $this->pageSlug
        );

        add_settings_field(
            'font_weights',
            __('وزن‌های مورد استفاده', 'vazir-font-wp'),
            [$this, 'renderWeightsField'],
            $this->pageSlug,
            'vazir_font_weights'
        );

        // Advanced Section
        add_settings_section(
            'vazir_font_advanced',
            __('تنظیمات پیشرفته', 'vazir-font-wp'),
            [$this, 'renderAdvancedSectionDesc'],
            $this->pageSlug
        );

        add_settings_field(
            'exclude_selectors',
            __('استثناء انتخابگرها', 'vazir-font-wp'),
            [$this, 'renderTextareaField'],
            $this->pageSlug,
            'vazir_font_advanced',
            [
                'name' => 'exclude_selectors',
                'description' => __('انتخابگرهای CSS که نباید فونت وزیر روی آن‌ها اعمال شود (هر کدام در خط جداگانه)', 'vazir-font-wp')
            ]
        );
    }

    public function renderSettingsPage()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('شما دسترسی لازم برای مشاهده این صفحه را ندارید.', 'vazir-font-wp'));
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="vazir-font-admin-header">
                <p><?php _e('این افزونه فونت وزیر را به تمام بخش‌های وردپرس شما اضافه می‌کند.', 'vazir-font-wp'); ?></p>
            </div>

            <?php settings_errors(); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('vazir_font_settings');
                do_settings_sections($this->pageSlug);
                submit_button(__('ذخیره تنظیمات', 'vazir-font-wp'));
                ?>
            </form>

            <div class="vazir-font-preview">
                <h3><?php _e('پیش‌نمایش فونت', 'vazir-font-wp'); ?></h3>
                <div class="font-preview-text">
                    <?php
                    $weights = [
                        '300' => __('300 (Light)', 'vazir-font-wp'),
                        '400' => __('400 (Regular)', 'vazir-font-wp'),
                        '500' => __('500 (Medium)', 'vazir-font-wp'),
                        '700' => __('700 (Bold)', 'vazir-font-wp'),
                        '900' => __('900 (Black)', 'vazir-font-wp')
                    ];
                    
                    foreach ($weights as $weight => $label) {
                        echo sprintf(
                            '<p style="font-family: \'Vazir\', sans-serif; font-size: 16px; font-weight: %s;">%s</p>',
                            esc_attr($weight),
                            esc_html($label)
                        );
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function sanitizeOptions($input)
    {
        $sanitized = [];
        $current_options = VazirFontPlugin::getOptions();

        // Sanitize checkboxes
        $checkboxes = ['enable_frontend', 'enable_admin', 'enable_gravity_forms'];
        foreach ($checkboxes as $checkbox) {
            $sanitized[$checkbox] = isset($input[$checkbox]) ? (bool) $input[$checkbox] : false;
        }

        // Sanitize font weights
        if (isset($input['font_weights']) && is_array($input['font_weights'])) {
            $allowedWeights = ['300', '400', '500', '700', '900'];
            $sanitized['font_weights'] = array_intersect($input['font_weights'], $allowedWeights);
            
            if (empty($sanitized['font_weights'])) {
                $sanitized['font_weights'] = ['400'];
                add_settings_error(
                    'vazir_font_options',
                    'no_weights_selected',
                    __('حداقل یک وزن فونت باید انتخاب شود. وزن 400 به صورت پیش‌فرض انتخاب شد.', 'vazir-font-wp'),
                    'warning'
                );
            }
        } else {
            $sanitized['font_weights'] = $current_options['font_weights'] ?? ['400'];
        }

        // Sanitize exclude selectors
        if (isset($input['exclude_selectors'])) {
            $selectors = explode("\n", $input['exclude_selectors']);
            $selectors = array_map('sanitize_text_field', $selectors);
            $selectors = array_filter($selectors);
            $sanitized['exclude_selectors'] = array_unique($selectors);
        } else {
            $sanitized['exclude_selectors'] = $current_options['exclude_selectors'] ?? [];
        }

        // Clear cache when options change
        if ($sanitized != $current_options) {
            VazirFontPlugin::clearCache();
        }

        return $sanitized;
    }
}