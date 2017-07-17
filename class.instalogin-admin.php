<?php

class InstaLogin_Admin {

  /**
   * Initializes actions and filters.
   */
  function init() {
    add_action( 'admin_init', array($this, 'admin_init') );
    add_action( 'admin_menu', array($this, 'admin_menu') );
    add_action( 'admin_notices', array($this, 'admin_notices') );
  }

  /**
   * Callback for the 'admin_init' action.
   */
  function admin_init() {
    // Add action to process the submission of the options page.
    // 'save_instalogin_options' is the name of the 'action' variable in the form.
    add_action( 'admin_post_save_instalogin_options', array( $this, 'process_options' ) );
  }

  /**
   * Callback for the 'admin_menu' action.
   */
  function admin_menu() {
    // Add link in the 'Settings' menu.
    add_options_page( 'InstaLogin Configuration', 'InstaLogin', 'manage_options', 'instalogin-admin', array( $this, 'options_page' ) );
  }

  /**
   * Callback for the 'admin_notices' action.
   */
  function admin_notices() {
    // Check if mcrypt is available.
    if ( ! function_exists( 'mcrypt_encrypt' ) ) {
      ?>
        <div class="error"><p><?php _e( 'The Mcrypt library is required by InstaLogin. Please install it.' ); ?></p></div>
      <?php
    }

    if ( empty( $_GET['m'] ) ) {
      return;
    }
    if ( $_GET['m'] == 'updated' ) {
      ?>
        <div class="updated"><p><?php _e( 'Settings saved.' ); ?></p></div>
      <?php
    }
    if ( $_GET['m'] == 'regenerated' ) {
      ?>
        <div class="updated"><p><?php _e( 'The secret key has been regenerated.' ); ?></p></div>
      <?php
    }

  }

  /**
   * Content callback for the options page.
   */
  function options_page() {
    $secret = get_option( 'instalogin_secret_key' );
    $enabled = get_option( 'instalogin_enabled' );

    ?>

    <h2>InstaLogin</h2>

    <form method="post" action="admin-post.php">
    <input type="hidden" name="action" value="save_instalogin_options" />
    <?php wp_nonce_field( 'instalogin' ); ?>

    <table class="form-table">

    <tr>
      <th scope="row">
        <label><?php _e('InstaLogin authentication') ?></label>
      </th>
      <td>
        <input type="checkbox" name="instalogin_enabled" <?php if ($enabled ) echo ' checked="checked" '; ?>/>Enabled
        <p class="description">If enabled, those who have the secret key will be able to log in to the site as any user using the InstaLogin app.</p>
      </td>
    </tr>

    <tr>
      <th scope="row">
        <label><?php _e('Secret key') ?></label>
      </th>
      <td>
        <input readonly="readonly" type="text" name="instalogin_secret_key" size="45" value="<?php echo esc_html( $secret ); ?>"/>
        <p class="description">Enter this secret key in the InstaLogin app. Generate a new key using the button below if you need to revoke access from the apps using this key.</p>
        <input type="submit" name="instalogin_regenerate" value="Regenerate Secret Key" class="button-primary"/>
      </td>
    </tr>

    </table>

    <input type="submit" value="Save Changes" class="button-primary"/>

    </form>

    <?php

  }

  /**
   * Submit callback for the options page.
   */
  function process_options() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( 'Access denied.' );
    }

    check_admin_referer( 'instalogin' );

    $message = '';

    if ( ! empty( $_POST['instalogin_regenerate'] ) ) {
      // 'Regenerate secret key' button was pressed.
      require_once( INSTALOGIN_PLUGIN_DIR . 'class.instalogin-service.php' );

      $instalogin_service = new Instalogin_Service();
      $instalogin_service->generate_secret_key();

      $message = 'regenerated';
    }
    else {

      if ( isset( $_POST['instalogin_secret_key'] ) ) {
        update_option( 'instalogin_secret_key', sanitize_text_field( $_POST['instalogin_secret_key'] ) );
      }

      if ( isset( $_POST['instalogin_enabled'] ) ) {
        update_option( 'instalogin_enabled', TRUE );
      }
      else {
        update_option( 'instalogin_enabled', FALSE );
      }

      $message = 'updated';
    }

    wp_redirect( add_query_arg( array( 'page' => 'instalogin-admin', 'm' => $message ), admin_url( 'options-general.php' ) ) );

    exit;
  }

}