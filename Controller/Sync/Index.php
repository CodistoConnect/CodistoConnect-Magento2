<?php

/**
 * Codisto Marketplace Connect Sync Extension
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

namespace Codisto\Connect\Controller\Sync;

class Index extends \Magento\Framework\App\Action\Action
{
    private $defaultConfigurableCount = 6;
    private $defaultSimpleCount = 250;

    private $context;
    private $dirList;
    private $storeManager;
    private $json;
    private $file;
    private $fileFactory;
    private $codistoHelper;
    private $sync;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Filesystem\DirectoryList $dirList,
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\Framework\Json\Helper\Data $json,
        \Magento\Framework\Filesystem\Io\File $file,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Codisto\Connect\Helper\Data $codistoHelper,
        \Codisto\Connect\Model\Sync $sync
    ) {
        parent::__construct($context);

        $this->context = $context;
        $this->dirList = $dirList;
        $this->storeManager = $storeManager;
        $this->json = $json;
        $this->file = $file;
        $this->fileFactory = $fileFactory;
        $this->codistoHelper = $codistoHelper;
        $this->sync = $sync;
    }

    public function execute()
    {
        // @codingStandardsIgnoreStart
        set_time_limit(0);

        @ini_set('zlib.output_compression', 'Off');
        @ini_set('output_buffering', 'Off');
        @ini_set('output_handler', '');
        @ini_set('display_errors', 1);
        @ini_set('display_startup_errors', 1);
        @error_reporting(E_ALL);

        ignore_user_abort(true);
        // @codingStandardsIgnoreEnd

        $request = $this->getRequest();
        $request->setDispatched(true);
        $server = $request->getServer();

        $storeId = $request->getQuery('storeid') == null ? 0 : (int)$request->getQuery('storeid');

        if ($storeId == 0) {
            $stores = $this->storeManager->getStores();
            foreach ($stores as $store) {
                $storeId = $store->getId();
                if ($storeId != 0) {
                    break;
                }
            }
        }

        if (!$this->codistoHelper->getConfig($storeId)) {
            return $this->_sendConfigError();
        }

        $this->storeManager->setCurrentStore($storeId);

        $store = $this->storeManager->getStore($storeId);

        if (!isset($server['HTTP_X_SYNC'])) {
            return $this->_sendPlainResponse(400, 'Bad Request', 'No Action');
        }

        if (!isset($server['HTTP_X_ACTION'])) {
            $server['HTTP_X_ACTION'] = '';
        }

        switch ($server['HTTP_X_ACTION']) {
            case 'GET':
                if ($this->_checkHash($store, $server)) {
                    if ($request->getQuery('first')) {
                        $syncDb = $this->codistoHelper->getSyncPath('sync-first-'.$storeId.'.db');
                    } else {
                        $syncDb = $this->codistoHelper->getSyncPath('sync-'.$storeId.'.db');
                    }

                    if ($request->getQuery('productid')
                        || $request->getQuery('categoryid')
                        || $request->getQuery('orderid')) {
                        if ($request->getQuery('orderid')) {
                            $orderIds = $this->json->jsonDecode($request->getQuery('orderid'));
                            if (!is_array($orderIds)) {
                                $orderIds = [$orderIds];
                            }

                            $orderIds = array_map('intval', $orderIds);

                            $this->sync->syncOrders($syncDb, $orderIds, $storeId);
                        }

                        $tmpDb = $this->codistoHelper->getSyncPathTemp('sync');

                        $db = $this->codistoHelper->createSqliteConnection($tmpDb);
                        $db->exec('PRAGMA synchronous=0');
                        $db->exec('PRAGMA temp_store=2');
                        $db->exec('PRAGMA page_size=65536');
                        $db->exec('PRAGMA encoding=\'UTF-8\'');
                        $db->exec('PRAGMA cache_size=15000');
                        $db->exec('PRAGMA soft_heap_limit=67108864');
                        $db->exec('PRAGMA journal_mode=MEMORY');

                        $db->exec('ATTACH DATABASE \''.$syncDb.'\' AS SyncDB');

                        $db->exec('BEGIN EXCLUSIVE TRANSACTION');

                        if ($request->getQuery('categoryid')) {
                            $db->exec('CREATE TABLE Category AS SELECT * FROM SyncDb.Category');
                        }

                        if ($request->getQuery('productid')) {
                            $productIds = $this->json->jsonDecode($request->getQuery('productid'));
                            if (!is_array($productIds)) {
                                $productIds = [$productIds];
                            }

                            $productIds = array_map('intval', $productIds);

                            $db->exec(
                                'CREATE TABLE Product AS '.
                                'SELECT * FROM SyncDb.Product WHERE ExternalReference IN '.
                                    '('.implode(',', $productIds).')'
                            );
                            $db->exec(
                                'CREATE TABLE ProductImage AS '.
                                'SELECT * FROM SyncDb.ProductImage WHERE ProductExternalReference IN '.
                                    '(SELECT ExternalReference FROM Product)'
                            );
                            $db->exec(
                                'CREATE TABLE CategoryProduct AS '.
                                'SELECT * FROM SyncDb.CategoryProduct WHERE ProductExternalReference IN '.
                                    '(SELECT ExternalReference FROM Product)'
                            );
                            $db->exec(
                                'CREATE TABLE SKU AS '.
                                'SELECT * FROM SyncDb.SKU WHERE ProductExternalReference IN '.
                                    '(SELECT ExternalReference FROM Product)'
                            );
                            $db->exec(
                                'CREATE TABLE SKULink AS '.
                                'SELECT * FROM SyncDb.SKULink WHERE ProductExternalReference IN '.
                                    '(SELECT ExternalReference FROM Product)'
                            );
                            $db->exec(
                                'CREATE TABLE SKUMatrix AS '.
                                'SELECT * FROM SyncDb.SKUMatrix WHERE ProductExternalReference IN '.
                                    '(SELECT ExternalReference FROM Product)'
                            );
                            $db->exec(
                                'CREATE TABLE SKUImage AS '.
                                'SELECT * FROM SyncDb.SKUImage WHERE SKUExternalReference IN '.
                                    '(SELECT ExternalReference FROM SKU)'
                            );
                            $db->exec(
                                'CREATE TABLE ProductOptionValue AS '.
                                'SELECT DISTINCT * FROM SyncDb.ProductOptionValue'
                            );
                            $db->exec(
                                'CREATE TABLE ProductHTML AS '.
                                'SELECT * FROM SyncDb.ProductHTML WHERE ProductExternalReference IN '.
                                    '(SELECT ExternalReference FROM Product)'
                            );
                            $db->exec(
                                'CREATE TABLE Attribute AS '.
                                'SELECT * FROM SyncDb.Attribute'
                            );
                            $db->exec(
                                'CREATE TABLE AttributeGroup AS '.
                                'SELECT * FROM SyncDb.AttributeGroup'
                            );
                            $db->exec(
                                'CREATE TABLE AttributeGroupMap AS '.
                                'SELECT * FROM SyncDb.AttributeGroupMap'
                            );
                            $db->exec(
                                'CREATE TABLE ProductAttributeValue AS '.
                                'SELECT * FROM SyncDb.ProductAttributeValue WHERE ProductExternalReference IN '.
                                    '(SELECT ExternalReference FROM Product)'
                            );
                            $db->exec(
                                'CREATE TABLE ProductQuestion AS '.
                                'SELECT * FROM SyncDb.ProductQuestion WHERE ProductExternalReference IN '.
                                    '(SELECT ExternalReference FROM Product)'
                            );
                            $db->exec(
                                'CREATE TABLE ProductQuestionAnswer AS '.
                                'SELECT * FROM SyncDb.ProductQuestionAnswer WHERE ProductQuestionExternalReference IN '.
                                    '(SELECT ExternalReference FROM ProductQuestion)'
                            );

                            if ($db->query(
                                'SELECT CASE WHEN EXISTS('.
                                    'SELECT 1 '.
                                    'FROM SyncDb.sqlite_master '.
                                    'WHERE lower(name) = \'productdelete\' AND type = \'table\''.
                                ') THEN 1 ELSE 0 END'
                            )->fetchColumn()) {
                                $db->exec(
                                    'CREATE TABLE ProductDelete AS '.
                                    'SELECT * FROM SyncDb.ProductDelete WHERE ExternalReference IN '.
                                        '('.implode(',', $productIds).')'
                                );
                            }
                        }

                        if ($request->getQuery('orderid')) {
                            $orderIds = $this->json->jsonDecode($request->getQuery('orderid'));
                            if (!is_array($orderIds)) {
                                $orderIds = [$orderIds];
                            }

                            $orderIds = array_map('intval', $orderIds);

                            $db->exec(
                                'CREATE TABLE [Order] AS '.
                                'SELECT * FROM SyncDb.[Order] WHERE ID IN '.
                                    '('.implode(',', $orderIds).')'
                            );
                        }

                        $db->exec('COMMIT TRANSACTION');
                        $db->exec('DETACH DATABASE SyncDB');
                        $db->exec('VACUUM');

                        return $this->_sendFile($tmpDb, [ 'remove' => true ]);
                    } else {
                        return $this->_sendFile($syncDb);
                    }
                } else {
                    return $this->_sendSecurityError();
                }
                break;
            case 'PRODUCTCOUNT':
                if ($this->_checkHash($store, $server)) {
                    return $this->_sendJsonResponse(200, 'OK', $this->sync->productTotals($storeId));
                } else {
                    return $this->_sendSecurityError();
                }
                break;
            case 'EXECUTEFIRST':
                if ($this->_checkHash($store, $server)) {
                    try {
                        $result = 'error';

                        $syncDb = $this->codistoHelper->getSyncPath('sync-first-'.$storeId.'.db');

                        if ($this->file->fileExists($syncDb)) {
                            $this->file->rm($syncDb);
                        }

                        $configurableCount = (int)$request->getQuery('configurablecount');
                        if (!$configurableCount || !is_numeric($configurableCount)) {
                            $configurableCount = $this->defaultConfigurableCount;
                        }

                        $simpleCount = (int)$request->getQuery('simplecount');
                        if (!$simpleCount || !is_numeric($simpleCount)) {
                            $simpleCount = $this->defaultSimpleCount;
                        }

                        if ($configurableCount > 0) {
                            $result = $this->sync->syncChunk($syncDb, 0, $configurableCount, $storeId, true);
                        }

                        if ($simpleCount > 0) {
                            $result = $this->sync->syncChunk($syncDb, $simpleCount, 0, $storeId, true);
                        }

                        if ($result == 'complete') {
                            $this->sync->syncTax($syncDb, $storeId);
                            $this->sync->syncStores($syncDb, $storeId);
                        } else {
                            throw new \Exception('First page execution failed');
                        }

                        return $this->_sendPlainResponse(200, 'OK', $result);
                    } catch (\Exception $e) {
                        if (property_exists($e, 'errorInfo') &&
                            $e->errorInfo[0] == 'HY000' &&
                            $e->errorInfo[1] == 5 &&
                            $e->errorInfo[2] == 'database is locked') {
                            return $this->_sendPlainResponse($response, 200, 'OK', 'throttle');
                        } elseif (property_exists($e, 'errorInfo') &&
                            $e->errorInfo[0] == 'HY000' &&
                            $e->errorInfo[1] == 8 &&
                            $e->errorInfo[2] == 'attempt to write a readonly database') {
                            if ($this->file->fileExists($syncDb)) {
                                $this->file->rm($syncDb);
                            }
                        } elseif (property_exists($e, 'errorInfo') &&
                            $e->errorInfo[0] == 'HY000' &&
                            $e->errorInfo[1] == 11 &&
                            $e->errorInfo[2] == 'database disk image is malformed') {
                            if ($this->file->fileExists($syncDb)) {
                                $this->file->rm($syncDb);
                            }
                        }

                        return $this->_sendExceptionError($e);
                    }
                } else {
                    return $this->_sendSecurityError();
                }
                break;

            case 'EXECUTEINCREMENT':
            case 'EXECUTECHUNK':
                if ($this->_checkHash($store, $server)) {
                    try {
                        $result = 'error';

                        $syncDb = $this->codistoHelper->getSyncPath('sync-'.$storeId.'.db');

                        if ($request->getPost('Init') == '1') {
                            if ($this->file->fileExists($syncDb)) {
                                $this->file->rm($syncDb);
                            }
                        }

                        $configurableCount = (int)$request->getQuery('configurablecount');
                        if (!$configurableCount || !is_numeric($configurableCount)) {
                            $configurableCount = $this->defaultConfigurableCount;
                        }

                        $simpleCount = (int)$request->getQuery('simplecount');
                        if (!$simpleCount || !is_numeric($simpleCount)) {
                            $simpleCount = $this->defaultSimpleCount;
                        }

                        $timeout = (int)$request->getQuery('timeout');
                        if (!$timeout || !is_numeric($timeout)) {
                            $timeout = $this->defaultSyncTimeout;
                        }

                        if ($timeout < 5) {
                            $timeout = 5;
                        }

                        $startTime = microtime(true);

                        for ($chunkCount = 0; $chunkCount < 2; $chunkCount++) {
                            $result = $this->sync->syncChunk(
                                $syncDb,
                                $simpleCount,
                                $configurableCount,
                                $storeId,
                                false
                            );

                            if ($result == 'complete') {
                                $this->sync->syncTax($syncDb, $storeId);
                                $this->sync->syncStaticBlocks($syncDb, $storeId);
                                $this->sync->syncStores($syncDb, $storeId);
                                break;
                            }

                            $duration = microtime(true) - $startTime;

                            if (($duration / ($chunkCount + 1)) * 2 > $timeout) {
                                break;
                            }

                            usleep(10000);
                        }

                        return $this->_sendPlainResponse(200, 'OK', $result);
                    } catch (\Exception $e) {
                        if (property_exists($e, 'errorInfo') &&
                            $e->errorInfo[0] == 'HY000' &&
                            $e->errorInfo[1] == 5 &&
                            $e->errorInfo[2] == 'database is locked') {
                            return $this->_sendPlainResponse(200, 'OK', 'throttle');
                        } elseif (property_exists($e, 'errorInfo') &&
                            $e->errorInfo[0] == 'HY000' &&
                            $e->errorInfo[1] == 8 &&
                            $e->errorInfo[2] == 'attempt to write a readonly database') {
                            if ($this->file->fileExists($syncDb)) {
                                $this->file->rm($syncDb);
                            }
                        } elseif (property_exists($e, 'errorInfo') &&
                            $e->errorInfo[0] == 'HY000' &&
                            $e->errorInfo[1] == 11 &&
                            $e->errorInfo[2] == 'database disk image is malformed') {
                            if ($this->file->fileExists($syncDb)) {
                                $this->file->rm($syncDb);
                            }
                        }

                        return $this->_sendExceptionError($e);
                    }
                } else {
                    return $this->_sendSecurityError();
                }
                break;
            case 'PULL':
                if ($this->_checkHash($store, $server)) {
                    try {
                        $syncDb = $this->codistoHelper->getSyncPath('sync-'.$storeId.'.db');

                        $productId = (int)$request->getPost('ProductID');
                        $productIds = [$productId];

                        $this->sync->updateProducts($syncDb, $productIds, $storeId);

                        $tmpDb = $this->codistoHelper->getSyncPathTemp('sync');

                        $db = $this->codistoHelper->createSqliteConnection($tmpDb);
                        $db->exec('PRAGMA synchronous=0');
                        $db->exec('PRAGMA temp_store=2');
                        $db->exec('PRAGMA page_size=65536');
                        $db->exec('PRAGMA encoding=\'UTF-8\'');
                        $db->exec('PRAGMA cache_size=15000');
                        $db->exec('PRAGMA soft_heap_limit=67108864');
                        $db->exec('PRAGMA journal_mode=MEMORY');

                        $db->exec('ATTACH DATABASE \''.$syncDb.'\' AS SyncDB');

                        $db->exec('BEGIN EXCLUSIVE TRANSACTION');
                        $db->exec(
                            'CREATE TABLE Product AS '.
                            'SELECT * FROM SyncDb.Product WHERE ExternalReference IN '.
                                '('.implode(',', $productIds).') OR ExternalReference IN '.
                                '(SELECT ProductExternalReference FROM SKU WHERE ExternalReference IN '.
                                    '('.implode(',', $productIds).'))'
                        );
                        $db->exec(
                            'CREATE TABLE ProductImage AS '.
                            'SELECT * FROM SyncDb.ProductImage WHERE ProductExternalReference IN '.
                                '(SELECT ExternalReference FROM Product)'
                        );
                        $db->exec(
                            'CREATE TABLE CategoryProduct AS '.
                            'SELECT * FROM SyncDb.CategoryProduct WHERE ProductExternalReference IN '.
                                '(SELECT ExternalReference FROM Product)'
                        );
                        $db->exec(
                            'CREATE TABLE SKU AS '.
                            'SELECT * FROM SyncDb.SKU WHERE ProductExternalReference IN '.
                                '(SELECT ExternalReference FROM Product)'
                        );
                        $db->exec(
                            'CREATE TABLE SKULink AS '.
                            'SELECT * FROM SyncDb.SKULink WHERE ProductExternalReference IN '.
                                '(SELECT ExternalReference FROM Product)'
                        );
                        $db->exec(
                            'CREATE TABLE SKUMatrix AS '.
                            'SELECT * FROM SyncDb.SKUMatrix WHERE ProductExternalReference IN '.
                                '(SELECT ExternalReference FROM Product)'
                        );
                        $db->exec(
                            'CREATE TABLE SKUImage AS '.
                            'SELECT * FROM SyncDb.SKUImage WHERE SKUExternalReference IN '.
                                '(SELECT ExternalReference FROM SKU)'
                        );
                        $db->exec(
                            'CREATE TABLE ProductOptionValue AS '.
                            'SELECT * FROM SyncDb.ProductOptionValue WHERE ProductExternalReference IN '.
                                '(SELECT ExternalReference FROM Product)'
                        );
                        $db->exec(
                            'CREATE TABLE ProductHTML AS '.
                            'SELECT * FROM SyncDb.ProductHTML WHERE ProductExternalReference IN '.
                                '(SELECT ExternalReference FROM Product)'
                        );
                        $db->exec(
                            'CREATE TABLE Attribute AS '.
                            'SELECT * FROM SyncDb.Attribute'
                        );
                        $db->exec(
                            'CREATE TABLE AttributeGroup AS '.
                            'SELECT * FROM SyncDb.AttributeGroup'
                        );
                        $db->exec(
                            'CREATE TABLE AttributeGroupMap AS '.
                            'SELECT * FROM SyncDb.AttributeGroupMap'
                        );
                        $db->exec(
                            'CREATE TABLE ProductAttributeValue AS '.
                            'SELECT * FROM SyncDb.ProductAttributeValue WHERE ProductExternalReference IN '.
                                '(SELECT ExternalReference FROM Product)'
                        );
                        $db->exec(
                            'CREATE TABLE ProductQuestion AS '.
                            'SELECT * FROM SyncDb.ProductQuestion WHERE ProductExternalReference IN '.
                                '(SELECT ExternalReference FROM Product)'
                        );
                        $db->exec(
                            'CREATE TABLE ProductQuestionAnswer AS '.
                            'SELECT * FROM SyncDb.ProductQuestionAnswer WHERE ProductQuestionExternalReference IN '.
                                '(SELECT ExternalReference FROM ProductQuestion)'
                        );
                        $db->exec('COMMIT TRANSACTION');
                        $db->exec('DETACH DATABASE SyncDB');
                        $db->exec('VACUUM');

                        return $this->_sendFile($tmpDb, [ 'remove' => true ]);
                    } catch (\Exception $e) {
                        return $this->_sendExceptionError($e);
                    }
                } else {
                    return $this->_sendSecurityError();
                }
                break;
            case 'TAX':
                if ($this->_checkHash($store, $server)) {
                    try {
                        $syncDb = $this->codistoHelper->getSyncPath('sync-'.$storeId.'.db');

                        $this->sync->syncTax($syncDb, $storeId);

                        $tmpDb = $this->codistoHelper->getSyncPathTemp('sync');

                        $db = $this->codistoHelper->createSqliteConnection($tmpDb);
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

                        return $this->_sendFile($tmpDb, [ 'remove' => true ]);
                    } catch (\Exception $e) {
                        return $this->_sendExceptionError($e);
                    }
                } else {
                    return $this->_sendSecurityError();
                }
                break;
            case 'STOREVIEW':
                if ($this->_checkHash($store, $server)) {
                    try {
                        $syncDb = $this->codistoHelper->getSyncPath('sync-'.$storeId.'.db');

                        $this->sync->syncStores($syncDb, $storeId);

                        $tmpDb = $this->codistoHelper->getSyncPathTemp('sync');

                        $db = $this->codistoHelper->createSqliteConnection($tmpDb);
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

                        return $this->_sendFile($tmpDb, [ 'remove' => true ]);
                    } catch (\Exception $e) {
                        return $this->_sendExceptionError($e);
                    }
                } else {
                    return $this->_sendSecurityError();
                }
                break;
            case 'ORDERS':
                if ($this->_checkHash($store, $server)) {
                    try {
                        $syncDb = $this->codistoHelper->getSyncPath('sync-'.$storeId.'.db');

                        if ($request->getQuery('orderid')) {
                            $orders = $this->json->jsonDecode($request->getQuery('orderid'));
                            if (!is_array($orders)) {
                                $orders = [$orders];
                            }

                            $this->sync->syncOrders($syncDb, $orders, $storeId);
                        }

                        $tmpDb = $this->codistoHelper->getSyncPathTemp('sync');

                        $db = $this->codistoHelper->createSqliteConnection($tmpDb);
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

                        return $this->_sendFile($tmpDb, [ 'remove' => true ]);
                    } catch (\Exception $e) {
                        return $this->_sendExceptionError($e);
                    }
                } else {
                    return $this->_sendSecurityError();
                }
                break;
            case 'TEMPLATE':
                if ($this->_checkHash($store, $server)) {
                    try {
                        if ($request->isGet()) {
                            $merchantid = (int)$request->getQuery('merchantid');

                            $templateDb = $this->codistoHelper->getSyncPath('template-'.$merchantid.'.db');

                            if ($request->getQuery('markreceived')) {
                                try {
                                    $db = $this->codistoHelper->createSqliteConnection($templateDb);

                                    $update = $db->prepare('UPDATE File SET LastModified = ? WHERE Name = ?');

                                    $files = $db->query('SELECT Name FROM File WHERE Changed != 0');
                                    $files->execute();

                                    $db->exec('BEGIN EXCLUSIVE TRANSACTION');

                                    while ($row = $files->fetch()) {
                                        $stat = stat(
                                            $this->dirList->getPath(
                                                \Magento\Framework\App\Filesystem\DirectoryList::APP
                                            ).'/design/ebay/'.$row['Name']
                                        );

                                        $lastModified = strftime('%Y-%m-%d %H:%M:%S', $stat['mtime']);

                                        $update->bindParam(1, $lastModified);
                                        $update->bindParam(2, $row['Name']);
                                        $update->execute();
                                    }

                                    $db->exec('UPDATE File SET Changed = 0');
                                    $db->exec('COMMIT TRANSACTION');
                                    $db = null;

                                    return $this->_sendJsonResponse(200, 'OK', [ 'ack' => 'ok' ]);
                                } catch (\Exception $e) {
                                    return $this->_sendExceptionError($e);
                                }
                            } else {
                                $this->sync->templateRead($templateDb);

                                $tmpDb = $this->codistoHelper->getSyncPathTemp('template');

                                $db = $this->codistoHelper->createSqliteConnection($tmpDb);
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

                                if ($fileCount == 0) {
                                    return $this->_sendPlainResponse(204, 'No Content', '');
                                } else {
                                    return $this->_sendFile($tmpDb, [ 'remove' => true ]);
                                }
                            }
                        } elseif ($request->isPost() || $request->isPut()) {
                            $tmpDb = $this->codistoHelper->getSyncPathTemp('template');

                            $tmpDbContent = $this->file->read('php://input');

                            $this->file->write($tmpDb, $tmpDbContent);

                            $this->sync->templateWrite($tmpDb);

                            $this->file->rm($tmpDb);

                            return $this->_sendJsonResponse(200, 'OK', [ 'ack' => 'ok' ]);
                        }
                    } catch (\Exception $e) {
                        return $this->_sendExceptionError($e);
                    }
                } else {
                    return $this->_sendSecurityError();
                }
                break;
            default:
                return $this->_sendPlainResponse(200, 'OK', 'No Action');
        }
    }

    private function _checkHash($store, $server)
    {
        return $this->codistoHelper->checkRequestHash($store->getConfig('codisto/hostkey'), $server);
    }

    private function _sendSecurityError()
    {
        $this->_sendPlainResponse(400, 'Security Error', 'Security Error');
    }

    private function _sendConfigError()
    {
        $this->_sendPlainResponse(500, 'Config Error', 'Config Error');
    }

    private function _sendExceptionError($exception)
    {
        $this->_sendPlainResponse(
            500,
            'Exception',
            'Exception: '.$exception->getMessage().
                ' on line: '.$exception->getLine().
                ' in file: '.$exception->getFile().
                ' '.$exception->getTraceAsString()
        );
    }

    private function _sendPlainResponse($status, $statustext, $body, $extraHeaders = null)
    {
        $response = $this->getResponse();
        $response->clearHeaders();
        $response->setStatusHeader($status, '1.0', $statustext);

        $rawResult = $this->context->getResultFactory()->create(
            \Magento\Framework\Controller\ResultFactory::TYPE_RAW
        );
        $rawResult->setHttpResponseCode($status);
        $rawResult->setHeader('Cache-Control', 'no-cache', true);
        $rawResult->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
        $rawResult->setHeader('Pragma', 'no-cache', true);
        $rawResult->setHeader('Content-Type', 'text/plain');

        if (is_array($extraHeaders)) {
            foreach ($extraHeaders as $key => $value) {
                $rawResult->setHeader($key, $value);
            }
        }

        $rawResult->setContents($body);
        return $rawResult;
    }

    private function _sendJsonResponse($status, $statustext, $body, $extraHeaders = null)
    {
        $extraHeaders;

        $response = $this->getResponse();
        $response->clearHeaders();
        $response->setStatusHeader($status, '1.0', $statustext);

        $jsonResult = $this->context->getResultFactory()->create(
            \Magento\Framework\Controller\ResultFactory::TYPE_JSON
        );
        $jsonResult->setHttpResponseCode($status);
        $jsonResult->setHeader('Cache-Control', 'no-cache', true);
        $jsonResult->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
        $jsonResult->setHeader('Pragma', 'no-cache', true);
        $jsonResult->setHeader('Content-Type', 'application/json');
        $jsonResult->setData($body);
        return $jsonResult;
    }

    private function _sendFile($syncDb, $sendOptions = [])
    {
        $response = $this->getResponse();
        $response->clearHeaders();
        $response->setStatusHeader(200, '1.0', 'OK');
        $response->setHeader('Content-Type', 'application/octet-stream');
        $response->setHeader('Pragma', 'no-cache');
        $response->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
        $response->setHeader('Cache-Control', 'no-cache', true);

        if (isset($sendOptions['syncresponse'])) {
            $response->setHeader('X-Codisto-SyncResponse', $sendOptions['syncresponse']);
        }

        $filename = basename($syncDb);

        $fileOptions = [ 'type' => 'filename', 'value' => '/codisto/'.$filename ];
        if (isset($sendOptions['remove']) && $sendOptions['remove']) {
            $fileOption['rm'] = true;
        }

        return $this->fileFactory->create(
            $filename,
            $fileOptions,
            \Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR
        );
    }
}
