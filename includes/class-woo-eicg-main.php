<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Woo_EICG_Main {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    private function includes() {
        require_once WOO_EICG_PLUGIN_DIR . 'includes/class-woo-eicg-admin.php';
        require_once WOO_EICG_PLUGIN_DIR . 'includes/class-woo-eicg-export.php';
        require_once WOO_EICG_PLUGIN_DIR . 'includes/class-woo-eicg-import.php';
    }

    private function init_hooks() {
        new Woo_EICG_Admin();
        new Woo_EICG_Export();
        new Woo_EICG_Import();
    }
}
