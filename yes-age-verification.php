<?php
/**
 * Plugin Name:       YES Age Verification
 * Plugin URI:        https://github.com/yes-agency/yes-age-verification
 * Description:       Lightweight, configurable age verification popup for websites selling age-restricted products.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            YES Internet
 * Author URI:        https://yesinternet.gr
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       yes-age-verification
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'YES_AGE_VERIFICATION_VERSION', '1.0.0' );
define( 'YES_AGE_VERIFICATION_FILE', __FILE__ );
define( 'YES_AGE_VERIFICATION_PATH', plugin_dir_path( __FILE__ ) );
define( 'YES_AGE_VERIFICATION_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class — singleton.
 *
 * -------------------------------------------------------------------------
 * Developer hooks
 * -------------------------------------------------------------------------
 *
 * FILTERS
 *
 * yes_age_verification_options ( array $options )
 *   Fired after plugin options are loaded and merged with defaults.
 *   Use this to override any option programmatically.
 *
 * yes_age_verification_matches_target_rules ( bool $matched, array $options )
 *   Fired after evaluating whether the current request matches any
 *   configured target rule. Return true/false to add or remove matches.
 *
 * yes_age_verification_is_active ( bool $active, array $options )
 *   Fired after the full visibility decision is made (mode + rules).
 *   The popup will show only when this returns true.
 *   Note: the early guards (plugin disabled, is_admin, excluded URL) are
 *   applied before this filter and cannot be overridden here.
 *
 * yes_age_verification_css ( string $css, array $options )
 *   Fired before the inline CSS is enqueued. Append or replace styles.
 *
 * yes_age_verification_js_config ( array $config, array $options )
 *   Fired before the JS config object is JSON-encoded and inlined.
 *   Add extra keys for custom JS extensions.
 *
 * yes_age_verification_bot_user_agents ( array $user_agents )
 *   Fired when building the list of search-engine / AI-crawler user-agent
 *   substrings sent to the frontend script. Recognized crawlers never see
 *   the popup overlay (the page itself is always rendered identically for
 *   caching purposes — only the in-browser display decision changes).
 *
 * yes_age_verification_popup_logo_html ( string $html, int $logo_id, array $options )
 *   Fired before the logo is output. Return an empty string to suppress.
 *
 * yes_age_verification_popup_title ( string $title, array $options )
 * yes_age_verification_popup_body ( string $body, array $options )
 * yes_age_verification_popup_question ( string $question, array $options )
 * yes_age_verification_popup_yes_text ( string $text, array $options )
 * yes_age_verification_popup_no_text ( string $text, array $options )
 * yes_age_verification_popup_footer ( string $footer, array $options )
 *   Fired before each popup text field is rendered. Return an empty string
 *   to suppress that element entirely.
 *
 * yes_age_verification_sanitize_options ( array $clean, array $raw )
 *   Fired at the end of the sanitize callback, before options are saved.
 *   Use this to add custom sanitization or extra option keys.
 *
 * ACTIONS
 *
 * yes_age_verification_before_popup ()
 *   Fired in wp_footer just before the overlay <div> is output.
 *
 * yes_age_verification_after_popup ()
 *   Fired in wp_footer just after the overlay <div> is output.
 *
 * yes_age_verification_popup_before_content ( array $options )
 *   Fired inside the modal <div>, before any content.
 *
 * yes_age_verification_popup_after_content ( array $options )
 *   Fired inside the modal <div>, after all content.
 *
 * -------------------------------------------------------------------------
 * Multilingual support — WPML & Polylang
 * -------------------------------------------------------------------------
 *
 * Settings stay single-language (saved once, in the site's default
 * language). The popup title, body text, question, button labels, footer
 * text and redirect URL are registered as translatable strings with WPML
 * String Translation (`wpml_register_single_string`) and Polylang
 * (`pll_register_string`) under the "yes-age-verification" context, and
 * resolved to the visitor's current language at render time
 * (`wpml_translate_single_string` / `pll__`). Site owners translate them
 * through the multilingual plugin's own string-translation screen — no
 * extra fields are added to this plugin's settings page.
 *
 * Target pages, categories and taxonomy terms are also resolved to their
 * translation in the visitor's current language (via `wpml_object_id`,
 * `pll_get_post` and `pll_get_term`) before the visibility rules are
 * evaluated, so a rule configured against the default-language page or
 * term also matches its translations.
 */
final class YES_Age_Verification {

	/** @var string Cookie written by JS once the visitor confirms their age. */
	const COOKIE_NAME = 'age_verification';

	/** @var self|null */
	private static $instance = null;

	/** @var array<string,mixed> */
	private $options;

	// -------------------------------------------------------------------------
	// Bootstrap
	// -------------------------------------------------------------------------

	/** @return self */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init',                  array( $this, 'load_textdomain' ) );
		add_action( 'init',                  array( $this, 'register_translatable_strings' ), 20 );
		add_filter( 'plugin_action_links_' . plugin_basename( YES_AGE_VERIFICATION_FILE ), array( $this, 'action_links' ) );
		add_action( 'admin_menu',            array( $this, 'admin_menu' ) );
		add_action( 'admin_init',            array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ) );
		add_action( 'wp_enqueue_scripts',    array( $this, 'frontend_enqueue' ) );
		add_action( 'wp_footer',             array( $this, 'render_popup' ) );
	}

	// -------------------------------------------------------------------------
	// i18n
	// -------------------------------------------------------------------------

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'yes-age-verification',
			false,
			dirname( plugin_basename( YES_AGE_VERIFICATION_FILE ) ) . '/languages'
		);

		$saved         = get_option( 'yes_age_verification_options', array() );
		$this->options = (array) apply_filters(
			'yes_age_verification_options',
			wp_parse_args( $saved, self::defaults() )
		);
	}

	// -------------------------------------------------------------------------
	// Crawler detection
	// -------------------------------------------------------------------------

	/**
	 * Returns user-agent substrings identifying known search-engine and
	 * AI-crawler bots. Sent to the frontend script (see frontend_enqueue())
	 * so it can skip showing the popup overlay to recognized crawlers — kept
	 * client-side so the server always renders identical, fully cacheable
	 * markup regardless of who's asking.
	 *
	 * @return string[]
	 */
	private function bot_user_agents(): array {
		return (array) apply_filters( 'yes_age_verification_bot_user_agents', array(
			'Googlebot', 'Googlebot-Image', 'Googlebot-Video', 'Storebot-Google', 'Google-InspectionTool',
			'Bingbot', 'BingPreview', 'Slurp', 'DuckDuckBot', 'Baiduspider', 'YandexBot',
			'Applebot', 'facebookexternalhit', 'Twitterbot', 'LinkedInBot', 'Discordbot',
			'GPTBot', 'ChatGPT-User', 'OAI-SearchBot', 'ClaudeBot', 'Claude-Web', 'anthropic-ai',
			'PerplexityBot', 'Perplexity-User', 'CCBot', 'Bytespider', 'Amazonbot', 'cohere-ai',
		) );
	}

	// -------------------------------------------------------------------------
	// Multilingual support — WPML & Polylang
	// -------------------------------------------------------------------------

	/**
	 * Returns the translatable popup text fields, keyed by the option key,
	 * mapped to the string name registered with WPML / Polylang and whether
	 * the field accepts multi-line (rich text) content.
	 *
	 * @return array<string,array{name:string,multiline:bool}>
	 */
	private function translatable_strings(): array {
		return array(
			'title'         => array( 'name' => 'popup_title',         'multiline' => false ),
			'body_text'     => array( 'name' => 'popup_body_text',     'multiline' => true ),
			'question_text' => array( 'name' => 'popup_question_text', 'multiline' => false ),
			'yes_text'      => array( 'name' => 'popup_yes_text',      'multiline' => false ),
			'no_text'       => array( 'name' => 'popup_no_text',       'multiline' => false ),
			'footer_text'   => array( 'name' => 'popup_footer_text',   'multiline' => true ),
			'redirect_url'  => array( 'name' => 'popup_redirect_url',  'multiline' => false ),
		);
	}

	/**
	 * Registers the popup text fields with WPML String Translation and/or
	 * Polylang, so site owners can provide per-language translations through
	 * those plugins' own interfaces — without duplicating settings fields.
	 */
	public function register_translatable_strings(): void {
		if ( empty( $this->options ) ) {
			return;
		}

		$context = 'yes-age-verification';

		foreach ( $this->translatable_strings() as $option_key => $string ) {
			$value = $this->options[ $option_key ] ?? '';

			if ( '' === $value ) {
				continue;
			}

			// WPML String Translation.
			do_action( 'wpml_register_single_string', $context, $string['name'], $value );

			// Polylang.
			if ( function_exists( 'pll_register_string' ) ) {
				pll_register_string( $string['name'], $value, $context, $string['multiline'] );
			}
		}
	}

	/**
	 * Translates a single string into the visitor's current language using
	 * WPML String Translation and/or Polylang, falling back to the original
	 * value when no translation is available or no multilingual plugin runs.
	 *
	 * @param string $name  The string name registered with WPML / Polylang.
	 * @param string $value The original (default-language) value.
	 */
	private function translate_string( string $name, string $value ): string {
		if ( '' === $value ) {
			return $value;
		}

		$context = 'yes-age-verification';

		// WPML String Translation.
		$value = (string) apply_filters( 'wpml_translate_single_string', $value, $context, $name );

		// Polylang.
		if ( function_exists( 'pll__' ) ) {
			$translated = pll__( $value );
			if ( '' !== $translated ) {
				$value = $translated;
			}
		}

		return $value;
	}

	/**
	 * Returns the plugin options with all translatable popup text fields
	 * resolved to the visitor's current language.
	 *
	 * @param array<string,mixed> $options
	 * @return array<string,mixed>
	 */
	private function translate_popup_options( array $options ): array {
		foreach ( $this->translatable_strings() as $option_key => $string ) {
			if ( empty( $options[ $option_key ] ) ) {
				continue;
			}

			$options[ $option_key ] = $this->translate_string( $string['name'], $options[ $option_key ] );
		}

		return $options;
	}

	/**
	 * Resolves a page/post ID to its translation in the current language
	 * when WPML or Polylang is active, falling back to the original ID.
	 */
	private function localize_post_id( int $id, string $post_type = 'page' ): int {
		$id = (int) apply_filters( 'wpml_object_id', $id, $post_type, true );

		if ( function_exists( 'pll_get_post' ) ) {
			$id = pll_get_post( $id ) ?: $id;
		}

		return $id;
	}

	/**
	 * Resolves a taxonomy term ID to its translation in the current language
	 * when WPML or Polylang is active, falling back to the original ID.
	 */
	private function localize_term_id( int $id, string $taxonomy ): int {
		$id = (int) apply_filters( 'wpml_object_id', $id, $taxonomy, true );

		if ( function_exists( 'pll_get_term' ) ) {
			$id = pll_get_term( $id ) ?: $id;
		}

		return $id;
	}

	// -------------------------------------------------------------------------
	// Defaults
	// -------------------------------------------------------------------------

	/**
	 * Returns defaults translated in the site language, regardless of the
	 * current user's language preference.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults_for_site(): array {
		$site_locale = get_option( 'WPLANG' ) ?: '';

		if ( ! $site_locale || $site_locale === get_user_locale() ) {
			return self::defaults();
		}

		$mo_file = YES_AGE_VERIFICATION_PATH . 'languages/yes-age-verification-' . $site_locale . '.mo';

		if ( ! file_exists( $mo_file ) ) {
			return self::defaults();
		}

		$mo = new MO();
		if ( ! $mo->import_from_file( $mo_file ) ) {
			return self::defaults();
		}

		$t = static function ( string $s ) use ( $mo ): string {
			return $mo->translate( $s ) ?: $s;
		};

		return array(
			'enabled'       => 0,
			'logo_id'       => 0,
			'logo_width'    => '',
			'title'         => $t( 'Welcome to our store' ),
			'body_text'     => $t( 'To visit our website, you must have reached the legal drinking age in your country of residence.' ),
			'question_text' => $t( 'Are you 18 years or older?' ),
			'yes_text'      => $t( 'Yes, I am' ),
			'no_text'       => $t( "No, I'm not" ),
			'footer_text'   => $t( 'By visiting this website, you agree to our <a href="#">Terms &amp; Conditions</a>.' ),
			'redirect_url'         => 'https://www.google.com',
			'cookie_days'          => 30,
			'overlay_color'        => 'rgba(0,0,0,0.78)',
			'mode'                 => 'exclusion',
			'exclude_urls'         => '',
			'target_pages'         => array(),
			'target_categories'    => array(),
			'target_post_types'    => array(),
			'target_taxonomies'    => array(),
			'target_url_regex'     => '',
			'target_wc_categories' => array(),
		);
	}

	/**
	 * Returns default option values.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'enabled'       => 0,
			'logo_id'       => 0,
			'logo_width'    => '',
			'title'         => __( 'Welcome to our store', 'yes-age-verification' ),
			'body_text'     => __( 'To visit our website, you must have reached the legal drinking age in your country of residence.', 'yes-age-verification' ),
			'question_text' => __( 'Are you 18 years or older?', 'yes-age-verification' ),
			'yes_text'      => __( 'Yes, I am', 'yes-age-verification' ),
			'no_text'       => __( "No, I'm not", 'yes-age-verification' ),
			'footer_text'   => __( 'By visiting this website, you agree to our <a href="#">Terms &amp; Conditions</a>.', 'yes-age-verification' ),
			'redirect_url'         => 'https://www.google.com',
			'cookie_days'          => 30,
			'overlay_color'        => 'rgba(0,0,0,0.78)',
			'mode'                 => 'exclusion',
			'exclude_urls'         => '',
			'target_pages'         => array(),
			'target_categories'    => array(),
			'target_post_types'    => array(),
			'target_taxonomies'    => array(),
			'target_url_regex'     => '',
			'target_wc_categories' => array(),
		);
	}

	// -------------------------------------------------------------------------
	// Admin — menu & settings registration
	// -------------------------------------------------------------------------

	/**
	 * @param array<int,string> $links
	 * @return array<int,string>
	 */
	public function action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=yes-age-verification' ) ),
			esc_html__( 'Settings', 'yes-age-verification' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	public function admin_menu(): void {
		add_options_page(
			esc_html__( 'Age Verification Settings', 'yes-age-verification' ),
			esc_html__( 'Age Verification', 'yes-age-verification' ),
			'manage_options',
			'yes-age-verification',
			array( $this, 'settings_page' )
		);
	}

	public function register_settings(): void {
		register_setting(
			'yes_age_verification_group',
			'yes_age_verification_options',
			array(
				'sanitize_callback' => array( $this, 'sanitize_options' ),
			)
		);
	}

	/**
	 * @param array<string,mixed> $raw
	 * @return array<string,mixed>
	 */
	public function sanitize_options( array $raw ): array {
		$clean = array();

		$clean['enabled']       = ! empty( $raw['enabled'] ) ? 1 : 0;
		$clean['logo_id']       = absint( $raw['logo_id'] ?? 0 );
		$clean['logo_width']    = absint( $raw['logo_width'] ?? 0 ) ?: '';
		$clean['title']         = sanitize_text_field( $raw['title'] ?? '' );
		$clean['body_text']     = wp_kses_post( $raw['body_text'] ?? '' );
		$clean['question_text'] = sanitize_text_field( $raw['question_text'] ?? '' );
		$clean['yes_text']      = sanitize_text_field( $raw['yes_text'] ?? '' );
		$clean['no_text']       = sanitize_text_field( $raw['no_text'] ?? '' );
		$clean['footer_text']   = wp_kses_post( $raw['footer_text'] ?? '' );
		$clean['redirect_url']  = esc_url_raw( $raw['redirect_url'] ?? '' );
		$clean['cookie_days']   = max( 1, min( 365, absint( $raw['cookie_days'] ?? 30 ) ) );

		$clean['overlay_color'] = preg_replace(
			'/[^a-zA-Z0-9\s,().#%]/',
			'',
			$raw['overlay_color'] ?? 'rgba(0,0,0,0.78)'
		) ?: 'rgba(0,0,0,0.78)';

		$clean['mode']         = 'inclusion' === ( $raw['mode'] ?? '' ) ? 'inclusion' : 'exclusion';
		$clean['exclude_urls'] = sanitize_textarea_field( $raw['exclude_urls'] ?? '' );

		$to_int_array = static function ( $value ): array {
			return array_values( array_filter( array_map( 'absint', (array) $value ) ) );
		};

		$clean['target_pages']         = $to_int_array( $raw['target_pages'] ?? array() );
		$clean['target_categories']    = $to_int_array( $raw['target_categories'] ?? array() );
		$clean['target_wc_categories'] = $to_int_array( $raw['target_wc_categories'] ?? array() );

		$clean['target_post_types'] = array();
		foreach ( (array) ( $raw['target_post_types'] ?? array() ) as $slug ) {
			$slug = sanitize_key( $slug );
			if ( $slug && post_type_exists( $slug ) ) {
				$clean['target_post_types'][] = $slug;
			}
		}

		$clean['target_taxonomies'] = array();
		foreach ( (array) ( $raw['target_taxonomies'] ?? array() ) as $pair ) {
			$pair = sanitize_text_field( wp_unslash( (string) $pair ) );
			if ( preg_match( '/^[a-z0-9_-]+:\d+$/', $pair ) ) {
				$clean['target_taxonomies'][] = $pair;
			}
		}

		$valid_patterns = array();
		foreach ( array_filter( array_map( 'trim', explode( "\n", wp_unslash( $raw['target_url_regex'] ?? '' ) ) ) ) as $pattern ) {
			$wrapped = '#' . str_replace( '#', '\#', $pattern ) . '#';
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( @preg_match( $wrapped, '' ) !== false ) {
				$valid_patterns[] = $pattern;
			} else {
				add_settings_error(
					'yes_age_verification_options',
					'invalid_regex',
					sprintf(
						/* translators: %s: the invalid regex pattern entered by the user */
						esc_html__( 'Invalid regex pattern removed: %s', 'yes-age-verification' ),
						'<code>' . esc_html( $pattern ) . '</code>'
					)
				);
			}
		}
		$clean['target_url_regex'] = implode( "\n", $valid_patterns );

		return (array) apply_filters( 'yes_age_verification_sanitize_options', $clean, $raw );
	}

	/**
	 * @param string $hook Current admin page hook.
	 */
	public function admin_enqueue( string $hook ): void {
		if ( 'settings_page_yes-age-verification' !== $hook ) {
			return;
		}

		wp_enqueue_media();

		if ( class_exists( 'WooCommerce' ) ) {
			wp_enqueue_style( 'woocommerce_admin_styles' );
			wp_enqueue_script( 'wc-enhanced-select' );
		}

		wp_enqueue_script(
			'yes-age-verification-admin',
			YES_AGE_VERIFICATION_URL . 'admin/admin.js',
			array( 'jquery' ),
			YES_AGE_VERIFICATION_VERSION,
			true
		);
	}

	public function settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'yes-age-verification' ) );
		}
		require YES_AGE_VERIFICATION_PATH . 'admin/settings.php';
	}

	// -------------------------------------------------------------------------
	// Frontend — visibility
	// -------------------------------------------------------------------------

	private function is_active(): bool {
		if ( empty( $this->options['enabled'] ) ) {
			return false;
		}

		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return false;
		}

		if ( ! empty( $this->options['exclude_urls'] ) ) {
			$excluded = array_filter( array_map( 'trim', explode( "\n", $this->options['exclude_urls'] ) ) );
			$current  = trailingslashit( preg_replace( '#^https?://#', '', home_url( add_query_arg( array() ) ) ) );
			foreach ( $excluded as $url ) {
				if ( trailingslashit( preg_replace( '#^https?://#', '', $url ) ) === $current ) {
					return false;
				}
			}
		}

		$matched = (bool) apply_filters(
			'yes_age_verification_matches_target_rules',
			$this->matches_target_rules(),
			$this->options
		);

		$mode = $this->options['mode'] ?? 'exclusion';

		if ( 'exclusion' === $mode ) {
			$active = ! $matched;
		} else {
			$has_rules = ! empty( $this->options['target_pages'] )
				|| ! empty( $this->options['target_categories'] )
				|| ! empty( $this->options['target_post_types'] )
				|| ! empty( $this->options['target_taxonomies'] )
				|| ! empty( $this->options['target_url_regex'] )
				|| ( ! empty( $this->options['target_wc_categories'] ) && function_exists( 'is_product' ) );

			$active = $has_rules ? $matched : true;
		}

		return (bool) apply_filters( 'yes_age_verification_is_active', $active, $this->options );
	}

	private function matches_target_rules(): bool {

		if ( ! empty( $this->options['target_pages'] ) ) {
			$page_ids = array_map(
				function ( $id ) {
					return $this->localize_post_id( $id, 'page' );
				},
				array_map( 'absint', (array) $this->options['target_pages'] )
			);
			if ( is_page( $page_ids ) ) {
				return true;
			}
			if ( function_exists( 'is_shop' ) && is_shop() ) {
				$shop_id = (int) wc_get_page_id( 'shop' );
				if ( $shop_id > 0 && in_array( $shop_id, $page_ids, true ) ) {
					return true;
				}
			}
		}

		if ( ! empty( $this->options['target_categories'] ) ) {
			$category_ids = array_map(
				function ( $id ) {
					return $this->localize_term_id( $id, 'category' );
				},
				array_map( 'absint', (array) $this->options['target_categories'] )
			);
			if ( $this->term_matches( 'category', $category_ids ) ) {
				return true;
			}
		}

		if ( ! empty( $this->options['target_post_types'] ) ) {
			$types = (array) $this->options['target_post_types'];
			if ( is_singular( $types ) || is_post_type_archive( $types ) ) {
				return true;
			}
		}

		if ( ! empty( $this->options['target_taxonomies'] ) ) {
			$by_taxonomy = array();
			foreach ( (array) $this->options['target_taxonomies'] as $pair ) {
				list( $taxonomy_slug, $term_id ) = explode( ':', $pair, 2 );
				$by_taxonomy[ $taxonomy_slug ][] = (int) $term_id;
			}
			foreach ( $by_taxonomy as $taxonomy => $term_ids ) {
				$localized_term_ids = array_map(
					function ( $id ) use ( $taxonomy ) {
						return $this->localize_term_id( $id, $taxonomy );
					},
					$term_ids
				);
				if ( $this->term_matches( $taxonomy, $localized_term_ids ) ) {
					return true;
				}
			}
		}

		if ( ! empty( $this->options['target_url_regex'] ) ) {
			$request_path = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
			foreach ( array_filter( array_map( 'trim', explode( "\n", $this->options['target_url_regex'] ) ) ) as $pattern ) {
				$wrapped = '#' . str_replace( '#', '\#', $pattern ) . '#';
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( @preg_match( $wrapped, $request_path ) === 1 ) {
					return true;
				}
			}
		}

		if ( ! empty( $this->options['target_wc_categories'] ) && function_exists( 'is_product' ) ) {
			$category_ids = array_map(
				function ( $id ) {
					return $this->localize_term_id( $id, 'product_cat' );
				},
				array_map( 'absint', (array) $this->options['target_wc_categories'] )
			);
			if ( $this->term_matches( 'product_cat', $category_ids ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $taxonomy
	 * @param int[]  $term_ids
	 */
	private function term_matches( string $taxonomy, array $term_ids ): bool {
		if ( is_tax( $taxonomy ) || is_category() && 'category' === $taxonomy ) {
			$term   = get_queried_object();
			$family = array_merge( array( $term->term_id ), get_ancestors( $term->term_id, $taxonomy ) );
			return ! empty( array_intersect( $family, $term_ids ) );
		}

		if ( is_product_category() && 'product_cat' === $taxonomy ) {
			$term   = get_queried_object();
			$family = array_merge( array( $term->term_id ), get_ancestors( $term->term_id, $taxonomy ) );
			return ! empty( array_intersect( $family, $term_ids ) );
		}

		if ( is_singular() ) {
			$terms = wp_get_post_terms( get_the_ID(), $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				return false;
			}
			$all = $terms;
			foreach ( $terms as $term_id ) {
				$all = array_merge( $all, get_ancestors( $term_id, $taxonomy ) );
			}
			return ! empty( array_intersect( $all, $term_ids ) );
		}

		return false;
	}

	// -------------------------------------------------------------------------
	// Frontend — asset loading
	// -------------------------------------------------------------------------

	private function get_css(): string {
		$overlay         = esc_attr( $this->options['overlay_color'] );
		$logo_width      = (int) $this->options['logo_width'];
		$logo_width_css  = $logo_width > 0 ? 'max-width:' . $logo_width . 'px;' : '';

		$css = '#yes-age-verification__overlay{position:fixed;inset:0;background:' . $overlay . ';z-index:999999;display:flex;align-items:center;justify-content:center;padding:16px}'
			. '#yes-age-verification__modal{background:#fff;border-radius:4px;max-width:580px;width:100%;padding:40px 48px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.35)}'
			. '#yes-age-verification__modal img{max-height:80px;' . $logo_width_css . 'width:auto;margin-bottom:24px}'
			. '#yes-age-verification__modal h2{font-size:1.5rem;font-weight:700;margin:0 0 16px;color:#1a1a1a}'
			. '#yes-age-verification__modal .yes-age-verification__body{font-size:.95rem;line-height:1.65;color:#444;margin:0 0 16px}'
			. '#yes-age-verification__modal .yes-age-verification__question{font-size:.95rem;font-weight:600;color:#1a1a1a;margin:20px 0 28px}'
			. '.yes-age-verification__buttons{display:flex;gap:12px;justify-content:center;margin:0 0 4px}'
			. '.yes-age-verification__button{flex:1;max-width:240px;padding:13px 24px;border:1.5px solid #1a1a1a;background:#fff;color:#1a1a1a;font-size:1rem;font-family:inherit;cursor:pointer;border-radius:2px;transition:background .15s,color .15s;letter-spacing:.01em}'
			. '.yes-age-verification__button:hover,.yes-age-verification__button:focus{background:#1a1a1a;color:#fff}'
			. '.yes-age-verification__button:focus-visible{outline:2px solid #1a1a1a;outline-offset:3px}'
			. '#yes-age-verification__modal .yes-age-verification__footer{font-size:.75rem;color:#767676;margin:20px 0 0;line-height:1.5}'
			. '#yes-age-verification__modal .yes-age-verification__footer a{color:#767676}'
			. 'body.yes-age-verification--locked{overflow:hidden}'
			. '@media(max-width:480px){#yes-age-verification__modal{padding:32px 24px}.yes-age-verification__buttons{flex-direction:column;align-items:stretch}.yes-age-verification__button{max-width:none}}';

		return (string) apply_filters( 'yes_age_verification_css', $css, $this->options );
	}

	public function frontend_enqueue(): void {
		if ( ! $this->is_active() ) {
			return;
		}

		wp_register_style( 'yes-age-verification', false, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_style( 'yes-age-verification' );
		wp_add_inline_style( 'yes-age-verification', $this->get_css() );

		wp_register_script( 'yes-age-verification', false, array(), null, array( 'in_footer' => true ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_script( 'yes-age-verification' );

		$redirect_url = $this->translate_string( 'popup_redirect_url', $this->options['redirect_url'] ?: 'https://www.google.com' );

		$js_config = (array) apply_filters( 'yes_age_verification_js_config', array(
			'cookie'   => self::COOKIE_NAME,
			'days'     => (int) $this->options['cookie_days'],
			'redirect' => esc_url( $redirect_url ?: 'https://www.google.com' ),
			'bots'     => array_values( $this->bot_user_agents() ),
		), $this->options );

		wp_add_inline_script(
			'yes-age-verification',
			'var yesAgeVerificationConfig=' . wp_json_encode( $js_config ) . ';' .
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			file_get_contents( YES_AGE_VERIFICATION_PATH . 'assets/age-verify.js' )
		);
	}

	// -------------------------------------------------------------------------
	// Frontend — popup markup
	// -------------------------------------------------------------------------

	public function render_popup(): void {
		if ( ! $this->is_active() ) {
			return;
		}

		$options = $this->translate_popup_options( $this->options );

		$title    = (string) apply_filters( 'yes_age_verification_popup_title',    $options['title'],         $options );
		$body     = (string) apply_filters( 'yes_age_verification_popup_body',     $options['body_text'],     $options );
		$question = (string) apply_filters( 'yes_age_verification_popup_question', $options['question_text'], $options );
		$yes_text = (string) apply_filters( 'yes_age_verification_popup_yes_text', $options['yes_text'],      $options );
		$no_text  = (string) apply_filters( 'yes_age_verification_popup_no_text',  $options['no_text'],       $options );
		$footer   = (string) apply_filters( 'yes_age_verification_popup_footer',   $options['footer_text'],   $options );

		$logo_html = ! empty( $options['logo_id'] )
			? wp_get_attachment_image( $options['logo_id'], 'full', false, array( 'loading' => 'eager' ) )
			: '';
		$logo_html = (string) apply_filters( 'yes_age_verification_popup_logo_html', $logo_html, (int) $options['logo_id'], $options );

		$dialog_label = ! empty( $title )
			? 'aria-labelledby="yes-age-verification__title"'
			: 'aria-label="' . esc_attr__( 'Age Verification', 'yes-age-verification' ) . '"';

		do_action( 'yes_age_verification_before_popup' );
		?>
		<div id="yes-age-verification__overlay" role="dialog" aria-modal="true" <?php echo $dialog_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> style="display:none">
			<div id="yes-age-verification__modal">

				<?php do_action( 'yes_age_verification_popup_before_content', $options ); ?>

				<?php if ( $logo_html ) : ?>
					<?php echo $logo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>

				<?php if ( ! empty( $title ) ) : ?>
					<h2 id="yes-age-verification__title"><?php echo esc_html( $title ); ?></h2>
				<?php endif; ?>

				<?php if ( ! empty( $body ) ) : ?>
					<div class="yes-age-verification__body"><?php echo wp_kses_post( $body ); ?></div>
				<?php endif; ?>

				<?php if ( ! empty( $question ) ) : ?>
					<p class="yes-age-verification__question"><?php echo esc_html( $question ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $yes_text ) || ! empty( $no_text ) ) : ?>
					<div class="yes-age-verification__buttons">
						<?php if ( ! empty( $yes_text ) ) : ?>
							<button class="yes-age-verification__button" id="yes-age-verification__button--yes" type="button">
								<?php echo esc_html( $yes_text ); ?>
							</button>
						<?php endif; ?>
						<?php if ( ! empty( $no_text ) ) : ?>
							<button class="yes-age-verification__button" id="yes-age-verification__button--no" type="button">
								<?php echo esc_html( $no_text ); ?>
							</button>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $footer ) ) : ?>
					<div class="yes-age-verification__footer"><?php echo wp_kses_post( $footer ); ?></div>
				<?php endif; ?>

				<?php do_action( 'yes_age_verification_popup_after_content', $options ); ?>

			</div>
		</div>
		<?php
		do_action( 'yes_age_verification_after_popup' );
	}
}

add_action( 'plugins_loaded', array( 'YES_Age_Verification', 'instance' ) );
