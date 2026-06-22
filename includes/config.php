<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Dashboard origin (hosts the device approval page). */
function iterochat_dashboard_url() {
	$url = defined( 'ITEROCHAT_DASHBOARD_URL' ) ? ITEROCHAT_DASHBOARD_URL : 'https://iterochat.com';
	return untrailingslashit( $url );
}

/** API origin (device-code + token + connection read). Override with ITEROCHAT_API_URL for dev. */
function iterochat_api_url() {
	$url = defined( 'ITEROCHAT_API_URL' ) ? ITEROCHAT_API_URL : 'https://api.iterochat.com';
	return untrailingslashit( $url );
}

/** Widget static host (serves widget.js). Override with ITEROCHAT_WIDGET_URL for dev. */
function iterochat_widget_url() {
	$url = defined( 'ITEROCHAT_WIDGET_URL' ) ? ITEROCHAT_WIDGET_URL : 'https://widget.iterochat.com';
	return untrailingslashit( $url );
}
