<?php
/**
 * Plugin Name: WooCommerce Square Stock Helper
 * Version: 1.0.0
 * Plugin URI: https://github.com/billrobbins
 * Description: Checks stock levels with Square when products are added to the cart.
 * Requires at least: 5.0.0
 * Tested up to: 6.0.0
 *
 * @package WordPress
 * @author WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ijab_square_add_to_cart_stock_check() {

	global $woocommerce;
	$items = $woocommerce->cart->get_cart();

	// Build array of product ids or variation ids
	$ids = array();
	foreach( $items as $item ) {
		if ( $item['variation_id'] != 0 ) {
			$ids[] = $item['variation_id'];
		} else {
			$ids[] = $item['product_id'];
		}
	}
	
	$last_product_id = end( $ids );

	// We need the variation ID to sync but first we need to check its parent to see if it should sync
	$product = wc_get_product( $last_product_id );
	$product_parent = $product->get_parent_id();

	if ( $product_parent === 0 ) {
		$sync_id = $last_product_id;
	} else {
		$sync_id = $product_parent;
	}

	// Only fetch stock levels if product is synced with Square
	$terms = wp_get_post_terms( $sync_id, 'wc_square_synced', [ 'fields' => 'names' ] );
	if ( empty( $terms ) || 'yes' !== $terms[0] ) {
		return;
	}

	// Fetches current stock from Square and updates WooCommerce
	$current_stock = new WC_Square_Cart_Stock_Checker();
	$current_stock->sync_single_product( $last_product_id );

}

add_action( 'woocommerce_add_to_cart', 'ijab_square_add_to_cart_stock_check' );


class WC_Square_Cart_Stock_Checker {

	/**
	 * Syncs a specific product by ID.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 */
	public function sync_single_product( $product_id ) {

		$log_entry = 'Attempting to sync inventory of single product ID: ' . $product_id . PHP_EOL;

		try {

			$product = wc_get_product( $product_id );

			// Does it have the _square_item_variation_id?
			$square_item_variation_id = get_post_meta( $product_id, '_square_item_variation_id', true ) ?: null;

			if ( null === $square_item_variation_id ) {
				$log_entry .= 'No _square_item_variation_id set.' . PHP_EOL;
				throw new Exception( 'Product ID: '. $product_id .' does not have a _square_item_variation_id set. \n' );
			} else {
				$log_entry .= '_square_item_variation_id: '. $square_item_variation_id . PHP_EOL;
			}

			// Set the args.
			$args = [
				'location_ids'       => [ wc_square()->get_settings_handler()->get_location_id() ],
				'catalog_object_ids' => [ $square_item_variation_id ],
			];

			// Query the inventory from Square for the product.
			$response = wc_square()->get_api()->batch_retrieve_inventory_counts( $args );

			foreach ( $response->get_counts() as $count ) {

				// Square can return multiple "types" of counts, WooCommerce only distinguishes whether a product is in stock or not
				if ( 'IN_STOCK' === $count->getState() ) {

					$log_entry .= 'Quantity updated from ' . $product->get_stock_quantity();
					$product->set_stock_quantity( $count->getQuantity() );
					$product->save();
					$log_entry .= ' to '. $count->getQuantity() . PHP_EOL;

				}
			}

		} catch ( Exception $e ) {
			$log_entry .= $e->getMessage();
		}

		$square_settings = wc_square();
		$logging_enabled = $square_settings->get_settings_handler()->is_debug_enabled();

		if ( $logging_enabled ) {
			WC_Square_Cart_Stock_Checker::log( $log_entry );
		}

	}

	static function log( $message = '' ) {
		$log = wc_get_logger();
		$log->debug( $message, [ 'source' => 'square-cart-stock-update' ] );
	}
}
