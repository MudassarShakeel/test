<?php
/**
 * Smart conditional logic: groups of rules (groups are OR, rules inside a group are AND).
 *
 * @package MudassarSnippetStudio
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rule definitions, sanitising and evaluation.
 */
class MSST_Conditions {

	/**
	 * Rule catalogue.
	 *
	 * @return array type => [ label, group, ops[], values[]|null, query(bool) ]
	 */
	public static function catalogue() {
		$is  = array(
			'is'     => __( 'is', 'mudassar-snippet-studio' ),
			'is_not' => __( 'is not', 'mudassar-snippet-studio' ),
		);
		$has = array(
			'contains'     => __( 'contains', 'mudassar-snippet-studio' ),
			'not_contains' => __( 'does not contain', 'mudassar-snippet-studio' ),
		);
		$num = array(
			'gt' => __( 'is greater than', 'mudassar-snippet-studio' ),
			'lt' => __( 'is less than', 'mudassar-snippet-studio' ),
		);

		$roles = array();
		foreach ( wp_roles()->roles as $slug => $role ) {
			$roles[ $slug ] = translate_user_role( $role['name'] );
		}
		$post_types = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $slug => $obj ) {
			$post_types[ $slug ] = $obj->labels->singular_name;
		}

		return array(
			'page_type'        => array(
				'label'  => __( 'Page type', 'mudassar-snippet-studio' ),
				'group'  => 'page',
				'ops'    => $is,
				'values' => array(
					'front_page' => __( 'Front page', 'mudassar-snippet-studio' ),
					'blog'       => __( 'Blog index', 'mudassar-snippet-studio' ),
					'single'     => __( 'Single post', 'mudassar-snippet-studio' ),
					'page'       => __( 'Page', 'mudassar-snippet-studio' ),
					'archive'    => __( 'Archive', 'mudassar-snippet-studio' ),
					'search'     => __( 'Search results', 'mudassar-snippet-studio' ),
					'404'        => __( '404 page', 'mudassar-snippet-studio' ),
				),
				'query'  => true,
			),
			'post_type'        => array(
				'label'  => __( 'Post type', 'mudassar-snippet-studio' ),
				'group'  => 'page',
				'ops'    => $is,
				'values' => $post_types,
				'query'  => true,
			),
			'url'              => array(
				'label'  => __( 'URL path', 'mudassar-snippet-studio' ),
				'group'  => 'page',
				'ops'    => $has + array( 'equals' => __( 'equals', 'mudassar-snippet-studio' ) ),
				'values' => null,
				'query'  => false,
			),
			'logged_in'        => array(
				'label'  => __( 'Logged in', 'mudassar-snippet-studio' ),
				'group'  => 'user',
				'ops'    => $is,
				'values' => array(
					'yes' => __( 'Yes', 'mudassar-snippet-studio' ),
					'no'  => __( 'No', 'mudassar-snippet-studio' ),
				),
				'query'  => false,
			),
			'role'             => array(
				'label'  => __( 'User role', 'mudassar-snippet-studio' ),
				'group'  => 'user',
				'ops'    => $is,
				'values' => $roles,
				'query'  => false,
			),
			'device'           => array(
				'label'  => __( 'Device', 'mudassar-snippet-studio' ),
				'group'  => 'user',
				'ops'    => $is,
				'values' => array(
					'mobile'  => __( 'Mobile', 'mudassar-snippet-studio' ),
					'desktop' => __( 'Desktop', 'mudassar-snippet-studio' ),
				),
				'query'  => false,
			),
			'referrer'         => array(
				'label'  => __( 'Referrer', 'mudassar-snippet-studio' ),
				'group'  => 'user',
				'ops'    => $has,
				'values' => null,
				'query'  => false,
			),
			'cookie'           => array(
				'label'  => __( 'Cookie name', 'mudassar-snippet-studio' ),
				'group'  => 'user',
				'ops'    => array(
					'exists'     => __( 'exists', 'mudassar-snippet-studio' ),
					'not_exists' => __( 'does not exist', 'mudassar-snippet-studio' ),
				),
				'values' => null,
				'query'  => false,
			),
			'date'             => array(
				'label'  => __( 'Date (YYYY-MM-DD)', 'mudassar-snippet-studio' ),
				'group'  => 'time',
				'ops'    => array(
					'after'  => __( 'is after', 'mudassar-snippet-studio' ),
					'before' => __( 'is before', 'mudassar-snippet-studio' ),
				),
				'values' => null,
				'query'  => false,
			),
			'wc_cart_total'    => array(
				'label'  => __( 'WooCommerce: cart total', 'mudassar-snippet-studio' ),
				'group'  => 'shop',
				'ops'    => $num,
				'values' => null,
				'query'  => false,
			),
			'wc_cart_product'  => array(
				'label'  => __( 'WooCommerce: product ID in cart', 'mudassar-snippet-studio' ),
				'group'  => 'shop',
				'ops'    => $is,
				'values' => null,
				'query'  => false,
			),
			'edd_cart_total'   => array(
				'label'  => __( 'EDD: cart total', 'mudassar-snippet-studio' ),
				'group'  => 'shop',
				'ops'    => $num,
				'values' => null,
				'query'  => false,
			),
			'edd_cart_product' => array(
				'label'  => __( 'EDD: download ID in cart', 'mudassar-snippet-studio' ),
				'group'  => 'shop',
				'ops'    => $is,
				'values' => null,
				'query'  => false,
			),
		);
	}

	/**
	 * Sanitise a stored or submitted rule structure against the catalogue.
	 *
	 * @param mixed $groups Groups of rules.
	 * @return array
	 */
	public static function clean( $groups ) {
		if ( ! is_array( $groups ) ) {
			return array();
		}
		$catalogue = self::catalogue();
		$clean     = array();
		foreach ( array_slice( $groups, 0, 10 ) as $rules ) {
			if ( ! is_array( $rules ) ) {
				continue;
			}
			$group = array();
			foreach ( array_slice( $rules, 0, 20 ) as $rule ) {
				$type = isset( $rule['type'] ) ? sanitize_key( $rule['type'] ) : '';
				if ( ! isset( $catalogue[ $type ] ) ) {
					continue;
				}
				$def = $catalogue[ $type ];
				$op  = isset( $rule['op'] ) ? sanitize_key( $rule['op'] ) : '';
				if ( ! isset( $def['ops'][ $op ] ) ) {
					continue;
				}
				$value = isset( $rule['value'] ) && is_scalar( $rule['value'] ) ? sanitize_text_field( (string) $rule['value'] ) : '';
				if ( is_array( $def['values'] ) && ! isset( $def['values'][ $value ] ) ) {
					continue;
				}
				$value = mb_substr( $value, 0, 200 );
				if ( '' === $value ) {
					continue;
				}
				$group[] = array(
					'type'  => $type,
					'op'    => $op,
					'value' => $value,
				);
			}
			if ( $group ) {
				$clean[] = $group;
			}
		}
		return $clean;
	}

	/**
	 * Does any rule need the main query to be ready?
	 *
	 * @param array $groups Groups.
	 * @return bool
	 */
	public static function needs_query( array $groups ) {
		$catalogue = self::catalogue();
		foreach ( $groups as $rules ) {
			foreach ( $rules as $rule ) {
				if ( ! empty( $catalogue[ $rule['type'] ]['query'] ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Evaluate: no groups means "always".
	 *
	 * @param array $groups Groups (already clean).
	 * @return bool
	 */
	public static function passes( array $groups ) {
		if ( ! $groups ) {
			return true;
		}
		foreach ( $groups as $rules ) {
			$all = true;
			foreach ( $rules as $rule ) {
				if ( ! self::check( $rule ) ) {
					$all = false;
					break;
				}
			}
			if ( $all ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Evaluate a single rule.
	 *
	 * @param array $rule Rule.
	 * @return bool
	 */
	private static function check( array $rule ) {
		$value = $rule['value'];
		switch ( $rule['type'] ) {
			case 'page_type':
				$map = array(
					'front_page' => is_front_page(),
					'blog'       => is_home(),
					'single'     => is_singular() && ! is_page(),
					'page'       => is_page(),
					'archive'    => is_archive(),
					'search'     => is_search(),
					'404'        => is_404(),
				);
				return self::is_op( $rule['op'], ! empty( $map[ $value ] ) );
			case 'post_type':
				return self::is_op( $rule['op'], is_singular( $value ) );
			case 'url':
				$path = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
				$path = (string) wp_parse_url( $path, PHP_URL_PATH );
				return self::text_op( $rule['op'], $path, $value );
			case 'logged_in':
				return self::is_op( $rule['op'], is_user_logged_in() === ( 'yes' === $value ) );
			case 'role':
				$user = wp_get_current_user();
				return self::is_op( $rule['op'], $user->exists() && in_array( $value, (array) $user->roles, true ) );
			case 'device':
				return self::is_op( $rule['op'], wp_is_mobile() === ( 'mobile' === $value ) );
			case 'referrer':
				$ref = wp_get_raw_referer();
				return self::text_op( $rule['op'], $ref ? $ref : '', $value );
			case 'cookie':
				$exists = isset( $_COOKIE[ $value ] );
				return 'exists' === $rule['op'] ? $exists : ! $exists;
			case 'date':
				$ts = strtotime( $value . ' 00:00:00 UTC' );
				if ( false === $ts ) {
					return false;
				}
				return 'after' === $rule['op'] ? time() > $ts : time() < $ts;
			case 'wc_cart_total':
				if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
					return false;
				}
				return self::num_op( $rule['op'], (float) WC()->cart->get_cart_contents_total(), (float) $value );
			case 'wc_cart_product':
				if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
					return false;
				}
				$found = false;
				foreach ( WC()->cart->get_cart() as $item ) {
					if ( (int) $item['product_id'] === (int) $value ) {
						$found = true;
						break;
					}
				}
				return self::is_op( $rule['op'], $found );
			case 'edd_cart_total':
				if ( ! function_exists( 'edd_get_cart_total' ) ) {
					return false;
				}
				return self::num_op( $rule['op'], (float) edd_get_cart_total(), (float) $value );
			case 'edd_cart_product':
				if ( ! function_exists( 'edd_item_in_cart' ) ) {
					return false;
				}
				return self::is_op( $rule['op'], (bool) edd_item_in_cart( (int) $value ) );
		}
		return false;
	}

	/**
	 * Apply is / is_not.
	 *
	 * @param string $op    Operator.
	 * @param bool   $matches Whether the subject matches.
	 * @return bool
	 */
	private static function is_op( $op, $matches ) {
		return 'is_not' === $op ? ! $matches : (bool) $matches;
	}

	/**
	 * Text operators (case-insensitive).
	 *
	 * @param string $op      Operator.
	 * @param string $subject Subject.
	 * @param string $needle  Value.
	 * @return bool
	 */
	private static function text_op( $op, $subject, $needle ) {
		$subject = strtolower( $subject );
		$needle  = strtolower( $needle );
		switch ( $op ) {
			case 'contains':
				return false !== strpos( $subject, $needle );
			case 'not_contains':
				return false === strpos( $subject, $needle );
			case 'equals':
				return untrailingslashit( $subject ) === untrailingslashit( $needle );
		}
		return false;
	}

	/**
	 * Numeric operators.
	 *
	 * @param string $op Operator.
	 * @param float  $a  Left.
	 * @param float  $b  Right.
	 * @return bool
	 */
	private static function num_op( $op, $a, $b ) {
		return 'gt' === $op ? $a > $b : $a < $b;
	}
}
