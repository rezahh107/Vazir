<?php
declare(strict_types=1);

$GLOBALS['vf_actions'] = [];
$GLOBALS['vf_filters'] = [];
$GLOBALS['vf_styles']  = [];
$GLOBALS['vf_inline']  = [];
$GLOBALS['vf_options'] = [];
$GLOBALS['vf_is_admin'] = false;
$GLOBALS['vf_transients'] = [];
$GLOBALS['vf_settings_errors'] = [];

const ABSPATH = '/tmp/wp/';
const MINUTE_IN_SECONDS = 60;

function plugin_dir_url( $file ) { return 'https://example.test/wp-content/plugins/vazir/'; }
function register_activation_hook( $file, $callback ) {}
function register_deactivation_hook( $file, $callback ) {}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['vf_actions'][$hook][] = [ $callback, $priority, $accepted_args ]; }
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['vf_filters'][$hook][] = [ $callback, $priority, $accepted_args ]; }
function load_plugin_textdomain( ...$args ) { return true; }
function plugin_basename( $file ) { return basename( $file ); }
function get_option( $name, $default = false ) { return $GLOBALS['vf_options'][$name] ?? $default; }
function update_option( $name, $value ) { $GLOBALS['vf_options'][$name] = $value; return true; }
function is_admin() { return (bool) $GLOBALS['vf_is_admin']; }
function wp_clear_scheduled_hook( $hook ) { $GLOBALS['vf_cleared_cron'][] = $hook; return 1; }
function apply_filters( $hook, $value ) { return $value; }
function esc_url( $url ) { return $url; }
function wp_register_style( $handle, $src = false, $deps = [], $ver = false ) { $GLOBALS['vf_styles'][$handle] = [ 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'enqueued' => false ]; return true; }
function wp_enqueue_style( $handle ) { if ( ! isset( $GLOBALS['vf_styles'][$handle] ) ) { $GLOBALS['vf_styles'][$handle] = [ 'src' => false, 'deps' => [], 'ver' => false, 'enqueued' => true ]; } else { $GLOBALS['vf_styles'][$handle]['enqueued'] = true; } }
function wp_add_inline_style( $handle, $css ) { $GLOBALS['vf_inline'][$handle][] = $css; return true; }
function is_rtl() { return true; }
function get_current_screen() { return new WP_Screen( 'toplevel_page_gf_edit_forms' ); }
function current_user_can( $capability ) { return 'manage_options' === $capability; }
function wp_verify_nonce( $nonce, $action ) { return 'test-nonce' === $nonce && 'vazir_font_settings-options' === $action; }
function get_current_user_id() { return 1; }
function get_transient( $key ) { return $GLOBALS['vf_transients'][$key] ?? false; }
function set_transient( $key, $value, $expiration ) { $GLOBALS['vf_transients'][$key] = $value; return true; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function add_settings_error( $setting, $code, $message, $type = 'error' ) { $GLOBALS['vf_settings_errors'][] = [ $setting, $code, $message, $type ]; }
function esc_html__( $text, $domain = 'default' ) { return $text; }

class WP_Screen {
	public string $id;
	public function __construct( string $id ) { $this->id = $id; }
}
class GFCommon { public static function get_version() { return '3.1.0.3'; } }
class GFForms { public static string $version = '3.1.0.3'; public static function is_gravity_page() { return true; } }

function vf_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	fwrite( STDOUT, "PASS: {$message}\n" );
}

require dirname( __DIR__ ) . '/vazir-font-wp.php';
VazirFontPlugin::get_instance()->init();

$loader = VazirFont_Loader::get_instance();
$weights = $loader->get_selected_weights();
vf_assert( [ '300', '400', '500', '700', '900' ] === $weights, 'default weight selection is preserved' );

$font_css = $loader->get_font_face_css();
vf_assert( 5 === substr_count( $font_css, '@font-face' ), 'one @font-face is generated per selected packaged weight' );
vf_assert( false === strpos( $font_css, "format('woff')" ), 'no WOFF fallback is advertised' );
vf_assert( false === strpos( $font_css, "format('truetype')" ), 'no TTF fallback is advertised' );
foreach ( $weights as $weight ) {
	vf_assert( false !== strpos( $font_css, "vazir-{$weight}.woff2" ), "WOFF2 URL exists in CSS for weight {$weight}" );
}

vf_assert( isset( $GLOBALS['vf_actions']['enqueue_block_assets'] ), 'editor content uses enqueue_block_assets' );
vf_assert( ! isset( $GLOBALS['vf_actions']['enqueue_block_editor_assets'] ), 'editor content no longer relies on enqueue_block_editor_assets' );
vf_assert( isset( $GLOBALS['vf_actions']['init'] ), 'translation loading is registered on init' );
vf_assert( ! isset( $GLOBALS['vf_actions']['cron_schedules'] ), 'no cron schedule action is registered' );
vf_assert( ! isset( $GLOBALS['vf_actions']['gform_post_render'] ), 'deprecated JavaScript event is not misregistered as a PHP action' );

VazirFontPlugin::update_options(
	[
		'exclude_selectors' => [ '.vf-excluded-component, [data-icon]:before', '.dashicons' ],
	]
);

$loader->enqueue_frontend_fonts();
vf_assert( isset( $GLOBALS['vf_styles']['vazir-font-frontend'] ) && $GLOBALS['vf_styles']['vazir-font-frontend']['enqueued'], 'frontend uses a registered/enqueued style handle' );
vf_assert( ! isset( $GLOBALS['vf_preloads'] ), 'no default preload path is emitted' );

$frontend_css = implode( "\n", $GLOBALS['vf_inline']['vazir-font-frontend'] ?? [] );
vf_assert( false !== strpos( $frontend_css, ':not(:where(.vf-excluded-component, .dashicons))' ), 'frontend Vazir rules preserve element selectors from mixed lists and exclude configured roots by predicate' );
vf_assert( false !== strpos( $frontend_css, ':not(:where(.vf-excluded-component, .dashicons) *)' ), 'frontend Vazir rules exclude descendants below configured roots' );
vf_assert( false === strpos( $frontend_css, ".vazir-font-enabled .vf-excluded-component {\n\tfont-family: inherit;" ), 'frontend exclusions do not emit competing font-family reset rules' );
vf_assert( false !== strpos( $frontend_css, 'font-family: inherit !important;' ), 'narrow frontend theme override remains present for non-excluded text' );
vf_assert( false === strpos( $frontend_css, '[data-icon]:before):not(' ), 'pseudo-element exclusions are not forced into relational negative guards' );

$GLOBALS['vf_is_admin'] = true;
$loader->enqueue_editor_content_fonts();
$editor_css = implode( "\n", $GLOBALS['vf_inline']['vazir-font-editor-content'] ?? [] );
vf_assert( false !== strpos( $editor_css, ':not(:where(.vf-excluded-component, .dashicons))' ), 'editor Vazir rules use the same negative exclusion boundary' );
vf_assert( false === strpos( $editor_css, ".editor-styles-wrapper .vf-excluded-component {\n\tfont-family: inherit;" ), 'editor exclusions do not emit a second competing reset mechanism' );
$GLOBALS['vf_is_admin'] = false;

$gf = VazirFont_GravityForms_Integration::get_instance();
$styles = $gf->filter_preview_styles( [ 'gravity-forms-orbital-theme' ], [] );
vf_assert( in_array( 'vazir-font-gravity-forms', $styles, true ), 'gform_preview_styles returns a WordPress style handle' );

$noconflict = $gf->add_noconflict_styles( [] );
vf_assert( in_array( 'vazir-font-gravity-forms', $noconflict, true ), 'gform_noconflict_styles allowlists the same style handle' );

$gf->enqueue_gravityforms_assets( [], true );
vf_assert( ! empty( $GLOBALS['vf_styles']['vazir-font-gravity-forms']['enqueued'] ), 'Gravity Forms frontend enqueue path runs without private-method fatal' );
$gf_css = implode( "\n", $GLOBALS['vf_inline']['vazir-font-gravity-forms'] ?? [] );
vf_assert( false !== strpos( $gf_css, '--gf-font-family-base' ), 'current Gravity Forms base font CSS API property is emitted' );
vf_assert( false !== strpos( $gf_css, '.gform_wrapper' ), 'legacy/current wrapper compatibility CSS is retained' );

$field_html = '<div style="color:red;font-family:Arial;font-size:14px">X</div>';
$filtered = $gf->remove_inline_font_styles( $field_html, null, null, 0, 1 );
vf_assert( false === stripos( $filtered, 'font-family' ) && false !== stripos( $filtered, 'color:red' ), 'field-content compatibility removes only inline font-family' );

vf_assert( isset( $GLOBALS['vf_filters']['gform_field_content'] ), 'field-content compatibility hook is retained pending browser characterization' );
vf_assert( isset( $GLOBALS['vf_filters']['gform_field_css_class'] ), 'field-class compatibility hook is retained pending browser characterization' );

$_POST['_wpnonce'] = 'test-nonce';
$settings = VazirFont_Admin_Settings::get_instance();
$sanitized = $settings->sanitize_options(
	[
		'enable_frontend'      => '1',
		'enable_gravity_forms' => 'true',
		'font_weights'         => [ '700', '999', '300' ],
		'exclude_selectors'    => ".custom-control\n.bad{color:red;}\n",
	]
);
vf_assert( true === $sanitized['enable_frontend'], 'settings sanitizer accepts enabled frontend boolean' );
vf_assert( false === $sanitized['enable_admin'], 'settings sanitizer treats an omitted checkbox as disabled' );
vf_assert( true === $sanitized['enable_gravity_forms'], 'settings sanitizer accepts enabled Gravity Forms boolean' );
vf_assert( [ '700', '300' ] === $sanitized['font_weights'], 'settings sanitizer rejects unsupported font weights' );
vf_assert( in_array( '.custom-control', $sanitized['exclude_selectors'], true ), 'settings sanitizer preserves a supported safe selector' );
vf_assert( count( $sanitized['exclude_selectors'] ) <= 50, 'settings sanitizer bounds selector count' );

fwrite( STDOUT, "ALL CONTRACT CHECKS PASSED\n" );