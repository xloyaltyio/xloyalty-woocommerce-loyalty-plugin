/**
 * Plugin Name: xLoyalty – Points & Rewards for WooCommerce
 * Description: Simple loyalty points & rewards for WooCommerce customers.
 * Version: 1.1.5
 * Author: xLoyalty.io
 * Author URI: https://www.xloyalty.io
 * License: GPLv2 or later
 * Text Domain: xloyalty
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 */


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('init', function () {
    if (class_exists('WooCommerce') && !WC()->session) {
        WC()->initialize_session(); // Εξαναγκάζει το session να ενεργοποιηθεί
        error_log('Xloyalty: WC session manually initialized.');
    }
}, 1);


/* ======================================================
 * Helper Function: Check if Plugin is Activated
 ====================================================== */
function xloyalty_is_plugin_activated() {
    $license_key = get_option('xloyalty_license_key');
    // Debugging (προαιρετικά):
     error_log('License Key Retrieved: ' . $license_key);
     error_log('License Key Length: ' . strlen($license_key));

    return (!empty($license_key) && strlen($license_key) === 64);
}

/* ======================================================
 * Check License Key Before Plugin Initialization
 ====================================================== */
add_action('init', 'xloyalty_check_license_key');
function xloyalty_check_license_key() {
    if (!xloyalty_is_plugin_activated()) {
        add_action('admin_init', function () {
            if (!isset($_GET['page']) || ($_GET['page'] !== 'xloyalty-register' && $_GET['page'] !== 'xloyalty-skip-activation')) {
                wp_safe_redirect(admin_url('admin.php?page=xloyalty-register'));
                exit;
            }
        });
    }
}


/* ======================================================
 * Display Admin Notices Based on Activation Status
 ====================================================== */
add_action('admin_notices', 'xloyalty_admin_notices');
function xloyalty_admin_notices() {
    if ( xloyalty_is_plugin_activated() ) {
        echo '<div class="notice notice-success"><p>' . esc_html__( 'Xloyalty plugin is activated. Enjoy using the plugin!', 'xloyalty' ) . '</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Xloyalty plugin is not activated. Please complete the registration form or skip activation to proceed.', 'xloyalty' ) . '</p></div>';
    }
}


add_shortcode( 'xloyalty_points', function () {
    if ( ! is_user_logged_in() ) {
        return esc_html__( 'Please log in to view your loyalty points.', 'xloyalty' );
    }

    $user_email = wp_get_current_user()->user_email;
    $points     = xloyalty_get_user_points( $user_email );

    // translators: %d is the number of loyalty points.
    return sprintf( esc_html__( 'You have %d Xloyalty.io points.', 'xloyalty' ), (int) $points );
} );

add_action('woocommerce_account_dashboard', 'xloyalty_display_points_history');
function xloyalty_display_points_history() {
    if ( ! is_user_logged_in() ) {
        return;
    }

    global $wpdb;
    $user_email = sanitize_email( wp_get_current_user()->user_email );
    $table_name = $wpdb->prefix . 'xloyalty_io_loyalty_transactions';

    $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE customer_email = %s ORDER BY transaction_date DESC", $user_email ) );

    if ( $results ) {
        echo '<h3>' . esc_html__( 'Your loyalty points history', 'xloyalty' ) . '</h3>';
        echo '<table class="shop_table shop_table_responsive">';
        echo '<thead><tr><th>' . esc_html__( 'Date', 'xloyalty' ) . '</th><th>' . esc_html__( 'Order', 'xloyalty' ) . '</th><th>' . esc_html__( 'Points', 'xloyalty' ) . '</th></tr></thead><tbody>';
        foreach ( $results as $row ) {
            echo '<tr>';
            $timestamp = strtotime( $row->transaction_date );
            echo '<td>' . esc_html( gmdate( 'd/m/Y H:i', $timestamp ) ) . '</td>';
            echo '<td>#' . esc_html( $row->woo_order_id ) . '</td>';
            echo '<td>' . esc_html( $row->points ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
}



/* ======================================================
 * Activation Hook to Redirect to Registration Form
 ====================================================== */
register_activation_hook(__FILE__, 'xloyalty_plugin_activate');
function xloyalty_plugin_activate() {
    add_option('xloyalty_activation_redirect', true);
}

/* ======================================================
 * Redirect to Registration Page After Activation
 ====================================================== */
add_action('admin_init', 'xloyalty_redirect_after_activation');
function xloyalty_redirect_after_activation() {
    if (get_option('xloyalty_activation_redirect', false)) {
        delete_option('xloyalty_activation_redirect');
        if (!isset($_GET['activate-multi'])) {
            wp_safe_redirect(admin_url('admin.php?page=xloyalty-register'));
            exit;
        }
    }
}

/* ======================================================
 * Add Registration Page to Admin Menu
 ====================================================== */
add_action('admin_menu', 'xloyalty_add_registration_page');
function xloyalty_add_registration_page() {
    add_menu_page(
        'Xloyalty Registration',
        'Xloyalty Registration',
        'manage_options',
        'xloyalty-register',
        'xloyalty_render_registration_form',
        'dashicons-admin-users',
        99
    );
}


/**
 * Enqueue admin script for xLoyalty registration page.
 */
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'xloyalty-register' ) {
        wp_enqueue_script(
            'xloyalty-admin-js',
            plugins_url( 'assets/js/xloyalty-admin.js', __FILE__ ),
            array(),
            '1.1.5',
            true
        );
    }
});

/* ======================================================
 * Render Registration Form
 ====================================================== */
function xloyalty_render_registration_form() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // -- ΑΦΑΙΡΕΘΗΚΕ ΤΟ if (isset($_POST['xloyalty_skip_activation'])) -- //

   if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['xloyalty_register'])) {
    // Συλλογή και απλή "απολύμανση" (sanitize) όλων των δεδομένων της φόρμας
    $company_name   = sanitize_text_field($_POST['company_name']);
    $eshop_url      = sanitize_text_field($_POST['eshop_url']);
    $contact_name   = sanitize_text_field($_POST['contact_name']);
    $business_phone = sanitize_text_field($_POST['business_phone']);
    $email          = sanitize_email($_POST['email']);
    $street         = sanitize_text_field($_POST['street']);
    $city           = sanitize_text_field($_POST['city']);

    // Εδώ θα μπορούσες να κάνεις οτιδήποτε με τα δεδομένα,
    // π.χ. να τα στείλεις σε API ή με email
    // ...
    
    echo '<div class="notice notice-success">
            <p>Thank you for registering. We will be in touch.</p>
          </div>';
}

    ?>
   <div class="wrap">
        <h1><?php esc_html_e('Register Your Xloyalty Plugin Free Version', 'xloyalty'); ?></h1>
        <p>
            <?php esc_html_e('Welcome to the free version of the xloyalty.io loyalty plugin for WooCommerce! To activate the plugin, simply fill out the form below so we can learn more about you. As part of the activation process, we may contact you to conduct a satisfaction survey regarding the free xloyalty.io plugin and to present the features of the xloyalty.io premium plugin. You will also receive a special discount for upgrading to the premium version, which includes advanced features for your e-shop and physical store. For more information, visit <a href="https://www.xloyalty.io" target="_blank">https://www.xloyalty.io</a>.', 'xloyalty'); ?>
        </p>
        <p>
            <?php esc_html_e('By activating the plugin, you agree to our <a href="https://www.xloyalty.io/terms/" target="_blank">Terms of Use and Privacy Policy</a>. There are no financial or other obligations for activating the free version of the xloyalty plugin for your eShop. The only purpose of contacting you will be to conduct a satisfaction survey and to provide free support via email.', 'xloyalty'); ?>
        </p>
        <p>
            <?php esc_html_e('For free support, please contact us at <a href="mailto:support@xloyalty.io">support@xloyalty.io</a>. We will respond within 5 business days.', 'xloyalty'); ?>
        </p>
    </div>

    <form method="post" id="xloyalty-form">
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Company Name', 'xloyalty'); ?></th>
                <td><input type="text" name="company_name" id="company_name"></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Eshop URL', 'xloyalty'); ?></th>
                <td><input type="url" name="eshop_url" id="eshop_url"></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Contact Name', 'xloyalty'); ?></th>
                <td><input type="text" name="contact_name" id="contact_name"></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Business Phone', 'xloyalty'); ?></th>
                <td><input type="text" name="business_phone" id="business_phone"></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Email', 'xloyalty'); ?></th>
                <td><input type="email" name="email" id="email"></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Street Address', 'xloyalty'); ?></th>
                <td><input type="text" name="street" id="street"></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('City', 'xloyalty'); ?></th>
                <td><input type="text" name="city" id="city"></td>
            </tr>
        </table>
        <p>
            <button type="submit" name="xloyalty_register" id="xloyalty_register" class="button button-primary">
                <?php esc_html_e('Register', 'xloyalty'); ?>
            </button>

            <!-- Skip Activation is now just a button that redirects. -->
            <a href="<?php echo esc_url(admin_url('admin.php?page=xloyalty-skip-activation')); ?>" class="button button-secondary">
    <?php esc_html_e('Skip Activation', 'xloyalty'); ?>
</a>
        </p>
    </form>

    
    <?php
}

/* ======================================================
 * Κρυφή σελίδα Skip Activation
 ====================================================== */
add_action('admin_menu', function () {
    // Add a hidden page (not visible in the menu)
    add_submenu_page(
        null,
        'Xloyalty Skip Activation',
        'Xloyalty Skip Activation',
        'manage_options',
        'xloyalty-skip-activation',
        'xloyalty_render_skip_activation_page'
    );
});

if (!function_exists('xloyalty_apply_points_discount_checkout')) {
    function xloyalty_apply_points_discount_checkout() {
        if (!is_user_logged_in()) {
            return;
        }

        if (!WC()->session) {
            WC()->initialize_session();
        }

        // Έλεγχος αν έχουν σταλεί πόντοι από POST (π.χ. κατά την αλλαγή επιλογών στο checkout)
        if (isset($_POST['loyaltyx_points_to_apply'])) {
            $points_to_apply = intval($_POST['loyaltyx_points_to_apply']);
            WC()->session->set('loyalty_points_to_apply', $points_to_apply);
        }

        // Optionally: refresh user points + log για debugging
        $user_email = wp_get_current_user()->user_email;
        $available_points = xloyalty_get_user_points($user_email);

        error_log("[Xloyalty] Checkout updated. Points to apply: " . $points_to_apply . " / Available: " . $available_points);
    }
}

function xloyalty_render_skip_activation_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Δημιουργούμε license key αν δεν υπάρχει ήδη
    $timestamp = time();
    $license_key = hash('sha256', $timestamp);  // 64 χαρακτήρες
    $result = update_option('xloyalty_license_key', $license_key);

    // Προσθήκη στο log για debugging
    error_log('[SKIP ACTIVATION] New License Key Generated: ' . $license_key);
    error_log('[SKIP ACTIVATION] Update Option Result: ' . ($result ? 'Success' : 'Failure'));

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Xloyalty Plugin Activation Skipped', 'xloyalty'); ?></h1>
        <p>
            <?php esc_html_e('Your registration was completed locally with a free license key. No information was sent to xloyalty.io server.', 'xloyalty'); ?>
        </p>
        <p>
            <?php esc_html_e('You can use the xloyalty.io free version plugin now, without free support. If you need free support from xloyalty io team, you have to <a href="admin.php?page=xloyalty-register">register for free</a>. If you decide to learn more about the Xloyalty.io premium plugin, please visit our website:', 'xloyalty'); ?>
            <a href="https://www.xloyalty.io" target="_blank">https://www.xloyalty.io</a>.
        </p>
    </div>
    <?php
}


/* ======================================================
 * Παρακάτω ο υπόλοιπος κώδικας για το WooCommerce loyalty
 * (το άφησα σχεδόν ως έχει).
 ====================================================== */

add_action('init', function () {
    if (!WC()->session) {
        error_log('WooCommerce session handler is NOT active.');
    } else {
        error_log('WooCommerce session handler is active.');
    }
}, 1);

// Activation Hook to Create Database Table
function xloyalty_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'xloyalty_io_loyalty_transactions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id INT NOT NULL AUTO_INCREMENT,
        money FLOAT NOT NULL,
        points INT NOT NULL,
        transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        woo_order_id INT NOT NULL,
        customer_email VARCHAR(255) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'xloyalty_create_table');

// Add Plugin Settings Page
function xloyalty_add_settings_page() {
    add_options_page(
        'Xloyalty.io Settings',
        'Xloyalty.io',
        'manage_options',
        'xloyalty-io-settings',
        'xloyalty_render_settings_page'
    );
}
add_action('admin_menu', 'xloyalty_add_settings_page');

function xloyalty_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( isset( $_POST['xloyalty_save_settings'] ) ) {
        check_admin_referer( 'xloyalty_save_settings_action', 'xloyalty_save_settings_nonce' );

        $points_per_euro = isset( $_POST['xloyalty_points_per_euro'] ) ? (int) $_POST['xloyalty_points_per_euro'] : 0;
        $conversion_rate = isset( $_POST['xloyalty_conversion_rate'] ) ? (int) $_POST['xloyalty_conversion_rate'] : 0;

        update_option( 'xloyalty_points_per_euro', $points_per_euro );
        update_option( 'xloyalty_conversion_rate', $conversion_rate );

        echo '<div class="updated"><p>' . esc_html__( 'Settings saved.', 'xloyalty' ) . '</p></div>';
    }

    $points_per_euro = (int) get_option( 'xloyalty_points_per_euro', 1 );
    $conversion_rate = (int) get_option( 'xloyalty_conversion_rate', 10 );

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'xLoyalty settings', 'xloyalty' ) . '</h1>';
    echo '<form method="post">';
    wp_nonce_field( 'xloyalty_save_settings_action', 'xloyalty_save_settings_nonce' );
    echo '<p><label for="xloyalty_points_per_euro">' . esc_html__( 'Points per Euro', 'xloyalty' ) . '</label><br />';
    echo '<input type="number" id="xloyalty_points_per_euro" name="xloyalty_points_per_euro" value="' . esc_attr( $points_per_euro ) . '" min="0" step="1" /></p>';
    echo '<p><label for="xloyalty_conversion_rate">' . esc_html__( 'Points conversion rate (points for 1 Euro)', 'xloyalty' ) . '</label><br />';
    echo '<input type="number" id="xloyalty_conversion_rate" name="xloyalty_conversion_rate" value="' . esc_attr( $conversion_rate ) . '" min="1" step="1" /></p>';
    echo '<p><input type="submit" name="xloyalty_save_settings" value="' . esc_attr__( 'Save settings', 'xloyalty' ) . '" class="button button-primary" /></p>';
    echo '</form>';
    echo '</div>';
}



// Add Settings Link on Plugins Page
function xloyalty_add_settings_link( $links ) {
    $url           = admin_url( 'options-general.php?page=xloyalty-io-settings' );
    $settings_link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'xloyalty' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}


add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'xloyalty_add_settings_link');

// Track WooCommerce Order Completion
add_action('woocommerce_order_status_completed', 'xloyalty_track_order_completion');
function xloyalty_track_order_completion($order_id) {
    global $wpdb;
    $order = wc_get_order($order_id);
    $total = $order->get_total();
    $points_per_euro = get_option('xloyalty_points_per_euro', 1);
    $points = intval($total * $points_per_euro);
    $customer_email = $order->get_billing_email();

    $table_name = $wpdb->prefix . 'xloyalty_io_loyalty_transactions';
    $wpdb->insert($table_name, [
        'money'          => $total,
        'points'         => $points,
        'woo_order_id'   => $order_id,
        'customer_email' => $customer_email
    ]);

    // Apply points used during checkout
    $points_to_apply = WC()->session->get('loyalty_points_to_apply');
    if ($points_to_apply > 0) {
        $wpdb->insert($table_name, [
            'money'          => 0,
            'points'         => -$points_to_apply,
            'transaction_date' => current_time('mysql'),
            'woo_order_id'   => $order_id,
            'customer_email' => $customer_email
        ]);
        WC()->session->__unset('loyalty_points_to_apply');
    }
}

// Display Points in Cart and Profile
add_action('woocommerce_before_cart', 'xloyalty_display_points_in_cart');
add_action('woocommerce_account_dashboard', 'xloyalty_display_points_in_profile');

function xloyalty_display_points_in_cart() {
    if ( is_user_logged_in() ) {
        $user_email = sanitize_email( wp_get_current_user()->user_email );
        $points     = (int) xloyalty_get_user_points( $user_email );

        echo '<div class="xloyalty-points">' . esc_html__( 'xLoyalty points:', 'xloyalty' ) . ' ' . esc_html( $points ) . '</div>';
        echo '<form method="post">';
        echo '<label for="loyaltyx_points_to_apply">' . esc_html__( 'Redeem points', 'xloyalty' ) . '</label>';
        echo '<input type="number" name="loyaltyx_points_to_apply" id="loyaltyx_points_to_apply" value="0" min="0" max="' . esc_attr( $points ) . '" />';
        echo '<button type="submit">' . esc_html__( 'Apply points', 'xloyalty' ) . '</button>';
        echo '</form>';
    }
}

function xloyalty_display_points_in_profile() {
    if ( is_user_logged_in() ) {
        $user_email = sanitize_email( wp_get_current_user()->user_email );
        $points     = (int) xloyalty_get_user_points( $user_email );

        echo '<h2>' . esc_html__( 'Your xLoyalty points', 'xloyalty' ) . '</h2>';
        echo '<p>' . esc_html( $points ) . ' ' . esc_html__( 'points', 'xloyalty' ) . '</p>';
    }
}

function xloyalty_get_user_points( $email ) {
    global $wpdb;

    $email      = sanitize_email( $email );
    $table_name = $wpdb->prefix . 'xloyalty_io_loyalty_transactions';

    $points = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(points) FROM {$table_name} WHERE customer_email = %s", $email ) );

    return $points ? (int) $points : 0;
}


// Points Redemption
add_action('woocommerce_cart_calculate_fees', 'xloyalty_apply_points_discount', 20, 1);
//add_action('woocommerce_checkout_update_order_review', 'xloyalty_apply_points_discount_checkout', 10, 1);

function xloyalty_apply_points_discount($cart) {
    if (!is_user_logged_in() || is_admin()) {
        return;
    }

    $user_email      = wp_get_current_user()->user_email;
    $points_to_apply = WC()->session->get('loyalty_points_to_apply', 0);
    $available_points = xloyalty_get_user_points($user_email);

    if ($points_to_apply > 0 && $points_to_apply <= $available_points) {
        $conversion_rate = get_option('xloyalty_conversion_rate', 10);
        $discount        = $points_to_apply / $conversion_rate;

        // Ensure the discount does not exceed the cart total
        if ($discount > $cart->get_subtotal()) {
            $discount = $cart->get_subtotal();
        }

        $cart->add_fee( esc_html__( 'Loyalty points discount', 'xloyalty' ), -$discount, true );
    }
}

// Store points to apply in WooCommerce session
add_action('woocommerce_cart_updated', 'xloyalty_store_points_in_session');
function xloyalty_store_points_in_session() {
    if (isset($_POST['loyaltyx_points_to_apply'])) {
        $points_to_apply = intval($_POST['loyaltyx_points_to_apply']);
        WC()->session->set('loyalty_points_to_apply', $points_to_apply);
    }
}

// Display points redemption field on checkout page
add_action('woocommerce_review_order_before_payment', 'xloyalty_display_points_redemption_field');
function xloyalty_display_points_redemption_field() {
    if ( ! is_user_logged_in() ) {
        return;
    }

    $user_email       = sanitize_email( wp_get_current_user()->user_email );
    $available_points = (int) xloyalty_get_user_points( $user_email );
    $points_to_apply  = (int) WC()->session->get( 'loyalty_points_to_apply', 0 );

    echo '<div class="xloyalty-redemption">';
    echo '<h3>' . esc_html__( 'Redeem your points', 'xloyalty' ) . '</h3>';
    echo '<form method="post">';
    /* translators: %d is the number of available points. */
    echo '<label for="loyaltyx_points_to_apply">' . sprintf( esc_html__( 'You have %d points available.', 'xloyalty' ), (int) $available_points ) . '</label>';
    echo '<input type="number" name="loyaltyx_points_to_apply" id="loyaltyx_points_to_apply" value="' . esc_attr( $points_to_apply ) . '" min="0" max="' . esc_attr( $available_points ) . '" />';
    echo '<button type="submit">' . esc_html__( 'Apply points', 'xloyalty' ) . '</button>';
    echo '</form>';
    echo '</div>';
}



// Ensure points discount is applied during checkout
add_action('woocommerce_checkout_create_order', 'xloyalty_apply_points_to_order', 10, 1);
function xloyalty_apply_points_to_order( $order ) {
    global $wpdb;

    $user_email       = sanitize_email( wp_get_current_user()->user_email );
    $points_to_apply  = (int) WC()->session->get( 'loyalty_points_to_apply', 0 );
    $available_points = (int) xloyalty_get_user_points( $user_email );

    if ( $points_to_apply > 0 && $points_to_apply <= $available_points ) {
        $conversion_rate = (int) get_option( 'xloyalty_conversion_rate', 10 );
        $discount        = $points_to_apply / $conversion_rate;

        // Log the redemption in the database.
        $table_name = $wpdb->prefix . 'xloyalty_io_loyalty_transactions';

        $wpdb->insert(
            $table_name,
            [
                'money'           => 0,
                'points'          => -$points_to_apply,
                'transaction_date'=> current_time( 'mysql' ),
                'woo_order_id'    => $order->get_id(),
                'customer_email'  => $user_email,
            ]
        );

        $discount_formatted = number_format( $discount, 2 );

$note = sprintf(
        /* translators: 1: points used, 2: discount amount. */
        esc_html__( 'Loyalty points discount applied: %1$d points for a discount of %2$s.', 'xloyalty' ),
        $points_to_apply,
        $discount_formatted
    );

        $order->add_order_note( $note );
    }
}



// Add a custom column for Loyalty Points in WooCommerce Customers Table
add_filter('manage_users_columns', 'xloyalty_add_points_column');
function xloyalty_add_points_column($columns) {
    $columns['loyalty_points'] = esc_html__( 'Loyalty Points', 'xloyalty' );
    return $columns;
}

add_action('manage_users_custom_column', 'xloyalty_show_points_column_data', 10, 3);
function xloyalty_show_points_column_data( $value, $column_name, $user_id ) {
    if ( 'loyalty_points' === $column_name ) {
        $user       = get_userdata( $user_id );
        $user_email = $user ? sanitize_email( $user->user_email ) : '';
        $points     = xloyalty_get_user_points( $user_email );

        return $points ? (int) $points : 0;
    }
    return $value;
}

// Make the new column sortable
add_filter('manage_users_sortable_columns', 'xloyalty_sortable_points_column');
function xloyalty_sortable_points_column($columns) {
    $columns['loyalty_points'] = 'loyalty_points';
    return $columns;
}

/**
 * Προσθήκη στήλης «Loyalty Points» στη λίστα Πελατών (WooCommerce → Πελάτες)
 */
add_filter('woocommerce_admin_customers_columns', 'xloyalty_add_customer_points_column');
function xloyalty_add_customer_points_column($columns) {
    $columns['loyalty_points'] = esc_html__( 'Loyalty Points', 'xloyalty' );
    return $columns;
}

/**
 * Εμφάνιση των πόντων στη στήλη «Loyalty Points»
 */
add_action('woocommerce_admin_customers_column_loyalty_points', 'xloyalty_show_customer_points');
function xloyalty_show_customer_points( $customer ) {
    $user_email = sanitize_email( $customer->get_email() );
    $points     = xloyalty_get_user_points( $user_email );
    echo $points ? (int) $points : 0;
}

add_action( 'admin_init', function() {
    if ( is_admin() && current_user_can('activate_plugins') && ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'xLoyalty requires WooCommerce to be installed and active.', 'xloyalty' ) . '</p></div>';
        } );
    }
} );
