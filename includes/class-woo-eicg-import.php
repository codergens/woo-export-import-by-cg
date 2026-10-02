<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Woo_EICG_Import {
    public function __construct() {
        add_action( 'admin_post_woo_eicg_import', array( $this, 'process_import' ) );
    }

    public function process_import() {
        if ( ! isset( $_POST['woo_eicg_import_nonce'] ) || ! wp_verify_nonce( $_POST['woo_eicg_import_nonce'], 'woo_eicg_import_action' ) ) {
            wp_die( 'Invalid nonce.' );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Unauthorized.' );
        }

        $action = isset( $_POST['import_action'] ) ? sanitize_text_field( $_POST['import_action'] ) : 'validate';

        if ( 'validate' === $action ) {
            if ( empty( $_FILES['import_file']['tmp_name'] ) ) {
                wp_redirect( add_query_arg( array( 'page' => 'woo-eicg', 'error' => 'no_file' ), admin_url( 'admin.php' ) ) );
                exit;
            }

            $file_contents = file_get_contents( $_FILES['import_file']['tmp_name'] );
            $data = json_decode( $file_contents, true );

            if ( ! $data || ! isset( $data['items'] ) ) {
                wp_redirect( add_query_arg( array( 'page' => 'woo-eicg', 'error' => 'invalid_format' ), admin_url( 'admin.php' ) ) );
                exit;
            }
            
            $this->validate_import( $data );
        } elseif ( 'process' === $action ) {
            $data = get_transient( 'woo_eicg_import_data_' . get_current_user_id() );
            if ( ! $data ) {
                 wp_redirect( add_query_arg( array( 'page' => 'woo-eicg', 'error' => 'session_expired' ), admin_url( 'admin.php' ) ) );
                 exit;
            }
            $this->do_import( $data );
        }
    }

    private function validate_import( $data ) {
        $conflicts = array();
        
        // Check product conflicts if orders are being imported
        if ( ! empty( $data['items']['orders'] ) ) {
            $missing_products = array();
            foreach ( $data['items']['orders'] as $order ) {
                foreach ( $order['items'] as $item ) {
                    if ( ! empty( $item['sku'] ) ) {
                        $product_id = wc_get_product_id_by_sku( $item['sku'] );
                        if ( ! $product_id ) {
                            $missing_products[] = $item['sku'];
                        }
                    } else {
                        // Attempt to match by name
                        $product = get_page_by_title( $item['name'], OBJECT, 'product' );
                        if ( ! $product ) {
                             $missing_products[] = $item['name'] . ' (Name)';
                        }
                    }
                }
            }
            
            if ( ! empty( $missing_products ) ) {
                $missing_products = array_unique( $missing_products );
                $conflicts[] = sprintf(
                    /* translators: %s: list of missing products */
                    __( '<strong>Conflict Detected:</strong> The imported orders contain products that are missing on this site. Please ensure both sites have the same products.<br><br><strong>Missing Products (SKU/Name):</strong> %s<br><br><strong>Instruction:</strong> Please export Products from the source site and import them here first before importing Orders.', 'woo-export-import-by-cg' ),
                    implode( ', ', $missing_products )
                );
            }
        }
        
        // Store data in a transient for actual import process to avoid re-upload
        set_transient( 'woo_eicg_import_data_' . get_current_user_id(), $data, 15 * MINUTE_IN_SECONDS );

        if ( empty( $conflicts ) ) {
            wp_redirect( add_query_arg( array( 'page' => 'woo-eicg', 'validate' => 'success' ), admin_url( 'admin.php' ) ) );
        } else {
            set_transient( 'woo_eicg_conflicts_' . get_current_user_id(), $conflicts, 15 * MINUTE_IN_SECONDS );
            wp_redirect( add_query_arg( array( 'page' => 'woo-eicg', 'validate' => 'conflict' ), admin_url( 'admin.php' ) ) );
        }
        exit;
    }

    private function do_import( $data ) {
        // Products
        if ( ! empty( $data['items']['products'] ) ) {
            foreach ( $data['items']['products'] as $prod_data ) {
                $existing_id = wc_get_product_id_by_sku( $prod_data['sku'] );
                if ( ! $existing_id && ! empty( $prod_data['sku'] ) ) {
                    $product = new WC_Product_Simple();
                    $product->set_name( $prod_data['name'] );
                    $product->set_sku( $prod_data['sku'] );
                    $product->set_regular_price( $prod_data['regular_price'] );
                    $product->set_sale_price( $prod_data['sale_price'] );
                    $product->set_description( $prod_data['description'] );
                    $product->set_short_description( $prod_data['short_description'] );
                    $product->set_manage_stock( $prod_data['manage_stock'] );
                    $product->set_stock_quantity( $prod_data['stock_quantity'] );
                    $product->set_stock_status( $prod_data['stock_status'] );
                    $product->save();
                }
            }
        }
        
        // Customers
        if ( ! empty( $data['items']['customers'] ) ) {
             foreach ( $data['items']['customers'] as $cust_data ) {
                 $user = get_user_by( 'email', $cust_data['email'] );
                 if ( ! $user ) {
                     $user_id = wc_create_new_customer( $cust_data['email'], '', '', array(
                         'first_name' => $cust_data['first_name'],
                         'last_name'  => $cust_data['last_name'],
                     ) );
                     if ( ! is_wp_error( $user_id ) ) {
                         $customer = new WC_Customer( $user_id );
                         $customer->set_billing( $cust_data['billing'] );
                         $customer->set_shipping( $cust_data['shipping'] );
                         $customer->save();
                     }
                 }
             }
        }
        
        // Orders
        if ( ! empty( $data['items']['orders'] ) ) {
            foreach ( $data['items']['orders'] as $order_data ) {
                $order = wc_create_order();
                $order->set_address( $order_data['billing'], 'billing' );
                $order->set_address( $order_data['shipping'], 'shipping' );
                
                // Add items
                foreach ( $order_data['items'] as $item_data ) {
                    $product_id = false;
                    if ( ! empty( $item_data['sku'] ) ) {
                        $product_id = wc_get_product_id_by_sku( $item_data['sku'] );
                    }
                    if ( ! $product_id ) {
                         $product_obj = get_page_by_title( $item_data['name'], OBJECT, 'product' );
                         if ( $product_obj ) {
                             $product_id = $product_obj->ID;
                         }
                    }
                    if ( $product_id ) {
                        $order->add_product( wc_get_product( $product_id ), $item_data['quantity'] );
                    } else {
                        // Create a dummy item if product doesn't exist
                        $item = new WC_Order_Item_Product();
                        $item->set_name( $item_data['name'] );
                        $item->set_quantity( $item_data['quantity'] );
                        $item->set_subtotal( $item_data['total'] );
                        $item->set_total( $item_data['total'] );
                        $order->add_item( $item );
                    }
                }
                
                $order->calculate_totals();
                $order->update_status( $order_data['status'], 'Imported order', true );
                
                // Check if customer email exists and link order
                if ( ! empty( $order_data['customer_email'] ) ) {
                    $user = get_user_by( 'email', $order_data['customer_email'] );
                    if ( $user ) {
                        $order->set_customer_id( $user->ID );
                    }
                }
                
                $order->save();
            }
        }
        
        delete_transient( 'woo_eicg_import_data_' . get_current_user_id() );
        wp_redirect( add_query_arg( array( 'page' => 'woo-eicg', 'import' => 'success' ), admin_url( 'admin.php' ) ) );
        exit;
    }
}
