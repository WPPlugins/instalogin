<?php

if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
   exit;

if ( get_option( 'instalogin_enabled' ) != FALSE ) {
   delete_option( 'instalogin_enabled' );
}

if ( get_option( 'instalogin_secret_key' ) != FALSE ) {
   delete_option( 'instalogin_secret_key' );
}
