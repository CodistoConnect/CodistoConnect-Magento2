<?php

/**
 * Codisto LINQ Sync Extension
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
 * @package   Codisto_Connect
 * @copyright 2016-2017 On Technology Pty. Ltd. (http://codisto.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      https://codisto.com/connect/
 */

namespace Codisto\Connect\Controller\Index;

use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\DB\Ddl\Table;

class Index extends \Magento\Framework\App\Action\Action
{
    private $context;
    private $eventManager;
    private $resourceConnection;
    private $deploymentConfigFactory;
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
    private $stockRegistryProvider;
    private $stockConfiguration;
    private $catalogInventoryConfig;
    private $orderService;
    private $stockManagement;
    private $itemsForReindex;
    private $codistoHelper;
    private $visitor;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\App\DeploymentConfigFactory $deploymentConfigFactory,
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
        \Magento\Checkout\Model\Session\Proxy $session, // @codingStandardsIgnoreLine Magento2.Classes.DiscouragedDependencies.ConstructorProxyInterceptor
        \Magento\Quote\Model\Quote\Address\RateRequestFactory $rateRequestFactory,
        \Magento\Shipping\Model\Shipping $shipping,
        \Magento\Quote\Model\Quote\Address\RateFactory $quoteAddressRateFactory,
        \Magento\Quote\Model\Quote\Address\ToOrder $orderConverter,
        \Magento\Quote\Model\Quote\Address\ToOrderAddress $orderAddressConverter,
        \Magento\Quote\Model\Quote\Item\ToOrderItem $orderItemConverter,
        \Magento\Quote\Model\Quote\Payment\ToOrderPayment $orderPaymentConverter,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \Magento\CatalogInventory\Model\Spi\StockRegistryProviderInterface $stockRegistryProvider,
        \Magento\CatalogInventory\Api\StockConfigurationInterface $stockConfiguration,
        \Magento\CatalogInventory\Model\Configuration $catalogInventoryConfig,
        \Magento\Sales\Api\InvoiceManagementInterface $orderService,
        \Magento\CatalogInventory\Api\StockManagementInterface $stockManagement,
        \Magento\CatalogInventory\Observer\ItemsForReindex $itemsForReindex,
        \Magento\Customer\Model\Visitor $visitor,
        \Codisto\Connect\Helper\Data $codistoHelper
    ) {
        parent::__construct($context);

        $this->context = $context;
        $this->eventManager = $context->getEventManager();
        $this->resourceConnection = $resourceConnection;
        $this->deploymentConfigFactory = $deploymentConfigFactory;
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
        $this->stockRegistryProvider = $stockRegistryProvider;
        $this->stockConfiguration = $stockConfiguration;
        $this->catalogInventoryConfig = $catalogInventoryConfig;
        $this->orderService = $orderService;
        $this->stockManagement = $stockManagement;
        $this->itemsForReindex = $itemsForReindex;
        $this->codistoHelper = $codistoHelper;
        $this->visitor = $visitor;
    }

    private function _storeId($storeId)
    {
        if ($storeId == 0) {
            foreach ($this->storeManager->getStores(false) as $store) {
                $storeId = $storeId == 0 ? $store->getId() : min($storeId, $store->getId());
            }
        }

        return $storeId;
    }

    private function _errorResponse($response, $statusCode, $statusText)
    {
        $response->setStatusHeader($statusCode, '1.0', $statusText);
        $rawResult = $this->context->getResultFactory()->create(
            \Magento\Framework\Controller\ResultFactory::TYPE_RAW
        );
        $rawResult->setHttpResponseCode($statusCode);
        $rawResult->setHeader('Cache-Control', 'no-cache', true);
        $rawResult->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
        $rawResult->setHeader('Pragma', 'no-cache', true);
        $rawResult->setContents($statusText);
        return $rawResult;
    }

    private function _orderFields($connection)
    {
        try {
            $connection->addColumn('sales_order', 'codisto_orderid', [
                'type' => Table::TYPE_TEXT,
                'length' => '10',
                'comment' => 'Codisto Order Id'
            ]);
        } catch (\Exception $e) {
            $e;
            // ignore if column is already present
        }

        try {
            $connection->addColumn('sales_order', 'codisto_merchantid', [
                'type' => Table::TYPE_TEXT,
                'length' => '10',
                'comment' => 'Codisto Merchant Id'
            ]);
        } catch (\Exception $e) {
            $e;
            // ignore if column is already present
        }

        try {
            $deploymentConfig = $this->deploymentConfigFactory->create();

            $tablePrefix = (string)$deploymentConfig->get(
                \Magento\Framework\Config\ConfigOptionsListConstants::CONFIG_PATH_DB_PREFIX
            );

            $deploymentConfig = null;

            // @codingStandardsIgnoreStart
            $connection->query('CREATE TABLE IF NOT EXISTS `'.$tablePrefix.'codisto_order_detail` '.
                '(order_id int(10) unsigned NOT NULL PRIMARY KEY,'.
                ' ebaysalesrecordnumber varchar(255) NOT NULL,'.
                ' ebaytransactionid varchar(255) NOT NULL,'.
                ' ebayuser varchar(255) NOT NULL,'.
                ' amazonorderid varchar(255) NOT NULL,'.
                ' amazonfulfillmentchannel varchar(255) NOT NULL)');
            // @codingStandardsIgnoreEnd
        } catch (\Exception $e) {
            $e;
            // ignore failures to add codisto_order_detail table
        }
    }

    private function _checkRequest($server)
    {
        $method = isset($server['REQUEST_METHOD']) ? $server['REQUEST_METHOD'] : 'GET';
        $contenttype = isset($server['CONTENT_TYPE']) ? $server['CONTENT_TYPE'] : '';

        if ($method != 'POST') {
            throw new NotFoundException(__('Resource Not Found'));
        }

        if ($contenttype != 'text/xml') {
            return $this->_errorResponse(400, 'Invalid Content Type');
        }

        return null;
    }

    private function _authRequest($storeId, $server)
    {
        $store = $this->storeManager->getStore($storeId);

        if (!$this->codistoHelper->getConfig($storeId)) {
            return $this->_errorResponse(500, 'Config Error');
        }

        if (!$this->codistoHelper->checkRequestHash($store->getConfig('codisto/hostkey'), $server)) {
            return $this->_errorResponse(400, 'Security Error');
        }

        return null;
    }

    public function execute()
    {
        // disable process time limits
        // ignore http client disconnects after receiving the POST payload
        // return all errors to the calling client
        // these settings ensure order submission is not interrupted and if
        // errors occur during transmission we can report the underlying issue
        // via the order screen

        $this->visitor->setSkipRequestLogging(true);

        set_time_limit(0); // @codingStandardsIgnoreLine MEQP1.Security.DiscouragedFunction.Found
        ignore_user_abort(false);
        @ini_set('display_errors', 1); // @codingStandardsIgnoreLine Generic.PHP.NoSilencedErrors.Discouraged
        @ini_set('display_startup_errors', 1); // @codingStandardsIgnoreLine Generic.PHP.NoSilencedErrors.Discouraged
        @error_reporting(E_ALL); // @codingStandardsIgnoreLine Generic.PHP.NoSilencedErrors.Discouraged

        $request = $this->getRequest();
        $response = $this->getResponse();
        $server = $request->getServer();

        $result = $this->_checkRequest($server);
        if ($result) {
            return $result;
        }

        $xml = simplexml_load_string(file_get_contents('php://input')); // @codingStandardsIgnoreLine MEQP1.Security.DiscouragedFunction.Found

        $ordercontent = $xml->entry->content->children('http://api.codisto.com/schemas/2009/');

        // treat failed length as if storeid is not present
        $authStoreId = @count($ordercontent->storeid) ? // @codingStandardsIgnoreLine Generic.PHP.NoSilencedErrors.Discouraged
            (int)$ordercontent->storeid : 0;

        $result = $this->_authRequest($authStoreId, $server);
        if ($result) {
            return $result;
        }

        $productsToReindex = [];
        $ordersProcessed = [];
        $invoicesProcessed = [];

        $connection = $this->resourceConnection->getConnection();

        $this->_orderFields($connection);

        $storeId = $this->_storeId($authStoreId);

        $this->storeManager->setCurrentStore($storeId);

        $store = $this->storeManager->getStore($storeId);

        $quote = null;
        $result = null;

        for ($Retry = 0;; $Retry++) {
            $productsToReindex = [];

            try {
                $quote = $this->quote;

                $this->_processQuote($quote, $xml, $store, $request);
            } catch (\Exception $e) {

                $jsonResult = $this->context->getResultFactory()->create(
                    \Magento\Framework\Controller\ResultFactory::TYPE_JSON
                );
                $jsonResult->setHttpResponseCode(200);
                $jsonResult->setHeader('Content-Type', 'application/json');
                $jsonResult->setHeader('Cache-Control', 'no-cache, must-revalidate', true);
                $jsonResult->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
                $jsonResult->setHeader('Pragma', 'no-cache', true);
                $jsonResult->setData(
                    [
                        'ack' => 'failed',
                        'code' => $e->getCode(),
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]
                );
                $result = $jsonResult;
                break;
            }

            // we require serializable isolation here to ensure that incoming orders
            // are guaranteed to be pushed only once
            $txIsoLevel = $this->codistoHelper->getTxIsoLevel($connection);
            $connection->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE'); // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
            $connection->beginTransaction();

            try {
                $order = $this->order->getCollection()
                    ->addFieldToFilter('codisto_orderid', $ordercontent->orderid)
                    ->addFieldToFilter(
                        ['codisto_merchantid', 'codisto_merchantid'],
                        [
                            ['in'=> [$ordercontent->merchantid, '']],
                            ['null'=> true]
                        ]
                    )
                    // the loop is a retry loop - it needs to re-retrieve the first item
                    ->getFirstItem(); // @codingStandardsIgnoreLine MEQP1.Performance.Loop.DataLoad

                if ($order && $order->getId()) {
                    $result = $this->_processOrderSync(
                        $quote,
                        $order,
                        $xml,
                        $productsToReindex,
                        $ordersProcessed,
                        $invoicesProcessed,
                        $store,
                        $request
                    );
                } else {
                    $result = $this->_processOrderCreate(
                        $quote,
                        $xml,
                        $productsToReindex,
                        $ordersProcessed,
                        $invoicesProcessed,
                        $store,
                        $request
                    );
                }

                $connection->commit();
                $connection->exec('SET TRANSACTION ISOLATION LEVEL '.$txIsoLevel);
                break;
            } catch (\Exception $e) {
                if ($Retry < 5 && $e->getCode() == 40001) {
                    $connection->rollback();
                    $connection->exec('SET TRANSACTION ISOLATION LEVEL '.$txIsoLevel);
                    sleep($Retry * 10); // @codingStandardsIgnoreLine MEQP1.Security.DiscouragedFunction.Found
                    continue;
                }

                $connection->rollback();
                $connection->exec('SET TRANSACTION ISOLATION LEVEL '.$txIsoLevel);

                $jsonResult = $this->context->getResultFactory()->create(
                    \Magento\Framework\Controller\ResultFactory::TYPE_JSON
                );
                $jsonResult->setHttpResponseCode(200);
                $jsonResult->setHeader('Content-Type', 'application/json');
                $jsonResult->setHeader('Cache-Control', 'no-cache', true);
                $jsonResult->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
                $jsonResult->setHeader('Pragma', 'no-cache', true);
                $jsonResult->setData(
                    [
                        'ack' => 'failed',
                        'code' => $e->getCode(),
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]
                );
                $result = $jsonResult;
                break;
            }
        }

        $result->renderResult($response);
        $response->sendResponse();

        return $this->codistoHelper->callExit();
    }

    private function _incrementId(
        $ordernumberformat,
        $order,
        $ebaysalesrecordnumber,
        $ebaytransactionid,
        $amazonorderid
    ) {
        if (preg_match(
            '/\{ordernumber\}|\{ebaysalesrecordnumber\}|\{ebaytransactionid\}|\{amazonorderid\}/',
            $ordernumberformat
        )) {
            $incrementId = preg_replace('/\{ordernumber\}/', (string)$order->getIncrementId(), $ordernumberformat);
            $incrementId = preg_replace('/\{ebaysalesrecordnumber\}/', $ebaysalesrecordnumber, $incrementId);
            $incrementId = preg_replace('/\{ebaytransactionid\}/', $ebaytransactionid, $incrementId);
            $incrementId = preg_replace('/\{amazonorderid\}/', $amazonorderid, $incrementId);
        } else {
            $incrementId = $ordernumberformat.''.(string)$order->getIncrementId();
        }

        return $incrementId;
    }

    private function _translateWeight($weight, $unit)
    {
        if (!is_numeric($weight)) {
            $weight = 0;
        } else {
            $weight = (float)$weight;
        }

        switch ($unit) {
            case 'kg':
                $weight = $weight / 1000.0;
                break;
            case 'pounds':
                $weight = $weight / 453.592;
                break;
            case 'ounces':
                $weight = $weight / 28.3495;
                break;
            default: // grams
                break;
        }

        $weight = max($weight, 1.0);

        return $weight;
    }

    private function _processOrderCreateState(
        $order,
        $ordercontent,
        $adjustStock,
        $ebaysalesrecordnumber,
        $amazonorderid
    ) {

        // ignore count failure on simple_xml - treat count failure as no customer instruction
        $customerInstruction = @count($ordercontent->instructions) ? (string)($ordercontent->instructions) : ''; // @codingStandardsIgnoreLine Generic.PHP.NoSilencedErrors.Discouraged

        $customerNote = '';
        if ($customerInstruction) {
            $customerNote = " <br><b>Checkout message from buyer:</b><br> " . $customerInstruction;
        }

        /* cancelled, processing, captured, inprogress, complete */
        if ($ordercontent->orderstate == 'cancelled') {
            $order->setData('state', \Magento\Sales\Model\Order::STATE_CANCELED);
            $order->setData('status', \Magento\Sales\Model\Order::STATE_CANCELED);
            $order->addStatusToHistory(
                \Magento\Sales\Model\Order::STATE_CANCELED,
                $amazonorderid ?
                    "Amazon Order $amazonorderid has been cancelled" . $customerNote
                    : "eBay Order $ebaysalesrecordnumber has been cancelled" . $customerNote
            );
        } elseif ($ordercontent->orderstate == 'inprogress' || $ordercontent->orderstate == 'processing') {
            $order->setData('state', \Magento\Sales\Model\Order::STATE_PROCESSING);
            $order->setData('status', \Magento\Sales\Model\Order::STATE_PROCESSING);
            $order->addStatusToHistory(
                \Magento\Sales\Model\Order::STATE_PROCESSING,
                $amazonorderid ?
                "Amazon Order $amazonorderid is in progress" . $customerNote
                : "eBay Order $ebaysalesrecordnumber is in progress" . $customerNote
            );
        } elseif ($ordercontent->orderstate == 'complete') {
            $order->setData('state', \Magento\Sales\Model\Order::STATE_COMPLETE);
            $order->setData('status', \Magento\Sales\Model\Order::STATE_COMPLETE);
            $order->addStatusToHistory(
                \Magento\Sales\Model\Order::STATE_COMPLETE,
                $amazonorderid ?
                "Amazon Order $amazonorderid is complete" . $customerNote
                : "eBay Order $ebaysalesrecordnumber is complete" . $customerNote
            );
        } else {
            $order->setData('state', \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
            $order->setData('status', \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
            $order->addStatusToHistory(
                \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT,
                $amazonorderid ?
                "Amazon Order $amazonorderid has been captured" . $customerNote
                : "eBay Order $ebaysalesrecordnumber has been captured" . $customerNote
            );
        }

        // ignore count failure on simple_xml - treat count failure as no merchant instruction
        $merchantInstruction = @count($ordercontent->merchantinstructions) ? strval($ordercontent->merchantinstructions) : ''; // @codingStandardsIgnoreLine Generic.PHP.NoSilencedErrors.Discouraged

        if($merchantInstruction) {
            $merchantInstruction = nl2br($merchantInstruction);
            $order->addStatusToHistory(
                $order->getStatus(),
                $merchantInstruction
            );
        }

        if ($ordercontent->orderstate != 'cancelled' &&
            $adjustStock == false) {
            $order->addStatusToHistory(
                $order->getStatus(),
                "NOTE: Stock level not adjusted, please check your inventory."
            );
        }
    }

    private function _processStockAdjustment(
        $store,
        $product,
        $adjustStock,
        $cancelled,
        $orderItem,
        &$productsToReindex
    ) {
        if (!$cancelled) {
            if ($adjustStock) {
                $stockItem = $product->getStockItem();
                if (!$stockItem) {
                    $stockItem = $this->stockRegistryProvider->getStockItem($product->getId(), $store->getWebsiteId());
                }

                $typeId = $product->getTypeId();
                if (!$typeId) {
                    $typeId = 'simple';
                }

                if ($this->catalogInventoryConfig->isQty($typeId)) {
                    if ($this->canSubtractQty($stockItem)) {
                        $productsToReindex[$product->getId()] = 0;

                        $stockItem->setQty($stockItem->getQty() - $orderItem->getQtyOrdered());
                        $stockItem->save();
                    }
                }
            }
        }
    }

    private function _processPayment(
        $order,
        $ordercontent,
        $ordertotal,
        $paypaltransactionid,
        $ebaysalesrecordnumber,
        $ebaytransactionid,
        $ebayusername,
        $amazonorderid,
        $amazonfulfillmentchannel
    ) {
        $payment = $order->getPayment();

        if($amazonorderid != '') {
            $payment->setMethod('amazon');
        } else {
            $payment->setMethod('ebay');
        }
        $payment->resetTransactionAdditionalInfo();
        $payment->setTransactionId(0);

        $transaction = $payment->addTransaction(
            \Magento\Sales\Model\Order\Payment\Transaction::TYPE_PAYMENT,
            null,
            false,
            ''
        );
        if ($paypaltransactionid) {
            $transaction->setTxnId($paypaltransactionid);
            $payment->setLastTransId($paypaltransactionid);
        }

        if ($ebaysalesrecordnumber) {
            $payment->setAdditionalInformation('ebaysalesrecordnumber', $ebaysalesrecordnumber);
        }
        if ($ebaytransactionid) {
            $payment->setAdditionalInformation('ebaytransactionid', $ebaytransactionid);
        }
        if ($ebayusername) {
            $payment->setAdditionalInformation('ebayuser', $ebayusername);
        }
        if ($amazonorderid) {
            $payment->setAdditionalInformation('amazonorderid', $amazonorderid);
        }
        if ($amazonfulfillmentchannel) {
            $payment->setAdditionalInformation('amazonfulfillmentchannel', $amazonfulfillmentchannel);
        }

        if ($ordercontent->paymentstatus == 'complete') {
            $payment->setBaseAmountPaid($ordertotal);
            $payment->setAmountPaid($ordertotal);
            $payment->setBaseAmountAuthorized($ordertotal);
            $payment->setBaseAmountPaidOnline($ordertotal);
            $payment->setAmountAuthorized($ordertotal);
            $payment->setIsTransactionClosed(1);
        } else {
            $payment->setBaseAmountPaid(0.0);
            $payment->setAmountPaid(0.0);
            $payment->setBaseAmountAuthorized($ordertotal);
            $payment->setBaseAmountPaidOnline($ordertotal);
            $payment->setAmountAuthorized($ordertotal);
            $payment->setIsTransactionClosed(0);
        }

        return $payment;
    }

    private function _processShippingDescription($order, $quote, $freightservice)
    {
        $shippingDescription = '';
        if (strtolower($freightservice) != 'freight') {
            $matchFound = false;

            $shippingDescription = (string)$quote->getShippingAddress()->getShippingDescription();
            if ($shippingDescription) {
                $shippingRates = $quote->getShippingAddress()->getAllShippingRates();

                foreach ($shippingRates as $rate) {
                    $shippingMethodTitle = (string)$rate->getMethodTitle();

                    if (strpos($shippingDescription, $shippingMethodTitle) !== false) {
                        $shippingDescription = str_replace($shippingMethodTitle, $freightservice, $shippingDescription);
                        $matchFound = true;
                        break;
                    }
                }
            }

            if (!$matchFound) {
                $shippingDescription = $freightservice;
            }
        }

        if ($shippingDescription) {
            $order->setShippingDescription($shippingDescription);
        }

        return $shippingDescription;
    }

    private function _processInvoice(
        $order,
        $ordercontent,
        $payment,
        $ordersubtotal,
        $ordertaxtotal,
        $ordertotal,
        &$invoiceids
    ) {
        if ($ordercontent->paymentstatus == 'complete') {
            $invoice = $this->orderService->prepareInvoice($order);

            if ($invoice->getTotalQty()) {
                $payment->setBaseAmountPaid(0.0);
                $payment->setAmountPaid(0.0);

                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
                $invoice->register();
            }
            $invoice->save();

            if (!in_array($invoice->getId(), $invoiceids)) {
                $invoiceids[] = $invoice->getId();
            }

            $order->setBaseSubtotalInvoiced($ordersubtotal);
            $order->setBaseTaxInvoiced($ordertaxtotal);
            $order->setBaseTotalInvoiced($ordertotal);
            $order->setSubtotalInvoiced($ordersubtotal);
            $order->setTaxInvoiced($ordertaxtotal);
            $order->setTotalInvoiced($ordertotal);
            $order->save();
        }
    }

    private function _processOrderLineProduct($request, $orderline, &$adjustStock)
    {
        $product = null;
        $productid = null;
        $productcode = '';
        $productname = '';

        $productcode = $orderline->productcode[0];
        if ($productcode == null) {
            $productcode = '';
        } else {
            $productcode = (string)$productcode;
        }

        $productname = $orderline->productname[0];
        if ($productname == null) {
            $productname = '';
        } else {
            $productname = (string)$productname;
        }

        $productid = $orderline->externalreference[0];
        if ($productid != null) {
            $productid = (int)($productid);

            $product = $this->productFactory->create();
            $product = $product->load($productid);

            if ($product->getId()) {
                $productcode = $product->getSku();
                $productname = $product->getName();
            } else {
                $product = null;
            }
        }

        if (!$product) {
            if ($request->getQuery('checkproduct')) {
                throw new \Exception("external reference not found"); // @codingStandardsIgnoreLine
            }
            $product = $this->productFactory->create();
            $adjustStock = false;
        }

        return [
            'product' => $product,
            'id' => $productid,
            'code' => $productcode,
            'name' => $productname
        ];
    }

    private function _processOrderCreate($quote, $xml, &$productsToReindex, &$orderids, &$invoiceids, $store, $request)
    {
        $ordercontent = $xml->entry->content->children('http://api.codisto.com/schemas/2009/');

        $paypaltransactionid = (string)$ordercontent->orderpayments[0]->orderpayment->transactionid;
        $ordernumberformat = (string)$ordercontent->ordernumberformat;

        $ordertotal = (float)($ordercontent->ordertotal[0]);
        $ordersubtotal = (float)($ordercontent->ordersubtotal[0]);
        $ordertaxtotal = (float)($ordercontent->ordertaxtotal[0]);
        $weightunit = (string)$ordercontent->weightunit;

        $ordersubtotal = $this->priceCurrency->round($ordersubtotal);
        $ordersubtotalincltax = $this->priceCurrency->round($ordersubtotal + $ordertaxtotal);
        $ordertotal = $this->priceCurrency->round($ordertotal);

        $ebaysalesrecordnumber = (string)$ordercontent->ebaysalesrecordnumber ?
            (string)$ordercontent->ebaysalesrecordnumber : '';

        $ebaytransactionid = (string)$ordercontent->ebaytransactionid ?
            (string)$ordercontent->ebaytransactionid : '';

        $ebayusername = (string)$ordercontent->ebayusername ?
            (string)$ordercontent->ebayusername : '';

        $amazonorderid = (string)$ordercontent->amazonorderid ?
            (string)$ordercontent->amazonorderid : '';

        $amazonfulfillmentchannel = (string)$ordercontent->amazonfulfillmentchannel ?
            (string)$ordercontent->amazonfulfillmentchannel : '';

        $quote->reserveOrderId();
        $order = $this->orderConverter->convert($quote->getShippingAddress());

        $shippingAddress = $this
            ->orderAddressConverter->convert($quote->getShippingAddress());
        $billingAddress = $this
            ->orderAddressConverter->convert(
                $quote->getBillingAddress()
            );

        $order->setBillingAddress($this->orderAddressConverter->convert($quote->getBillingAddress()));
        $order->setShippingAddress($this->orderAddressConverter->convert($quote->getShippingAddress()));
        $order->setPayment($this->orderPaymentConverter->convert($quote->getPayment()));
        $order->setCustomer($quote->getCustomer());
        $order->setCodistoOrderid((string)$ordercontent->orderid);
        $order->setCodistoMerchantid((string)$ordercontent->merchantid);
        $order->setIncrementId(
            $this->_incrementId($ordernumberformat, $order, $ebaysalesrecordnumber, $ebaytransactionid, $amazonorderid)
        );

        $weight_total = 0;

        $quoteItems = $quote->getItemsCollection()->getItems();
        $quoteIdx = 0;

        foreach ($ordercontent->orderlines->orderline as $orderline) {
            if ($orderline->productcode[0] == 'FREIGHT') {
                continue;
            }

            $adjustStock = @count($ordercontent->adjuststock) ? (($ordercontent->adjuststock == "false") ? false : true) : true; // @codingStandardsIgnoreLine Generic.PHP.NoSilencedErrors.Discouraged

            $productData = $this->_processOrderLineProduct($request, $orderline, $adjustStock);

            $qty = (int)$orderline->quantity[0];
            $subtotalinctax = (float)($orderline->defaultcurrencylinetotalinctax[0]);
            $subtotal = (float)($orderline->defaultcurrencylinetotal[0]);

            $price = (float)($orderline->defaultcurrencyprice[0]);
            $priceinctax = (float)($orderline->defaultcurrencypriceinctax[0]);
            $taxamount = $priceinctax - $price;
            $taxpercent = $price == 0 ? 0 : round($priceinctax / $price - 1.0, 2) * 100;

            if(isset($productData['product']) && is_object($productData['product'])) {
                $weight = (float)$productData['product']->getWeight();
            }

            if(!$weight) {
                $weight = $this->_translateWeight($orderline->weight[0], $weightunit);
            }

            $weight_total += ($weight * $qty);

            $orderItem = $this->orderItemConverter->convert($quoteItems[$quoteIdx], []);

            $quoteIdx++;

            $orderItem->setStoreId($store->getId());
            $orderItem->setData('product', $productData['product']);

            if ($productData['id'] || $productData['id'] == 0) {
                $orderItem->setProductId($productData['id']);
            }

            if (($productData['id'] || $productData['id'] == 0) && $productData['product']) {
                $orderItem->setBaseCost($productData['product']->getCost());
                $orderItem->setOriginalPrice($productData['product']->getFinalPrice());
                $orderItem->setBaseOriginalPrice($productData['product']->getFinalPrice());
            } else {
                $orderItem->setOriginalPrice($priceinctax);
                $orderItem->setBaseOriginalPrice($priceinctax);
            }

            $orderItem->setIsVirtual(false);
            $orderItem->setProductType('simple');
            $orderItem->setSku($productData['code']);
            $orderItem->setName($productData['name']);
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
            $orderItem->setRowWeight($weight * $qty);
            $orderItem->setWeeeTaxApplied(\Zend_Json::encode([]));

            $order->addItem($orderItem);

            $this->_processStockAdjustment(
                $store,
                $productData['product'],
                $adjustStock,
                ($ordercontent->orderstate != 'cancelled') ? false : true,
                $orderItem,
                $productsToReindex
            );
        }

        $this->itemsForReindex->setItems(
            $this->stockManagement->registerProductsSale($productsToReindex, $quote->getStore()->getWebsiteId())
        );

        $quote->setInventoryProcessed(true);

        $order->setQuote($quote);

        $freightservice = 'Freight';
        $freighttotal =  0.0;
        $freighttotalextax =  0.0;
        $freighttax = 0.0;
        $taxpercent =  0.0;
        $taxrate =  1.0;

        foreach ($ordercontent->orderlines->orderline as $orderline) {
            if ($orderline->productcode[0] == 'FREIGHT') {
                $freighttotal += floatval($orderline->defaultcurrencylinetotalinctax[0]);
                $freighttotalextax += floatval($orderline->defaultcurrencylinetotal[0]);
                $freighttax = $freighttotal - $freighttotalextax;
                $freightservice = (string)$orderline->productname[0];
            }
        }

        $this->_processShippingDescription($order, $quote, $freightservice);

        $ordersubtotal -= $freighttotalextax;
        $ordersubtotalincltax -= $freighttotal;

        $order->setBaseShippingAmount($freighttotalextax);
        $order->setShippingAmount($freighttotalextax);

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

        try {
            if (!$request->getQuery('skiporderevent')) {
                $order->place();
                $this->orderRepository->save($order);
            }
        } catch (\Exception $e) {
            $order->addStatusToHistory(
                \Magento\Sales\Model\Order::STATE_PROCESSING,
                "Exception Occurred Placing Order : ".$e->getMessage()
            );
        }

        $this->_processOrderCreateState($order, $ordercontent, $adjustStock, $ebaysalesrecordnumber, $amazonorderid);

        $order->setBaseTotalPaid(0);
        $order->setTotalPaid(0);
        $order->setBaseTotalDue(0);
        $order->setTotalDue(0);
        $order->setDue(0);

        $payment = $this->_processPayment(
            $order,
            $ordercontent,
            $ordertotal,
            $paypaltransactionid,
            $ebaysalesrecordnumber,
            $ebaytransactionid,
            $ebayusername,
            $amazonorderid,
            $amazonfulfillmentchannel
        );

        $quote->setIsActive(false)->save();

        $this->eventManager->dispatch(
            'checkout_type_onepage_save_order',
            ['order'=>$order, 'quote'=>$quote]
        );
        $this->eventManager->dispatch(
            'sales_model_service_quote_submit_before',
            ['order'=>$order, 'quote'=>$quote]
        );

        $payment->save();

        $order->save();

        $this->eventManager->dispatch(
            'sales_model_service_quote_submit_success',
            ['order'=>$order, 'quote'=>$quote]
        );
        $this->eventManager->dispatch(
            'sales_model_service_quote_submit_after',
            ['order'=>$order, 'quote'=>$quote]
        );

        $this->_processInvoice(
            $order,
            $ordercontent,
            $payment,
            $ordersubtotal,
            $ordertaxtotal,
            $ordertotal,
            $invoiceids
        );

        $this->_processOrderDetail(
            $order,
            $ebaysalesrecordnumber,
            $ebaytransactionid,
            $ebayusername,
            $amazonorderid,
            $amazonfulfillmentchannel
        );

        $response = $this->getResponse();

        $jsonResult = $this->context->getResultFactory()->create(
            \Magento\Framework\Controller\ResultFactory::TYPE_JSON
        );
        $jsonResult->setHttpResponseCode(200);
        $jsonResult->setHeader('Content-Type', 'application/json');
        $jsonResult->setHeader('Cache-Control', 'no-cache, must-revalidate', true);
        $jsonResult->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
        $jsonResult->setHeader('Pragma', 'no-cache', true);
        $jsonResult->setData(
            [
                'ack' => 'ok',
                'orderid' => $order->getIncrementId()
            ]
        );

        if (!in_array($order->getId(), $orderids)) {
            $orderids[] = $order->getId();
        }

        return $jsonResult;
    }

    private function _processOrderSyncCustomer($quote, $order)
    {
        $customer = $quote->getCustomer();
        if ($customer) {
            $order->setCustomer($customer);
        }

        return $customer;
    }

    private function _processOrderSyncStockReserved($order)
    {
        $orderlineStockReserved = [];
        foreach ($order->getAllItems() as $item) {
            $productId = $item->getProductId();
            if ($productId || $productId == 0) {
                if (isset($orderlineStockReserved[$productId])) {
                    $orderlineStockReserved[$productId] += $item->getQtyOrdered();
                } else {
                    $orderlineStockReserved[$productId] = $item->getQtyOrdered();
                }
            }
        }

        return $orderlineStockReserved;
    }

    private function _processOrderSyncInvoice(
        $order,
        $ordercontent,
        $ordersubtotal,
        $ordertaxtotal,
        $ordertotal,
        $paypaltransactionid,
        $amazonorderid,
        &$invoiceids
    ) {
        if (!$order->hasInvoices()) {
            if ($ordercontent->paymentstatus == 'complete' && $order->canInvoice()) {
                $order->setBaseTotalPaid($ordertotal);
                $order->setTotalPaid($ordertotal);
                $order->setBaseTotalDue(0.0);
                $order->setTotalDue(0.0);
                $order->setDue(0.0);

                $payment = $order->getPayment();

                if ($paypaltransactionid) {
                    $transaction = $payment->getTransaction(0);
                    if ($transaction) {
                        $transaction->setTxnId($paypaltransactionid);
                        $payment->setLastTransId($paypaltransactionid);
                    }
                }

                if($amazonorderid != '') {
                    $payment->setMethod('amazon');
                } else {
                    $payment->setMethod('ebay');
                }
                $payment->setParentTransactionId(null)
                    ->setIsTransactionClosed(1);

                $payment->save();

                $invoice = $this->orderService->prepareInvoice($order);

                if ($invoice->getTotalQty()) {
                    $payment->setBaseAmountPaid(0.0);
                    $payment->setAmountPaid(0.0);

                    $order->setBaseTotalPaid($ordertotal);
                    $order->setTotalPaid($ordertotal);

                    $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
                    $invoice->register();
                }
                $invoice->save();

                if (!in_array($invoice->getId(), $invoiceids)) {
                    $invoiceids[] = $invoice->getId();
                }

                $order->setBaseSubtotalInvoiced($ordersubtotal);
                $order->setBaseTaxInvoiced($ordertaxtotal);
                $order->setBaseTotalInvoiced($ordertotal);
                $order->setSubtotalInvoiced($ordersubtotal);
                $order->setTaxInvoiced($ordertaxtotal);
                $order->setTotalInvoiced($ordertotal);
                $order->save();
            }
        }
    }

    private function _processOrderSyncLine($order, $productid, $productcode, $quoteitem)
    {
        $visited = [];
        $itemFound = false;
        foreach ($order->getAllItems() as $item) {
            if (!isset($visited[$item->getId()])) {
                if ($productid) {
                    if ($item->getProductId() == $productid) {
                        $itemFound = true;
                        $visited[$item->getId()] = true;
                        break;
                    }
                } else {
                    if ($item->getSku() == $productcode) {
                        $itemFound = true;
                        $visited[$item->getId()] = true;
                        break;
                    }
                }
            }
        }

        if (!$itemFound) {
            $item = $this->orderItemConverter->convert($quoteitem, []);
        }

        return [
            'item' => $item,
            'new' => !$itemFound
        ];
    }

    private function _processOrderSyncLineRemove($order, $ordercontent)
    {
        $visited = [];
        foreach ($order->getAllItems() as $item) {
            $itemFound = false;

            $orderlineIndex = 0;
            foreach ($ordercontent->orderlines->orderline as $orderline) {
                if ($orderline->productcode[0] == 'FREIGHT') {
                    continue;
                }

                if (!isset($visited[$orderlineIndex])) {
                    $productcode = $orderline->productcode[0] ? (string)$orderline->productcode[0] : '';
                    $productname = $orderline->productname[0] ? (string)$orderline->productname[0] : '';
                    $productid = $orderline->externalreference[0] ? (int)$orderline->externalreference[0] : null;

                    if ($productid) {
                        if ($item->getProductId() == $productid) {
                            $itemFound = true;
                            $visited[$orderlineIndex] = true;
                        }
                    } else {
                        if ($item->getSku() == $productcode) {
                            $itemFound = true;
                            $visited[$orderlineIndex] = true;
                        }
                    }
                }

                $orderlineIndex++;
            }

            if (!$itemFound) {
                $item->delete(); // @codingStandardsIgnoreLine MEQP1.Performance.Loop.ModelLSD
            }
        }
    }

    private function _processOrderSyncAdjustStock(
        $store,
        $product,
        $productId,
        $orderlineStockReserved,
        $adjustStock,
        $cancelled,
        &$productsToReindex
    ) {
        if (!$cancelled) {
            if ($adjustStock) {
                $stockItem = $product->getStockItem();
                if (!$stockItem) {
                    $stockItem = $this->stockRegistryProvider->getStockItem($product->getId(), $store->getWebsiteId());
                }

                $typeId = $product->getTypeId();
                if (!$typeId) {
                    $typeId = 'simple';
                }

                if ($this->catalogInventoryConfig->isQty($typeId)) {
                    if ($this->canSubtractQty($stockItem)) {
                        $stockReserved = isset($orderlineStockReserved[$productId])
                            ? $orderlineStockReserved[$productId] : 0;

                        $stockMovement = $qty - $stockReserved;

                        if ($stockMovement > 0) {
                            $productsToReindex[$productId] = 0;

                            $stockItem->setQty($stockItem->getQty() - $stockMovement);
                            $stockItem->save();
                        } elseif ($stockMovement < 0) {
                            $productsToReindex[$productId] = 0;

                            $stockMovement = abs($stockMovement);

                            $stockItem->setQty($stockItem->getQty() + $stockMovement);
                            $stockItem->save();
                        }
                    }
                }
            }
        }
    }

    private function _processOrderSyncRevertStock($store, $cancelled, $orderline, &$productsToReindex)
    {
        $catalog = $this->productFactory->create();
        $prodid = $catalog->getIdBySku((string)$orderline->productcode[0]);

        if (!$prodid || $prodid == 0) {
            return;
        }

        $product = $this->productFactory->create()->load($prodid);
        if (!$product) {
            return;
        }

        $qty = $orderline->quantity[0];

        $stockItem = $product->getStockItem();
        if (!$stockItem) {
            $stockItem = $this->stockRegistryProvider->getStockItem($product->getId(), $store->getWebsiteId());
        }

        $typeId = $product->getTypeId();
        if (!$typeId) {
            $typeId = 'simple';
        }

        if ($this->catalogInventoryConfig->isQty($typeId) &&
            $this->canSubtractQty($stockItem)) {
            $productsToReindex[$product->getId()] = 0;

            if (!$cancelled) {
                $stockItem->setQty($stockItem->getQty() - (int)$qty);
            }

            $stockItem->save();
        }
    }

    private function _processOrderSyncState(
        $store,
        $order,
        $orderstatus,
        $ordercontent,
        $ebaysalesrecordnumber,
        $amazonorderid,
        &$productsToReindex
    ) {
        /* States: cancelled, processing, captured, inprogress, complete */
        if (($ordercontent->orderstate == 'captured' ||
            $ordercontent->paymentstatus != 'complete') &&
            $ordercontent->orderstate != 'cancelled' &&
            ($orderstatus!=\Magento\Sales\Model\Order::STATE_PROCESSING &&
                $orderstatus!=\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT &&
                $orderstatus!=\Magento\Sales\Model\Order::STATE_NEW)) {
            $order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
            $order->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
            $order->addStatusToHistory(
                $order->getStatus(),
                $amazonorderid ?
                    "Amazon Order $amazonorderid is pending payment"
                    : "eBay Order $ebaysalesrecordnumber is pending payment"
            );
        }

        if ($ordercontent->orderstate == 'cancelled' && $orderstatus!=\Magento\Sales\Model\Order::STATE_CANCELED) {
            $order->setState(\Magento\Sales\Model\Order::STATE_CANCELED);
            $order->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
            $order->addStatusToHistory(
                $order->getStatus(),
                $amazonorderid ?
                    "Amazon Order $amazonorderid has been cancelled"
                    : "eBay Order $ebaysalesrecordnumber has been cancelled"
            );
        }

        if (($ordercontent->orderstate == 'inprogress' || $ordercontent->orderstate == 'processing') &&
            $ordercontent->paymentstatus == 'complete' &&
            $orderstatus!=\Magento\Sales\Model\Order::STATE_PROCESSING &&
            $orderstatus!=\Magento\Sales\Model\Order::STATE_COMPLETE) {
            $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
            $order->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
            $order->addStatusToHistory(
                $order->getStatus(),
                $amazonorderid ?
                    "Amazon Order $amazonorderid is in progress"
                    : "eBay Order $ebaysalesrecordnumber is in progress"
            );
        }

        if ($ordercontent->orderstate == 'complete' &&
            $orderstatus!=\Magento\Sales\Model\Order::STATE_COMPLETE) {
            $order->setData('state', \Magento\Sales\Model\Order::STATE_COMPLETE);
            $order->setData('status', \Magento\Sales\Model\Order::STATE_COMPLETE);
            $order->addStatusToHistory(
                $order->getStatus(),
                $amazonorderid ?
                    "Amazon Order $amazonorderid is complete"
                    : "eBay Order $ebaysalesrecordnumber is complete"
            );
        }

        if (($ordercontent->orderstate == 'cancelled'
            && $orderstatus!= \Magento\Sales\Model\Order::STATE_CANCELED) ||
            ($ordercontent->orderstate != 'cancelled'
                && $orderstatus == \Magento\Sales\Model\Order::STATE_CANCELED)) {
            $cancelled = $ordercontent->orderstate == 'cancelled';

            foreach ($ordercontent->orderlines->orderline as $orderline) {
                if ($orderline->productcode[0] == 'FREIGHT') {
                    continue;
                }

                $this->_processOrderSyncRevertStock($store, $cancelled, $orderline, $productsToReindex);
            }
        }
    }

    private function _processOrderSync(
        $quote,
        $order,
        $xml,
        &$productsToReindex,
        &$orderids,
        &$invoiceids,
        $store,
        $request
    ) {
        $orderstatus = $order->getStatus();
        $ordercontent = $xml->entry->content->children('http://api.codisto.com/schemas/2009/');
        $weightunit = (string)$ordercontent->weightunit;

        $order->setCodistoMerchantid((string)$ordercontent->merchantid);

        $paypaltransactionid = $ordercontent->orderpayments[0]->orderpayment->transactionid;

        $customer = $this->_processOrderSyncCustomer($quote, $order);

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

        $ebaysalesrecordnumber = (string)$ordercontent->ebaysalesrecordnumber ?
            (string)$ordercontent->ebaysalesrecordnumber : '';

        $ebaytransactionid = (string)$ordercontent->ebaytransactionid ?
            (string)$ordercontent->ebaytransactionid : '';

        $ebayusername = (string)$ordercontent->ebayusername ?
            (string)$ordercontent->ebayusername : '';

        $amazonorderid = (string)$ordercontent->amazonorderid ?
            (string)$ordercontent->amazonorderid : '';

        $amazonfulfillmentchannel = (string)$ordercontent->amazonfulfillmentchannel ?
            (string)$ordercontent->amazonfulfillmentchannel : '';

        $currencyCode = (string)$ordercontent->transactcurrency[0];
        $ordertotal = (float)($ordercontent->defaultcurrencytotal[0]);
        $ordersubtotal = (float)($ordercontent->defaultcurrencysubtotal[0]);
        $ordertaxtotal = (float)($ordercontent->defaultcurrencytaxtotal[0]);

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

        foreach ($ordercontent->orderlines->orderline as $orderline) {
            if ($orderline->productcode[0] == 'FREIGHT') {
                $freighttotal += (float)($orderline->defaultcurrencylinetotalinctax[0]);
                $freighttotalextax += (float)($orderline->defaultcurrencylinetotal[0]);
                $freighttax = $freighttotal - $freighttotalextax;
                $freightservice = (string)$orderline->productname[0];
            }
        }

        $this->_processShippingDescription($order, $quote, $freightservice);

        $ordersubtotal -= $freighttotalextax;
        $ordersubtotalincltax -= $freighttotal;

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

        $orderlineStockReserved = $this->_processOrderSyncStockReserved($order);

        $weight_total = 0;

        $quoteItems = $quote->getItemsCollection()->getItems();
        $quoteIdx = 0;

        $totalquantity = 0;
        foreach ($ordercontent->orderlines->orderline as $orderline) {
            if ($orderline->productcode[0] == 'FREIGHT') {
                continue;
            }

            $adjustStock = false;

            $productData = $this->_processOrderLineProduct($request, $orderline, $adjustStock);

            $qty = (int)$orderline->quantity[0];
            $subtotalinctax = (float)($orderline->defaultcurrencylinetotalinctax[0]);
            $subtotal = (float)($orderline->defaultcurrencylinetotal[0]);

            $totalquantity += $qty;

            $price = (float)($orderline->defaultcurrencyprice[0]);
            $priceinctax = (float)($orderline->defaultcurrencypriceinctax[0]);
            $taxamount = $priceinctax - $price;
            $taxpercent = $price == 0 ? 0 : round($priceinctax / $price - 1.0, 2) * 100;

            $weight = $this->_translateWeight($orderline->weight[0], $weightunit);

            $weight_total += $weight;

            $itemData = $this->_processOrderSyncLine(
                $order,
                $productData['id'],
                $productData['code'],
                $quoteItems[$quoteIdx]
            );
            $item = $itemData['item'];

            $quoteIdx++;

            $item->setStoreId($store->getId());

            $item->setData('product', $productData['product']);

            if ($productData['id'] || $productData['id'] == 0) {
                $item->setProductId($productData['id']);
            }

            if (($productData['id'] || $productData['id'] == 0) && $productData['product']) {
                $item->setBaseCost($productData['product']->getCost());
                $item->setOriginalPrice($productData['product']->getFinalPrice());
                $item->setBaseOriginalPrice($productData['product']->getFinalPrice());
            } else {
                $item->setOriginalPrice($priceinctax);
                $item->setBaseOriginalPrice($priceinctax);
            }

            $item->setIsVirtual(false);
            $item->setProductType('simple');
            $item->setSku($productData['code']);
            $item->setName($productData['name']);
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
            $item->setWeeeTaxApplied(\Zend_Json::encode([]));

            if ($itemData['new']) {
                $order->addItem($item);
            }

            $this->_processOrderSyncAdjustStock(
                $store,
                $productData['product'],
                $productData['id'],
                $orderlineStockReserved,
                $adjustStock,
                ($ordercontent->orderstate != 'cancelled') ? false : true,
                $productsToReindex
            );
        }

        $this->_processOrderSyncLineRemove($order, $ordercontent);

        $order->setTotalQtyOrdered((int)$totalquantity);
        $order->setWeight($weight_total);

        $this->_processOrderSyncState(
            $store,
            $order,
            $orderstatus,
            $ordercontent,
            $ebaysalesrecordnumber,
            $amazonorderid,
            $productsToReindex
        );
        $order->save();

        $this->_processOrderSyncInvoice(
            $order,
            $ordercontent,
            $ordersubtotal,
            $ordertaxtotal,
            $ordertotal,
            $paypaltransactionid,
            $amazonorderid,
            $invoiceids
        );

        $this->_processOrderDetail(
            $order,
            $ebaysalesrecordnumber,
            $ebaytransactionid,
            $ebayusername,
            $amazonorderid,
            $amazonfulfillmentchannel
        );

        $response = $this->getResponse();

        $jsonResult = $this->context->getResultFactory()->create(
            \Magento\Framework\Controller\ResultFactory::TYPE_JSON
        );
        $jsonResult->setHttpResponseCode(200);
        $jsonResult->setHeader('Content-Type', 'application/json');
        $jsonResult->setHeader('Cache-Control', 'no-cache, must-revalidate', true);
        $jsonResult->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
        $jsonResult->setHeader('Pragma', 'no-cache', true);
        $jsonResult->setData(
            [
                'ack' => 'ok',
                'orderid' => $order->getIncrementId()
            ]
        );

        if (!in_array($order->getId(), $orderids)) {
            $orderids[] = $order->getId();
        }

        return $jsonResult;
    }

    private function _processCustomerData(
        $customer,
        $websiteId,
        $email,
        $addressBilling,
        $addressShipping,
        $order_source,
        $store
    ) {
        $customer->loadByEmail($email);

        if (!$customer->getId()) {
            $customerGroupId = null;

            if ($order_source == 'ebay') {
                $ebayGroup = $this->customerGroupFactory->create();
                $ebayGroup->load('eBay', 'customer_group_code');
                if (!$ebayGroup->getId()) {
                    $defaultGroup = $this->customerGroupFactory->create()->load(1);

                    $ebayGroup->setCode('eBay');
                    $ebayGroup->setTaxClassId($defaultGroup->getTaxClassId());
                    $ebayGroup->save();
                }
                $customerGroupId = $ebayGroup->getId();
            } elseif ($order_source == 'amazon') {
                $amazonGroup = $this->customerGroupFactory->create();
                $amazonGroup->load('Amazon', 'customer_group_code');
                if (!$amazonGroup->getId()) {
                    $defaultGroup = $this->customerGroupFactory->create()->load(1);

                    $amazonGroup->setCode('Amazon');
                    $amazonGroup->setTaxClassId($defaultGroup->getTaxClassId());
                    $amazonGroup->save();
                }
                $customerGroupId = $amazonGroup->getId();
            }

            $customer->setWebsiteId($websiteId);
            $customer->setStoreId($store->getId());
            $customer->setEmail($email);
            $customer->setFirstname((string)$addressBilling['firstname']);
            $customer->setLastname((string)$addressBilling['lastname']);
            $customer->setPassword('');
            if ($customerGroupId) {
                $customer->setGroupId($customerGroupId);
            }
            $customer->save();
            $customer->setConfirmation(null);
            $customer->save();

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
    }

    private function _processCustomer(
        $connection,
        $store,
        $websiteId,
        $email,
        $addressBilling,
        $addressShipping,
        $order_source
    ) {
        $customer = $this->customerFactory->create();
        $customer->setWebsiteId($websiteId);
        $customer->setStoreId($store->getId());

        $txIsoLevel = $this->codistoHelper->getTxIsoLevel($connection);

        for ($Retry = 0;; $Retry++) {
            try {
                // need to control transaction isolation level here manually to
                // avoid duplicating customer records
                $connection->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE'); // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
                $connection->beginTransaction();

                $this->_processCustomerData(
                    $customer,
                    $websiteId,
                    $email,
                    $addressBilling,
                    $addressShipping,
                    $order_source,
                    $store
                );

                $connection->commit();
                $connection->exec('SET TRANSACTION ISOLATION LEVEL '.$txIsoLevel);
                break;
            } catch (\Exception $e) {
                if ($Retry < 5 && $e->getCode() == 40001) {
                    $connection->rollback();
                    $connection->exec('SET TRANSACTION ISOLATION LEVEL '.$txIsoLevel);
                    sleep($Retry * 10); // @codingStandardsIgnoreLine MEQP1.Security.DiscouragedFunction.Found
                    continue;
                }

                $connection->rollback();
                $connection->exec('SET TRANSACTION ISOLATION LEVEL '.$txIsoLevel);
                throw $e;
            }
        }

        return $customer;
    }

    private function _processQuoteAddress($address, $address_lines)
    {
        if (is_numeric($address_lines)) {
            $address_lines = min(max((int)$address_lines, 1), 3);
        } else {
            $address_lines = 3;
        }

        $first_name = $last_name = '';

        if (strpos($address->name, ' ') !== false) {
            $name = explode(' ', (string)$address->name, 2);
            $first_name = $name[0];
            $last_name = $name[1];
        } else {
            $first_name = (string)$address->name;
            $last_name = '-';
        }

        $phone = (string)$address->phone;
        if (!$phone) {
            $phone = 'Not Available';
        }

        $email = (string)$address->email;
        if (!$email || $email == 'Invalid Request') {
            $email = 'mail@example.com';
        }

        $regionCollection = $this->getRegionCollection($address->countrycode);

        $regionsel_id = 0;
        foreach ($regionCollection as $region) {
            if (in_array($address->division, [$region['code'], $region['name']])) {
                $regionsel_id = $region['region_id'];
            }
        }

        $addressLine = $address_lines == 1 ?
                            ((string)$address->address1.($address->address2 ? ' '.(string)$address->address2 : '')) :
                            ((string)$address->address1.($address->address2 ? "\n".(string)$address->address2 : ''));

        return [
            'email' => $email,
            'prefix' => '',
            'suffix' => '',
            'company' => (string)$address->companyname,
            'firstname' => (string)$first_name,
            'middlename' => '',
            'lastname' => (string)$last_name,
            'street' => $addressLine,
            'city' => (string)$address->place,
            'postcode' => (string)$address->postalcode,
            'telephone' => (string)$phone,
            'fax' => '',
            'country_id' => (string)$address->countrycode,
            'region_id' => $regionsel_id, // id from directory_country_region table
            'region' => (string)$address->division
        ];
    }

    private function _processQuoteShipping($request, $store, $quote, $shippingAddress, $currencyCode)
    {
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

        if (!$request->getQuery('skipshipping')) {
            try {
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

                $pickupRegex = '/(?:^|\W|_)pick\s*up(?:\W|_|$)|(?:^|\W|_)click\s+.*\s+collect(?:\W|_|$)/i';

                foreach ($shippingRates as $shippingRate) {
                    if ($shippingRate instanceof \Magento\Quote\Model\Quote\Address\RateResult\Method &&
                        ($freightcost === null
                            || ($shippingRate->getPrice() !== null
                            && $shippingRate->getPrice() < $freightcost))) {
                        $isPickup = $shippingRate->getPrice() == 0 &&
                                    (preg_match($pickupRegex, (string)($shippingRate->getMethod())) ||
                                        preg_match($pickupRegex, (string)($shippingRate->getCarrierTitle())) ||
                                        preg_match($pickupRegex, (string)($shippingRate->getMethodTitle())));

                        if (!$isPickup) {
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
            } catch (\Exception $e) {
                $e;
                // ignore failures loading shipping rates
            }
        }

        if (!$freightRate) {
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
    }

    private function _processQuote($quote, $xml, $store, $request)
    {
        $connection = $this->resourceConnection->getConnection();

        $ordercontent = $xml->entry->content->children('http://api.codisto.com/schemas/2009/');

        $register_customer = (string)$ordercontent->register_customer == 'false' ? false : true;
        $order_source = (string)$ordercontent->source;

        $websiteId = $store->getWebsiteId();

        $address_lines = $store->getConfig('customer/address/street_lines');

        $addressBilling = $this->_processQuoteAddress($ordercontent->orderaddresses->orderaddress[0], $address_lines);
        $addressShipping = $this->_processQuoteAddress($ordercontent->orderaddresses->orderaddress[1], $address_lines);

        $email = $addressBilling['email'];

        if ($addressShipping['email'] == '' ||
            $addressShipping['email'] == 'Invalid Request' ||
            $addressShipping['email'] == 'mail@example.com') {
            $addressShipping['email'] = $email;
        }

        $customer = null;

        if ($register_customer && $email != 'mail@example.com') {
            $customer = $this->_processCustomer(
                $connection,
                $store,
                $websiteId,
                $email,
                $addressBilling,
                $addressShipping,
                $order_source
            );
        }

        $currencyCode = (string)$ordercontent->transactcurrency[0];
        $ordertotal = (float)($ordercontent->ordertotal[0]);
        $ordersubtotal = (float)($ordercontent->ordersubtotal[0]);
        $ordertaxtotal = (float)($ordercontent->ordertaxtotal[0]);

        $ordersubtotal = $this->priceCurrency->round($ordersubtotal);
        $ordersubtotalincltax = $this->priceCurrency->round($ordersubtotal + $ordertaxtotal);
        $ordertotal = $this->priceCurrency->round($ordertotal);

        $amazonorderid = (string)$ordercontent->amazonorderid ?
            (string)$ordercontent->amazonorderid : '';

        $quote->setCurrency();
        $quote->setIsSuperMode(true);
        $quote->setStore($store);
        $quote->setCheckoutMethod(\Magento\Checkout\Model\Type\Onepage::METHOD_GUEST);

        if ($customer) {
            $customerData = $this->customerRepository->getById($customer->getId());

            $quote->assignCustomer($customerData);
            $quote->setCustomerId($customer->getId());
            $quote->setCustomerGroupId($customer->getGroupId());
        } else {
            $quote->setCustomerId(null);
            $quote->setCustomerEmail($email);
            $quote->setCustomerFirstName((string)$addressBilling['firstname']);
            $quote->setCustomerLastName((string)$addressBilling['lastname']);
            $quote->setCustomerIsGuest(true);
            $quote->setCustomerGroupId(\Magento\Customer\Model\Group::NOT_LOGGED_IN_ID);
        }

        $billingAddress = $quote->getBillingAddress();
        $shippingAddress = $quote->getShippingAddress();
        if ($customer) {
            $billingAddress->setCustomer($customer);
            $shippingAddress->setCustomer($customer);
        }
        $billingAddress->addData($addressBilling);
        $shippingAddress->addData($addressShipping);

        $totalitemcount = 0;
        $totalitemqty = 0;

        foreach ($ordercontent->orderlines->orderline as $orderline) {
            if ($orderline->productcode[0] == 'FREIGHT') {
                continue;
            }

            $adjustStock = @count($ordercontent->adjuststock) ? (($ordercontent->adjuststock == "false") ? false : true) : true; // @codingStandardsIgnoreLine Generic.PHP.NoSilencedErrors.Discouraged

            $productData = $this->_processOrderLineProduct($request, $orderline, $adjustStock);

            $productData['product']->setIsSuperMode(true);

            $qty = (int)$orderline->quantity[0];
            $subtotalinctax = (float)($orderline->defaultcurrencylinetotalinctax[0]);
            $subtotal = (float)($orderline->defaultcurrencylinetotal[0]);

            $totalitemcount++;
            $totalitemqty += $qty;

            $price = (float)($orderline->defaultcurrencyprice[0]);
            $priceinctax = (float)($orderline->defaultcurrencypriceinctax[0]);
            $taxamount = $priceinctax - $price;
            $taxpercent = $price == 0 ? 0 : round($priceinctax / $price - 1.0, 2) * 100;
            $weight = (float)($orderline->weight[0]);

            $item = $this->quoteItemFactory->create();
            $item->setStoreId($store->getId());
            $item->setQuote($quote);

            $item->setData('product', $productData['product']);
            $item->setProductId($productData['id']);
            $item->setProductType('simple');
            $item->setIsRecurring(false);

            if ($productData['product']) {
                $item->setTaxClassId($productData['product']->getTaxClassId());
                $item->setBaseCost($productData['product']->getCost());
            }

            $item->setSku($productData['code']);
            $item->setName($productData['name']);
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
            $item->setWeeeTaxApplied(\Zend_Json::encode([]));

            $quote->getItemsCollection()->addItem($item);
        }

        $freighttotal = 0.0;
        $freighttotalextax = 0.0;
        $freighttax = 0.0;
        $freightservice = '';

        foreach ($ordercontent->orderlines->orderline as $orderline) {
            if ($orderline->productcode[0] == 'FREIGHT') {
                $freighttotal += (float)($orderline->defaultcurrencylinetotalinctax[0]);
                $freighttotalextax += (float)($orderline->defaultcurrencylinetotal[0]);
                $freighttax = (float)$freighttotal - $freighttotalextax;
                $freightservice = $orderline->productname[0];
            }
        }

        $ordersubtotal -= $freighttotalextax;
        $ordersubtotalincltax -= $freighttotal;

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
        if($amazonorderid != '') {
            $quotePayment->setMethod('amazon');
        } else {
            $quotePayment->setMethod('ebay');
        }
        $quotePayment->save();

        // ignore count failure on simple_xml - treat count failure as no customer instruction
        $customerInstruction = @count($ordercontent->instructions) ? (string)($ordercontent->instructions) : ''; // @codingStandardsIgnoreLine Generic.PHP.NoSilencedErrors.Discouraged

        if($customer){
            $checkoutSession = $this->session;
            $checkoutSession->setCustomerData($customer);
            $checkoutSession->replaceQuote($quote);
            $checkoutSession->setData('customer_comment', $customerInstruction);
            $checkoutSession->setData('destination_type', 'residence');
        }

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
        $shippingAddress->setAppliedTaxes([]);
        $shippingAddress->setShippingDiscountAmount(0);
        $shippingAddress->setBaseShippingDiscountAmount(0);
        $shippingAddress->setSubtotalInclTax($ordersubtotalincltax);
        $shippingAddress->setBaseSubtotalTotalInclTax($ordersubtotalincltax);

        $this->_processQuoteShipping($request, $store, $quote, $shippingAddress, $currencyCode, $freighttotal);

        $shippingAddress->save();
    }

    private function _processOrderDetail(
        $order,
        $ebaysalesrecordnumber,
        $ebaytransactionid,
        $ebayusername,
        $amazonorderid,
        $amazonfulfillmentchannel
    ) {
        try {
            $deploymentConfig = $this->deploymentConfigFactory->create();

            $tablePrefix = (string)$deploymentConfig->get(
                \Magento\Framework\Config\ConfigOptionsListConstants::CONFIG_PATH_DB_PREFIX
            );

            $deploymentConfig = null;

            $adapter = $this->resourceConnection->getConnection();

            $orderDetail = [
                'order_id' => $order->getId(),
                'ebaysalesrecordnumber' => $ebaysalesrecordnumber,
                'ebaytransactionid' => $ebaytransactionid,
                'ebayuser' => $ebayusername,
                'amazonorderid' => $amazonorderid,
                'amazonfulfillmentchannel' => $amazonfulfillmentchannel
            ];

            // @codingStandardsIgnoreStart
            $adapter->query(
                'REPLACE INTO `'.$tablePrefix.'codisto_order_detail` '.
                    '('.implode(',',array_keys($orderDetail)).') '.
                    'VALUES ('.implode(',',array_fill(1, count($orderDetail), '?')).')',
                array_values($orderDetail));
            // @codingStandardsIgnoreEnd
        } catch (\Exception $e) {
            $e;
            // ignore failure to update codisto_order_detail
        }
    }

    private function getRegionCollection($countryCode)
    {
        return $this->country->loadByCode($countryCode)->getRegions();
    }

    private function canSubtractQty($stockItem)
    {
        return $stockItem->getManageStock() && $this->stockConfiguration->canSubtractQty();
    }
}
