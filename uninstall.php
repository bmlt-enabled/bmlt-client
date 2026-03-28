<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$bmltclient_options = [
	'bmltclient_root_server',
	'bmltclient_service_body',
	'bmltclient_css_template',
];

foreach ( $bmltclient_options as $bmltclient_option ) {
	delete_option( $bmltclient_option );
}
