<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The stored connection, merged over defaults. */
function iterochat_get_connection() {
	$defaults = array(
		'access_token' => '',
		'widget_key'   => '',
		'org_id'       => '',
		'org_name'     => '',
		'site_label'   => '',
		'enabled'      => true,
		'revoked'      => false,
		'connected_at' => 0,
	);
	$stored = get_option( ITEROCHAT_OPTION, array() );
	return wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );
}

/** True when a token is held and the connection is not flagged revoked. */
function iterochat_is_connected() {
	$c = iterochat_get_connection();
	return ! empty( $c['access_token'] ) && empty( $c['revoked'] );
}

/** Persist a partial update merged over current state (no autoload; holds a token). */
function iterochat_update_connection( array $patch ) {
	$next = array_merge( iterochat_get_connection(), $patch );
	update_option( ITEROCHAT_OPTION, $next, false );
	return $next;
}

/** Remove the connection entirely. */
function iterochat_clear_connection() {
	delete_option( ITEROCHAT_OPTION );
}
