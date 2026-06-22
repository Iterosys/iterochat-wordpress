<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'iterochat_connection' );
delete_transient( 'iterochat_device_flow' );
