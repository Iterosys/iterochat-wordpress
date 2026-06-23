<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'iterochat_register_menu' );
function iterochat_register_menu() {
	add_menu_page(
		__( 'IteroChat', 'iterochat' ),
		__( 'IteroChat', 'iterochat' ),
		'manage_options',
		'iterochat',
		'iterochat_render_settings_page',
		'dashicons-format-chat',
		80
	);
}

/** Store a one-shot admin notice for the current user. */
function iterochat_set_notice( $type, $text ) {
	set_transient( 'iterochat_notice_' . get_current_user_id(), array( 'type' => $type, 'text' => $text ), 60 );
}

/** Read and clear the one-shot notice. */
function iterochat_take_notice() {
	$key = 'iterochat_notice_' . get_current_user_id();
	$n   = get_transient( $key );
	if ( $n ) {
		delete_transient( $key );
	}
	return $n;
}

/** Enqueue the polling script only on our page while a device flow is pending. */
add_action( 'admin_enqueue_scripts', 'iterochat_enqueue_assets' );
function iterochat_enqueue_assets( $hook ) {
	if ( 'toplevel_page_iterochat' !== $hook ) {
		return;
	}
	$flow = get_transient( 'iterochat_device_flow' );
	if ( empty( $flow['verification_uri_complete'] ) ) {
		return;
	}
	wp_enqueue_script( 'iterochat-connect', ITEROCHAT_PLUGIN_URL . 'assets/connect.js', array(), ITEROCHAT_VERSION, true );
	wp_localize_script( 'iterochat-connect', 'iterochatConnect', array(
		'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
		'nonce'           => wp_create_nonce( 'iterochat_poll' ),
		'interval'        => isset( $flow['interval'] ) ? (int) $flow['interval'] : 5,
		'verificationUrl' => $flow['verification_uri_complete'],
	) );
}

/** Handle the POST actions. A single nonce protects every settings-page form, and it is
 *  verified before any request data is read or processed. */
add_action( 'admin_init', 'iterochat_handle_actions' );
function iterochat_handle_actions() {
	if ( ! is_admin() || ! isset( $_POST['iterochat_action'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'iterochat_admin' );
	$action = sanitize_key( wp_unslash( $_POST['iterochat_action'] ) );

	if ( 'connect' === $action ) {
		iterochat_start_connect();
	} elseif ( 'cancel' === $action ) {
		delete_transient( 'iterochat_device_flow' );
		iterochat_redirect_to_settings();
	} elseif ( 'disconnect' === $action ) {
		iterochat_clear_connection();
		iterochat_set_notice( 'success', __( 'Disconnected. The widget has been removed from your site.', 'iterochat' ) );
		iterochat_redirect_to_settings();
	} elseif ( 'toggle' === $action ) {
		$enabled = isset( $_POST['iterochat_enabled'] );
		iterochat_update_connection( array( 'enabled' => $enabled ) );
		iterochat_set_notice( 'success', __( 'Saved.', 'iterochat' ) );
		iterochat_redirect_to_settings();
	}
}

/** Begin the device flow: request a code, store the pending flow, render the waiting state. */
function iterochat_start_connect() {
	$verifier  = Iterochat_OAuth::generate_verifier();
	$challenge = Iterochat_OAuth::challenge_from_verifier( $verifier );
	$site      = wp_parse_url( home_url(), PHP_URL_HOST );

	$resp = Iterochat_OAuth::request_device_code( iterochat_api_url(), $challenge, $site );
	if ( is_wp_error( $resp ) ) {
		iterochat_set_notice( 'error', __( 'Could not start the connection: ', 'iterochat' ) . $resp->get_error_message() );
		iterochat_redirect_to_settings();
		return;
	}

	$ttl = isset( $resp['expires_in'] ) ? (int) $resp['expires_in'] : 600;
	set_transient(
		'iterochat_device_flow',
		array(
			'device_code'               => $resp['device_code'],
			'verifier'                  => $verifier,
			'verification_uri_complete' => isset( $resp['verification_uri_complete'] )
				? $resp['verification_uri_complete']
				: iterochat_dashboard_url() . '/auth/device',
			'interval'                  => isset( $resp['interval'] ) ? (int) $resp['interval'] : 5,
		),
		$ttl
	);
	iterochat_redirect_to_settings();
}

function iterochat_redirect_to_settings() {
	wp_safe_redirect( admin_url( 'admin.php?page=iterochat' ) );
	exit;
}

/** Poll the token endpoint (called by the admin-side JS). */
add_action( 'wp_ajax_iterochat_poll', 'iterochat_ajax_poll' );
function iterochat_ajax_poll() {
	check_ajax_referer( 'iterochat_poll' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json( array( 'status' => 'error', 'message' => __( 'Not allowed.', 'iterochat' ) ) );
	}
	$flow = get_transient( 'iterochat_device_flow' );
	if ( empty( $flow['device_code'] ) ) {
		wp_send_json( array( 'status' => 'expired' ) );
	}

	$result = Iterochat_OAuth::poll_token( iterochat_api_url(), $flow['device_code'], $flow['verifier'] );

	if ( is_array( $result ) ) {
		iterochat_update_connection( array(
			'access_token' => $result['access_token'],
			'widget_key'   => isset( $result['widget_key'] ) ? $result['widget_key'] : '',
			'org_id'       => isset( $result['org_id'] ) ? $result['org_id'] : '',
			'org_name'     => isset( $result['org_name'] ) ? $result['org_name'] : '',
			'site_label'   => wp_parse_url( home_url(), PHP_URL_HOST ),
			'enabled'      => true,
			'revoked'      => false,
			'connected_at' => time(),
		) );
		delete_transient( 'iterochat_device_flow' );
		wp_send_json( array( 'status' => 'connected' ) );
	}
	if ( 'authorization_pending' === $result ) {
		wp_send_json( array( 'status' => 'pending' ) );
	}
	if ( in_array( $result, array( 'expired_token', 'access_denied' ), true ) ) {
		delete_transient( 'iterochat_device_flow' );
		$msg = ( 'access_denied' === $result )
			? __( 'The connection was declined.', 'iterochat' )
			: __( 'The connection request expired. Please try again.', 'iterochat' );
		wp_send_json( array( 'status' => 'error', 'message' => $msg ) );
	}
	// Transport error (WP_Error): keep waiting.
	wp_send_json( array( 'status' => 'pending' ) );
}

/** The settings page. */
function iterochat_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'iterochat' ) );
	}

	// Liveness check: pick up a revoke or a rotated widget key.
	if ( iterochat_is_connected() ) {
		$conn = iterochat_get_connection();
		$live = Iterochat_OAuth::read_connection( iterochat_api_url(), $conn['access_token'] );
		if ( is_wp_error( $live ) && 'revoked' === $live->get_error_code() ) {
			iterochat_update_connection( array( 'revoked' => true ) );
		} elseif ( ! is_wp_error( $live ) && ! empty( $live['widget_key'] ) ) {
			iterochat_update_connection( array(
				'widget_key' => $live['widget_key'],
				'org_name'   => isset( $live['org_name'] ) ? $live['org_name'] : $conn['org_name'],
			) );
		}
	}

	$notice    = iterochat_take_notice();
	$connected = iterochat_is_connected();
	$conn      = iterochat_get_connection();
	$flow      = get_transient( 'iterochat_device_flow' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'IteroChat', 'iterochat' ); ?></h1>

		<?php if ( $notice ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
				<p><?php echo esc_html( $notice['text'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $connected ) : ?>
			<p>
				<?php
				/* translators: %s: organization name */
				printf( esc_html__( 'Connected to %s.', 'iterochat' ), '<strong>' . esc_html( $conn['org_name'] ) . '</strong>' );
				?>
			</p>
			<p>
				<a href="<?php echo esc_url( iterochat_dashboard_url() . '/dashboard/conversations' ); ?>" class="button button-secondary" target="_blank" rel="noopener">
					<?php esc_html_e( 'Open IteroChat dashboard', 'iterochat' ); ?>
				</a>
			</p>
			<form method="post">
				<?php wp_nonce_field( 'iterochat_admin' ); ?>
				<input type="hidden" name="iterochat_action" value="toggle" />
				<label>
					<input type="checkbox" name="iterochat_enabled" value="1" <?php checked( ! empty( $conn['enabled'] ) ); ?> />
					<?php esc_html_e( 'Show the chat widget on this site', 'iterochat' ); ?>
				</label>
				<?php submit_button( __( 'Save', 'iterochat' ), 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" style="margin-top:1em;">
				<?php wp_nonce_field( 'iterochat_admin' ); ?>
				<input type="hidden" name="iterochat_action" value="disconnect" />
				<?php submit_button( __( 'Disconnect', 'iterochat' ), 'delete', 'submit', false ); ?>
			</form>

		<?php elseif ( ! empty( $flow['verification_uri_complete'] ) ) : ?>
			<p><?php esc_html_e( 'Waiting for you to approve the connection in the tab that opened on iterochat.com.', 'iterochat' ); ?></p>
			<p>
				<a href="<?php echo esc_url( $flow['verification_uri_complete'] ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Open the approval page', 'iterochat' ); ?>
				</a>
			</p>
			<p id="iterochat-status" class="description"><?php esc_html_e( 'Waiting for approval...', 'iterochat' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'iterochat_admin' ); ?>
				<input type="hidden" name="iterochat_action" value="cancel" />
				<?php submit_button( __( 'Cancel', 'iterochat' ), 'link', 'submit', false ); ?>
			</form>

		<?php elseif ( ! empty( $conn['revoked'] ) ) : ?>
			<div class="notice notice-warning"><p>
				<?php esc_html_e( 'This connection was revoked from your IteroChat dashboard. Reconnect to restore the widget.', 'iterochat' ); ?>
			</p></div>
			<?php iterochat_render_connect_button(); ?>

		<?php else : ?>
			<p><?php esc_html_e( 'Connect your site to IteroChat to add the AI customer support chat widget. No code required.', 'iterochat' ); ?></p>
			<?php iterochat_render_connect_button(); ?>
			<p>
				<a href="<?php echo esc_url( iterochat_dashboard_url() . '/auth/signup' ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( "Don't have an account? Create one.", 'iterochat' ); ?>
				</a>
			</p>
		<?php endif; ?>
	</div>
	<?php
}

/** The "Connect to IteroChat" button (a nonce-protected POST). */
function iterochat_render_connect_button() {
	?>
	<form method="post">
		<?php wp_nonce_field( 'iterochat_admin' ); ?>
		<input type="hidden" name="iterochat_action" value="connect" />
		<?php submit_button( __( 'Connect to IteroChat', 'iterochat' ), 'primary', 'submit', false ); ?>
	</form>
	<?php
}
