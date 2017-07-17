<?php

class Instalogin {

  /**
   * Initializes actions and filters.
   */
  function init() {
    register_activation_hook( dirname(__FILE__) . '/instalogin.php', array( $this, 'activate' ) );
    register_deactivation_hook( dirname(__FILE__) . '/instalogin.php', array( $this, 'deactivate' ) );

    add_action( 'generate_rewrite_rules', array( $this, 'generate_rewrite_rules' ) );
    add_action( 'template_redirect', array( $this, 'template_redirect' ) );
    add_action( 'wp_login', array( $this, 'wp_login' ) );

    add_filter( 'query_vars', array( $this, 'query_vars' ) );
  }

  /**
   * Callback for the activation hook.
   */
  function activate() {
    global $wp_rewrite;

    if ( get_option( 'instalogin_secret_key' ) == FALSE ) {
      require_once( INSTALOGIN_PLUGIN_DIR . 'class.instalogin-service.php' );
      $instalogin_service = new Instalogin_Service();
      $instalogin_service->generate_secret_key();
    }

    $wp_rewrite->flush_rules();
  }

  /**
   * Callback for the deactivation hook.
   */
  function deactivate() {
    global $wp_rewrite;

    remove_action( 'generate_rewrite_rules', array( $this, 'generate_rewrite_rules' ) );
    $wp_rewrite->flush_rules();
  }

  /**
   * Callback for the 'generate_rewrite_rules' action.
   */
  function generate_rewrite_rules( $wp_rewrite ) {
    $rules = array(
      'instalogin/service/?$' => 'index.php?instalogin=service',
      'instalogin/auth/([0-9]+)/(.+)/?$' => 'index.php?instalogin=auth&user_id=$matches[1]&auth_token=$matches[2]',
    );
    $wp_rewrite->rules = $rules + $wp_rewrite->rules;
  }

  /**
   * Callback for the 'template_redirect' action.
   */
  function template_redirect() {
    if ( get_query_var('instalogin') == 'service' ) {
      // Requested url path: instalogin/service.

      require_once( INSTALOGIN_PLUGIN_DIR . 'class.instalogin-service.php' );

      $instalogin_service = new Instalogin_Service();
      $response = $instalogin_service->process_request();

      if ( $response ) {
        $json = json_encode( $response );

        header( 'Content-Type: application/json', TRUE );
        echo $json;
      }
      else {
        status_header( 404 );
        include( get_query_template( '404' ) );
      }

      exit;
    }
    else if ( get_query_var('instalogin') == 'auth' ) {
      // Requested url path: instalogin/auth/[user_id]/[token].

      require_once( INSTALOGIN_PLUGIN_DIR . 'class.instalogin-service.php' );

      $instalogin_service = new Instalogin_Service();
      $response = $instalogin_service->process_login();

      if ( ! $response ) {
        status_header( 404 );
        include( get_query_template( '404' ) );

        exit;
      }

    }
  }

  /**
   * Callback for the 'wp_login' action.
   */
  function wp_login( $user_login ) {
    $user = get_user_by( 'login', $user_login );
    // Remove token to prevent multiple use.
    delete_user_meta( $user->ID, 'instalogin_token' );
  }

  /**
   * Callback for the 'query_vars' filter.
   */
  function query_vars( $qvars ) {
    array_push( $qvars, 'instalogin' );
    array_push( $qvars, 'user_id' );
    array_push( $qvars, 'auth_token' );
    return $qvars;
  }

}
