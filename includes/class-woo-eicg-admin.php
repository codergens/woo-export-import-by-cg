<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Woo_EICG_Admin {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Export/Import by CG', 'woo-export-import-by-cg' ),
            __( 'Export/Import by CG', 'woo-export-import-by-cg' ),
            'manage_woocommerce',
            'woo-eicg',
            array( $this, 'render_admin_page' )
        );
    }

    public function enqueue_scripts( $hook ) {
        if ( 'woocommerce_page_woo-eicg' !== $hook ) {
            return;
        }
        // wp_enqueue_style( 'woo-eicg-admin', WOO_EICG_PLUGIN_URL . 'assets/css/admin.css', array(), WOO_EICG_VERSION );
    }

    public function render_admin_page() {
        require_once WOO_EICG_PLUGIN_DIR . 'views/admin-page.php';
    }
}
