<?php
/**
 * Plugin Name: Woo Export Import by CG
 * Plugin URI:  https://codergens.com/
 * Description: Export and Import WooCommerce items like orders, customers, products with date range selection and conflict detection.
 * Version:     1.0.0
 * Author:      CoderGens
 * Author URI:  https://codergens.com/
 * Text Domain: woo-export-import-by-cg
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'WOO_EICG_VERSION', '1.0.0' );
define( 'WOO_EICG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WOO_EICG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WOO_EICG_PLUGIN_DIR . 'includes/class-woo-eicg-main.php';

function woo_eicg_init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'woo_eicg_missing_wc_notice' );
        return;
    }
    Woo_EICG_Main::get_instance();
}
add_action( 'plugins_loaded', 'woo_eicg_init' );

function woo_eicg_missing_wc_notice() {
    echo '<div class="error"><p>' . esc_html__( 'Woo Export Import by CG requires WooCommerce to be installed and active.', 'woo-export-import-by-cg' ) . '</p></div>';
}
