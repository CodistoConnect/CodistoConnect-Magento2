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
    private $visitor;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Filesystem\DirectoryList $dirList,
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\Framework\Json\Helper\Data $json,
        \Magento\Framework\Filesystem\Io\File $file,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Magento\Customer\Model\Visitor $visitor,
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
        $this->visitor = $visitor;
    }

    private function _storeId($request)
    {
        $storeId = $request->getQuery('storeid') == null ? 0 : (int)$request->getQuery('storeid');

        if ($storeId == 0) {
            foreach ($this->storeManager->getStores(false) as $store) {
                $storeId = $storeId == 0 ? $store->getId() : min($store->getId(), $storeId);
            }
        }

        return $storeId;
    }

    private function _syncActionGetSyncDb($storeId, $request)
    {
        if ($request->getQuery('first')) {
            $syncDb = $this->codistoHelper->getSyncPath('sync-first-'.$storeId.'.db');
        } else {
            $syncDb = $this->codistoHelper->getSyncPath('sync-'.$storeId.'.db');
        }

        return $syncDb;
    }

    private function _syncActionGetSyncOrders($request)
    {
        if ($request->getQuery('orderid')) {
            $orderIds = $this->json->jsonDecode($request->getQuery('orderid'));
            if (!is_array($orderIds)) {
                $orderIds = [$orderIds];
            }

            $orderIds = array_map('intval', $orderIds);

            $this->sync->syncOrders($syncDb, $orderIds, $storeId);
        }
    }

    private function _syncActionGetIncremental($syncDb)
    {
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

        $qry = $db->query( // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
            'SELECT CASE WHEN EXISTS(SELECT 1 FROM SyncDb.sqlite_master WHERE type = \'table\' AND name = \'Sync\') THEN -1 ELSE 0 END'
        );
        $syncComplete = $qry->fetchColumn();
        $qry->closeCursor();
        if (!$syncComplete) {
            @unlink($tmpDb); // @codingStandardsIgnoreLine Generic.PHP.NoSilencedErrors.Discouraged

            throw new \Exception('Attempting to download partial sync db - incremental'); // @codingStandardsIgnoreLine MEQP2.Exceptions.DirectThrow.FoundDirectThrow
        }

        $qry = $db->query( // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
            'SELECT CASE WHEN EXISTS(SELECT 1 FROM SyncDb.sqlite_master WHERE type = \'table\' AND name = \'ProductChange\') THEN -1 ELSE 0 END'
        );
        $productChange = $qry->fetchColumn();
        $qry->closeCursor();
        if ($productChange) {
            $qry = $db->query( // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
                'SELECT CASE WHEN EXISTS(SELECT 1 FROM SyncDb.ProductChange) THEN -1 ELSE 0 END'
            );
            $productsAvailable = $qry->fetchColumn();
            $qry->closeCursor();

            if ($productsAvailable) {
                $db->exec(
                    'CREATE TABLE Product AS '.
                    'SELECT * FROM SyncDb.Product WHERE ExternalReference IN '.
                        '(SELECT ExternalReference FROM SyncDb.ProductChange)'
                );
                $db->exec(
                    'CREATE TABLE ProductImage AS '.
                    'SELECT * FROM SyncDb.ProductImage WHERE ProductExternalReference IN '.
                        '(SELECT ExternalReference FROM SyncDb.ProductChange)'
                );
                $db->exec(
                    'CREATE TABLE CategoryProduct AS '.
                    'SELECT * FROM SyncDb.CategoryProduct WHERE ProductExternalReference IN '.
                        '(SELECT ExternalReference FROM SyncDb.ProductChange)'
                );
                $db->exec(
                    'CREATE TABLE SKU AS '.
                    'SELECT * FROM SyncDb.SKU WHERE ProductExternalReference IN '.
                        '(SELECT ExternalReference FROM SyncDb.ProductChange)'
                );
                $db->exec(
                    'CREATE TABLE SKULink AS '.
                    'SELECT * FROM SyncDb.SKULink WHERE ProductExternalReference IN '.
                        '(SELECT ExternalReference FROM SyncDb.ProductChange)'
                );
                $db->exec(
                    'CREATE TABLE SKUMatrix AS '.
                    'SELECT * FROM SyncDb.SKUMatrix WHERE ProductExternalReference IN '.
                        '(SELECT ExternalReference FROM SyncDb.ProductChange)'
                );
                $db->exec(
                    'CREATE TABLE ProductOptionValue AS '.
                    'SELECT DISTINCT * FROM SyncDb.ProductOptionValue' // @codingStandardsIgnoreLine
                );
                $db->exec(
                    'CREATE TABLE ProductHTML AS '.
                    'SELECT * FROM SyncDb.ProductHTML WHERE ProductExternalReference IN '.
                        '(SELECT ExternalReference FROM SyncDb.ProductChange)'
                );
                $db->exec(
                    'CREATE TABLE ProductRelated AS '.
                    'SELECT * FROM SyncDb.ProductRelated WHERE ProductExternalReference IN '.
                        '(SELECT ExternalReference FROM SyncDb.ProductChange)'
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
                        '(SELECT ExternalReference FROM SyncDb.ProductChange)'
                );
                $db->exec(
                    'CREATE TABLE ProductQuestion AS '.
                    'SELECT * FROM SyncDb.ProductQuestion WHERE ProductExternalReference IN '.
                        '(SELECT ExternalReference FROM SyncDb.ProductChange)'
                );
                $db->exec(
                    'CREATE TABLE ProductQuestionAnswer AS '.
                    'SELECT * FROM SyncDb.ProductQuestionAnswer WHERE ProductQuestionExternalReference IN '.
                        '(SELECT ExternalReference FROM ProductQuestion)'
                );
                $db->exec(
                    'CREATE TABLE ProductChange AS '.
                    'SELECT * FROM SyncDb.ProductChange'
                );
            }
        }

        $qry = $db->query( // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
            'SELECT CASE WHEN EXISTS(SELECT 1 FROM SyncDb.sqlite_master WHERE type = \'table\' AND name = \'CategoryChange\') THEN -1 ELSE 0 END'
        );
        $categoryChange = $qry->fetchColumn();
        $qry->closeCursor();
        if ($categoryChange) {
            $qry = $db->query( // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
                'SELECT CASE WHEN EXISTS(SELECT 1 FROM SyncDb.CategoryChange) THEN -1 ELSE 0 END'
            );
            $categoriesAvailable = $qry->fetchColumn();
            $qry->closeCursor();

            if ($categoriesAvailable) {
                $db->exec('CREATE TABLE Category AS SELECT * FROM SyncDb.Category');
                $db->exec('CREATE TABLE CategoryChange AS SELECT * FROM SyncDb.CategoryChange');
            }
        }

        $qry = $db->query( // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
            'SELECT CASE WHEN EXISTS(SELECT 1 FROM SyncDb.sqlite_master WHERE type = \'table\' AND name = \'OrderChange\') THEN -1 ELSE 0 END'
        );
        $orderChange = $qry->fetchColumn();
        $qry->closeCursor();
        if ($orderChange) {
            $qry = $db->query( // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
                'SELECT CASE WHEN EXISTS(SELECT 1 FROM SyncDb.OrderChange) THEN -1 ELSE 0 END'
            );
            $ordersAvailable = $qry->fetchColumn();
            $qry->closeCursor();

            if ($ordersAvailable) {
                $db->exec(
                    'CREATE TABLE [Order] AS '.
                    'SELECT * FROM SyncDb.[Order] WHERE ExternalReference = \'\' '.
                    'OR ExternalReference IN (SELECT ExternalReference FROM SyncDb.OrderChange)'
                );
                $db->exec('CREATE TABLE OrderChange AS SELECT * FROM SyncDb.OrderChange');
            }
        }

        $db->exec('COMMIT TRANSACTION');
        $db->exec('DETACH DATABASE SyncDB');
        $db->exec('VACUUM');

        return $this->_sendFile($tmpDb, ['remove' => true, 'syncresponse' => 'incremental']);
    }

    private function _syncActionGet($store, $storeId, $request, $server)
    {
        if (!$this->_checkHash($store, $server)) {
            return $this->_sendSecurityError();
        }

        $syncDb = $this->_syncActionGetSyncDb($storeId, $request);

        if (!$request->getQuery('first') &&
            is_string($request->getQuery('incremental'))) {
            return $this->_syncActionGetIncremental($syncDb);
        }

        if (!($request->getQuery('productid')
            || $request->getQuery('categoryid')
            || $request->getQuery('orderid'))) {
            return $this->_sendFile($syncDb);
        }

        $this->_syncActionGetSyncOrders($request);

        $tmpDb = $this->codistoHelper->getSyncPathTemp('sync');

        $db = $this->codistoHelper->createSqliteConnection($tmpDb);
        $db->exec('PRAGMA synchronous=0');
        $db->exec('PRAGMA temp_store=2');
        $db->exec('PRAGMA page_size=65536');
        $db->exec('PRAGMA encoding=\'UTF-8\'');
        $db->exec('PRAGMA cache_size=15000');
        $db->exec('PRAGMA soft_heap_limit=67108864');
        $db->exec('PRAGMA journal_mode=MEMORY');

        $db->exec('ATTACH DATABASE \''.$syncDb.'\' AS SyncDb');

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
            $productStr = '("' . implode('","', $productIds) . '")';

            $db->exec(
                'CREATE TABLE Product AS '.
                'SELECT * FROM SyncDb.Product WHERE ExternalReference IN '.
                    $productStr
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

            // this is local sqlite query
            if ($db->query( // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
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
    }

    private function _syncActionProductCount($store, $storeId, $server)
    {
        if (!$this->_checkHash($store, $server)) {
            return $this->_sendSecurityError();
        }

        return $this->_sendJsonResponse(200, 'OK', $this->sync->productTotals($storeId));
    }

    private function _syncActionExecuteFirstConfigurable($storeId, $request)
    {
        $result = '';

        $configurableCount = (int)$request->getQuery('configurablecount');
        if (!$configurableCount || !is_numeric($configurableCount)) {
            $configurableCount = $this->defaultConfigurableCount;
        }

        if ($configurableCount > 0) {
            $result = $this->sync->syncChunk($syncDb, 0, $configurableCount, $storeId, true);
        }

        return $result;
    }

    private function _syncActionExecuteFirstSimple($storeId, $request)
    {
        $result = '';

        $simpleCount = (int)$request->getQuery('simplecount');
        if (!$simpleCount || !is_numeric($simpleCount)) {
            $simpleCount = $this->defaultSimpleCount;
        }

        if ($simpleCount > 0) {
            $result = $this->sync->syncChunk($syncDb, $simpleCount, 0, $storeId, true);
        }

        return $result;
    }

    private function _syncActionExecuteFirstException($e, $syncDb)
    {
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

        return null;
    }

    private function _syncActionExecuteFirst($store, $storeId, $request, $server)
    {
        if (!$this->_checkHash($store, $server)) {
            return $this->_sendSecurityError();
        }

        $syncDb = $this->codistoHelper->getSyncPath('sync-first-'.$storeId.'.db');

        try {
            $result = 'error';

            if ($this->file->fileExists($syncDb)) {
                $this->file->rm($syncDb);
            }

            $result = $this->_syncActionExecuteFirstConfigurable($storeId, $request);
            if ($result != 'complete') {
                $result = $this->_syncActionExecuteFirstSimple($storeId, $request);
            }

            if ($result == 'complete') {
                $this->sync->syncTax($syncDb, $storeId);
                $this->sync->syncStores($syncDb, $storeId);
            } else {
                throw new \Exception('First page execution failed'); // @codingStandardsIgnoreLine MEQP2.Exceptions.DirectThrow.FoundDirectThrow
            }

            return $this->_sendPlainResponse(200, 'OK', $result);
        } catch (\Exception $e) {
            $result = $this->_syncActionExecuteFirstException($e, $syncDb);
            if ($result) {
                return $result;
            }

            return $this->_sendExceptionError($e);
        }
    }

    private function _syncActionExecuteChunkInit($request, $storeId, $simpleCount, $configurableCount, $syncDb)
    {
        if ($request->getPost('Init') == '1') {
            $forceInit = $request->getPost('forceinit');
            $forceInit = is_string($forceInit) && $forceInit == '1';

            if (!$forceInit) {
                if ($this->codistoHelper->canSyncIncrementally($syncDb, $storeId)) {
                    $result = $this->sync->syncIncremental($simpleCount, $configurableCount);
                    return $this->_sendPlainResponse(200, 'OK', 'incremental-'.$result);
                }
            }

            if ($this->file->fileExists($syncDb)) {
                $this->file->rm($syncDb);
            }
        }

        return null;
    }

    private function _syncActionExecuteChunkCounts($request)
    {
        $configurableCount = (int)$request->getQuery('configurablecount');
        if (!$configurableCount || !is_numeric($configurableCount)) {
            $configurableCount = $this->defaultConfigurableCount;
        }

        $simpleCount = (int)$request->getQuery('simplecount');
        if (!$simpleCount || !is_numeric($simpleCount)) {
            $simpleCount = $this->defaultSimpleCount;
        }

        return ['configurablecount' => $configurableCount, 'simplecount' => $simpleCount];
    }

    private function _syncActionExecuteChunkTimeout($request)
    {
        $timeout = (int)$request->getQuery('timeout');
        if (!$timeout || !is_numeric($timeout)) {
            $timeout = $this->defaultSyncTimeout;
        }
        $timeout = max(5, $timeout);

        return $timeout;
    }

    private function _syncActionExecuteChunkException($e, $syncDb)
    {
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

        return null;
    }

    private function _syncActionExecuteChunk($store, $storeId, $request, $server)
    {
        if (!$this->_checkHash($store, $server)) {
            return $this->_sendSecurityError();
        }

        $syncDb = $this->codistoHelper->getSyncPath('sync-'.$storeId.'.db');

        try {
            $result = 'error';

            $countLimits = $this->_syncActionExecuteChunkCounts($request);

            $result = $this->_syncActionExecuteChunkInit(
                $request,
                $storeId,
                $countLimits['simplecount'],
                $countLimits['configurablecount'],
                $syncDb
            );
            if ($result) {
                return $result;
            }

            if (is_string($request->getQuery('incremental'))) {
                $result = $this->sync->syncIncremental($countLimits['simplecount'], $countLimits['configurablecount']);

                $result = 'incremental-'.$result;

            } else {
                $result = $this->sync->syncChunk(
                    $syncDb,
                    $countLimits['simplecount'],
                    $countLimits['configurablecount'],
                    $storeId,
                    false
                );
            }

            if ($result == 'complete') {
                $result = 'catalog-complete';

                $this->sync->syncTax($syncDb, $storeId);
                $this->sync->syncStores($syncDb, $storeId);
            }

            return $this->_sendPlainResponse(200, 'OK', $result);
        } catch (\Exception $e) {

            $result = $this->_syncActionExecuteChunkException($e, $syncDb);
            if ($result) {
                return $result;
            }

            return $this->_sendExceptionError($e);
        }
    }

    private function _syncActionChangeComplete($store, $storeId, $server)
    {
        if (!$this->_checkHash($store, $server)) {
            return $this->_sendSecurityError();
        }

        $tmpDb = $this->codistoHelper->getSyncPathTemp('sync-complete');

        try {
            $syncDb = $this->codistoHelper->getSyncPath('sync-'.$storeId.'.db');

            file_put_contents($tmpDb, file_get_contents('php://input')); // @codingStandardsIgnoreLine

            $this->sync->syncChangeComplete($syncDb, $tmpDb);

            @unlink($tmpDb); // @codingStandardsIgnoreLine

            return $this->_sendPlainResponse(200, 'OK', 'ok');
        } catch (\Exception $e) {
            @unlink($tmpDb); // @codingStandardsIgnoreLine
            return $this->_sendExceptionError($e);
        }
    }

    private function _syncActionPull($store, $storeId, $request, $server)
    {
        if (!$this->_checkHash($store, $server)) {
            return $this->_sendSecurityError();
        }

        try {
            $syncDb = $this->codistoHelper->getSyncPath('sync-'.$storeId.'.db');

            $productId = (int)$request->getPost('ProductID');
            $productIds = [$productId];

            if (!$productId && $request->getQuery('productid')) {
                $productIds = $this->json->jsonDecode($request->getQuery('productid'));
                if (!is_array($productIds)) {
                    $productIds = [$productIds];
                }
            }

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
    }

    private function _syncActionTax($store, $storeId, $server)
    {
        if ($this->_checkHash($store, $server)) {
            return $this->_sendSecurityError();
        }

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
    }

    private function _syncActionStoreView($store, $storeId, $server)
    {
        if (!$this->_checkHash($store, $server)) {
            return $this->_sendSecurityError();
        }

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
    }

    private function _syncActionStaticBlocks($store, $storeId, $server)
    {
        if (!$this->_checkHash($store, $server)) {
            return $this->_sendSecurityError();
        }

        try {
            $syncDb = $this->codistoHelper->getSyncPath('sync-'.$storeId.'.db');

            $this->sync->syncStaticBlocks($syncDb, $storeId);
        } catch (\Exception $e) {
            $e;
        }

        return $this->_sendPlainResponse(200, 'OK', 'complete');
    }

    private function _syncActionOrders($store, $storeId, $request, $server)
    {
        if (!$this->_checkHash($store, $server)) {
            return $this->_sendSecurityError();
        }

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
    }

    private function _syncActionTemplate($store, $request, $server)
    {
        if (!$this->_checkHash($store, $server)) {
            return $this->_sendSecurityError();
        }

        try {
            if ($request->isGet()) {
                $merchantid = (int)$request->getQuery('merchantid');

                $templateDb = $this->codistoHelper->getSyncPath('template-'.$merchantid.'.db');

                if ($request->getQuery('markreceived')) {
                    try {
                        $db = $this->codistoHelper->createSqliteConnection($templateDb);

                        $update = $db->prepare('UPDATE File SET LastModified = ? WHERE Name = ?');

                        // this is PDO usage on the local sqlite sync database, not the magento data store
                        $files = $db->query('SELECT Name FROM File WHERE Changed != 0'); // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
                        $files->execute();

                        $db->exec('BEGIN EXCLUSIVE TRANSACTION');

                        while ($row = $files->fetch()) {
                            $stat = stat( // @codingStandardsIgnoreLine
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

                    // this is local sqlite sync database usage, not magento data store
                    $fileCountStmt = $db->query('SELECT COUNT(*) AS fileCount FROM File'); // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
                    $fileCountStmt->execute();
                    $fileCountRow = $fileCountStmt->fetch();
                    $fileCount = $fileCountRow['fileCount'];
                    $db = null;

                    if ($fileCount == 0) {
                        return $this->_sendPlainResponse(204, 'No Content', '');
                    }

                    return $this->_sendFile($tmpDb, [ 'remove' => true ]);
                }
            } elseif ($request->isPost() || $request->isPut()) {
                $tmpDb = $this->codistoHelper->getSyncPathTemp('template');

                $tmpDbContent = $this->file->read('php://input');

                $this->file->write($tmpDb, $tmpDbContent);

                $this->sync->templateWrite($tmpDb);

                $this->file->rm($tmpDb);

                return $this->_sendJsonResponse(200, 'OK', [ 'ack' => 'ok' ]);
            }

            throw new \Exception('Invalid Request Method'); // @codingStandardsIgnoreLine MEQP2.Exceptions.DirectThrow.FoundDirectThrow
        } catch (\Exception $e) {
            return $this->_sendExceptionError($e);
        }
    }

    // the switch table to determine the sync activity to run
    // exceeds cyclomatic complexity, however, it is simple and secure
    // so overriding code sniffer warnings
    public function execute() // @codingStandardsIgnoreLine Generic.Metrics.CyclomaticComplexity.TooHigh
    {
        $this->visitor->setSkipRequestLogging(true);

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

        $storeId = $this->_storeId($request);

        if (!$this->codistoHelper->getConfig($storeId)) {
            return $this->_sendConfigError();
        }

        $this->storeManager->setCurrentStore($storeId);

        $store = $this->storeManager->getStore($storeId);

        if (!isset($server['HTTP_X_SYNC'])) {
            return $this->_sendPlainResponse(400, 'Bad Request', 'No Action');
        }

        $action = isset($server['HTTP_X_ACTION']) ? $server['HTTP_X_ACTION'] : '';

        switch ($action) {
            case 'GET':
                return $this->_syncActionGet($store, $storeId, $request, $server);

            case 'PRODUCTCOUNT':
                return $this->_syncActionProductCount($store, $storeId, $server);

            case 'EXECUTEFIRST':
                return $this->_syncActionExecuteFirst($store, $storeId, $request, $server);

            case 'EXECUTEINCREMENT':
                if (!$this->codistoHelper->getTriggerMode()) {
                    return $this->sendPlainResponse(400, 'Bad Request', 'No Action');
                }
                // fall through to EXECUTECHUNK
            case 'EXECUTECHUNK':
                return $this->_syncActionExecuteChunk($store, $storeId, $request, $server);

            case 'CHANGECOMPLETE':
                return $this->_syncActionChangeComplete($store, $storeId, $server);

            case 'PULL':
                return $this->_syncActionPull($store, $storeId, $request, $server);

            case 'TAX':
                return $this->_syncActionTax($store, $storeId, $server);

            case 'STOREVIEW':
                return $this->_syncActionStoreView($store, $storeId, $server);

            case 'BLOCKS':
                return $this->_syncActionStaticBlocks($store, $storeId, $server);

            case 'ORDERS':
                return $this->_syncActionOrders($store, $storeId, $request, $server);

            case 'TEMPLATE':
                return $this->_syncActionTemplate($store, $request, $server);

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
        $response->setNoCacheHeaders();
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
        $rawResult->renderResult($response);
        return $rawResult;
    }

    private function _sendJsonResponse($status, $statustext, $body, $extraHeaders = null)
    {
        $extraHeaders;

        $response = $this->getResponse();
        $response->setNoCacheHeaders();
        $response->setStatusHeader($status, '1.0', $statustext);

        $jsonResult = $this->context->getResultFactory()->create(
            \Magento\Framework\Controller\ResultFactory::TYPE_JSON
        );
        $jsonResult->setHttpResponseCode($status);
        $jsonResult->setHeader('Content-Type', 'application/json');
        $jsonResult->setData($body);
        return $jsonResult;
    }

    private function _sendFile($syncDb, $sendOptions = [])
    {
        $response = $this->getResponse();
        $response->setNoCacheHeaders();
        $response->setStatusHeader(200, '1.0', 'OK');
        $response->setHeader('Content-Type', 'application/octet-stream');

        if (isset($sendOptions['syncresponse'])) {
            $response->setHeader('X-Codisto-SyncResponse', $sendOptions['syncresponse']);
        }

        $filename = basename($syncDb); // @codingStandardsIgnoreLine MEQP1.Security.DiscouragedFunction.Found

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
