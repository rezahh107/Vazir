<?php
/** Deterministic Gravity Flow profile fixtures using the installed product APIs. */
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
$artifact_dir = getenv( 'VAZIR_LAB_ARTIFACT_DIR' );
if ( ! is_string( $artifact_dir ) || '' === $artifact_dir ) { throw new RuntimeException( 'VAZIR_LAB_ARTIFACT_DIR is required.' ); }
wp_mkdir_p( $artifact_dir );
if ( ! class_exists( 'GFAPI' ) || ! class_exists( 'GFCommon' ) || ! class_exists( 'Gravity_Flow_API' ) ) { throw new RuntimeException( 'Gravity Forms / Gravity Flow runtime APIs are unavailable.' ); }
if ( '3.1.1.1' !== (string) GFCommon::get_version() || ! defined( 'GRAVITY_FLOW_VERSION' ) || '3.1.0' !== GRAVITY_FLOW_VERSION ) { throw new RuntimeException( 'Gravity Flow profile requires Gravity Forms 3.1.1.1 and Gravity Flow 3.1.0.' ); }
$existing = get_option( 'vazir_flow_evidence_fixture_manifest' );
if ( is_array( $existing ) && ! empty( $existing['form_id'] ) && ! empty( $existing['entry_id'] ) ) { file_put_contents( $artifact_dir . '/gravityflow-fixture.json', wp_json_encode( $existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" ); return; }
$operator = get_user_by( 'login', getenv( 'VAZIR_LAB_ADMIN_USER' ) ?: 'vazir_lab_admin' );
if ( ! $operator instanceof WP_User ) { throw new RuntimeException( 'Synthetic Gravity Flow operator is unavailable.' ); }
$options = VazirFontPlugin::get_options();
$exclude = isset( $options['exclude_selectors'] ) && is_array( $options['exclude_selectors'] ) ? $options['exclude_selectors'] : array();
if ( ! in_array( '.vf-flow-excluded', $exclude, true ) ) { $exclude[] = '.vf-flow-excluded'; }
VazirFontPlugin::update_options( array( 'enable_frontend' => true, 'enable_admin' => true, 'enable_gravity_forms' => true, 'exclude_selectors' => $exclude ) );
$form_id = GFAPI::add_form( array( 'title' => 'Vazir Gravity Flow Evidence', 'description' => 'Synthetic workflow fixture for typography characterization.', 'labelPlacement' => 'top_label', 'markupVersion' => 2, 'fields' => array( array( 'id' => 1, 'label' => 'نام درخواست', 'type' => 'text', 'isRequired' => true ), array( 'id' => 2, 'label' => 'وضعیت نمونه', 'type' => 'select', 'choices' => array( array( 'text' => 'در انتظار بررسی', 'value' => 'pending' ) ) ) ), 'button' => array( 'type' => 'text', 'text' => 'ارسال' ) ) );
if ( is_wp_error( $form_id ) ) { throw new RuntimeException( $form_id->get_error_message() ); }
$form_id = (int) $form_id;
$api = new Gravity_Flow_API( $form_id );
$step_id = $api->add_step( array( 'step_name' => 'Vazir Evidence Review', 'step_type' => 'approval', 'description' => 'Synthetic approval step.', 'type' => 'select', 'assignees' => array( 'user_id|' . (int) $operator->ID ), 'assignee_policy' => 'all', 'instructions' => 'Synthetic evidence only.' ) );
if ( ! $step_id || is_wp_error( $step_id ) ) { throw new RuntimeException( 'Unable to create Gravity Flow approval step.' ); }
$entry_id = GFAPI::add_entry( array( 'form_id' => $form_id, 'created_by' => (int) $operator->ID, '1' => 'درخواست آزمایشی Flow', '2' => 'pending' ) );
if ( is_wp_error( $entry_id ) ) { throw new RuntimeException( $entry_id->get_error_message() ); }
$entry_id = (int) $entry_id; $api->process_workflow( $entry_id ); $current = $api->get_current_step( GFAPI::get_entry( $entry_id ) );
if ( ! $current ) { throw new RuntimeException( 'Synthetic entry did not reach the approval step.' ); }
$form_page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Vazir Gravity Flow Form Evidence', 'post_name' => 'vazir-gravity-flow-form-evidence', 'post_content' => sprintf( '[gravityform id="%d" title="true" description="true" ajax="true" theme="orbital"]', $form_id ) ), true );
if ( is_wp_error( $form_page_id ) ) { throw new RuntimeException( $form_page_id->get_error_message() ); }
$page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Vazir Gravity Flow Evidence', 'post_name' => 'vazir-gravity-flow-evidence', 'post_content' => '<div class="vf-flow-excluded" id="vf-flow-excluded" style="font-family: monospace;">Flow excluded typography</div>' . sprintf( '[gravityflow page="inbox" form_id="%d"]', $form_id ) ), true );
if ( is_wp_error( $page_id ) ) { throw new RuntimeException( $page_id->get_error_message() ); }
$manifest = array( 'form_id' => $form_id, 'entry_id' => $entry_id, 'step_id' => (int) $current->get_id(), 'step_name' => (string) $current->get_name(), 'operator_id' => (int) $operator->ID, 'frontend_inbox_url' => get_permalink( (int) $page_id ), 'gravity_forms_url' => get_permalink( (int) $form_page_id ), 'admin_inbox_url' => admin_url( 'admin.php?page=gravityflow-inbox' ), 'admin_entry_url' => admin_url( 'admin.php?page=gravityflow-inbox&view=entry&id=' . $form_id . '&lid=' . $entry_id ), 'exclude_selector' => '.vf-flow-excluded', 'gravityflow_version' => GRAVITY_FLOW_VERSION );
update_option( 'vazir_flow_evidence_fixture_manifest', $manifest, false );
file_put_contents( $artifact_dir . '/gravityflow-fixture.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
