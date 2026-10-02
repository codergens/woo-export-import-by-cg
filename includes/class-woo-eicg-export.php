<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Woo_EICG_Export {
    public function __construct() {
        add_action( 'admin_post_woo_eicg_export', array( $this, 'process_export' ) );
    }

    public function process_export() {
        if ( ! isset( $_POST['woo_eicg_export_nonce'] ) || ! wp_verify_nonce( $_POST['woo_eicg_export_nonce'], 'woo_eicg_export_action' ) ) {
            wp_die( 'Invalid nonce.' );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Unauthorized.' );
        }

        $items = isset( $_POST['export_items'] ) ? array_map( 'sanitize_text_field', $_POST['export_items'] ) : array();
        $start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : '';
        $end_date = isset( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : '';

        if ( empty( $items ) ) {
            wp_die( 'No items selected for export.' );
        }

        $export_data = array(
            'site_url'    => site_url(),
            'export_date' => current_time( 'mysql' ),
            'items'       => array(),
        );

        if ( in_array( 'products', $items ) ) {
            $export_data['items']['products'] = $this->get_products( $start_date, $end_date );
        }

        if ( in_array( 'customers', $items ) ) {
            $export_data['items']['customers'] = $this->get_customers( $start_date, $end_date );
        }

        if ( in_array( 'orders', $items ) ) {
            $export_data['items']['orders'] = $this->get_orders( $start_date, $end_date );
        }

        $json = wp_json_encode( $export_data );

        header( 'Content-Description: File Transfer' );
        header( 'Content-Type: application/json' );
        header( 'Content-Disposition: attachment; filename="woo-export-' . date( 'Y-m-d' ) . '.json"' );
        header( 'Expires: 0' );
        header( 'Cache-Control: must-revalidate' );
        header( 'Pragma: public' );
        header( 'Content-Length: ' . strlen( $json ) );
        
        echo $json;
        exit;
    }

    private function get_products( $start_date, $end_date ) {
        $args = array(
            'limit' => -1,
            'status' => 'publish',
            'return' => 'objects'
        );
        
        if ( ! empty( $start_date ) || ! empty( $end_date ) ) {
            $args['date_created'] = '';
            if ( ! empty( $start_date ) ) {
                $args['date_created'] .= '>=' . $start_date;
            }
            if ( ! empty( $end_date ) ) {
                $args['date_created'] .= ( !empty( $args['date_created'] ) ? '...' : '<=' ) . $end_date;
            }
        }

        $products = wc_get_products( $args );
        $data = array();
        foreach ( $products as $product ) {
            $data[] = array(
                'id' => $product->get_id(),
                'sku' => $product->get_sku(),
                'name' => $product->get_name(),
                'price' => $product->get_price(),
                'regular_price' => $product->get_regular_price(),
                'sale_price' => $product->get_sale_price(),
                'description' => $product->get_description(),
                'short_description' => $product->get_short_description(),
                'manage_stock' => $product->get_manage_stock(),
                'stock_quantity' => $product->get_stock_quantity(),
                'stock_status' => $product->get_stock_status(),
                'type' => $product->get_type(),
            );
        }
        return $data;
    }

    private function get_customers( $start_date, $end_date ) {
        $args = array(
            'role'    => 'customer',
            'orderby' => 'user_registered',
            'order'   => 'ASC',
        );

        if ( ! empty( $start_date ) || ! empty( $end_date ) ) {
            $date_query = array();
            if ( ! empty( $start_date ) ) {
                $date_query['after'] = $start_date;
            }
            if ( ! empty( $end_date ) ) {
                $date_query['before'] = $end_date;
            }
            $date_query['inclusive'] = true;
            $args['date_query'] = array( $date_query );
        }

        $users = get_users( $args );
        $data = array();
        foreach ( $users as $user ) {
            $customer = new WC_Customer( $user->ID );
            $data[] = array(
                'id' => $customer->get_id(),
                'email' => $customer->get_email(),
                'first_name' => $customer->get_first_name(),
                'last_name' => $customer->get_last_name(),
                'billing' => $customer->get_billing(),
                'shipping' => $customer->get_shipping(),
            );
        }
        return $data;
    }

    private function get_orders( $start_date, $end_date ) {
        $args = array(
            'limit' => -1,
            'return' => 'objects',
        );

        if ( ! empty( $start_date ) || ! empty( $end_date ) ) {
            $args['date_created'] = '';
            if ( ! empty( $start_date ) ) {
                $args['date_created'] .= '>=' . $start_date;
            }
            if ( ! empty( $end_date ) ) {
                $args['date_created'] .= ( !empty( $args['date_created'] ) ? '...' : '<=' ) . $end_date;
            }
        }

        $orders = wc_get_orders( $args );
        $data = array();
        foreach ( $orders as $order ) {
            $items = array();
            foreach ( $order->get_items() as $item_id => $item ) {
                $product = $item->get_product();
                $items[] = array(
                    'product_id' => $item->get_product_id(),
                    'sku'        => $product ? $product->get_sku() : '',
                    'name'       => $item->get_name(),
                    'quantity'   => $item->get_quantity(),
                    'total'      => $item->get_total(),
                );
            }

            $data[] = array(
                'id' => $order->get_id(),
                'order_key' => $order->get_order_key(),
                'customer_id' => $order->get_customer_id(),
                'customer_email' => $order->get_billing_email(),
                'status' => $order->get_status(),
                'currency' => $order->get_currency(),
                'date_created' => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
                'billing' => $order->get_address( 'billing' ),
                'shipping' => $order->get_address( 'shipping' ),
                'items' => $items,
                'total' => $order->get_total(),
            );
        }
        return $data;
    }
}
