<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Dashboard origin (hosts the device approval page). */
function iterochat_dashboard_url() {
	$url = defined( 'ITEROCHAT_DASHBOARD_URL' ) ? ITEROCHAT_DASHBOARD_URL : 'https://iterochat.com';
	return untrailingslashit( $url );
}

/** API origin (device-code + token + connection read). */
function iterochat_api_url() {
	// NOTE: placeholder until the real production API origin is confirmed.
	$url = defined( 'ITEROCHAT_API_URL' ) ? ITEROCHAT_API_URL : 'https://iterochat.com';
	return untrailingslashit( $url );
}

/** Widget static host (serves widget.js). */
function iterochat_widget_url() {
	// NOTE: placeholder until the real production widget host is confirmed.
	$url = defined( 'ITEROCHAT_WIDGET_URL' ) ? ITEROCHAT_WIDGET_URL : 'https://iterochat.com';
	return untrailingslashit( $url );
}
