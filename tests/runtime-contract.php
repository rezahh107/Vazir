<?php
declare(strict_types=1);

$GLOBALS['vf_actions'] = [];
$GLOBALS['vf_filters'] = [];
$GLOBALS['vf_styles']  = [];
$GLOBALS['vf_inline']  = [];
$GLOBALS['vf_options'] = [];
$GLOBALS['vf_is_admin'] = false;

const ABSPATH = '/tmp/wp/';

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
vf_assert( ! isset( $GLOBALS['vf_actions']['cron_schedules'] ), 'no cron schedule action is registered' );
vf_assert( ! isset( $GLOBALS['vf_actions']['gform_post_render'] ), 'deprecated JavaScript event is not misregistered as a PHP action' );

$loader->enqueue_frontend_fonts();
vf_assert( isset( $GLOBALS['vf_styles']['vazir-font-frontend'] ) && $GLOBALS['vf_styles']['vazir-font-frontend']['enqueued'], 'frontend uses a registered/enqueued style handle' );
vf_assert( ! isset( $GLOBALS['vf_preloads'] ), 'no default preload path is emitted' );

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

fwrite( STDOUT, "ALL CONTRACT CHECKS PASSED\n" );
