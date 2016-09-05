<?php

/**
 * Codisto eBay Sync Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category	Codisto
 * @package	 codisto/codisto-connect
 * @copyright   Copyright (c) 2016 On Technology Pty. Ltd. (http://codisto.com/)
 * @license	 http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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

	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Store\Model\StoreManager $storeManager,
		\Magento\Directory\Model\Region $region,
		\Magento\Directory\Model\Currency $currency,
		\Magento\Quote\Model\Quote $quote,
		\Magento\Quote\Model\Quote\ItemFactory $quoteItemFactory,
		\Magento\Catalog\Model\Product $product,
		\Magento\Checkout\Model\Session $session,
		\Magento\Shipping\Model\Shipment\RequestFactory $shipmentRequestFactory,
		\Magento\Shipping\Model\Shipping $shipping
	) {
		parent::__construct($context);

		$this->context = $context;
		$this->storeManager = $storeManager;
		$this->region = $region;
		$this->quote = $quote;
		$this->quoteItemFactory = $quoteItemFactory;
		$this->product = $product;
		$this->session = $session;
		$this->shipmentRequestFactory = $shipmentRequestFactory;
	}

	public function execute()
	{
		set_time_limit(0);
		ignore_user_abort(false);

		$output = '';

		$request = $this->getRequest();
		$response = $this->getResponse();

		try
		{
			$storeId = $request->getQuery('storeid') == null ? 0 : (int)$request->getQuery('storeid');

			if($storeId == 0)
			{
				$stores = $this->storeManager->getStores();
				foreach($stores as $store)
				{
					$storeId = $store->getId();
					if($storeId != 0)
						break;
				}
			}

			$this->storeManager->setCurrentStore($storeId);

			$store = $this->storeManager->getStore($storeId);

			$currencyCode = $request->getPost('CURRENCY');
			if(!$currencyCode)
				$currencyCode = $store->getCurrentCurrencyCode();

			$place = $request->getPost('PLACE');
			if(!$place)
				$place = '';
			$postalcode = $request->getPost('POSTALCODE');
			$division = $request->getPost('DIVISION');
			$countrycode = $request->getPost('COUNTRYCODE');
			$regionid = null;
			$regioncode = null;

			if($countrycode == 'AU')
			{
				$pc = $postalcode{0};

				if ($pc == 2 || $pc == 1) {
					$regiontext = 'NSW';
				} else if ($pc == 3 || $pc == 8) {
					$regiontext = 'VIC';
				} else if ($pc == 4) {
					$regiontext = 'QLD';
				} else if ($pc == 5) {
					$regiontext = 'SA';
				} else if ($pc == 6) {
					$regiontext = 'WA';
				} else if ($pc == 7) {
					$regiontext = 'TAS';
				}

				$pc3 = $postalcode{0} . $postalcode{1};
				if ($pc3 == '08' || $pc3 == '09') {
					$regiontext = 'NT';
				}

				if ($postalcode == '0872') {
					$regiontext = 'SA';
				} else if ($postalcode == '2611' || $postalcode == '3500' || $postalcode == '3585' || $postalcode == '3586' || $postalcode == '3644' || $postalcode == '3707') {
					$regiontext = 'NSW';
				} else if ($postalcode == '2620') {
					$regiontext = 'ACT';
				}

				if (intval($postalcode) >= 2600 && intval($postalcode) <= 2618) {
					$regiontext = 'ACT';
				}

				$region = $this->region->loadByCode($regiontext, $countrycode);
				if($region)
				{
					$regionid = $region->getId();
					$regioncode = $region->getCode();
				}
			}
			else
			{
				$region = $this->region->loadByName($division, $countrycode);
				if($region)
				{
					$regionid = $region->getId();
					$regioncode = $region->getCode();
				}
			}

			$total = 0;
			$itemqty = 0;
			$totalweight = 0;

			$quote = $this->quote;
			$quote->setIsSuperMode(true);

			for($inputidx = 0; ; $inputidx++)
			{
				if(!$request->getPost('PRODUCTCODE('.$inputidx.')'))
					break;

				$productid = (int)$request->getPost('PRODUCTID('.$inputidx.')');
				if(!$productid)
				{
					$productcode = $request->getPost('PRODUCTCODE('.$inputidx.')');
					$productid = $this->product->getIdBySku($productcode);
				}
				else
				{
					$sku = $this->product->getResource()->getProductsSku(array($productid));
					if(empty($sku))
					{
						$productcode = $request->getPost('PRODUCTCODE('.$inputidx.')');
						$productid = $this->product->getIdBySku($productcode);
					}
				}

				$productqty = $request->getPost('PRODUCTQUANTITY('.$inputidx.')');
				if(!$productqty && $productqty !=0)
					$productqty = 1;

				$productprice = floatval($request->getPost('PRODUCTPRICE('.$inputidx.')'));
				$productpriceincltax = floatval($request->getPost('PRODUCTPRICEINCTAX('.$inputidx.')'));
				$producttax = floatval($request->getPost('PRODUCTTAX('.$inputidx.')'));

				if($productid)
				{
					$product = $this->product->load($productid);

					if($product)
					{
						$product->setIsSuperMode(true);

						$taxpercent = $productprice == 0 ? 0 : round($productpriceincltax / $productprice - 1.0, 2) * 100;

						$item = $this->quoteItemFactory->create();
						$item->setStoreId($store->getId());
						$item->setQuote($quote);

						$item->setData('product', $product);
						$item->setProductId($productid);
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
						$item->setData('qty', $productqty);
						$item->setPrice($productprice);
						$item->setBasePrice($productprice);
						$item->setCustomPrice($productprice);
						$item->setDiscountPercent(0);
						$item->setDiscountAmount(0);
						$item->setBaseDiscountAmount(0);
						$item->setTaxPercent($taxpercent);
						$item->setTaxAmount($producttax * $productqty);
						$item->setBaseTaxAmount($producttax * $productqty);
						$item->setRowTotal($productprice * $productqty);
						$item->setBaseRowTotal($productprice * $productqty);
						$item->setRowTotalWithDiscount($productprice * $productqty);
						$item->setRowWeight($product->getWeight() * $productqty);
						$item->setOriginalCustomPrice($productprice);
						$item->setPriceInclTax($productpriceincltax);
						$item->setBasePriceInclTax($productpriceincltax);
						$item->setRowTotalInclTax($productpriceincltax * $productqty);
						$item->setBaseRowTotalInclTax($productpriceincltax * $productqty);
						$item->setWeeeTaxApplied(serialize(array()));

						$total += $productpriceincltax * $productqty;
						$itemqty += $productqty;
						$totalweight += $product->getWeight() * $productqty;

						$quote->getItemsCollection()->addItem($item);
					}
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
			if($regionid)
				$shippingRequest->setDestRegionId($regionid);
			if($regioncode)
				$shippingRequest->setDestRegionCode($regioncode);
			if($place)
				$shippingRequest->setDestCity($place);
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
			foreach($shippingRates as $shippingRate)
			{
				if($shippingRate instanceof \Magento\Quote\Model\Quote\Address\RateResult\Method)
				{
					$isPickup = $shippingRate->getPrice() == 0 &&
								(preg_match('/(?:^|\W|_)pick\s*up(?:\W|_|$)/i', strval($shippingRate->getMethod())) ||
									preg_match('/(?:^|\W|_)pick\s*up(?:\W|_|$)/i', strval($shippingRate->getCarrierTitle())) ||
									preg_match('/(?:^|\W|_)pick\s*up(?:\W|_|$)/i', strval($shippingRate->getMethodTitle())));

					if(!$isPickup)
					{
						$output .= 'FREIGHTNAME('.$outputidx.')='.rawurlencode($shippingRate->getMethodTitle()).'&FREIGHTCHARGEINCTAX('.$outputidx.')='.$shippingRate->getPrice().'&';
						$outputidx++;
					}
				}
			}

			try
			{
				$quote
					->setIsActive(false)
					->delete();
			}
			catch(Exception $e)
			{

			}

		}
		catch(Exception $e)
		{

		}

		$response->clearHeaders();

		$rawResult = $this->context->getResultFactory()->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
		$rawResult->setHttpResponseCode(200);
		$rawResult->setHeader('Cache-Control', 'no-cache', true);
		$rawResult->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
		$rawResult->setHeader('Pragma', 'no-cache', true);
		$rawResult->setContents($output);
		return $rawResult;
	}
}
