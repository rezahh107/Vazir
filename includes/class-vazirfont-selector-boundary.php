<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Internal selector-boundary utilities shared by inheritable Gravity adapters.
 */
final class VazirFont_Selector_Boundary {
	private function __construct() {}

	/**
	 * Convert validated document-context exclusions into selectors that are safe
	 * inside a target-relative :has() descendant-containment guard.
	 *
	 * Local selectors are already relative-safe. An ancestor-qualified selector
	 * is relativized only when its first top-level relationship is a descendant
	 * combinator from the adapter's guaranteed host scope. Other top-level
	 * complex relationships remain document-context-dependent and fail closed.
	 *
	 * @param string[] $exclude_selectors Valid element-level exclusions.
	 * @return string[]|null
	 */
	public static function for_descendant_containment(
		string $target_selector,
		array $exclude_selectors,
		string $scope_selector,
		string $target_scope_prefix
	): ?array {
		$relative = array();
		foreach ( $exclude_selectors as $exclude_selector ) {
			if ( false !== stripos( $exclude_selector, ':has(' ) ) {
				return null;
			}

			$candidate = self::relative_descendant_selector(
				$target_selector,
				$exclude_selector,
				$scope_selector,
				$target_scope_prefix
			);
			if ( null === $candidate ) {
				return null;
			}
			$relative[] = $candidate;
		}
		return $relative;
	}

	private static function relative_descendant_selector(
		string $target_selector,
		string $exclude_selector,
		string $scope_selector,
		string $target_scope_prefix
	): ?string {
		$combinator = self::first_top_level_combinator( $exclude_selector );
		if ( null === $combinator ) {
			return $exclude_selector;
		}

		if ( 'descendant' !== $combinator['type'] ) {
			return null;
		}

		$prefix = trim( substr( $exclude_selector, 0, $combinator['index'] ) );
		if ( $scope_selector !== $prefix || 0 !== strpos( $target_selector, $target_scope_prefix ) ) {
			return null;
		}

		$relative = trim( substr( $exclude_selector, $combinator['index'] + $combinator['length'] ) );
		return '' === $relative ? null : $relative;
	}

	/**
	 * Locate the first genuinely top-level selector combinator. Quoted strings,
	 * attribute selectors, and functional pseudo-class arguments are opaque so
	 * combinator-looking content inside them cannot affect classification.
	 *
	 * @return array{type:string,index:int,length:int}|null
	 */
	private static function first_top_level_combinator( string $selector ): ?array {
		$quote         = '';
		$bracket_depth = 0;
		$paren_depth   = 0;
		$length        = strlen( $selector );

		for ( $index = 0; $index < $length; $index++ ) {
			$char = $selector[ $index ];
			if ( '' !== $quote ) {
				if ( $char === $quote ) {
					$quote = '';
				}
				continue;
			}
			if ( '"' === $char || "'" === $char ) {
				$quote = $char;
				continue;
			}
			if ( '[' === $char ) {
				$bracket_depth++;
				continue;
			}
			if ( ']' === $char && $bracket_depth > 0 ) {
				$bracket_depth--;
				continue;
			}
			if ( 0 !== $bracket_depth ) {
				continue;
			}
			if ( '(' === $char ) {
				$paren_depth++;
				continue;
			}
			if ( ')' === $char && $paren_depth > 0 ) {
				$paren_depth--;
				continue;
			}
			if ( 0 !== $paren_depth ) {
				continue;
			}

			if ( '>' === $char || '+' === $char || '~' === $char ) {
				$type = '>' === $char ? 'child' : ( '+' === $char ? 'adjacent' : 'sibling' );
				return array( 'type' => $type, 'index' => $index, 'length' => 1 );
			}

			if ( ctype_space( $char ) ) {
				$start = $index;
				while ( $index + 1 < $length && ctype_space( $selector[ $index + 1 ] ) ) {
					$index++;
				}
				$next = $index + 1 < $length ? $selector[ $index + 1 ] : '';
				if ( '>' === $next || '+' === $next || '~' === $next ) {
					$type = '>' === $next ? 'child' : ( '+' === $next ? 'adjacent' : 'sibling' );
					return array( 'type' => $type, 'index' => $index + 1, 'length' => 1 );
				}
				return array( 'type' => 'descendant', 'index' => $start, 'length' => $index - $start + 1 );
			}
		}

		return null;
	}
}
