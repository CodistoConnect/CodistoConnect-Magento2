<?php

/**
 * Codisto LINQ Sync Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package i30n the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @package   Codisto_Connect
 * @copyright 2016-2017 On Technology Pty. Ltd. (http://codisto.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      https://codisto.com/connect/
 */

namespace Codisto\Connect\Controller\Index;

class Calc extends \Magento\Framework\App\Action\Action
{
    private $context;
    private $storeManager;
    private $region;
    private $currency;
    private $quote;
    private $quoteItemFactory;
    private $product;
    private $session;
    private $shipmentRequestFactory;
    private $shipping;
    private $visitor;
    private $codistoHelper;

    private $pickupRegex = '/(?:^|\W|_)pick\s*up(?:\W|_|$)|(?:^|\W|_)click\s+.*\s+collect(?:\W|_|$)/i';

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\Directory\Model\Region $region,
        \Magento\Directory\Model\Currency $currency,
        \Magento\Quote\Model\Quote $quote,
        \Magento\Quote\Model\Quote\ItemFactory $quoteItemFactory,
        \Magento\Catalog\Model\Product $product,
        \Magento\Checkout\Model\Session\Proxy $session, // @codingStandardsIgnoreLine Magento2.Classes.DiscouragedDependencies.ConstructorProxyInterceptor
        \Magento\Shipping\Model\Shipment\RequestFactory $shipmentRequestFactory,
        \Magento\Customer\Model\Visitor $visitor,
        \Magento\Shipping\Model\Shipping $shipping,
        \Codisto\Connect\Helper\Data $codistoHelper
    ) {
        parent::__construct($context);

        $this->context = $context;
        $this->storeManager = $storeManager;
        $this->region = $region;
        $this->currency = $currency;
        $this->quote = $quote;
        $this->quoteItemFactory = $quoteItemFactory;
        $this->product = $product;
        $this->session = $session;
        $this->shipmentRequestFactory = $shipmentRequestFactory;
        $this->shipping = $shipping;
        $this->codistoHelper = $codistoHelper;
        $this->visitor = $visitor;
    }

    private function _storeId()
    {
        $storeId = $request->getQuery('storeid') == null ? 0 : (int)$request->getQuery('storeid');

        if ($storeId == 0) {
            foreach ($this->storeManager->getStores(false) as $store) {
                $storeId = $storeId == 0 ? $store->getId() : min($store->getId(), $storeId);
            }
        }

        return $storeId;
    }

    private function _regionData($countrycode, $division, $postalcode) // @codingStandardsIgnoreLine Generic.Metrics.CyclomaticComplexity.MaxExceeded
    {
        $regionid = null;
        $regioncode = null;

        if ($countrycode == 'AU') {
            $pc = $postalcode{0};

            if ($pc == 2 || $pc == 1) {
                $regiontext = 'NSW';
            } elseif ($pc == 3 || $pc == 8) {
                $regiontext = 'VIC';
            } elseif ($pc == 4) {
                $regiontext = 'QLD';
            } elseif ($pc == 5) {
                $regiontext = 'SA';
            } elseif ($pc == 6) {
                $regiontext = 'WA';
            } elseif ($pc == 7) {
                $regiontext = 'TAS';
            }

            $pc3 = $postalcode{0} . $postalcode{1};
            if ($pc3 == '08' || $pc3 == '09') {
                $regiontext = 'NT';
            }

            if ($postalcode == '0872') {
                $regiontext = 'SA';
            } elseif ($postalcode == '2611'
                || $postalcode == '3500'
                || $postalcode == '3585'
                || $postalcode == '3586'
                || $postalcode == '3644'
                || $postalcode == '3707') {
                $regiontext = 'NSW';
            } elseif ($postalcode == '2620') {
                $regiontext = 'ACT';
            }

            if ((int)($postalcode) >= 2600 && (int)($postalcode) <= 2618) {
                $regiontext = 'ACT';
            }

            $region = $this->region->loadByCode($regiontext, $countrycode);
            if ($region) {
                $regionid = $region->getId();
                $regioncode = $region->getCode();
            }
        } else {
            $region = $this->region->loadByName($division, $countrycode);
            if ($region) {
                $regionid = $region->getId();
                $regioncode = $region->getCode();
            }
        }

        return ['regionid' => $regionid, 'regioncode' => $regioncode];
    }

    private function _freightOutput($shippingRate, &$output, &$outputidx)
    {
        if ($shippingRate instanceof \Magento\Quote\Model\Quote\Address\RateResult\Method) {
            $isPickup = $shippingRate->getPrice() == 0 &&
                        (preg_match($this->pickupRegex, (string)($shippingRate->getMethod())) ||
                            preg_match($this->pickupRegex, (string)($shippingRate->getCarrierTitle())) ||
                            preg_match($this->pickupRegex, (string)($shippingRate->getMethodTitle())));

            if (!$isPickup) {
                $output .= 'FREIGHTNAME('.$outputidx.')='.rawurlencode($shippingRate->getMethodTitle()).
                            '&FREIGHTCHARGEINCTAX('.$outputidx.')='.$shippingRate->getPrice().'&';
                $outputidx++;
            }
        }
    }

    private function _productData($request, $inputidx)
    {
        $productData = [];

        $productid = (int)$request->getPost('PRODUCTID('.$inputidx.')');
        if (!$productid) {
            $productcode = $request->getPost('PRODUCTCODE('.$inputidx.')');
            $productid = $this->product->getIdBySku($productcode);
        } else {
            $sku = $this->product->getResource()->getProductsSku([$productid]);
            if (empty($sku)) {
                $productcode = $request->getPost('PRODUCTCODE('.$inputidx.')');
                $productid = $this->product->getIdBySku($productcode);
            }
        }

        if ($productid) {
            $productqty = $request->getPost('PRODUCTQUANTITY('.$inputidx.')');
            if (!$productqty && $productqty !=0) {
                $productqty = 1;
            }

            $productprice = floatval($request->getPost('PRODUCTPRICE('.$inputidx.')'));
            $productpriceincltax = floatval($request->getPost('PRODUCTPRICEINCTAX('.$inputidx.')'));
            $producttax = floatval($request->getPost('PRODUCTTAX('.$inputidx.')'));

            $productData['qty'] = $productqty;
            $productData['price'] = $productprice;
            $productData['priceincltax'] = $productpriceincltax;
            $productData['taxpercent'] = $productprice == 0 ?
                                        0 : round($productpriceincltax / $productprice - 1.0, 2) * 100;
            $productData['taxamount'] = $producttax * $productqty;
            $productData['rowtotal'] = $productprice * $productqty;
            $productData['rowtotalincltax'] = $productpriceincltax * $productqty;

            $productData['product'] = $this->product->load($productid);

            $productData['rowweight'] = $product->getWeight() * $productqty;
        }

        return $productData;
    }

    public function execute()
    {
        $this->visitor->setSkipRequestLogging(true);

        // calls to freight services can take longer than standard php request time limit
        set_time_limit(0); // @codingStandardsIgnoreLine MEQP1.Security.DiscouragedFunction.Found
        ignore_user_abort(false);

        $output = '';

        $request = $this->getRequest();
        $response = $this->getResponse();

        try {
            $storeId = $this->_storeId();

            $this->storeManager->setCurrentStore($storeId);

            $store = $this->storeManager->getStore($storeId);

            $currencyCode = $request->getPost('CURRENCY') ?
                $request->getPost('CURRENCY') : $store->getCurrentCurrencyCode();

            $place = $request->getPost('PLACE') ?
                $request->getPost('PLACE') : '';

            $postalcode = $request->getPost('POSTALCODE');
            $division = $request->getPost('DIVISION');
            $countrycode = $request->getPost('COUNTRYCODE');

            $regionData = $this->_regionData($countrycode, $division, $postalcode);

            $total = 0;
            $itemqty = 0;
            $totalweight = 0;

            $quote = $this->quote;
            $quote->setIsSuperMode(true);

            for ($inputidx = 0;; $inputidx++) {
                if (!$request->getPost('PRODUCTCODE('.$inputidx.')')) {
                    break;
                }

                $productData = $this->_productData($request, $inputidx);

                if ($productData['product']) {
                    $product = $productData['product'];
                    $product->setIsSuperMode(true);

                    $item = $this->quoteItemFactory->create();
                    $item->setStoreId($store->getId());
                    $item->setQuote($quote);

                    $item->setData('product', $product);
                    $item->setProductId($product->getId());
                    $item->setProductType('simple');
                    $item->setIsRecurring(false);
                    $item->setTaxClassId($product->getTaxClassId());
                    $item->setBaseCost($product->getCost());
                    $item->setSku($product->getSku());
                    $item->setName($product->getName());
                    $item->setIsVirtual(0);
                    $item->setIsQtyDecimal(0);
                    $item->setNoDiscount(true);
                    $item->setWeight($product->getWeight());
                    $item->setData('qty', $productData['qty']);
                    $item->setPrice($productData['price']);
                    $item->setBasePrice($productData['price']);
                    $item->setCustomPrice($productData['price']);
                    $item->setDiscountPercent(0);
                    $item->setDiscountAmount(0);
                    $item->setBaseDiscountAmount(0);
                    $item->setTaxPercent($productData['taxpercent']);
                    $item->setTaxAmount($productData['taxamount']);
                    $item->setBaseTaxAmount($productData['taxamount']);
                    $item->setRowTotal($productData['rowtotal']);
                    $item->setBaseRowTotal($productData['rowtotal']);
                    $item->setRowTotalWithDiscount($productData['rowtotal']);
                    $item->setRowWeight($productData['rowweight']);
                    $item->setOriginalCustomPrice($productData['price']);
                    $item->setPriceInclTax($productData['priceincltax']);
                    $item->setBasePriceInclTax($productData['priceincltax']);
                    $item->setRowTotalInclTax($productData['rowtotalincltax']);
                    $item->setBaseRowTotalInclTax($productData['rowtotalincltax']);
                    $item->setWeeeTaxApplied(\Zend_Json::encode([]));

                    $total += $productData['rowtotalincltax'];
                    $itemqty += $productData['qty'];
                    $totalweight += $productData['rowweight'];

                    $quote->getItemsCollection()->addItem($item);
                }
            }

            $quote->save();

            $checkoutSession = $this->session;
            $checkoutSession->replaceQuote($quote);
            $checkoutSession->setData('destination_type', 'residence');

            $currency = $this->currency->load($currencyCode);

            $shippingRequest = $this->shipmentRequestFactory->create();
            $shippingRequest->setAllItems($quote->getAllItems());
            $shippingRequest->setDestCountryId($countrycode);
            if ($regionData['regionid']) {
                $shippingRequest->setDestRegionId($regionData['regionid']);
            }
            if ($regionData['regioncode']) {
                $shippingRequest->setDestRegionCode($regionData['regioncode']);
            }
            if ($place) {
                $shippingRequest->setDestCity($place);
            }
            $shippingRequest->setDestPostcode($postalcode);
            $shippingRequest->setPackageValue($total);
            $shippingRequest->setPackageValueWithDiscount($total);
            $shippingRequest->setPackageWeight($totalweight);
            $shippingRequest->setPackageQty($itemqty);
            $shippingRequest->setPackagePhysicalValue($total);
            $shippingRequest->setFreeMethodWeight($totalweight);
            $shippingRequest->setStoreId($store->getId());
            $shippingRequest->setWebsiteId($store->getWebsiteId());
            $shippingRequest->setFreeShipping(0);
            $shippingRequest->setBaseCurrency($currency);
            $shippingRequest->setPackageCurrency($currency);
            $shippingRequest->setBaseSubtotalInclTax($total);

            $shippingResult = $this->shipping->collectRates($shippingRequest)->getResult();

            $shippingRates = $shippingResult->getAllRates();

            $outputidx = 0;
            foreach ($shippingRates as $shippingRate) {
                $this->_freightOutput($shippingRate, $output, $outputidx);
            }

            $quote->setIsActive(false)->delete();
        } catch (\Exception $e) {
            $e;
            // ignore any exceptions generating quote results
            // so a valid response is always emitted
        }

        $rawResult = $this->context->getResultFactory()->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
        $rawResult->setHttpResponseCode(200);
        $rawResult->setHeader('Cache-Control', 'no-cache', true);
        $rawResult->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
        $rawResult->setHeader('Pragma', 'no-cache', true);
        $rawResult->setContents($output);

        $rawResult->renderResult($response);
        $response->sendResponse();

        return $this->codistoHelper->callExit();
    }
}
