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

use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Event\ManagerInterface as EventManager;

class Index extends \Magento\Framework\App\Action\Action
{
	private $context;
	private $eventManager;
	private $resourceConnection;
	private $pageFactory;
	private $storeManager;
	private $quote;
	private $order;
	private $orderRepository;
	private $customerRepository;
	private $customerFactory;
	private $customerAddressFactory;
	private $customerGroupFactory;
	private $country;
	private $productFactory;
	private $quoteItemFactory;
	private $quoteAddressRateFactory;
	private $session;
	private $rateRequestFactory;
	private $shipping;
	private $orderConverter;
	private $orderAddressConverter;
	private $orderItemConverter;
	private $orderPaymentConverter;
	private $priceCurrency;
	private $stockItemFactory;
	private $catalogInventoryConfig;
	private $orderService;
	private $stockManagement;
	private $itemsForReindex;
	private $codistoHelper;

	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\App\ResourceConnection $resourceConnection,
		\Magento\Framework\View\Result\PageFactory $pageFactory,
		\Magento\Store\Model\StoreManager $storeManager,
		\Magento\Quote\Model\Quote $quote,
		\Magento\Sales\Model\Order $order,
		\Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
		\Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
		\Magento\Customer\Model\CustomerFactory $customerFactory,
		\Magento\Customer\Model\AddressFactory $customerAddressFactory,
		\Magento\Customer\Model\GroupFactory $customerGroupFactory,
		\Magento\Directory\Model\Country $country,
		\Magento\Catalog\Model\ProductFactory $productFactory,
		\Magento\Quote\Model\Quote\ItemFactory $quoteItemFactory,
		\Magento\Checkout\Model\Session $session,
		\Magento\Quote\Model\Quote\Address\RateRequestFactory $rateRequestFactory,
		\Magento\Shipping\Model\Shipping $shipping,
		\Magento\Quote\Model\Quote\Address\RateFactory $quoteAddressRateFactory,
		\Magento\Quote\Model\Quote\Address\ToOrder $orderConverter,
		\Magento\Quote\Model\Quote\Address\ToOrderAddress $orderAddressConverter,
		\Magento\Quote\Model\Quote\Item\ToOrderItem $orderItemConverter,
		\Magento\Quote\Model\Quote\Payment\ToOrderPayment $orderPaymentConverter,
		\Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
		\Magento\CatalogInventory\Model\Stock\ItemFactory $stockItemFactory,
		\Magento\CatalogInventory\Model\Configuration $catalogInventoryConfig,
		\Magento\Sales\Api\InvoiceManagementInterface $orderService,
		\Magento\CatalogInventory\Api\StockManagementInterface $stockManagement,
		\Magento\CatalogInventory\Observer\ItemsForReindex $itemsForReindex,
		\Codisto\Connect\Helper\Data $codistoHelper
	) {
		parent::__construct($context);

		$this->context = $context;
		$this->eventManager = $context->getEventManager();
		$this->resourceConnection = $resourceConnection;
		$this->pageFactory = $pageFactory;
		$this->storeManager = $storeManager;
		$this->quote = $quote;
		$this->order = $order;
		$this->orderRepository = $orderRepository;
		$this->customerRepository = $customerRepository;
		$this->customerFactory = $customerFactory;
		$this->customerAddressFactory = $customerAddressFactory;
		$this->customerGroupFactory = $customerGroupFactory;
		$this->country = $country;
		$this->productFactory = $productFactory;
		$this->quoteItemFactory = $quoteItemFactory;
		$this->session = $session;
		$this->rateRequestFactory = $rateRequestFactory;
		$this->shipping = $shipping;
		$this->quoteAddressRateFactory = $quoteAddressRateFactory;
		$this->orderConverter = $orderConverter;
		$this->orderAddressConverter = $orderAddressConverter;
		$this->orderItemConverter = $orderItemConverter;
		$this->orderPaymentConverter = $orderPaymentConverter;
		$this->priceCurrency = $priceCurrency;
		$this->stockItemFactory = $stockItemFactory;
		$this->catalogInventoryConfig = $catalogInventoryConfig;
		$this->orderService = $orderService;
		$this->stockManagement = $stockManagement;
		$this->itemsForReindex = $itemsForReindex;
		$this->codistoHelper = $codistoHelper;
	}

	public function execute()
	{
		set_time_limit(0);
		ignore_user_abort(false);

		$request = $this->getRequest();
		$response = $this->getResponse();
		$server = $request->getServer();
		$method = isset($server['REQUEST_METHOD']) ? $server['REQUEST_METHOD'] : 'GET';
		$contenttype = isset($server['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

		if($method == 'POST')
		{
			if($contenttype == 'text/xml')
			{
				$xml = simplexml_load_string(file_get_contents('php://input'));

				$ordercontent = $xml->entry->content->children('http://api.codisto.com/schemas/2009/');

				$storeId = @count($ordercontent->storeid) ? (int)$ordercontent->storeid : 0;

				$store = $this->storeManager->getStore($storeId);

				if(!$this->codistoHelper->getConfig($storeId))
				{
					$response->clearHeaders();
					$response->setStatusHeader(500, '1.0', 'Security Error');
					$rawResult = $this->context->getResultFactory()->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
					$rawResult->setHttpResponseCode(500);
					$rawResult->setHeader('Cache-Control', 'no-cache', true);
					$rawResult->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
					$rawResult->setHeader('Pragma', 'no-cache', true);
					$rawResult->setContents('Config Error');
					return $rawResult;
				}

				if($this->codistoHelper->checkRequestHash($store->getConfig('codisto/hostkey'), $server))
				{
					$productsToReindex = array();
					$ordersProcessed = array();
					$invoicesProcessed = array();

					$connection = $this->resourceConnection->getConnection();

					try
					{
						$connection->addColumn(
								$this->resourceConnection->getTableName('sales_order'),
								'codisto_orderid',
								'varchar(10)'
							);
					}
					catch(Exception $e)
					{

					}

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

					$quote = null;
					$result = null;

					for($Retry = 0; ; $Retry++)
					{
						$productsToReindex = array();

						try
						{
							$quote = $this->quote;

							$this->ProcessQuote($quote, $xml, $store, $request);
						}
						catch(Exception $e)
						{
							$response->clearHeaders();
							$jsonResult = $this->context->getResultFactory()->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
							$jsonResult->setHttpResponseCode(200);
							$jsonResult->setHeader('Content-Type', 'application/json');
							$jsonResult->setHeader('Cache-Control', 'no-cache, must-revalidate', true);
							$jsonResult->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
							$jsonResult->setHeader('Pragma', 'no-cache', true);
							$jsonResult->setData(array( 'ack' => 'failed', 'code' => $e->getCode(), 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()));
							$result = $jsonResult;
							break;
						}

						$connection->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
						$connection->beginTransaction();

						try
						{
							$order = $this->order->getCollection()->addAttributeToFilter('codisto_orderid', $ordercontent->orderid)->getFirstItem();

							if($order && $order->getId())
							{
								$result = $this->ProcessOrderSync($quote, $order, $xml, $productsToReindex, $ordersProcessed, $invoicesProcessed, $store, $request);
							}
							else
							{
								$result = $this->ProcessOrderCreate($quote, $xml, $productsToReindex, $ordersProcessed, $invoicesProcessed, $store, $request);
							}

							$connection->commit();
							break;
						}
						catch(Exception $e)
						{
							if($Retry < 5)
							{
								if($e->getCode() == 40001)
								{
									$connection->rollback();
									sleep($Retry * 10);
									continue;
								}
							}

							$connection->rollback();

							$response->clearHeaders();
							$jsonResult = $this->context->getResultFactory()->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
							$jsonResult->setHttpResponseCode(200);
							$jsonResult->setHeader('Content-Type', 'application/json');
							$jsonResult->setHeader('Cache-Control', 'no-cache', true);
							$jsonResult->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
							$jsonResult->setHeader('Pragma', 'no-cache', true);
							$jsonResult->setData(array( 'ack' => 'failed', 'code' => $e->getCode(), 'message' => $e->getMessage()));
							$result = $jsonResult;
						}
					}

					return $result;
				}
				else
				{
					$response->clearHeaders();
					$response->setStatusHeader(400, '1.0', 'Security Error');
					$rawResult = $this->context->getResultFactory()->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
					$rawResult->setHttpResponseCode(400);
					$rawResult->setHeader('Cache-Control', 'no-cache, must-revalidate', true);
					$rawResult->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
					$rawResult->setHeader('Pragma', 'no-cache', true);
					$rawResult->setContents('Security Error');
					return $rawResult;
				}
			}
			else
			{
				$response->clearHeaders();
				$response->setStatusHeader(400, '1.0', 'Invalid Content Type');
				$rawResult = $this->context->getResultFactory()->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
				$rawResult->setHttpResponseCode(400);
				$rawResult->setHeader('Cache-Control', 'no-cache, must-revalidate', true);
				$rawResult->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
				$rawResult->setHeader('Pragma', 'no-cache', true);
				$rawResult->setContents('Invalid Content Type');
				return $rawResult;
			}
		}
		else
		{
			throw new NotFoundException(__('Resource Not Found'));
		}
	}

	private function ProcessOrderCreate($quote, $xml, &$productsToReindex, &$orderids, &$invoiceids, $store, $request)
	{
		$ordercontent = $xml->entry->content->children('http://api.codisto.com/schemas/2009/');

		$paypaltransactionid = (string)$ordercontent->orderpayments[0]->orderpayment->transactionid;
		$ordernumberformat = (string)$ordercontent->ordernumberformat;

		$ordertotal = floatval($ordercontent->ordertotal[0]);
		$ordersubtotal = floatval($ordercontent->ordersubtotal[0]);
		$ordertaxtotal = floatval($ordercontent->ordertaxtotal[0]);

		$ordersubtotal = $this->priceCurrency->round($ordersubtotal);
		$ordersubtotalincltax = $this->priceCurrency->round($ordersubtotal + $ordertaxtotal);
		$ordertotal = $this->priceCurrency->round($ordertotal);

		$ebaysalesrecordnumber = (string)$ordercontent->ebaysalesrecordnumber;
		if(!$ebaysalesrecordnumber)
			$ebaysalesrecordnumber = '';

		$ebaytransactionid = (string)$ordercontent->ebaytransactionid;

		$ebayusername = (string)$ordercontent->ebayusername;
		if(!$ebayusername)
			$ebayusername = '';

		$quote->reserveOrderId();
		$order = $this->orderConverter->convert($quote->getShippingAddress());

		$shippingAddress = $this->orderAddressConverter->convert($quote->getShippingAddress());
		$billingAddress = $this->orderAddressConverter->convert($quote->getBillingAddress());

		$order->setBillingAddress($this->orderAddressConverter->convert($quote->getBillingAddress()));
		$order->setShippingAddress($this->orderAddressConverter->convert($quote->getShippingAddress()));
		$order->setPayment($this->orderPaymentConverter->convert($quote->getPayment()));
		$order->setCustomer($quote->getCustomer());
		$order->setCodistoOrderid((string)$ordercontent->orderid);

		if(preg_match('/\{ordernumber\}|\{ebaysalesrecordnumber\}|\{ebaytransactionid\}/', $ordernumberformat))
		{
			$incrementId = preg_replace('/\{ordernumber\}/', (string)$order->getIncrementId(), $ordernumberformat);
			$incrementId = preg_replace('/\{ebaysalesrecordnumber\}/', $ebaysalesrecordnumber, $incrementId);
			$incrementId = preg_replace('/\{ebaytransactionid\}/', $ebaytransactionid, $incrementId);
			$order->setIncrementId($incrementId);
		}
		else
		{
			$incrementId = $ordernumberformat.''.(string)$order->getIncrementId();
			$order->setIncrementId($incrementId);
		}

		$weight_total = 0;

		$quoteItems = $quote->getItemsCollection()->getItems();
		$quoteIdx = 0;

		foreach($ordercontent->orderlines->orderline as $orderline)
		{
			if($orderline->productcode[0] != 'FREIGHT')
			{
				$adjustStock = true;

				$product = null;

				$productcode = $orderline->productcode[0];
				if($productcode == null)
					$productcode = '';
				else
					$productcode = (string)$productcode;

				$productname = $orderline->productname[0];
				if($productname == null)
					$productname = '';
				else
					$productname = (string)$productname;

				$productid = $orderline->externalreference[0];
				if($productid != null)
				{
					$productid = intval($productid);

					$product = $this->productFactory->create();
					$product = $product->load($productid);

					if($product->getId())
					{
						$productcode = $product->getSku();
						$productname = $product->getName();
					}
					else
					{
						$product = null;
					}
				}

				if(!$product)
				{
					if($request->getQuery('checkproduct')) {
						throw new Exception("external reference not found");
					}
					$product = $this->productFactory->create();
					$adjustStock = false;
				}

				$qty = (int)$orderline->quantity[0];
				$subtotalinctax = floatval($orderline->linetotalinctax[0]);
				$subtotal = floatval($orderline->linetotal[0]);

				$price = floatval($orderline->price[0]);
				$priceinctax = floatval($orderline->priceinctax[0]);
				$taxamount = $priceinctax - $price;
				$taxpercent = $price == 0 ? 0 : round($priceinctax / $price - 1.0, 2) * 100;
				$weight = floatval($orderline->weight[0]);
				if($weight == 0)
					$weight = 1;

				$weight_total += $weight;

				$orderItem = $this->orderItemConverter->convert($quoteItems[$quoteIdx]);

				$quoteIdx++;

				$orderItem->setStoreId($store->getId());
				$orderItem->setData('product', $product);

				if($productid)
					$orderItem->setProductId($productid);

				if($productid)
					$orderItem->setBaseCost($product->getCost());

				if($productid)
				{
					$orderItem->setOriginalPrice($product->getFinalPrice());
					$orderItem->setBaseOriginalPrice($product->getFinalPrice());
				}
				else
				{
					$orderItem->setOriginalPrice($priceinctax);
					$orderItem->setBaseOriginalPrice($priceinctax);
				}

				$orderItem->setIsVirtual(false);
				$orderItem->setProductType('simple');
				$orderItem->setSku($productcode);
				$orderItem->setName($productname);
				$orderItem->setIsQtyDecimal(false);
				$orderItem->setNoDiscount(true);
				$orderItem->setQtyOrdered($qty);
				$orderItem->setPrice($price);
				$orderItem->setPriceInclTax($priceinctax);
				$orderItem->setBasePrice($price);
				$orderItem->setBasePriceInclTax($priceinctax);
				$orderItem->setTaxPercent($taxpercent);
				$orderItem->setTaxAmount($taxamount * $qty);
				$orderItem->setTaxBeforeDiscount($taxamount * $qty);
				$orderItem->setBaseTaxBeforeDiscount($taxamount * $qty);
				$orderItem->setDiscountAmount(0);
				$orderItem->setWeight($weight);
				$orderItem->setBaseRowTotal($subtotal);
				$orderItem->setBaseRowTotalInclTax($subtotalinctax);
				$orderItem->setRowTotal($subtotal);
				$orderItem->setRowTotalInclTax($subtotalinctax);
				$orderItem->setWeeeTaxApplied(\Zend_Json::encode(array()));

				$order->addItem($orderItem);

				if($ordercontent->orderstate != 'cancelled')
				{
					if($adjustStock)
					{
						$stockItem = $product->getStockItem();
						if(!$stockItem)
						{
							$stockItem = $this->stockItemFactory->create()
											->loadByProduct($product)
											->setStoreId($store->getId());
						}

						$typeId = $product->getTypeId();
						if(!$typeId)
							$typeId = 'simple';

						if($this->catalogInventoryConfig->isQty($typeId))
						{
							if($stockItem->canSubtractQty())
							{
								$productsToReindex[$product->getId()] = 0;

								$stockItem->subtractQty($orderItem->getQtyOrdered());
								$stockItem->save();
							}
						}
					}
				}
			}
		}

		$this->itemsForReindex->setItems(
			$this->stockManagement->registerProductsSale($productsToReindex, $quote->getStore()->getWebsiteId()));

		$quote->setInventoryProcessed(true);

		$order->setQuote($quote);

		$freightservice = 'Freight';
		$freighttotal =  0.0;
		$freighttotalextax =  0.0;
		$freighttax = 0.0;
		$taxpercent =  0.0;
		$taxrate =  1.0;

		foreach($ordercontent->orderlines->orderline as $orderline)
		{
			if($orderline->productcode[0] == 'FREIGHT')
			{
				$freighttotal += floatval($orderline->linetotalinctax[0]);
				$freighttotalextax += floatval($orderline->linetotal[0]);
				$freighttax = $freighttotal - $freighttotalextax;
				$freightservice = (string)$orderline->productname[0];
			}
		}

		if(strtolower($freightservice) != 'freight')
		{
			$order->setShippingDescription($freightservice);
		}

		$ordersubtotal -= $freighttotalextax;
		$ordersubtotalincltax -= $freighttotal;
		$ordertaxtotal -= $freighttax;

		$order->setBaseShippingAmount($freighttotal);
		$order->setShippingAmount($freighttotal);

		$order->setBaseShippingInclTax($freighttotal);
		$order->setShippingInclTax($freighttotal);

		$order->setBaseShippingTaxAmount($freighttax);
		$order->setShippingTaxAmount($freighttax);

		$order->setBaseSubtotal($ordersubtotal);
		$order->setSubtotal($ordersubtotal);

		$order->setBaseSubtotalInclTax($ordersubtotalincltax);
		$order->setSubtotalInclTax($ordersubtotalincltax);

		$order->setBaseTaxAmount($ordertaxtotal);
		$order->setTaxAmount($ordertaxtotal);


		$order->setDiscountAmount(0.0);
		$order->setShippingDiscountAmount(0.0);
		$order->setBaseShippingDiscountAmount(0.0);

		$order->setBaseHiddenTaxAmount(0.0);
		$order->setHiddenTaxAmount(0.0);
		$order->setBaseHiddenShippingTaxAmnt(0.0);
		$order->setHiddenShippingTaxAmount(0.0);

		$order->setBaseGrandTotal($ordertotal);
		$order->setGrandTotal($ordertotal);

		$order->setWeight($weight_total);

		$order->setBaseSubtotalInvoiced(0.0);
		$order->setBaseTaxInvoiced(0.0);
		$order->setBaseTotalInvoiced(0.0);
		$order->setSubtotalInvoiced(0.0);
		$order->setTaxInvoiced(0.0);
		$order->setTotalInvoiced(0.0);

		$order->setBaseTotalDue($ordertotal);
		$order->setTotalDue($ordertotal);
		$order->setDue($ordertotal);

		$order->save();

		try
		{
			if(!$request->getQuery('skiporderevent'))
			{
				$order->place();
				$this->orderRepository->save($order);
			}
		}
		catch(Exception $e)
		{
			$order->addStatusToHistory(\Magento\Sales\Model\Order::STATE_PROCESSING, "Exception Occurred Placing Order : ".$e->getMessage());
		}

		/* cancelled, processing, captured, inprogress, complete */
		if($ordercontent->orderstate == 'cancelled') {

			$order->setData('state', \Magento\Sales\Model\Order::STATE_CANCELED);
			$order->setData('status', \Magento\Sales\Model\Order::STATE_CANCELED);
			$order->addStatusToHistory(\Magento\Sales\Model\Order::STATE_CANCELED, "eBay Order $ebaysalesrecordnumber has been cancelled");

		} else if($ordercontent->orderstate == 'inprogress' || $ordercontent->orderstate == 'processing') {

			$order->setData('state', \Magento\Sales\Model\Order::STATE_PROCESSING);
			$order->setData('status', \Magento\Sales\Model\Order::STATE_PROCESSING);
			$order->addStatusToHistory(\Magento\Sales\Model\Order::STATE_PROCESSING, "eBay Order $ebaysalesrecordnumber is in progress");

		} else if ($ordercontent->orderstate == 'complete') {

			$order->setData('state', \Magento\Sales\Model\Order::STATE_COMPLETE);
			$order->setData('status', \Magento\Sales\Model\Order::STATE_COMPLETE);
			$order->addStatusToHistory(\Magento\Sales\Model\Order::STATE_COMPLETE, "eBay Order $ebaysalesrecordnumber is complete");

		} else {

			$order->setData('state', \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
			$order->setData('status', \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
			$order->addStatusToHistory(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT, "eBay Order $ebaysalesrecordnumber has been captured");

		}

		if($adjustStock == false) {
			$order->addStatusToHistory($order->getStatus(), "NOTE: Stock level not adjusted, please check your inventory.");
		}

		$order->setBaseTotalPaid(0);
		$order->setTotalPaid(0);
		$order->setBaseTotalDue(0);
		$order->setTotalDue(0);
		$order->setDue(0);

		$payment = $order->getPayment();

		$payment->setMethod('ebay');
		$payment->resetTransactionAdditionalInfo();
		$payment->setTransactionId(0);

		$transaction = $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_PAYMENT, null, false, '');
		if($paypaltransactionid)
		{
			$transaction->setTxnId($paypaltransactionid);
			$payment->setLastTransId($paypaltransactionid);
		}

		$payment->setAdditionalInformation('ebaysalesrecordnumber', $ebaysalesrecordnumber);
		$payment->setAdditionalInformation('ebayuser', $ebayusername);

		if($ordercontent->paymentstatus == 'complete')
		{
			$payment->setBaseAmountPaid($ordertotal);
			$payment->setAmountPaid($ordertotal);
			$payment->setBaseAmountAuthorized($ordertotal);
			$payment->setBaseAmountPaidOnline($ordertotal);
			$payment->setAmountAuthorized($ordertotal);
			$payment->setIsTransactionClosed(1);

		}
		else
		{
			$payment->setBaseAmountPaid(0.0);
			$payment->setAmountPaid(0.0);
			$payment->setBaseAmountAuthorized($ordertotal);
			$payment->setBaseAmountPaidOnline($ordertotal);
			$payment->setAmountAuthorized($ordertotal);
			$payment->setIsTransactionClosed(0);
		}

		$quote->setIsActive(false)->save();

		$this->eventManager->dispatch('checkout_type_onepage_save_order', array('order'=>$order, 'quote'=>$quote));
		$this->eventManager->dispatch('sales_model_service_quote_submit_before', array('order'=>$order, 'quote'=>$quote));

		$payment->save();

		$order->save();

		$this->eventManager->dispatch('sales_model_service_quote_submit_success', array('order'=>$order, 'quote'=>$quote));
		$this->eventManager->dispatch('sales_model_service_quote_submit_after', array('order'=>$order, 'quote'=>$quote));


		if($ordercontent->paymentstatus == 'complete')
		{
			$invoice = $this->orderService->prepareInvoice($order);

			if($invoice->getTotalQty())
			{
				$payment->setBaseAmountPaid(0.0);
				$payment->setAmountPaid(0.0);

				$invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
				$invoice->register();
			}
			$invoice->save();

			if(!in_array($invoice->getId(), $invoiceids))
				$invoiceids[] = $invoice->getId();

			$order->setBaseSubtotalInvoiced($ordersubtotal);
			$order->setBaseTaxInvoiced($ordertaxtotal);
			$order->setBaseTotalInvoiced($ordertotal);
			$order->setSubtotalInvoiced($ordersubtotal);
			$order->setTaxInvoiced($ordertaxtotal);
			$order->setTotalInvoiced($ordertotal);
			$order->save();
		}

		$response = $this->getResponse();

		$response->clearHeaders();
		$jsonResult = $this->context->getResultFactory()->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
		$jsonResult->setHttpResponseCode(200);
		$jsonResult->setHeader('Content-Type', 'application/json');
		$jsonResult->setHeader('Cache-Control', 'no-cache, must-revalidate', true);
		$jsonResult->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
		$jsonResult->setHeader('Pragma', 'no-cache', true);
		$jsonResult->setData(array( 'ack' => 'ok', 'orderid' => $order->getIncrementId()));

		if(!in_array($order->getId(), $orderids))
			$orderids[] = $order->getId();

		return $jsonResult;
	}

	private function ProcessOrderSync($quote, $order, $xml, &$productsToReindex, &$orderids, &$invoiceids, $store, $request)
	{

		$orderstatus = $order->getStatus();
		$ordercontent = $xml->entry->content->children('http://api.codisto.com/schemas/2009/');

		$paypaltransactionid = $ordercontent->orderpayments[0]->orderpayment->transactionid;

		$customer = $quote->getCustomer();
		if($customer)
			$order->setCustomer($customer);

		$orderBillingAddress = $order->getBillingAddress();
		$quoteBillingAddress = $quote->getBillingAddress();

		$orderBillingAddress->setPrefix($quoteBillingAddress->getPrefix());
		$orderBillingAddress->setFirstname($quoteBillingAddress->getFirstname());
		$orderBillingAddress->setMiddlename($quoteBillingAddress->getMiddlename());
		$orderBillingAddress->setLastname($quoteBillingAddress->getLastname());
		$orderBillingAddress->setSuffix($quoteBillingAddress->getSuffix());
		$orderBillingAddress->setCompany($quoteBillingAddress->getCompany());
		$orderBillingAddress->setStreet($quoteBillingAddress->getStreet());
		$orderBillingAddress->setCity($quoteBillingAddress->getCity());
		$orderBillingAddress->setRegion($quoteBillingAddress->getRegion());
		$orderBillingAddress->setRegionId($quoteBillingAddress->getRegionId());
		$orderBillingAddress->setPostcode($quoteBillingAddress->getPostcode());
		$orderBillingAddress->setCountryId($quoteBillingAddress->getCountryId());
		$orderBillingAddress->setTelephone($quoteBillingAddress->getTelephone());
		$orderBillingAddress->setFax($quoteBillingAddress->getFax());
		$orderBillingAddress->setEmail($quoteBillingAddress->getEmail());

		$orderShippingAddress = $order->getShippingAddress();
		$quoteShippingAddress = $quote->getShippingAddress();

		$orderShippingAddress->setPrefix($quoteShippingAddress->getPrefix());
		$orderShippingAddress->setFirstname($quoteShippingAddress->getFirstname());
		$orderShippingAddress->setMiddlename($quoteShippingAddress->getMiddlename());
		$orderShippingAddress->setLastname($quoteShippingAddress->getLastname());
		$orderShippingAddress->setSuffix($quoteShippingAddress->getSuffix());
		$orderShippingAddress->setCompany($quoteShippingAddress->getCompany());
		$orderShippingAddress->setStreet($quoteShippingAddress->getStreet());
		$orderShippingAddress->setCity($quoteShippingAddress->getCity());
		$orderShippingAddress->setRegion($quoteShippingAddress->getRegion());
		$orderShippingAddress->setRegionId($quoteShippingAddress->getRegionId());
		$orderShippingAddress->setPostcode($quoteShippingAddress->getPostcode());
		$orderShippingAddress->setCountryId($quoteShippingAddress->getCountryId());
		$orderShippingAddress->setTelephone($quoteShippingAddress->getTelephone());
		$orderShippingAddress->setFax($quoteShippingAddress->getFax());
		$orderShippingAddress->setEmail($quoteShippingAddress->getEmail());

		$ebaysalesrecordnumber = (string)$ordercontent->ebaysalesrecordnumber;
		if(!$ebaysalesrecordnumber)
			$ebaysalesrecordnumber = '';

		$currencyCode = (string)$ordercontent->transactcurrency[0];
		$ordertotal = floatval($ordercontent->ordertotal[0]);
		$ordersubtotal = floatval($ordercontent->ordersubtotal[0]);
		$ordertaxtotal = floatval($ordercontent->ordertaxtotal[0]);

		$ordersubtotal = $this->priceCurrency->round($ordersubtotal);
		$ordersubtotalincltax = $this->priceCurrency->round($ordersubtotal + $ordertaxtotal);
		$ordertotal = $this->priceCurrency->round($ordertotal);

		$freightcarrier = 'Post';
		$freightservice = 'Freight';
		$freighttotal =  0.0;
		$freighttotalextax =  0.0;
		$freighttax = 0.0;
		$taxpercent =  0.0;
		$taxrate =  1.0;

		foreach($ordercontent->orderlines->orderline as $orderline)
		{
			if($orderline->productcode[0] == 'FREIGHT')
			{
				$freighttotal += floatval($orderline->linetotalinctax[0]);
				$freighttotalextax += floatval($orderline->linetotal[0]);
				$freighttax = $freighttotal - $freighttotalextax;
				$freightservice = (string)$orderline->productname[0];
			}
		}

		if(strtolower($freightservice) != 'freight')
		{
			$order->setShippingDescription($freightservice);
		}

		$ordersubtotal -= $freighttotalextax;
		$ordersubtotalincltax -= $freighttotal;
		$ordertaxtotal -= $freighttax;

		$order->setBaseShippingAmount($freighttotal);
		$order->setShippingAmount($freighttotal);

		$order->setBaseShippingInclTax($freighttotal);
		$order->setShippingInclTax($freighttotal);

		$order->setBaseShippingTaxAmount($freighttax);
		$order->setShippingTaxAmount($freighttax);

		$order->setBaseSubtotal($ordersubtotal);
		$order->setSubtotal($ordersubtotal);

		$order->setBaseSubtotalInclTax($ordersubtotalincltax);
		$order->setSubtotalInclTax($ordersubtotalincltax);

		$order->setBaseTaxAmount($ordertaxtotal);
		$order->setTaxAmount($ordertaxtotal);

		$order->setDiscountAmount(0.0);
		$order->setShippingDiscountAmount(0.0);
		$order->setBaseShippingDiscountAmount(0.0);

		$order->setBaseHiddenTaxAmount(0.0);
		$order->setHiddenTaxAmount(0.0);
		$order->setBaseHiddenShippingTaxAmnt(0.0);
		$order->setHiddenShippingTaxAmount(0.0);

		$order->setBaseGrandTotal($ordertotal);
		$order->setGrandTotal($ordertotal);

		$orderlineStockReserved = array();
		foreach($order->getAllItems() as $item)
		{
			$productId = $item->getProductId();
			if($productId || $productId == 0)
			{
				if(isset($orderlineStockReserved[$productId]))
					$orderlineStockReserved[$productId] += $item->getQtyOrdered();
				else
					$orderlineStockReserved[$productId] = $item->getQtyOrdered();
			}
		}

		$visited = array();

		$weight_total = 0;

		$quoteItems = $quote->getItemsCollection()->getItems();
		$quoteIdx = 0;

		$totalquantity = 0;
		foreach($ordercontent->orderlines->orderline as $orderline)
		{
			if($orderline->productcode[0] != 'FREIGHT')
			{
				$adjustStock = true;

				$product = null;

				$productcode = $orderline->productcode[0];
				if($productcode == null)
					$productcode = '';
				else
					$productcode = (string)$productcode;

				$productname = $orderline->productname[0];
				if($productname == null)
					$productname = '';
				else
					$productname = (string)$productname;

				$productid = $orderline->externalreference[0];

				if($productid != null)
				{
					$productid = intval($productid);

					$product = $this->productFactory->create()->load($productid);

					if($product->getId())
					{
						$productcode = $product->getSku();
						$productname = $product->getName();
					}
					else
					{
						$product = $this->productFactory->create();
					}
				}

				if(!$product)
				{
					if($request->getQuery('checkproduct')) {
						throw new Exception('externalreference not found');
					}
					$product = $this->productFactory->create();
					$adjustStock = false;
				}

				$qty = (int)$orderline->quantity[0];
				$subtotalinctax = floatval($orderline->linetotalinctax[0]);
				$subtotal = floatval($orderline->linetotal[0]);

				$totalquantity += $qty;

				$price = floatval($orderline->price[0]);
				$priceinctax = floatval($orderline->priceinctax[0]);
				$taxamount = $priceinctax - $price;
				$taxpercent = $price == 0 ? 0 : round($priceinctax / $price - 1.0, 2) * 100;
				$weight = floatval($orderline->weight[0]);
				if($weight == 0)
					$weight = 1;

				$weight_total += $weight;

				$itemFound = false;
				foreach($order->getAllItems() as $item)
				{
					if(!isset($visited[$item->getId()]))
					{
						if($productid)
						{
							if($item->getProductId() == $productid)
							{
								$itemFound = true;
								$visited[$item->getId()] = true;
								break;
							}
						}
						else
						{
							if($item->getSku() == $productcode)
							{
								$itemFound = true;
								$visited[$item->getId()] = true;
								break;
							}
						}
					}
				}

				if(!$itemFound)
				{
					$item = $this->orderItemConverter->convert($quoteItems[$quoteIdx]);
				}

				$quoteIdx++;

				$item->setStoreId($store->getId());

				$item->setData('product', $product);

				if($productid)
					$item->setProductId($productid);

				if($productid)
					$item->setBaseCost($product->getCost());

				if($productid)
				{
					$item->setOriginalPrice($product->getFinalPrice());
					$item->setBaseOriginalPrice($product->getFinalPrice());
				}
				else
				{
					$item->setOriginalPrice($priceinctax);
					$item->setBaseOriginalPrice($priceinctax);
				}

				$item->setIsVirtual(false);
				$item->setProductType('simple');
				$item->setSku($productcode);
				$item->setName($productname);
				$item->setIsQtyDecimal(false);
				$item->setNoDiscount(true);
				$item->setQtyOrdered($qty);
				$item->setPrice($price);
				$item->setPriceInclTax($priceinctax);
				$item->setBasePrice($price);
				$item->setBasePriceInclTax($priceinctax);
				$item->setTaxPercent($taxpercent);
				$item->setTaxAmount($taxamount * $qty);
				$item->setTaxBeforeDiscount($taxamount * $qty);
				$item->setBaseTaxBeforeDiscount($taxamount * $qty);
				$item->setDiscountAmount(0);
				$item->setWeight($weight);
				$item->setBaseRowTotal($subtotal);
				$item->setBaseRowTotalInclTax($subtotalinctax);
				$item->setRowTotal($subtotal);
				$item->setRowTotalInclTax($subtotalinctax);
				$item->setWeeeTaxApplied(\Zend_Json::encode(array()));

				if(!$itemFound)
					$order->addItem($item);

				if($ordercontent->orderstate != 'cancelled')
				{
					if($adjustStock)
					{
						$stockItem = $product->getStockItem();
						if(!$stockItem)
						{
							$stockItem = $this->stockItemFactory->create()
											->loadByProduct($product)
											->setStoreId($store->getId());
						}

						$typeId = $product->getTypeId();
						if(!$typeId)
							$typeId = 'simple';

						if($this->catalogInventoryConfig->isQty($typeId))
						{
							if($stockItem->canSubtractQty())
							{
								$stockReserved = isset($orderlineStockReserved[$productid]) ? $orderlineStockReserved[$productid] : 0;

								$stockMovement = $qty - $stockReserved;

								if($stockMovement > 0)
								{
									$productsToReindex[$product->getId()] = 0;

									$stockItem->subtractQty($stockMovement);
									$stockItem->save();
								}
								else if($stockMovement < 0)
								{
									$productsToReindex[$product->getId()] = 0;

									$stockMovement = abs($stockMovement);

									$stockItem->addQty($stockMovement);
									$stockItem->save();
								}
							}
						}
					}
				}
			}
		}

		$visited = array();
		foreach($order->getAllItems() as $item)
		{
			$itemFound = false;

			$orderlineIndex = 0;
			foreach($ordercontent->orderlines->orderline as $orderline)
			{
				if(!isset($visited[$orderlineIndex]) &&
						$orderline->productcode[0] != 'FREIGHT')
				{
					$productcode = $orderline->productcode[0];
					if($productcode == null)
						$productcode = '';
					else
						$productcode = (string)$productcode;

					$productname = $orderline->productname[0];
					if($productname == null)
						$productname = '';
					else
						$productname = (string)$productname;

					$productid = $orderline->externalreference[0];
					if($productid != null)
					{
						$productid = intval($productid);
					}

					if($productid)
					{
						if($item->getProductId() == $productid)
						{
							$itemFound = true;
							$visited[$orderlineIndex] = true;
						}
					}
					else
					{
						if($item->getSku() == $productcode)
						{
							$itemFound = true;
							$visited[$orderlineIndex] = true;
						}
					}
				}

				$orderlineIndex++;
			}

			if(!$itemFound)
				$item->delete();
		}

		$order->setTotalQtyOrdered((int)$totalquantity);
		$order->setWeight($weight_total);

		/* States: cancelled, processing, captured, inprogress, complete */
		if(($ordercontent->orderstate == 'captured' ||
			$ordercontent->paymentstatus != 'complete') &&
			($orderstatus!=\Magento\Sales\Model\Order::STATE_PROCESSING &&
				$orderstatus!=\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT &&
				$orderstatus!=\Magento\Sales\Model\Order::STATE_NEW))
		{

			$order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
			$order->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
			$order->addStatusToHistory($order->getStatus(), "eBay Order $ebaysalesrecordnumber is pending payment");
		}

		if($ordercontent->orderstate == 'cancelled' && $orderstatus!=\Magento\Sales\Model\Order::STATE_CANCELED)
		{
			$order->setState(\Magento\Sales\Model\Order::STATE_CANCELED);
			$order->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
			$order->addStatusToHistory($order->getStatus(), "eBay Order $ebaysalesrecordnumber has been cancelled");
		}

		if(($ordercontent->orderstate == 'inprogress' || $ordercontent->orderstate == 'processing') &&
			$ordercontent->paymentstatus == 'complete' &&
			$orderstatus!=\Magento\Sales\Model\Order::STATE_PROCESSING &&
			$orderstatus!=\Magento\Sales\Model\Order::STATE_COMPLETE)
		{
			$order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
			$order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
			$order->addStatusToHistory($order->getStatus(), "eBay Order $ebaysalesrecordnumber is in progress");
		}

		if($ordercontent->orderstate == 'complete' &&
			$orderstatus!=\Magento\Sales\Model\Order::STATE_COMPLETE)
		{

			$order->setData('state', \Magento\Sales\Model\Order::STATE_COMPLETE);
			$order->setData('status', \Magento\Sales\Model\Order::STATE_COMPLETE);
			$order->addStatusToHistory($order->getStatus(), "eBay Order $ebaysalesrecordnumber is complete");
		}

		if(
			($ordercontent->orderstate == 'cancelled' && $orderstatus!= \Magento\Sales\Model\Order::STATE_CANCELED) ||
			($ordercontent->orderstate != 'cancelled' && $orderstatus == \Magento\Sales\Model\Order::STATE_CANCELED))
		{
			foreach($ordercontent->orderlines->orderline as $orderline)
			{
				if($orderline->productcode[0] != 'FREIGHT')
				{
					$catalog = $this->productFactory->create();
					$prodid = $catalog->getIdBySku((string)$orderline->productcode[0]);

					if($prodid)
					{
						$product = $this->productFactory->create()->load($prodid);
						if($product)
						{
							$qty = $orderline->quantity[0];
							$totalquantity += $qty;

							$stockItem = $product->getStockItem();
							if(!$stockItem)
							{
								$stockItem = $this->stockItemFactory->create()
												->loadByProduct($product)
												->setStoreId($store->getId());
							}

							$typeId = $product->getTypeId();
							if(!$typeId)
								$typeId = 'simple';

							if($this->catalogInventoryConfig->isQty($typeId))
							{
								if($stockItem->canSubtractQty())
								{
									if($ordercontent->orderstate == 'cancelled') {

										$productsToReindex[$product->getId()] = 0;

										$stockItem->addQty(intval($qty));

									} else {

										$productsToReindex[$product->getId()] = 0;

										$stockItem->subtractQty(intval($qty));

									}

									$stockItem->save();
								}
							}
						}
					}

				}
			}
		}

		$order->save();

		if(!$order->hasInvoices())
		{
			if($ordercontent->paymentstatus == 'complete' && $order->canInvoice())
			{
				$order->setBaseTotalPaid($ordertotal);
				$order->setTotalPaid($ordertotal);
				$order->setBaseTotalDue(0.0);
				$order->setTotalDue(0.0);
				$order->setDue(0.0);

				$payment = $order->getPayment();

				if($paypaltransactionid)
				{
					$transaction = $payment->getTransaction(0);
					if($transaction)
					{
						$transaction->setTxnId($paypaltransactionid);
						$payment->setLastTransId($paypaltransactionid);
					}
				}

				$payment->setMethod('ebay');
				$payment->setParentTransactionId(null)
					->setIsTransactionClosed(1);

				$payment->save();

				$invoice = $this->orderService->prepareInvoice($order);

				if($invoice->getTotalQty())
				{
					$payment->setBaseAmountPaid(0.0);
					$payment->setAmountPaid(0.0);

					$invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
					$invoice->register();
				}
				$invoice->save();

				if(!in_array($invoice->getId(), $invoiceids))
					$invoiceids[] = $invoice->getId();

				$order->setBaseSubtotalInvoiced($ordersubtotal);
				$order->setBaseTaxInvoiced($ordertaxtotal);
				$order->setBaseTotalInvoiced($ordertotal);
				$order->setSubtotalInvoiced($ordersubtotal);
				$order->setTaxInvoiced($ordertaxtotal);
				$order->setTotalInvoiced($ordertotal);
				$order->save();
			}
		}

		$response = $this->getResponse();

		$response->clearHeaders();

		$jsonResult = $this->context->getResultFactory()->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
		$jsonResult->setHttpResponseCode(200);
		$jsonResult->setHeader('Content-Type', 'application/json');
		$jsonResult->setHeader('Cache-Control', 'no-cache, must-revalidate', true);
		$jsonResult->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
		$jsonResult->setHeader('Pragma', 'no-cache', true);
		$jsonResult->setData(array( 'ack' => 'ok', 'orderid' => $order->getIncrementId()));

		if(!in_array($order->getId(), $orderids))
			$orderids[] = $order->getId();

		return $jsonResult;
	}

	private function ProcessQuote($quote, $xml, $store, $request)
	{
		$connection = $this->resourceConnection->getConnection();

		$ordercontent = $xml->entry->content->children('http://api.codisto.com/schemas/2009/');

		$register_customer = (string)$ordercontent->register_customer == 'false' ? false : true;

		$websiteId = $store->getWebsiteId();

		$billing_address = $ordercontent->orderaddresses->orderaddress[0];
		$billing_first_name = $billing_last_name = '';

		if(strpos($billing_address->name, ' ') !== false) {
			$billing_name = explode(' ', (string)$billing_address->name, 2);
			$billing_first_name = $billing_name[0];
			$billing_last_name = $billing_name[1];
		} else {
			$billing_first_name = (string)$billing_address->name;
		}

		$billing_phone = (string)$billing_address->phone;
		if(!$billing_phone)
			$billing_phone = 'Not Available';

		$shipping_address = $ordercontent->orderaddresses->orderaddress[1];
		$shipping_first_name = $shipping_last_name = '';

		if(strpos($shipping_address->name, ' ') !== false) {
			$shipping_name = explode(' ', (string)$shipping_address->name, 2);
			$shipping_first_name = $shipping_name[0];
			$shipping_last_name = $shipping_name[1];
		} else {
			$shipping_first_name = (string)$shipping_address->name;
		}

		$shipping_phone = (string)$shipping_address->phone;
		if(!$shipping_phone)
			$shipping_phone = 'Not Available';

		$email = (string)$billing_address->email;
		if(!$email || $email == 'Invalid Request')
			$email = 'mail@example.com';

		$regionCollection = $this->getRegionCollection($billing_address->countrycode);

		$regionsel_id = 0;
		foreach($regionCollection as $region)
		{
			if(in_array($billing_address->division, array($region['code'], $region['name'])))
			{

				$regionsel_id = $region['region_id'];
			}
		}

		$addressBilling = array(
			'email' => $email,
			'prefix' => '',
			'suffix' => '',
			'company' => (string)$billing_address->companyname,
			'firstname' => (string)$billing_first_name,
			'middlename' => '',
			'lastname' => (string)$billing_last_name,
			'street' => (string)$billing_address->address1.($billing_address->address2 ? "\n".$billing_address->address2 : ''),
			'city' => (string)$billing_address->place,
			'postcode' => (string)$billing_address->postalcode,
			'telephone' => (string)$billing_phone,
			'fax' => '',
			'country_id' => (string)$billing_address->countrycode,
			'region_id' => $regionsel_id, // id from directory_country_region table
			'region' => (string)$billing_address->division
		);

		$regionsel_id_ship = 0;
		foreach($regionCollection as $region)
		{
			if(in_array($shipping_address->division, array($region['code'], $region['name'])))
			{
				$regionsel_id_ship = $region['region_id'];
			}
		}

		$addressShipping = array(
			'email' => $email,
			'prefix' => '',
			'suffix' => '',
			'company' => (string)$shipping_address->companyname,
			'firstname' => (string)$shipping_first_name,
			'middlename' => '',
			'lastname' => (string)$shipping_last_name,
			'street' => (string)$shipping_address->address1.($shipping_address->address2 ? "\n".$shipping_address->address2 : ''),
			'city' => (string)$shipping_address->place,
			'postcode' => (string)$shipping_address->postalcode,
			'telephone' => (string)$shipping_phone,
			'fax' => '',
			'country_id' => (string)$shipping_address->countrycode,
			'region_id' => $regionsel_id_ship, // id from directory_country_region table
			'region' => (string)$shipping_address->division
		);

		$customer = null;

		if($register_customer && $email != 'mail@example.com')
		{
			$customer = $this->customerFactory->create();
			$customer->setWebsiteId($websiteId);
			$customer->setStoreId($store->getId());

			for($Retry = 0; ; $Retry++)
			{
				try
				{
					$connection->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
					$connection->beginTransaction();

					$customer->loadByEmail($email);

					if(!$customer->getId())
					{
						$ebayGroup = $this->customerGroupFactory->create();
						$ebayGroup->load('eBay', 'customer_group_code');
						if(!$ebayGroup->getId())
						{
							$defaultGroup = $this->customerGroupFactory->create()->load(1);

							$ebayGroup->setCode('eBay');
							$ebayGroup->setTaxClassId($defaultGroup->getTaxClassId());
							$ebayGroup->save();
						}

						$customerGroupId = $ebayGroup->getId();

						$customer->setWebsiteId($websiteId);
						$customer->setStoreId($store->getId());
						$customer->setEmail($email);
						$customer->setFirstname((string)$billing_first_name);
						$customer->setLastname((string)$billing_last_name);
						$customer->setPassword('');
						$customer->setGroupId($customerGroupId);
						$customer->save();
						$customer->setConfirmation(null);
						$customer->save();

						$customerId = $customer->getId();

						$customerAddress = $this->customerAddressFactory->create();
						$customerAddress->setData($addressBilling)
							->setCustomerId($customer->getId())
							->setIsDefaultBilling(1)
							->setSaveInAddressBook(1);
						$customerAddress->save();


						$customerAddress->setData($addressShipping)
							->setCustomerId($customer->getId())
							->setIsDefaultShipping(1)
							->setSaveInAddressBook(1);
						$customerAddress->save();
					}
					else
					{
						$customerId = $customer->getId();
						$customerGroupId = $customer->getGroupId();
					}

					$connection->commit();
					break;
				}
				catch(Exception $e)
				{
					if($Retry < 5)
					{
						if($e->getCode() == 40001)
						{
							$connection->rollback();
							sleep($Retry * 10);
							continue;
						}
					}

					$connection->rollback();
					throw $e;
				}
			}
		}

		$currencyCode = (string)$ordercontent->transactcurrency[0];
		$ordertotal = floatval($ordercontent->ordertotal[0]);
		$ordersubtotal = floatval($ordercontent->ordersubtotal[0]);
		$ordertaxtotal = floatval($ordercontent->ordertaxtotal[0]);

		$ordersubtotal = $this->priceCurrency->round($ordersubtotal);
		$ordersubtotalincltax = $this->priceCurrency->round($ordersubtotal + $ordertaxtotal);
		$ordertotal = $this->priceCurrency->round($ordertotal);

		$quote->setCurrency();
		$quote->setIsSuperMode(true);
		$quote->setStore($store);
		$quote->setCheckoutMethod(\Magento\Checkout\Model\Type\Onepage::METHOD_GUEST);

		if($customer)
		{
			$customerData = $this->customerRepository->getById($customerId);

			$quote->assignCustomer($customerData);
			$quote->setCustomerId($customerId);
			$quote->setCustomerGroupId($customerGroupId);
		}
		else
		{
			$quote->setCustomerId(null);
			$quote->setCustomerEmail($email);
			$quote->setCustomerFirstName((string)$billing_first_name);
			$quote->setCustomerLastName((string)$billing_last_name);
			$quote->setCustomerIsGuest(true);
			$quote->setCustomerGroupId(\Magento\Customer\Model\Group::NOT_LOGGED_IN_ID);
		}

		$billingAddress = $quote->getBillingAddress();
		if($customer)
			$billingAddress->setCustomer($customer);
		$billingAddress->addData($addressBilling);

		$shippingAddress = $quote->getShippingAddress();
		if($customer)
			$shippingAddress->setCustomer($customer);
		$shippingAddress->addData($addressShipping);

		$totalitemcount = 0;
		$totalitemqty = 0;

		foreach($ordercontent->orderlines->orderline as $orderline)
		{
			if($orderline->productcode[0] != 'FREIGHT')
			{
				$adjustStock = true;

				$product = null;
				$productcode = (string)$orderline->productcode;
				if($productcode == null)
					$productcode = '';
				$productname = (string)$orderline->productname;
				if($productname == null)
					$productname = '';

				$productid = $orderline->externalreference[0];
				if($productid != null)
				{
					$productid = intval($productid);

					$product = $this->productFactory->create();
					$product = $product->load($productid);

					if($product->getId())
					{
						$productcode = $product->getSku();
						$productname = $product->getName();
					}
					else
					{
						$product = null;
					}
				}

				if(!$product)
				{
					if($request->getQuery('checkproduct')) {
						throw new Exception('externalreference not found');
					}
					$product = $this->productFactory->create();
				}

				$product->setIsSuperMode(true);

				$qty = (int)$orderline->quantity[0];
				$subtotalinctax = floatval($orderline->linetotalinctax[0]);
				$subtotal = floatval($orderline->linetotal[0]);

				$totalitemcount += 1;
				$totalitemqty += $qty;

				$price = floatval($orderline->price[0]);
				$priceinctax = floatval($orderline->priceinctax[0]);
				$taxamount = $priceinctax - $price;
				$taxpercent = $price == 0 ? 0 : round($priceinctax / $price - 1.0, 2) * 100;
				$weight = floatval($orderline->weight[0]);

				$item = $this->quoteItemFactory->create();
				$item->setStoreId($store->getId());
				$item->setQuote($quote);

				$item->setData('product', $product);
				$item->setProductId($productid);
				$item->setProductType('simple');
				$item->setIsRecurring(false);

				if($productid)
					$item->setTaxClassId($product->getTaxClassId());

				if($productid)
					$item->setBaseCost($product->getCost());

				$item->setSku($productcode);
				$item->setName($productname);
				$item->setIsVirtual(false);
				$item->setIsQtyDecimal(false);
				$item->setNoDiscount(true);
				$item->setWeight($weight);
				$item->setData('qty', $qty);
				$item->setPrice($price);
				$item->setBasePrice($price);
				$item->setCustomPrice($price);
				$item->setDiscountPercent(0);
				$item->setDiscountAmount(0);
				$item->setBaseDiscountAmount(0);
				$item->setTaxPercent($taxpercent);
				$item->setTaxAmount($taxamount * $qty);
				$item->setBaseTaxAmount($taxamount * $qty);
				$item->setRowTotal($subtotal);
				$item->setBaseRowTotal($subtotal);
				$item->setRowTotalWithDiscount($subtotal);
				$item->setRowWeight($weight * $qty);
				$item->setOriginalCustomPrice($price);
				$item->setPriceInclTax($priceinctax);
				$item->setBasePriceInclTax($priceinctax);
				$item->setRowTotalInclTax($subtotalinctax);
				$item->setBaseRowTotalInclTax($subtotalinctax);
				$item->setWeeeTaxApplied(\Zend_Json::encode(array()));

				$quote->getItemsCollection()->addItem($item);
			}
		}

		$freighttotal = 0.0;
		$freighttotalextax = 0.0;
		$freighttax = 0.0;
		$freightservice = '';

		foreach($ordercontent->orderlines->orderline as $orderline)
		{
			if($orderline->productcode[0] == 'FREIGHT')
			{
				$freighttotal += floatval($orderline->linetotalinctax[0]);
				$freighttotalextax += floatval($orderline->linetotal[0]);
				$freighttax = (float)$freighttotal - $freighttotalextax;
				$freightservice = $orderline->productname[0];
			}
		}

		$ordersubtotal -= $freighttotalextax;
		$ordersubtotalincltax -= $freighttotal;
		$ordertaxtotal -= $freighttax;

		$quote->setBaseCurrencyCode($currencyCode);
		$quote->setStoreCurrencyCode($currencyCode);
		$quote->setQuoteCurrencyCode($currencyCode);
		$quote->setItemsCount($totalitemcount);
		$quote->setItemsQty($totalitemqty);
		$quote->setVirtualItemsQty(0);
		$quote->setGrandTotal($ordertotal);
		$quote->setBaseGrandTotal($ordertotal);
		$quote->setSubtotal($ordersubtotal);
		$quote->setBaseSubtotal($ordersubtotal);
		$quote->setSubtotal($ordersubtotal);
		$quote->setBaseSubtotalWithDiscount($ordersubtotal);
		$quote->setSubtotalWithDiscount($ordersubtotal);
		$quote->setData('trigger_recollect', 0);
		$quote->setTotalsCollectedFlag(true);
		$quote->setInventoryProcessed(false);
		$quote->save();

		$quotePayment = $quote->getPayment();
		$quotePayment->setMethod('ebay');
		$quotePayment->save();


		$customerInstruction = @count($ordercontent->instructions) ? strval($ordercontent->instructions) : '';

		$checkoutSession = $this->session;
		$checkoutSession->setCustomer($customer);
		$checkoutSession->replaceQuote($quote);
		$checkoutSession->setData('customer_comment', $customerInstruction);
		$checkoutSession->setData('destination_type', 'residence');

		$shippingAddress = $quote->getShippingAddress();
		$shippingAddress->setSubtotal($ordersubtotal);
		$shippingAddress->setBaseSubtotal($ordersubtotal);
		$shippingAddress->setSubtotalWithDiscount($ordersubtotal);
		$shippingAddress->setBaseSubtotalWithDiscount($ordersubtotal);
		$shippingAddress->setTaxAmount($ordertaxtotal);
		$shippingAddress->setBaseTaxAmount($ordertaxtotal);
		$shippingAddress->setShippingTaxAmount($freighttax);
		$shippingAddress->setBaseShippingTaxAmount($freighttax);
		$shippingAddress->setDiscountAmount(0);
		$shippingAddress->setBaseDiscountAmount(0);
		$shippingAddress->setGrandTotal($ordertotal);
		$shippingAddress->setBaseGrandTotal($ordertotal);
		$shippingAddress->setAppliedTaxes(array());
		$shippingAddress->setShippingDiscountAmount(0);
		$shippingAddress->setBaseShippingDiscountAmount(0);
		$shippingAddress->setSubtotalInclTax($ordersubtotalincltax);
		$shippingAddress->setBaseSubtotalTotalInclTax($ordersubtotalincltax);

		$freightcode = 'flatrate_flatrate';
		$freightcarrier = 'Post';
		$freightcarriertitle = 'Post';
		$freightmethod = 'Freight';
		$freightmethodtitle = 'Freight';
		$freightmethoddescription = '';
		$freighttotal =  0.0;
		$freighttotalextax =  0.0;
		$freighttax = 0.0;
		$taxpercent =  0.0;
		$taxrate =  1.0;

		$freightcost = null;
		$freightRate = null;

		try {

			if(!$request->getQuery('skipshipping')) {
				$shippingRequest = $this->rateRequestFactory->create();
				$shippingRequest->setAllItems($quote->getAllItems());
				$shippingRequest->setDestCountryId($shippingAddress->getCountryId());
				$shippingRequest->setDestRegionId($shippingAddress->getRegionId());
				$shippingRequest->setDestRegionCode($shippingAddress->getRegionCode());
				$shippingRequest->setDestStreet($shippingAddress->getStreet(-1));
				$shippingRequest->setDestCity($shippingAddress->getCity());
				$shippingRequest->setDestPostcode($shippingAddress->getPostcode());
				$shippingRequest->setPackageValue($quote->getBaseSubtotal());
				$shippingRequest->setPackageValueWithDiscount($quote->getBaseSubtotalWithDiscount());
				$shippingRequest->setPackageWeight($quote->getWeight());
				$shippingRequest->setPackageQty($quote->getItemQty());
				$shippingRequest->setPackagePhysicalValue($quote->getBaseSubtotal());
				$shippingRequest->setFreeMethodWeight($quote->getWeight());
				$shippingRequest->setStoreId($store->getId());
				$shippingRequest->setWebsiteId($store->getWebsiteId());
				$shippingRequest->setFreeShipping(false);
				$shippingRequest->setBaseCurrency($currencyCode);
				$shippingRequest->setPackageCurrency($currencyCode);
				$shippingRequest->setBaseSubtotalInclTax($quote->getBaseSubtotalInclTax());

				$shippingResult = $this->shipping->collectRates($shippingRequest)->getResult();

				$shippingRates = $shippingResult->getAllRates();

				foreach($shippingRates as $shippingRate)
				{
					if($shippingRate instanceof \Magento\Quote\Model\Quote\Address\RateResult\Metho)
					{
						if(is_null($freightcost) || (!is_null($shippingRate->getPrice()) && $shippingRate->getPrice() < $freightcost))
						{
							$isPickup = $shippingRate->getPrice() == 0 &&
										(preg_match('/(?:^|\W|_)pick\s*up(?:\W|_|$)/i', strval($shippingRate->getMethod())) ||
											preg_match('/(?:^|\W|_)pick\s*up(?:\W|_|$)/i', strval($shippingRate->getCarrierTitle())) ||
											preg_match('/(?:^|\W|_)pick\s*up(?:\W|_|$)/i', strval($shippingRate->getMethodTitle())));

							if(!$isPickup)
							{
								$freightRate = $this->quoteAddressRateFactory->create()
												->importShippingRate($shippingRate);

								$freightcode = $freightRate->getCode();
								$freightcarrier = $freightRate->getCarrier();
								$freightcarriertitle = $freightRate->getCarrierTitle();
								$freightmethod = $freightRate->getMethod();
								$freightmethodtitle = $freightRate->getMethodTitle();
								$freightmethoddescription = $freightRate->getMethodDescription();
							}
						}
					}
				}
			}
		}
		catch(Exception $e)
		{

		}

		if(!$freightRate)
		{
			$freightRate = $this->quoteAddressRateFactory->create();
			$freightRate->setCode($freightcode)
				->setCarrier($freightcarrier)
				->setCarrierTitle($freightcarriertitle)
				->setMethod($freightmethod)
				->setMethodTitle($freightmethodtitle)
				->setMethodDescription($freightmethoddescription)
				->setPrice($freighttotal);
		}

		$shippingAddress->addShippingRate($freightRate);
		$shippingAddress->setShippingMethod($freightcode);
		$shippingAddress->setShippingDescription($freightcarriertitle.' - '.$freightmethodtitle);
		$shippingAddress->setShippingAmount($freighttotal);
		$shippingAddress->setBaseShippingAmount($freighttotal);
		$shippingAddress->save();
	}

	private function getRegionCollection($countryCode)
	{
		return $this->country->loadByCode($countryCode)->getRegions();
	}
}
