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

namespace Codisto\Connect\Controller\Sync;

class Index extends \Magento\Framework\App\Action\Action
{
	private $defaultConfigurableCount = 6;
	private $defaultSimpleCount = 250;

	private $context;
	private $dirList;
	private $storeManager;
	private $json;
	private $fileFactory;
	private $codistoHelper;
	private $sync;

	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Filesystem\DirectoryList $dirList,
		\Magento\Store\Model\StoreManager $storeManager,
		\Magento\Framework\Json\Helper\Data $json,
		\Magento\Framework\App\Response\Http\FileFactory $fileFactory,
		\Codisto\Connect\Helper\Data $codistoHelper,
		\Codisto\Connect\Model\Sync $sync
	) {
		parent::__construct($context);

		$this->context = $context;
		$this->dirList = $dirList;
		$this->storeManager = $storeManager;
		$this->json = $json;
		$this->fileFactory = $fileFactory;
		$this->codistoHelper = $codistoHelper;
		$this->sync = $sync;
	}

	public function execute()
	{
		set_time_limit(0);

		@ini_set('zlib.output_compression', 'Off');
		@ini_set('output_buffering', 'Off');
		@ini_set('output_handler', '');
		@ini_set('display_errors', 1);
		@ini_set('display_startup_errors', 1);
		@error_reporting(E_ALL);

		ignore_user_abort(true);

		$request = $this->getRequest();
		$request->setDispatched(true);
		$server = $request->getServer();

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

		if(!$this->codistoHelper->getConfig($storeId))
		{
			return $this->sendConfigError();
		}

		$this->storeManager->setCurrentStore($storeId);

		$store = $this->storeManager->getStore($storeId);

		if (isset($server['HTTP_X_SYNC']))
		{
			if (!isset($server['HTTP_X_ACTION']))
			{
				$server['HTTP_X_ACTION'] = '';
			}

			switch ($request->getServer('HTTP_X_ACTION')) {

			case 'GET':

				if($this->checkHash($store, $server))
				{
					if($request->getQuery('first'))
						$syncDb = $this->codistoHelper->getSyncPath('sync-first-'.$storeId.'.db');
					else
						$syncDb = $this->codistoHelper->getSyncPath('sync-'.$storeId.'.db');

					if($request->getQuery('productid') || $request->getQuery('categoryid') || $request->getQuery('orderid'))
					{
						if($request->getQuery('orderid'))
						{
							$orderIds = $this->json->jsonDecode($request->getQuery('orderid'));
							if(!is_array($orderIds))
								$orderIds = array($orderIds);

							$orderIds = array_map('intval', $orderIds);

							$this->sync->SyncOrders($syncDb, $orderIds, $storeId);
						}

						$tmpDb = $this->codistoHelper->getSyncPathTemp('sync');

						$db = new \PDO('sqlite:' . $tmpDb);
						$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

						$db->exec('PRAGMA synchronous=0');
						$db->exec('PRAGMA temp_store=2');
						$db->exec('PRAGMA page_size=65536');
						$db->exec('PRAGMA encoding=\'UTF-8\'');
						$db->exec('PRAGMA cache_size=15000');
						$db->exec('PRAGMA soft_heap_limit=67108864');
						$db->exec('PRAGMA journal_mode=MEMORY');

						$db->exec('ATTACH DATABASE \''.$syncDb.'\' AS SyncDB');

						$db->exec('BEGIN EXCLUSIVE TRANSACTION');

						if($request->getQuery('categoryid'))
						{
							$db->exec('CREATE TABLE Category AS SELECT * FROM SyncDb.Category');
						}

						if($request->getQuery('productid'))
						{
							$productIds = $this->json->jsonDecode($request->getQuery('productid'));
							if(!is_array($productIds))
								$productIds = array($productIds);

							$productIds = array_map('intval', $productIds);

							$db->exec('CREATE TABLE Product AS SELECT * FROM SyncDb.Product WHERE ExternalReference IN ('.implode(',', $productIds).')');
							$db->exec('CREATE TABLE ProductImage AS SELECT * FROM SyncDb.ProductImage WHERE ProductExternalReference IN (SELECT ExternalReference FROM Product)');
							$db->exec('CREATE TABLE CategoryProduct AS SELECT * FROM SyncDb.CategoryProduct WHERE ProductExternalReference IN (SELECT ExternalReference FROM Product)');
							$db->exec('CREATE TABLE SKU AS SELECT * FROM SyncDb.SKU WHERE ProductExternalReference IN (SELECT ExternalReference FROM Product)');
							$db->exec('CREATE TABLE SKULink AS SELECT * FROM SyncDb.SKULink WHERE ProductExternalReference IN (SELECT ExternalReference FROM Product)');
							$db->exec('CREATE TABLE SKUMatrix AS SELECT * FROM SyncDb.SKUMatrix WHERE ProductExternalReference IN (SELECT ExternalReference FROM Product)');
							$db->exec('CREATE TABLE SKUImage AS SELECT * FROM SyncDb.SKUImage WHERE SKUExternalReference IN (SELECT ExternalReference FROM SKU)');
							$db->exec('CREATE TABLE ProductOptionValue AS SELECT DISTINCT * FROM SyncDb.ProductOptionValue');
							$db->exec('CREATE TABLE ProductHTML AS SELECT * FROM SyncDb.ProductHTML WHERE ProductExternalReference IN (SELECT ExternalReference FROM Product)');
							$db->exec('CREATE TABLE Attribute AS SELECT * FROM SyncDb.Attribute');
							$db->exec('CREATE TABLE AttributeGroup AS SELECT * FROM SyncDb.AttributeGroup');
							$db->exec('CREATE TABLE AttributeGroupMap AS SELECT * FROM SyncDb.AttributeGroupMap');
							$db->exec('CREATE TABLE ProductAttributeValue AS SELECT * FROM SyncDb.ProductAttributeValue WHERE ProductExternalReference IN (SELECT ExternalReference FROM Product)');
							$db->exec('CREATE TABLE ProductQuestion AS SELECT * FROM SyncDb.ProductQuestion WHERE ProductExternalReference IN (SELECT ExternalReference FROM Product)');
							$db->exec('CREATE TABLE ProductQuestionAnswer AS SELECT * FROM SyncDb.ProductQuestionAnswer WHERE ProductQuestionExternalReference IN (SELECT ExternalReference FROM ProductQuestion)');

							if($db->query('SELECT CASE WHEN EXISTS(SELECT 1 FROM SyncDb.sqlite_master WHERE lower(name) = \'productdelete\' AND type = \'table\') THEN 1 ELSE 0 END')->fetchColumn())
								$db->exec('CREATE TABLE ProductDelete AS SELECT * FROM SyncDb.ProductDelete WHERE ExternalReference IN ('.implode(',', $productIds).')');
						}

						if($request->getQuery('orderid'))
						{
							$orderIds = $this->json->jsonDecode($request->getQuery('orderid'));
							if(!is_array($orderIds))
								$orderIds = array($orderIds);

							$orderIds = array_map('intval', $orderIds);

							$db->exec('CREATE TABLE [Order] AS SELECT * FROM SyncDb.[Order] WHERE ID IN ('.implode(',', $orderIds).')');
						}

						$db->exec('COMMIT TRANSACTION');
						$db->exec('DETACH DATABASE SyncDB');
						$db->exec('VACUUM');

						return $this->sendFile($tmpDb, [ 'remove' => true ]);
					}
					else
					{
						return $this->sendFile($syncDb);
					}
				}
				else
				{
					return $this->sendSecurityError();
				}

			case 'PRODUCTCOUNT':

				if($this->checkHash($store, $server))
				{
					return $this->sendJsonResponse(200, 'OK', $this->sync->ProductTotals($storeId));
				}
				else
				{
					return $this->sendSecurityError();
				}

			case 'EXECUTEFIRST':

				if($this->checkHash($store, $server))
				{
					try
					{
						$result = 'error';

						$syncDb = $this->codistoHelper->getSyncPath('sync-first-'.$storeId.'.db');

						if(file_exists($syncDb))
							unlink($syncDb);

						$configurableCount = (int)$request->getQuery('configurablecount');
						if(!$configurableCount || !is_numeric($configurableCount))
							$configurableCount = $this->defaultConfigurableCount;

						$simpleCount = (int)$request->getQuery('simplecount');
						if(!$simpleCount || !is_numeric($simpleCount))
							$simpleCount = $this->defaultSimpleCount;


						if($configurableCount > 0)
						{
							$result = $this->sync->SyncChunk($syncDb, 0, $configurableCount, $storeId, true);
						}

						if($simpleCount > 0)
						{
							$result = $this->sync->SyncChunk($syncDb, $simpleCount, 0, $storeId, true);
						}

						if($result == 'complete')
						{
							$this->sync->SyncTax($syncDb, $storeId);
							$this->sync->SyncStores($syncDb, $storeId);
						}
						else
						{
							throw new \Exception('First page execution failed');
						}

						return $this->sendPlainResponse(200, 'OK', $result);
					}
					catch(\Exception $e)
					{
						if(property_exists($e, 'errorInfo') &&
							$e->errorInfo[0] == 'HY000' &&
							$e->errorInfo[1] == 5 &&
							$e->errorInfo[2] == 'database is locked')
						{
							return $this->sendPlainResponse($response, 200, 'OK', 'throttle');
						}
						else if(property_exists($e, 'errorInfo') &&
							$e->errorInfo[0] == 'HY000' &&
							$e->errorInfo[1] == 8 &&
							$e->errorInfo[2] == 'attempt to write a readonly database')
						{
							if(file_exists($syncDb))
								unlink($syncDb);
						}
						else if(property_exists($e, 'errorInfo') &&
							$e->errorInfo[0] == 'HY000' &&
							$e->errorInfo[1] == 11 &&
							$e->errorInfo[2] == 'database disk image is malformed')
						{
							if(file_exists($syncDb))
								unlink($syncDb);
						}

						return $this->sendExceptionError($e);
					}
				}
				else
				{
					return $this->sendSecurityError();
				}

			case 'EXECUTECHUNK':

				if($this->checkHash($store, $server))
				{
					try
					{
						$result = 'error';

						$syncDb = $this->codistoHelper->getSyncPath('sync-'.$storeId.'.db');

						if($request->getPost('Init') == '1')
						{
							if(file_exists($syncDb))
								unlink($syncDb);
						}

						$configurableCount = (int)$request->getQuery('configurablecount');
						if(!$configurableCount || !is_numeric($configurableCount))
							$configurableCount = $this->defaultConfigurableCount;

						$simpleCount = (int)$request->getQuery('simplecount');
						if(!$simpleCount || !is_numeric($simpleCount))
							$simpleCount = $this->defaultSimpleCount;

						$timeout = (int)$request->getQuery('timeout');
						if(!$timeout || !is_numeric($timeout))
							$timeout = $this->defaultSyncTimeout;

						if($timeout < 5)
							$timeout = 5;

						$startTime = microtime(true);

						for($chunkCount = 0; $chunkCount < 2; $chunkCount++)
						{
							$result = $this->sync->SyncChunk($syncDb, $simpleCount, $configurableCount, $storeId, false);

							if($result == 'complete')
							{
								$this->sync->SyncTax($syncDb, $storeId);
								$this->sync->SyncStaticBlocks($syncDb, $storeId);
								$this->sync->SyncStores($syncDb, $storeId);
								break;
							}

							$duration = microtime(true) - $startTime;

							if(($duration / ($chunkCount + 1)) * 2 > $timeout)
								break;

							usleep(10000);
						}

						return $this->sendPlainResponse(200, 'OK', $result);
					}
					catch(\Exception $e)
					{
						if(property_exists($e, 'errorInfo') &&
							$e->errorInfo[0] == 'HY000' &&
							$e->errorInfo[1] == 5 &&
							$e->errorInfo[2] == 'database is locked')
						{
							return $this->sendPlainResponse(200, 'OK', 'throttle');
						}
						else if(property_exists($e, 'errorInfo') &&
							$e->errorInfo[0] == 'HY000' &&
							$e->errorInfo[1] == 8 &&
							$e->errorInfo[2] == 'attempt to write a readonly database')
						{
							if(file_exists($syncDb))
								unlink($syncDb);
						}
						else if(property_exists($e, 'errorInfo') &&
							$e->errorInfo[0] == 'HY000' &&
							$e->errorInfo[1] == 11 &&
							$e->errorInfo[2] == 'database disk image is malformed')
						{
							if(file_exists($syncDb))
								unlink($syncDb);
						}

						return $this->sendExceptionError($e);
					}
				}
				else
				{
					return $this->sendSecurityError();
				}

			case 'PULL':

				if($this->checkHash($store, $server))
				{
					try
					{
						$syncDb = $this->codistoHelper->getSyncPath('sync-'.$storeId.'.db');

						$productId = (int)$request->getPost('ProductID');
						$productIds = array($productId);

						$this->sync->UpdateProducts($syncDb, $productIds, $storeId);

						$tmpDb = $this->codistoHelper->getSyncPathTemp('sync');

						$db = new \PDO('sqlite:' . $tmpDb);
						$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

						$db->exec('PRAGMA synchronous=0');
						$db->exec('PRAGMA temp_store=2');
						$db->exec('PRAGMA page_size=65536');
						$db->exec('PRAGMA encoding=\'UTF-8\'');
						$db->exec('PRAGMA cache_size=15000');
						$db->exec('PRAGMA soft_heap_limit=67108864');
						$db->exec('PRAGMA journal_mode=MEMORY');

						$db->exec('ATTACH DATABASE \''.$syncDb.'\' AS SyncDB');

						$db->exec('BEGIN EXCLUSIVE TRANSACTION');
						$db->exec('CREATE TABLE Product AS SELECT * FROM SyncDb.Product WHERE ExternalReference IN ('.implode(',', $productIds).') OR ExternalReference IN (SELECT ProductExternalReference FROM SKU WHERE ExternalReference IN ('.implode(',', $productIds).'))');
						$db->exec('CREATE TABLE ProductImage AS SELECT * FROM SyncDb.ProductImage WHERE ProductExternalReference IN (SELECT ExternalReference FROM Product)');
						$db->exec('CREATE TABLE CategoryProduct AS SELECT * FROM SyncDb.CategoryProduct WHERE ProductExternalReference IN (SELECT ExternalReference FROM Product)');
						$db->exec('CREATE TABLE SKU AS SELECT * FROM SyncDb.SKU WHERE ProductExternalReference IN (SELECT ExternalReference FROM Product)');
						$db->exec('CREATE TABLE SKULink AS SELECT * FROM SyncDb.SKULink WHERE ProductExternalReference IN (SELECT ExternalReference FROM Product)');
						$db->exec('CREATE TABLE SKUMatrix AS SELECT * FROM SyncDb.SKUMatrix WHERE ProductExternalReference IN (SELECT ExternalReference FROM Product)');
						$db->exec('CREATE TABLE SKUImage AS SELECT * FROM SyncDb.SKUImage WHERE SKUExternalReference IN (SELECT ExternalReference FROM SKU)');
						$db->exec('CREATE TABLE ProductOptionValue AS SELECT * FROM SyncDb.ProductOptionValue WHERE ProductExternalReference IN (SELECT ExternalReference FROM Product)');
						$db->exec('CREATE TABLE ProductHTML AS SELECT * FROM SyncDb.ProductHTML WHERE ProductExternalReference IN (SELECT ExternalReference FROM Product)');
						$db->exec('CREATE TABLE Attribute AS SELECT * FROM SyncDb.Attribute');
						$db->exec('CREATE TABLE AttributeGroup AS SELECT * FROM SyncDb.AttributeGroup');
						$db->exec('CREATE TABLE AttributeGroupMap AS SELECT * FROM SyncDb.AttributeGroupMap');
						$db->exec('CREATE TABLE ProductAttributeValue AS SELECT * FROM SyncDb.ProductAttributeValue WHERE ProductExternalReference IN (SELECT ExternalReference FROM Product)');
						$db->exec('CREATE TABLE ProductQuestion AS SELECT * FROM SyncDb.ProductQuestion WHERE ProductExternalReference IN (SELECT ExternalReference FROM Product)');
						$db->exec('CREATE TABLE ProductQuestionAnswer AS SELECT * FROM SyncDb.ProductQuestionAnswer WHERE ProductQuestionExternalReference IN (SELECT ExternalReference FROM ProductQuestion)');
						$db->exec('COMMIT TRANSACTION');
						$db->exec('DETACH DATABASE SyncDB');
						$db->exec('VACUUM');

						return $this->sendFile($tmpDb, [ 'remove' => true ]);
					}
					catch(\Exception $e)
					{
						return $this->sendExceptionError($e);
					}
				}
				else
				{
					return $this->sendSecurityError();
				}

			case 'TAX':

				if($this->checkHash($store, $server))
				{
					try
					{
						$syncDb = $this->codistoHelper->getSyncPath('sync-'.$storeId.'.db');

						$this->sync->SyncTax($syncDb, $storeId);

						$tmpDb = $this->codistoHelper->getSyncPathTemp('sync');

						$db = new \PDO('sqlite:' . $tmpDb);
						$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

						$db->exec('PRAGMA synchronous=0');
						$db->exec('PRAGMA temp_store=2');
						$db->exec('PRAGMA page_size=65536');
						$db->exec('PRAGMA encoding=\'UTF-8\'');
						$db->exec('PRAGMA cache_size=15000');
						$db->exec('PRAGMA soft_heap_limit=67108864');
						$db->exec('PRAGMA journal_mode=MEMORY');

						$db->exec('ATTACH DATABASE \''.$syncDb.'\' AS SyncDB');

						$db->exec('BEGIN EXCLUSIVE TRANSACTION');
						$db->exec('CREATE TABLE TaxClass AS SELECT * FROM SyncDb.TaxClass');
						$db->exec('CREATE TABLE TaxCalculation AS SELECT * FROM SyncDb.TaxCalculation');
						$db->exec('CREATE TABLE TaxCalculationRule AS SELECT * FROM SyncDb.TaxCalculationRule');
						$db->exec('CREATE TABLE TaxCalculationRate AS SELECT * FROM SyncDb.TaxCalculationRate');
						$db->exec('COMMIT TRANSACTION');
						$db->exec('VACUUM');

						return $this->sendFile($tmpDb, [ 'remove' => true ]);
					}
					catch(\Exception $e)
					{
						return $this->sendExceptionError($e);
					}
				}
				else
				{
					return $this->sendSecurityError();
				}

			case 'STOREVIEW':

				if($this->checkHash($store, $server))
				{
					try
					{
						$syncDb = $this->codistoHelper->getSyncPath('sync-'.$storeId.'.db');

						$this->sync->SyncStores($syncDb, $storeId);

						$tmpDb = $this->codistoHelper->getSyncPathTemp('sync');

						$db = new \PDO('sqlite:' . $tmpDb);
						$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

						$db->exec('PRAGMA synchronous=0');
						$db->exec('PRAGMA temp_store=2');
						$db->exec('PRAGMA page_size=65536');
						$db->exec('PRAGMA encoding=\'UTF-8\'');
						$db->exec('PRAGMA cache_size=15000');
						$db->exec('PRAGMA soft_heap_limit=67108864');
						$db->exec('PRAGMA journal_mode=MEMORY');

						$db->exec('ATTACH DATABASE \''.$syncDb.'\' AS SyncDB');

						$db->exec('BEGIN EXCLUSIVE TRANSACTION');
						$db->exec('CREATE TABLE Store AS SELECT * FROM SyncDb.Store');
						$db->exec('COMMIT TRANSACTION');
						$db->exec('VACUUM');

						return $this->sendFile($tmpDb, [ 'remove' => true ]);
					}
					catch(\Exception $e)
					{
						return $this->sendExceptionError($e);
					}
				}
				else
				{
					return $this->sendSecurityError();
				}

			case 'ORDERS':

				if($this->checkHash($store, $server))
				{
					try
					{
						$syncDb = $this->codistoHelper->getSyncPath('sync-'.$storeId.'.db');

						if($request->getQuery('orderid'))
						{
							$orders = $this->json->jsonDecode($request->getQuery('orderid'));
							if(!is_array($orders))
								$orders = array($orders);

							$this->sync->SyncOrders($syncDb, $orders, $storeId);
						}

						$tmpDb = $this->codistoHelper->getSyncPathTemp('sync');

						$db = new \PDO('sqlite:' . $tmpDb);
						$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

						$db->exec('PRAGMA synchronous=0');
						$db->exec('PRAGMA temp_store=2');
						$db->exec('PRAGMA page_size=65536');
						$db->exec('PRAGMA encoding=\'UTF-8\'');
						$db->exec('PRAGMA cache_size=15000');
						$db->exec('PRAGMA soft_heap_limit=67108864');
						$db->exec('PRAGMA journal_mode=MEMORY');

						$db->exec('ATTACH DATABASE \''.$syncDb.'\' AS SyncDB');

						$db->exec('BEGIN EXCLUSIVE TRANSACTION');
						$db->exec('CREATE TABLE [Order] AS SELECT * FROM SyncDb.[Order]');
						$db->exec('COMMIT TRANSACTION');
						$db->exec('VACUUM');

						return $this->sendFile($tmpDb, [ 'remove' => true ]);
					}
					catch(\Exception $e)
					{
						return $this->sendExceptionError($e);
					}
				}
				else
				{
					return $this->sendSecurityError();
				}

			case 'TEMPLATE':

				if($this->checkHash($store, $server))
				{
					try
					{
						if($request->isGet())
						{
							$merchantid = (int)$request->getQuery('merchantid');

							$templateDb = $this->codistoHelper->getSyncPath('template-'.$merchantid.'.db');

							if($request->getQuery('markreceived'))
							{
								try
								{
									$db = new \PDO('sqlite:' . $templateDb);
									$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

									$update = $db->prepare('UPDATE File SET LastModified = ? WHERE Name = ?');

									$files = $db->query('SELECT Name FROM File WHERE Changed != 0');
									$files->execute();

									$db->exec('BEGIN EXCLUSIVE TRANSACTION');

									while($row = $files->fetch())
									{
										$stat = stat($this->dirList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::APP).'/design/ebay/'.$row['Name']);

										$lastModified = strftime('%Y-%m-%d %H:%M:%S', $stat['mtime']);

										$update->bindParam(1, $lastModified);
										$update->bindParam(2, $row['Name']);
										$update->execute();
									}

									$db->exec('UPDATE File SET Changed = 0');
									$db->exec('COMMIT TRANSACTION');
									$db = null;

									return $this->sendJsonResponse(200, 'OK', array( 'ack' => 'ok' ));
								}
								catch(\Exception $e)
								{
									return $this->sendExceptionError($e);
								}
							}
							else
							{
								$this->sync->TemplateRead($templateDb);

								$tmpDb = $this->codistoHelper->getSyncPathTemp('template');

								$db = new \PDO('sqlite:' . $tmpDb);
								$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
								$db->exec('PRAGMA synchronous=0');
								$db->exec('PRAGMA temp_store=2');
								$db->exec('PRAGMA page_size=512');
								$db->exec('PRAGMA encoding=\'UTF-8\'');
								$db->exec('PRAGMA cache_size=15000');
								$db->exec('PRAGMA soft_heap_limit=67108864');
								$db->exec('PRAGMA journal_mode=OFF');
								$db->exec('ATTACH DATABASE \''.$templateDb.'\' AS Source');
								$db->exec('CREATE TABLE File AS SELECT * FROM Source.File WHERE Changed != 0');
								$db->exec('DETACH DATABASE Source');
								$db->exec('VACUUM');

								$fileCountStmt = $db->query('SELECT COUNT(*) AS fileCount FROM File');
								$fileCountStmt->execute();
								$fileCountRow = $fileCountStmt->fetch();
								$fileCount = $fileCountRow['fileCount'];
								$db = null;

								if($fileCount == 0)
								{
									return $this->sendPlainResponse(204, 'No Content', '');
								}
								else
								{
									return $this->sendFile($tmpDb, [ 'remove' => true ]);
								}
							}
						}
						else if($request->isPost() || $request->isPut())
						{
							$tmpDb = $this->codistoHelper->getSyncPathTemp('template');

							file_put_contents($tmpDb, file_get_contents('php://input'));

							$this->sync->TemplateWrite($tmpDb);

							unlink($tmpDb);

							return $this->sendJsonResponse(200, 'OK', array( 'ack' => 'ok' ));
						}
					}
					catch(\Exception $e)
					{
						return $this->sendExceptionError($e);
					}
				}
				else
				{
					return $this->sendSecurityError();
				}

			default:

				return $this->sendPlainResponse(200, 'OK', 'No Action');

			}
		}
	}

	private function checkHash($store, $server)
	{
		return $this->codistoHelper->checkRequestHash($store->getConfig('codisto/hostkey'), $server);
	}

	private function sendSecurityError()
	{
		$this->sendPlainResponse(400, 'Security Error', 'Security Error');
	}

	private function sendConfigError()
	{
		$this->sendPlainResponse(500, 'Config Error', 'Config Error');
	}

	private function sendExceptionError($exception)
	{
		$this->sendPlainResponse(500, 'Exception', 'Exception: '.$exception->getMessage().' on line: '.$exception->getLine().' in file: '.$exception->getFile().' '.$exception->getTraceAsString());
	}

	private function sendPlainResponse($status, $statustext, $body, $extraHeaders = null)
	{
		$response = $this->getResponse();
		$response->clearHeaders();
		$response->setStatusHeader($status, '1.0', $statustext);

		$rawResult = $this->context->getResultFactory()->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
		$rawResult->setHttpResponseCode($status);
		$rawResult->setHeader('Cache-Control', 'no-cache', true);
		$rawResult->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
		$rawResult->setHeader('Pragma', 'no-cache', true);
		$rawResult->setHeader('Content-Type', 'text/plain');

		if(is_array($extraHeaders))
		{
			foreach($extraHeaders as $key => $value)
			{
				$rawResult->setHeader($key, $value);
			}
		}

		$rawResult->setContents($body);
		return $rawResult;
	}

	private function sendJsonResponse($status, $statustext, $body, $extraHeaders = null)
	{
		$response = $this->getResponse();
		$response->clearHeaders();
		$response->setStatusHeader($status, '1.0', $statustext);

		$jsonResult = $this->context->getResultFactory()->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
		$jsonResult->setHttpResponseCode($status);
		$jsonResult->setHeader('Cache-Control', 'no-cache', true);
		$jsonResult->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
		$jsonResult->setHeader('Pragma', 'no-cache', true);
		$jsonResult->setHeader('Content-Type', 'application/json');
		$jsonResult->setData($body);
		return $jsonResult;
	}

	private function sendFile($syncDb, $sendOptions = [])
	{
		$response = $this->getResponse();
		$response->clearHeaders();
		$response->setStatusHeader(200, '1.0', 'OK');
		$response->setHeader('Content-Type', 'application/octet-stream');
		$response->setHeader('Pragma', 'no-cache');
		$response->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
		$response->setHeader('Cache-Control', 'no-cache', true);

		if(isset($sendOptions['syncresponse']))
			$response->setHeader('X-Codisto-SyncResponse', $sendOptions['syncresponse']);

		$filename = basename($syncDb);

		$fileOptions = [ 'type' => 'filename', 'value' => '/codisto/'.$filename ];
		if(isset($sendOptions['remove']) && $sendOptions['remove'])
			$fileOption['rm'] = true;

		return $this->fileFactory->create($filename, $fileOptions, \Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR);
	}
}
