<?php
/**
 * Plugin Name: BMLT Client
 * Plugin URI: https://wordpress.org/plugins/bmlt-client/
 * Description: Embeds the BMLT Client meeting finder widget on any page or post using a shortcode.
 * Version: 1.0.0
 * Author: bmltenabled
 * Author URI: https://bmlt.app
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bmlt-client
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BMLTCLIENT_VERSION', '1.0.0' );

class BmltClient {

	private static ?self $instance = null;

	const DEFAULT_CDN_URL = 'https://cdn.aws.bmlt.app/bmlt-client/app.js';

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'bmlt_client', [ static::class, 'setup_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ static::class, 'assets' ] );
		add_action( 'admin_menu', [ static::class, 'admin_menu' ] );
		add_action( 'admin_init', [ static::class, 'register_settings' ] );
	}

	// -------------------------------------------------------------------------
	// Shortcode
	// -------------------------------------------------------------------------

	public static function setup_shortcode( array $atts ): string {
		$atts = shortcode_atts(
			[
				'root_server'  => get_option( 'bmltclient_root_server', 'https://latest.aws.bmlt.app/main_server/' ),
				'service_body' => get_option( 'bmltclient_service_body', '1047,1048' ),
			],
			$atts,
			'bmlt_client'
		);

		$root_server = esc_url( trim( $atts['root_server'] ) );

		if ( empty( $root_server ) ) {
			return '<p style="color:red"><strong>BMLT Client:</strong> a <code>root_server</code> URL is required.</p>';
		}

		$div = '<div id="bmlt-meeting-list" data-root-server="' . $root_server . '"';

		if ( ! empty( $atts['service_body'] ) ) {
			$div .= ' data-service-body="' . esc_attr( trim( $atts['service_body'] ) ) . '"';
		}

		$div .= '></div>';

		$template = get_option( 'bmltclient_css_template', '' );

		if ( 'full_width' === $template ) {
			return '<div class="bmltclient-full-width">' . $div . '</div>';
		}

		if ( 'full_width_force' === $template ) {
			return '<div class="bmltclient-full-width-force">' . $div . '</div>';
		}

		return $div;
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	public static function assets(): void {
		global $post;
		if ( ! $post || ! has_shortcode( $post->post_content, 'bmlt_client' ) ) {
			return;
		}

		wp_enqueue_script( 'bmlt-client', self::DEFAULT_CDN_URL, [], BMLTCLIENT_VERSION, [ 'strategy' => 'defer' ] );

		/**
		 * Filter the BmltMeetingListConfig passed to the widget.
		 *
		 * Add this to your theme's functions.php to configure the widget:
		 *
		 *   add_filter( 'bmltclient_config', function( $config ) {
		 *       return array_merge( $config, [
		 *           'language'          => 'es',
		 *           'geolocation'       => true,
		 *           'geolocationRadius' => 20,
		 *           'height'            => 800,
		 *           'columns'           => [ 'time', 'name', 'location', 'address', 'service_body' ],
		 *       ] );
		 *   } );
		 *
		 * See https://client.bmlt.app/ for all available options.
		 *
		 * @param array $config Configuration array passed to BmltMeetingListConfig.
		 */
		$config = (array) apply_filters( 'bmltclient_config', [] );
		if ( ! empty( $config ) ) {
			wp_localize_script( 'bmlt-client', 'BmltMeetingListConfig', $config );
		}

		wp_register_style( 'bmlt-client-style', false, [], BMLTCLIENT_VERSION );
		wp_enqueue_style( 'bmlt-client-style' );
		wp_add_inline_style( 'bmlt-client-style', self::build_css() );
	}

	private static function build_css(): string {
		$template = get_option( 'bmltclient_css_template', '' );

		if ( 'full_width' === $template ) {
			return '.bmltclient-full-width { width: 100%; }';
		}

		if ( 'full_width_force' === $template ) {
			return '.bmltclient-full-width-force { width: 100vw !important; position: relative !important; left: 50% !important; margin-left: -50vw !important; box-sizing: border-box !important; max-width: none !important; }';
		}

		return '';
	}

	// -------------------------------------------------------------------------
	// Admin
	// -------------------------------------------------------------------------

	public static function admin_menu(): void {
		add_options_page(
			'BMLT Client Settings',
			'BMLT Client',
			'manage_options',
			'bmlt-client',
			[ static::class, 'settings_page' ]
		);
	}

	public static function register_settings(): void {
		$group = 'bmltclient-group';

		register_setting( $group, 'bmltclient_root_server', 'esc_url_raw' );
		register_setting( $group, 'bmltclient_service_body', 'sanitize_text_field' );
		register_setting( $group, 'bmltclient_css_template', 'sanitize_text_field' );
	}

	public static function settings_page(): void {
		?>
		<div class="wrap">
			<h1>BMLT Client Settings</h1>

			<?php settings_errors(); ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'bmltclient-group' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="bmltclient_root_server">Root Server URL</label></th>
						<td>
							<input type="url" id="bmltclient_root_server" name="bmltclient_root_server"
								   value="<?php echo esc_attr( get_option( 'bmltclient_root_server', 'https://latest.aws.bmlt.app/main_server/' ) ); ?>"
								   class="regular-text" placeholder="https://your-server/main_server" />
							<p class="description">Required. The full URL to your BMLT root server.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bmltclient_service_body">Service Body IDs</label></th>
						<td>
							<input type="text" id="bmltclient_service_body" name="bmltclient_service_body"
								   value="<?php echo esc_attr( get_option( 'bmltclient_service_body', '1047,1048' ) ); ?>"
								   class="regular-text" placeholder="42 or 42,57,103" />
							<p class="description">Optional. Single ID or comma-separated list. Leave empty to show all meetings. Child service bodies are always included.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bmltclient_css_template">CSS Template</label></th>
						<td>
							<select id="bmltclient_css_template" name="bmltclient_css_template">
								<option value="" <?php selected( get_option( 'bmltclient_css_template', '' ), '' ); ?>><?php esc_html_e( '— None —', 'bmlt-client' ); ?></option>
								<option value="full_width" <?php selected( get_option( 'bmltclient_css_template', '' ), 'full_width' ); ?>>Full Width</option>
								<option value="full_width_force" <?php selected( get_option( 'bmltclient_css_template', '' ), 'full_width_force' ); ?>>Full Width (Force Viewport)</option>
							</select>
							<p class="description">Full Width fits the content area. Full Width (Force Viewport) breaks out to span the full browser width.</p>
						</td>
					</tr>
				</table>

				<h2>Advanced Configuration</h2>
				<p>
					<?php esc_html_e( 'To configure language, geolocation, columns, map tiles, and other options, add a filter to your theme\'s', 'bmlt-client' ); ?>
					<code>functions.php</code>:
				</p>
				<pre style="background:#f6f7f7;padding:12px;overflow:auto"><code>add_filter( 'bmltclient_config', function( $config ) {
	return array_merge( $config, [
		'language'          => 'en',
		'geolocation'       => true,
		'geolocationRadius' => 20,
		'height'            => 800,
		'columns'           => [ 'time', 'name', 'location', 'address', 'service_body' ],
	] );
} );</code></pre>
				<p><a href="https://client.bmlt.app/" target="_blank"><?php esc_html_e( 'See documentation for all available options.', 'bmlt-client' ); ?></a></p>

				<h2>Shortcode Usage</h2>
				<p><?php esc_html_e( 'Place this shortcode on any page or post:', 'bmlt-client' ); ?></p>
				<code>[bmlt_client]</code>
				<p><?php esc_html_e( 'Override root server or service body per page:', 'bmlt-client' ); ?></p>
				<code>[bmlt_client root_server="https://your-server/main_server" service_body="42"]</code>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

BmltClient::get_instance();
