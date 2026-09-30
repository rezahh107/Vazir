<?php
/**
 * Plugin Name: Vazir GravityView Classic Editor Evidence Fixture
 * Description: Test-only classic-editor post type used by the licensed GravityView evidence lab.
 */
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

add_action(
	'init',
	static function (): void {
		$is_cli = defined( 'WP_CLI' ) && WP_CLI;
		$is_evidence_request = false;

		if ( ! $is_cli && is_admin() ) {
			$manifest = get_option( 'vazir_view_evidence_fixture_manifest' );
			$evidence_post_id = is_array( $manifest ) ? (int) ( $manifest['oembed_editor_post_id'] ?? 0 ) : 0;
			$request_post_id = 0;
			if ( isset( $_REQUEST['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only test fixture routing.
				$request_post_id = absint( wp_unslash( $_REQUEST['post'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} elseif ( isset( $_REQUEST['post_ID'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only test fixture routing.
				$request_post_id = absint( wp_unslash( $_REQUEST['post_ID'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
			$is_evidence_request = $evidence_post_id > 0 && $request_post_id === $evidence_post_id;
		}

		if ( ! $is_cli && ! $is_evidence_request ) {
			return;
		}

		register_post_type(
			'vazir_gv_oembed',
			array(
				'label'        => 'Vazir GravityView oEmbed Evidence',
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'show_in_rest' => false,
				'supports'     => array( 'title', 'editor' ),
			)
		);
	}
);