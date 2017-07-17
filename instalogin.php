<?php
/*
Plugin Name: InstaLogin
Plugin URI: https://wordpress.org/plugins/instalogin/
Description: Supporting plugin for the <a href="http://glekli.github.io/InstaLogin/">InstaLogin app</a>.
Version: 1.1
Author: Gergely Lekli
Author URI:
License: GPLv2
*/

define( 'INSTALOGIN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once( INSTALOGIN_PLUGIN_DIR . 'class.instalogin.php' );
$instalogin_main = new Instalogin();
$instalogin_main->init();

if ( is_admin() ) {
  require_once( INSTALOGIN_PLUGIN_DIR . 'class.instalogin-admin.php' );
  $instalogin_admin = new Instalogin_Admin();
  $instalogin_admin->init();
}






