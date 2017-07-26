<?php

if ( !defined('WP_UNINSTALL_PLUGIN') ) {
    exit();
}

require_once('classy_wp_list_pages.php');

foreach ( $c_wp_lp_options as $k => $v ) {
	delete_option( $k );
}