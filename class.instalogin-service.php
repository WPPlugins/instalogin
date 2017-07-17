<?php

class Instalogin_Service {

  /**
   * Processes a request to the service url.
   * Takes input from the request body.
   *
   * @return array Json data to be returned to the client. Returns FALSE on failure.
   */
  function process_request() {

    if ( ! get_option( 'instalogin_enabled' ) ) {
      return FALSE;
    }

    $error = FALSE;

    if ( $_SERVER['REQUEST_METHOD'] != 'POST' ) {
      // Accept only POST requests.
      $error = 'Invalid request.';
    }

    if ( ! $error ) {
      $data = file_get_contents( 'php://input' );

      // Content should be encrypted json.
      $data = $this->decrypt_data( $data );

      $params = array();

      if ( $data ) {
        $params = json_decode( $data, TRUE );
      }

      if ( empty( $params ) ) {
        $error = 'Invalid request content. Please verify your secret key.';
      }
    }

    if ( ! $error ) {
      // The 'cmd' parameter specifies the function to execute.
      $command = preg_replace( '@[^a-z_]@', '', $params['cmd'] );
      $callback = $command . '_command';

      if ( ! method_exists( $this, $callback ) ) {
        $error = 'Invalid command.';
      }
    }

    if ( ! $error ) {
      // Call the command requested in the 'cmd' parameter.
      $response = $this->$callback( $params );
    }
    else {
      $response = array(
        'status' => 'error',
        'error' => $error,
      );
    }

    return $response;
  }

  /**
   * Generates a secret key to be used for InstaLogin authentication.
   */
  function generate_secret_key() {
    // Generate random bytes to be used as the key.
    // AES128 key is 16 bytes. We need 2 - one for encryption and one for hmac.
    $key = mcrypt_create_iv( 32, MCRYPT_RAND );
    update_option( 'instalogin_secret_key', base64_encode( $key ) );
  }

  /**
   * Encrypts data using the site's secret key.
   * Returns base64 encoded data in the following format:
   *   Bytes 0-31  : HMAC-SHA256 calculated over the IV+encrypted plaintext.
   *   Bytes 32-47 : IV.
   *   Bytes 48-   : AES128 encrypted plaintext.
   *
   * @param string $data Data as string to encrypt.
   * @return string Base64 encoded encrypted data; or FALSE on error.
   */
  function encrypt_data( $data ) {

    $key = base64_decode( get_option( 'instalogin_secret_key', '' ) );

    // First half of the key is the encryption key, second half is the hmac key.
    $key_size = 16;
    $encryption_key = substr( $key, 0, $key_size );
    $hmac_key = substr( $key, $key_size, $key_size );
    if ( ! $encryption_key || ! $hmac_key ) {
      return FALSE;
    }

    // Add padding to the input data.
    $block_size = 16;
    $padding = $block_size - ( strlen( $data ) % $block_size );
    $data .= str_repeat( chr( $padding ), $padding );

    // Create random iv and prepend it to the cipher.
    $iv = mcrypt_create_iv( 16, MCRYPT_RAND );

    $cipher = mcrypt_encrypt( MCRYPT_RIJNDAEL_128, $encryption_key, $data, MCRYPT_MODE_CBC, $iv );
    if ( ! $cipher ) {
      return FALSE;
    }

    $cipher = $iv . $cipher;

    $hmac = hash_hmac( 'sha256', $cipher, $hmac_key, TRUE );

    return base64_encode( $hmac . $cipher );
  }

  /**
   * Decrypts data using the site's secret key.
   *
   * @param string $data Base64 encoded data to decrypt (as returned by instalogin_encrypt_data()).
   * @return string Decrypted data; or FALSE if HMAC check fails and on error.
   */
  function decrypt_data( $data ) {
    $data = base64_decode( $data );
    if ( ! $data ) {
      return FALSE;
    }

    $key = base64_decode( get_option( 'instalogin_secret_key', '' ) );

    $key_size = 16;
    $encryption_key = substr( $key, 0, $key_size );
    $hmac_key = substr( $key, $key_size, $key_size );
    if ( ! $encryption_key || ! $hmac_key ) {
      return FALSE;
    }

    // First 32 bytes are the hmac; next 16 bytes are the iv.
    $hmac = substr( $data, 0, 32 );
    $iv = substr( $data, 32, 16 );
    $data = substr( $data, 48 );

    $calculated_hmac = hash_hmac( 'sha256', $iv . $data, $hmac_key, TRUE );

    if ( strcmp( $hmac, $calculated_hmac ) !== 0 ) {
      return FALSE;
    }

    $plaintext = mcrypt_decrypt( MCRYPT_RIJNDAEL_128, $encryption_key, $data, MCRYPT_MODE_CBC, $iv );

    // Remove padding.
    if ( $plaintext ) {
      $padding = ord( $plaintext[strlen( $plaintext ) - 1] );
      $plaintext = substr( $plaintext, 0, -$padding );
    }

    return $plaintext;
  }

  /**
   * Callback for the 'get_login' command.
   *
   * @param array $params Array of parameters received with the request.
   *   Expected keys:
   *     'name': The name of the user to log in.
   * @return array Response to be sent back as json to the client.
   */
  function get_login_command( $params ) {
    $error = FALSE;

    if ( empty( $params['name'] ) ) {
      $error = 'User name not specified.';
    }
    else {
      $user = get_user_by( 'login', $params['name'] );

      if ( empty( $user->ID ) ) {
        $error = 'This user does not exist.';
      }
    }

    if ( ! $error ) {
      // Generate token for one-time login.
      $token = wp_generate_password( 20, FALSE );

      delete_user_meta( $user->ID, 'instalogin_token' );
      update_user_meta( $user->ID, 'instalogin_token', $token );

      $login_url = home_url( 'instalogin/auth/' . $user->ID . '/' . $token );

      $response = array(
        'status' => 'ok',
        'data' => $this->encrypt_data( $login_url ),
      );
    }
    else {
      $response = array(
        'status' => 'error',
        'error' => $error,
      );
    }

    return $response;
  }

  /**
   * Callback for the 'validate_users' command.
   *
   * @param array $params Array of parameters received with the request.
   *   Expected keys:
   *     'names': Comma separated list of user names to validate.
   * @return array Response to be sent back as json to the client.
   */
  function validate_users_command( $params ) {
    $error = FALSE;

    if ( empty( $params['names'] ) || ! is_string( $params['names'] ) ) {
      $error = 'User names not specified.';
    }
    else {
      $user_names = explode( ',', $params['names'] );
      $existing_names = array();

      if ( ! empty( $user_names ) ) {
        global $wpdb;

        $placeholders = array_fill( 0, count( $user_names ), '%s' );
        $placeholders = implode( ', ', $placeholders );
        $query = "SELECT user_login FROM $wpdb->users WHERE user_login IN ($placeholders)";

        $existing_names = $wpdb->get_col( $wpdb->prepare( $query, $user_names ) );
      }

      $incorrect_names = array_diff( $user_names, $existing_names );

      if ( empty( $incorrect_names ) ) {
        $result = 'verified';
      }
      else {
        $result = 'invalid:' . implode( ',', $incorrect_names );
      }

    }

    if ( ! $error ) {
      $response = array(
        'status' => 'ok',
        'data' => $this->encrypt_data( $result ),
      );
    }
    else {
      $response = array(
        'status' => 'error',
        'error' => $error,
      );
    }

    return $response;
  }

  /**
   * Processes a one-time login url. Performs a redirection
   * after successful login.
   *
   * @return bool FALSE on failure.
   */
  function process_login() {
    $user = get_user_by( 'id', get_query_var( 'user_id' ) );
    if ( empty( $user->ID ) ) {
      return FALSE;
    }

    if ( is_user_logged_in() ) {
      wp_logout();
    }

    add_filter( 'authenticate', array( $this, 'authenticate' ), 0, 3 );
    $authenticated_user = wp_signon( array( 'user_login' => $user->user_login ) );
    remove_filter( 'authenticate', array( $this, 'authenticate' ), 0, 3 );

    if ( ! empty( $authenticated_user->ID ) ) {
        wp_set_current_user( $authenticated_user->ID, $authenticated_user->user_login );

        // Login succeeded. Redirect to home page.
        wp_redirect( home_url() );
        exit;
    }

    return FALSE;
  }

  /**
   * Callback for the 'authenticate' filter.
   */
  function authenticate( $user, $username, $password ) {

    if ( get_query_var( 'instalogin' ) == 'auth' ) {
      // Authenticate user based on the token in the url.
      $login_user = get_user_by( 'login', $username );

      // Sanity check to make sure the user being authenticated corresponds to the id in the url.
      if ( ! empty( $login_user->ID ) && $login_user->ID == get_query_var( 'user_id' ) ) {
        $user_token = get_user_meta( $login_user->ID, 'instalogin_token', TRUE );

        if ( ! empty( $user_token ) && $user_token === get_query_var( 'auth_token' ) ) {
          return new WP_User( $login_user->ID );
        }

      }
    }

    return $user;
  }

}
