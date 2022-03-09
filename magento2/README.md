# Faslet Magento 2 Example

This is an example of how to implement the Faslet Widget in Magento 2.3

## What's included

- [x] Widget integration in product pages
- [x] Getting the brand, product info and variants from a Configurable product
- [x] Add to cart inside the widget
- [x] Order tracking

## What's not included
- [ ] Getting the Tag from the product attributes. If your store requires Faslet Tags (as opposed to product ids), then you need to pass these through from the back office to be configurable by the store manager.

## Notes and assumptions

- It is assumed that the products are ConfigurableProducts and that is how sizes and colors are handled
- The Tag is hardcoded in the example but it is assumed that most stores would configure this via Custom Attributes which can be set in the Product Admin view
- The Color, Size and Manufacturer (Brand) attribute codes are hardcoded in the example, but should be adjusted for each store as needed
- Add-to-cart is configured to use a direct url which is POSTed to from the frontend. It is very likely that there are easier ways to do this,
either purely in frontend code, or by creating a controller which does some of this work. We did not include those in the example.
- Order tracking uses the fact that Magento 2.3.7 adds 2 items to the cart when buying a variable product. The variant itself is added, but 0 quantity.
We skip over this in order tracking, and simply look up the SKU of the item purchased.


For more info, or if you have any questions, please contact tech@faslet.me.