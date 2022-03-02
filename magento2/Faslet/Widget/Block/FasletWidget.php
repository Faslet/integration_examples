<?php

namespace Faslet\Widget\Block;

use Magento\Framework\View\Element\Template;
use Faslet\Widget;
use Faslet\OrderTracking;
use Magento\Catalog\Block\Product\ListProduct;
use Magento\Framework\View\Element\Template\Context;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Logger\Monolog;
use Magento\Framework\Data\Form\FormKey;

class FasletWidget extends Template
{
  private $widget;
  private $orderTracking;
  private $productRepository;
  private $logger;
  private $listProduct;
  private $formKey;
  private $shopId = "Faslet Demo";


  public function __construct(
    Context $context,
    ProductRepository $productRepository,
    Monolog $logger,
    ListProduct $listProduct,
    FormKey $formKey,
    array $data = []
  ) {
    $this->logger = $logger;
    $this->logger->debug('creating faslet block');

    $this->productRepository = $productRepository;
    $this->listProduct = $listProduct;
    $this->formKey = $formKey;

    $this->widget = new Widget($this->shopId);
    $this->orderTracking = new OrderTracking($this->shopId);

    parent::__construct($context, $data);
  }

  public function getFasletOrderTrackingSnippet()
  {
    $this->logger->debug('creating faslet order tracking snippet');

    $this->orderTracking
      ->withOrderNumber("")
      ->withPaymentStatus("");

    $this->orderTracking
      ->buildOrderTracking();
  }

  public function getFasletWidgetScriptTag()
  {
    $this->logger->debug('creating faslet widget script tag');
    return $this->widget->buildScriptTag();
  }

  public function getFasletWidgetSnippet()
  {
    // These are based on our demo store, please change these for your store, or fetch them from Magento
    $COLOR_ID = 4;
    $SIZE_ID = 5;
    $MANUFACTURER_CODE = "manufacturer";
    // TODO: Get this from a custom attribute
    $TAG_FOR_THIS_PRODUCT = "Faslet_Jacket_Male";

    $this->logger->debug('creating faslet widget snippet');

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
      ->withProductName($product->getName())
      ->withFasletProductTag($TAG_FOR_THIS_PRODUCT);

    $configOptions = $product->getExtensionAttributes()->getConfigurableProductOptions();

    $colorOptions = [];
    $sizeOptions = [];

    foreach ($configOptions as $configOption) {
      if ($configOption->getId() == $COLOR_ID) {
        $colorOptions = $configOption->getOptions();
      } else if ($configOption->getId() == $SIZE_ID) {
        $sizeOptions = $configOption->getOptions();
      }
    }

    $linkedProductIds = $product->getExtensionAttributes()->getConfigurableProductLinks();

    foreach ($colorOptions as $colorOption) {
      $this->widget->addColor($colorOption["value_index"], $colorOption["label"]);
    }

    $baseCartUrl = $this->listProduct->getAddToCartUrl($product);
    $fk = $this->formKey->getFormKey();
    $baseCartUrl = substr($baseCartUrl, 0, -3) . "%id%/?qty=1&form_key=$fk";

    foreach ($linkedProductIds as $linkedProductId) {

      $linkedProduct = $this->productRepository->getById($linkedProductId);
      $isSalable = $linkedProduct->getIsSalable();
      $color = $linkedProduct->getCustomAttribute("color");
      $size = $linkedProduct->getCustomAttribute("size");
      $colorValue = $color->getValue();
      $sizeValue = $size->getValue();
      $sizeLabel = "";

      foreach ($sizeOptions as $sizeOption) {
        if ($sizeOption["value_index"] == $sizeValue) {
          $sizeLabel = $sizeOption["label"];
        }
      }

      $this->widget->addVariant($linkedProductId, $sizeLabel, $isSalable, $linkedProduct->getSku(), $colorValue);
    }

    $this->widget->withAddToCartSnippet("
    function(id) {
      return fetch(\"$baseCartUrl\".replace(\"%id%\", id), { \"method\": \"POST\" }).then(function() { window.location.reload() });
    }");

    return $this->widget->buildWidget();
  }
}
