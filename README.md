# WooCommerce Square Stock Helper

One of the obstacles to running a store with Square is that Square can't send push notifications about inventory changes to other services like WooCommerce. In order to know about stock changes on the Square side, WooCommerce will request new stock levels each hour.

The issue is a product could sell out on the Square side and then be purchased in WooCommerce before the stock level is updated. The merchant ends up selling stock that they don't own.

This helper plugin adds an additional sync to prevent this from happening. Each time a product is added to the cart, it will request the current stock level from Square. If the product has gone out of stock, a notice will be added to the cart page and the customer won't be able to check out until they remove the product.

If Square's debug logging is enabled, this plugin will add a new log showing the product ID that was added to the cart and the original and new stock levels.
