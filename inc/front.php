<?php
namespace Lowest_Price;
use Lowest_Price;
use WC_Product_Variation;

class Front {

    public static $asset_name = 'lowest-price';

    public function __construct() {

        if( WPLP_DISPLAY_TYPE == 'alt' ) {

            add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_style' ) );

            add_action( 'woocommerce_product_meta_end', array( $this, 'display_lowest_price_in_meta' ) );

        } else {

            add_filter( 'woocommerce_get_price_html', array( $this, 'get_price_html' ), 10, 2 );

        }

    }

    public function get_lowest_price( $object ) {

        global $wpdb;

        if( $object->get_type() == 'variable' && get_class( $object ) != 'WC_Product_Variation' ) {
            $prices = $object->get_variation_prices();
            $price = $prices['regular_price'];
        } else {
            $price = $object->get_regular_price( 'lowest_price' );
        }

        $ts_30_days_ago = time() - 30 * 24 * 60 * 60;

        if( WPLP_CALCULATE_TYPE == 'last_change' && ( $result = $wpdb->get_row( $wpdb->prepare( "SELECT timestamp FROM {$wpdb->prefix}price_history WHERE product_id = %d AND timestamp_end = 0", $object->get_id() ), ARRAY_A ) ) ) {

            $ts_30_days_ago = $result['timestamp'] - 30 * 24 * 60 * 60;
        }

        if( $result = $wpdb->get_row( $wpdb->prepare( "SELECT price FROM {$wpdb->prefix}price_history WHERE product_id = %d AND timestamp_end > %d ORDER BY price ASC LIMIT 0, 1", $object->get_id(), $ts_30_days_ago ), ARRAY_A ) ) {

            if( $result['price'] < $price ) {
                $price = $result['price'];
            }
        }

        return $price;
    }

    public function get_price_html( $price_html, $product ) {

        if( is_admin() || $product->get_type() == 'variable' ) {
            return $price_html;
        }

        if ( '' === $product->get_price() ) {
            $price_html = apply_filters( 'woocommerce_empty_price_html', '', $product );
        } elseif ( $product->is_on_sale() ) {

            $lowest_price_in_30_days = $this->get_lowest_price( $product );

            if( WPLP_DISPLAY_TYPE == 'text' ) {
                $price_html = '<span class="lowest_price">' . __( 'Lowest price in last 30 days', 'lowest-price' ) . ': <span class="lowest_amount">' . wc_price( $lowest_price_in_30_days ) . '</span></span><br />';
                $price_html .= '<span class="actual_price">' . __( 'Actual price', 'lowest-price' ) . ': <span class="actual_amount">' . wc_price( $product->get_price() ) . '</span></span>';
            } else {
                $price_html = wc_format_sale_price( wc_get_price_to_display( $product, array( 'price' => $lowest_price_in_30_days ) ), wc_get_price_to_display( $product ) ) . $product->get_price_suffix();
            }

        } else {
            $price_html = wc_price( wc_get_price_to_display( $product ) ) . $product->get_price_suffix();
        }

        return $price_html;
    }

    public function display_lowest_price_in_meta() { 

        global $product;

        if( !$product->is_on_sale( 'lowest_price' ) ) {
            return;
        }

        if( $product->get_type() == 'variable' ) {

            $variations = $product->get_children();

            $prices_arr = array();

            foreach ( $variations as $variation ) {

                $single_variation = new WC_Product_Variation( $variation );

                $prices_arr[ 0 ] = __( 'N/A', 'lowest-price' );
                if( $single_variation->is_on_sale() ) {
                    $prices_arr[ $variation ] = strip_tags( wc_price( $this->get_lowest_price( $single_variation ) ) );
                } else {
                    $prices_arr[ $variation ] = __( 'N/A', 'lowest-price' );
                }

            }

            $price = '<span class="lowest_amount js-variable-price" data-variations=\'' . json_encode($prices_arr) . '\'>' . $prices_arr[ 0 ] . '</span>';

        } else {

            $price = '<span class="lowest_amount">' . strip_tags( wc_price( $this->get_lowest_price( $product ) ) ) . '</span>';

        }

        echo '<span class="lowest_price">' . __( 'Lowest price in last 30 days', 'lowest-price' ) . ': ' . $price . '</span>';
    }

    public function wp_enqueue_style() {

        $plugin_version = Lowest_Price::$plugin_version;

        wp_enqueue_script( self::$asset_name, Lowest_Price::$plugin_url . '/asset/public/script.js', array( 'jquery' ), $plugin_version, false );
    }
}
