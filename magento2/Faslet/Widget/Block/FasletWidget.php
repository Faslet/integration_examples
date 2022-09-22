<?php

namespace Faslet\Widget\Block;

use Magento\Framework\View\Element\Template;
use Faslet\Widget;
use Faslet\OrderTracking;
use Magento\Catalog\Block\Product\ListProduct;
use Magento\Framework\View\Element\Template\Context;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Logger\Monolog;
use Magento\Checkout\Model\Session;

class FasletWidget extends Template
{
  private $widget;
  private $orderTracking;
  private $productRepository;
  private $logger;
  private $listProduct;
  private $checkoutSession;
  private $shopId = "Faslet Demo";


  public function __construct(
    Context $context,
    ProductRepository $productRepository,
    Monolog $logger,
    ListProduct $listProduct,
    Session $checkoutSession,
    array $data = []
  ) {
    $this->logger = $logger;
    $this->logger->debug('creating faslet block');

    $this->productRepository = $productRepository;
    $this->listProduct = $listProduct;
    $this->checkoutSession = $checkoutSession;

    $this->widget = new Widget($this->shopId);
    $this->orderTracking = new OrderTracking($this->shopId);

    parent::__construct($context, $data);
  }

  public function getFasletOrderTrackingSnippet()
  {
    $this->logger->debug('creating faslet order tracking snippet');
    $order = $this->checkoutSession->getLastRealOrder();

    $orderId = $order->getRealOrderId();
    $orderStatus = $order->getStatus();

    $this->orderTracking
      ->withOrderNumber($orderId)
      ->withPaymentStatus($orderStatus);

    foreach ($order->getAllItems() as $orderItem) {

      $productId = $orderItem->getProductId();
      $productName = $orderItem->getName();
      $price = $orderItem->getPrice();
      // This may not work for your store, adjust this to what works for your store.
      $quantity = $orderItem->getQtyToInvoice();

      // Magento adds 2 items to your cart, one being the parent product, one being the variant. Variant is added with quantity 0, so skip that.
      if ($quantity == 0) {
        continue;
      }

      $sku = $orderItem->getSku();

      // Since the SKU is not the product, but rather an option of it
      $variant = $this->productRepository->get($sku);
      $variantId = $variant->getId();
      $variantName = $variant->getName();

      $this->orderTracking->addProduct($productId, $variantId, $productName, $variantName, $price * $quantity, $quantity, $sku);
    }

    return $this->orderTracking->buildOrderTracking();
  }

  public function getFasletWidgetScriptTag()
  {
    $this->logger->debug('creating faslet widget script tag');
    return $this->widget->buildScriptTag();
  }

  public function getFasletWidgetSnippet()
  {
    // These are based on our demo store, please change these for your store
    $COLOR_CODE = "color";
    $SIZE_CODE = "size";
    $MANUFACTURER_CODE = "manufacturer";

    $this->logger->debug('creating faslet widget snippet');

    // This should be the parent product id, not a variant id
    $id = $this->getRequest()->getParam('id');

    $product = $this->productRepository->getById($id);

    // Fallback image, please change this for your store
    $image = "https://placekitten.com/100";

    $mediaEntries = $product->getMediaGalleryEntries();
    if ($mediaEntries && count($mediaEntries) > 0) {
      $imagePath = $product->getMediaGalleryEntries()[0]->getFile();

      $urlMedia = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);

      $image = $urlMedia . 'catalog/product' . $imagePath;
    }

    $brandName = $product->getAttributeText($MANUFACTURER_CODE);

    $this->widget
      ->withBrand($brandName)
      ->withProductId("$id")
      ->withProductImage($image)
      ->withProductName($product->getName());

    $colorSuperId = "";
    $sizeSuperId = "";

    // TODO: I don't like this, but I can't see a better way to get the attribute ids out, or get a basic add to cart url for a product option
    foreach ($product->getAttributes() as $attribute) {
      if ($attribute->getName() === $COLOR_CODE) {
        $colorSuperId = $attribute->getAttributeId();
      } else if ($attribute->getName() === $SIZE_CODE) {
        $sizeSuperId = $attribute->getAttributeId();
      }
    }

    $configOptions = $product->getExtensionAttributes()->getConfigurableProductOptions();

    $colorOptions = [];
    $sizeOptions = [];

    foreach ($configOptions as $configOption) {
      if ($configOption["attribute_id"] == $colorSuperId) {
        $colorOptions = $configOption->getOptions();
      } else if ($configOption["attribute_id"] == $sizeSuperId) {
        $sizeOptions = $configOption->getOptions();
      }
    }


    $linkedProductIds = $product->getExtensionAttributes()->getConfigurableProductLinks();

    foreach ($colorOptions as $colorOption) {
      $this->widget->addColor($colorOption["value_index"], $colorOption["label"]);
    }

    $variants = [];

    foreach ($linkedProductIds as $linkedProductId) {

      $linkedProduct = $this->productRepository->getById($linkedProductId);
      $isSalable = $linkedProduct->getIsSalable();
      $color = $linkedProduct->getCustomAttribute($COLOR_CODE);
      $size = $linkedProduct->getCustomAttribute($SIZE_CODE);
      $colorValue = $color->getValue();
      $sizeValue = $size->getValue();
      $sizeLabel = "";

      foreach ($sizeOptions as $sizeOption) {
        if ($sizeOption["value_index"] == $sizeValue) {
          $sizeLabel = $sizeOption["label"];
        }
      }

      $variants[$linkedProductId] = ["size" => $sizeValue, "color" => $colorValue];

      $this->widget->addVariant($linkedProductId, $sizeLabel, $isSalable, $linkedProduct->getSku(), $colorValue);
    }

    $baseCartUrl = $this->listProduct->getAddToCartUrl($product);
    $baseCartUrl = $baseCartUrl . "?qty=1&form_key=%fk%&selected_configurable_option=%id%&super_attribute[$sizeSuperId]=%sid%&super_attribute[$colorSuperId]=%cid%";

    $this->widget->withAddToCartSnippet("
    function(id) {
      var variants = " . json_encode($variants) . ";
      var formKey = document.forms['product_addtocart_form'].querySelector('input[name=form_key]').value;
      return fetch(\"$baseCartUrl\"
          .replace(\"%id%\", id)
          .replace(\"%fk%\", formKey)
          .replace(\"%sid%\", variants[id].size)
          .replace(\"%cid%\", variants[id].color), 
        { \"method\": \"POST\" }).then(function() { window.location.reload() });
    }");

    return $this->widget->buildWidget();
  }
}
