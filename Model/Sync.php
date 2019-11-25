<?php

/**
 * Codisto Marketplace Sync Extension
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

namespace Codisto\Connect\Model;

use Magento\Framework\UrlInterface;
use Magento\Framework\DB\Ddl\Table;

class Sync
{
    private $resourceConnection;
    private $deploymentConfigFactory;
    private $productCollectionFactory;
    private $productAttributeCollectionFactory;
    private $productAttributeGroupFactory;
    private $cmsBlockCollectionFactory;
    private $taxClassCollectionFactory;
    private $taxCalcCollectionFactory;
    private $taxCalcRuleCollectionFactory;
    private $taxCalcRateCollectionFactory;
    private $salesOrderCollectionFactory;
    private $eavAttributeCollectionFactory;
    private $productFactory;
    private $categoryFactory;
    private $categoryCollectionFactory;
    private $configurableTypeFactory;
    private $groupedTypeFactory;
    private $bundleTypeFactory;
    private $stockItemFactory;
    private $groupFactory;
    private $attributeGroupFactory;

    private $storeManager;

    private $dirList;

    private $json;

    private $dateTime;

    private $taxHelper;

    private $mediaConfigFactory;
    private $mediaConfig;

    private $iteratorFactory;

    private $codistoHelper;

    private $currentEntityId;
    private $productsProcessed;
    private $ordersProcessed;

    private $ebayGroupId;

    private $attributeCache;
    private $attributeLabelCache;
    private $groupCache;
    private $optionCache;
    private $optionTextCache;

    private $availableProductFields;

    private $productFlatState;
    private $categoryFlatState;

    private $urlBuilder;

    /*
        @codingStandardsIgnoreStart MEQP2.Classes.CollectionDependency.CollectionDependency
            \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory is required as we use the walk interface
            which is not compatible with ProductRepositoryInterface
    */


    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\App\DeploymentConfigFactory $deploymentConfigFactory,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory $productAttributeCollectionFactory,
        \Magento\Catalog\Model\Product\Attribute\GroupFactory $productAttributeGroupFactory,
        \Magento\Cms\Model\ResourceModel\Block\CollectionFactory $cmsBlockCollectionFactory,
        \Magento\Tax\Model\ResourceModel\TaxClass\CollectionFactory $taxClassCollectionFactory,
        \Magento\Tax\Model\ResourceModel\Calculation\CollectionFactory $taxCalcCollectionFactory,
        \Magento\Tax\Model\ResourceModel\Calculation\Rule\CollectionFactory $taxCalcRuleCollectionFactory,
        \Magento\Tax\Model\ResourceModel\Calculation\Rate\CollectionFactory $taxCalcRateCollectionFactory,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $salesOrderCollectionFactory,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory $eavAttributeCollectionFactory,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory,
        \Magento\ConfigurableProduct\Model\Product\Type\ConfigurableFactory $configurableTypeFactory,
        \Magento\GroupedProduct\Model\Product\Type\GroupedFactory $groupedTypeFactory,
        \Magento\Bundle\Model\Product\TypeFactory $bundleTypeFactory,
        \Magento\CatalogInventory\Model\Stock\ItemFactory $stockItemFactory,
        \Magento\Customer\Model\GroupFactory $groupFactory,
        \Magento\Catalog\Model\Product\Attribute\GroupFactory $attributeGroupFactory,
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\Framework\Filesystem\DirectoryList $dirList,
        \Magento\Framework\Json\Helper\Data $json,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        \Magento\Catalog\Helper\Data $taxHelper,
        \Magento\Tax\Api\TaxCalculationInterface $taxCalc,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Catalog\Model\Product\Media\ConfigFactory $mediaConfigFactory,
        \Magento\Framework\Model\ResourceModel\IteratorFactory $iteratorFactory,
        \Magento\Catalog\Model\Indexer\Product\Flat\StateFactory $productFlatState,
        \Magento\Catalog\Model\Indexer\Category\Flat\StateFactory $categoryFlatState,
        \Codisto\Connect\Helper\Data $codistoHelper
    // @codingStandardsIgnoreEnd MEQP2.Classes.CollectionDependency.CollectionDependency
    ) {

        $this->resourceConnection = $resourceConnection;
        $this->deploymentConfigFactory = $deploymentConfigFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productAttributeCollectionFactory = $productAttributeCollectionFactory;
        $this->productAttributeGroupFactory = $productAttributeGroupFactory;
        $this->cmsBlockCollectionFactory = $cmsBlockCollectionFactory;
        $this->taxClassCollectionFactory = $taxClassCollectionFactory;
        $this->taxCalcCollectionFactory = $taxCalcCollectionFactory;
        $this->taxCalcRuleCollectionFactory = $taxCalcRuleCollectionFactory;
        $this->taxCalcRateCollectionFactory = $taxCalcRateCollectionFactory;
        $this->salesOrderCollectionFactory = $salesOrderCollectionFactory;
        $this->eavAttributeCollectionFactory = $eavAttributeCollectionFactory;
        $this->productFactory = $productFactory;
        $this->categoryFactory = $categoryFactory;
        $this->configurableTypeFactory = $configurableTypeFactory;
        $this->groupedTypeFactory = $groupedTypeFactory;
        $this->bundleTypeFactory = $bundleTypeFactory;
        $this->stockItemFactory = $stockItemFactory;
        $this->groupFactory = $groupFactory;
        $this->attributeGroupFactory = $attributeGroupFactory;
        $this->storeManager = $storeManager;
        $this->dirList = $dirList;
        $this->json = $json;
        $this->dateTime = $dateTime;
        $this->taxHelper = $taxHelper;
        $this->taxCalc = $taxCalc;
        $this->mediaConfigFactory = $mediaConfigFactory;
        $this->iteratorFactory = $iteratorFactory;
        $this->productFlatState = $productFlatState;
        $this->categoryFlatState = $categoryFlatState;
        $this->urlBuilder = $context->getUrl();
        $this->codistoHelper = $codistoHelper;
        $this->scopeConfig = $scopeConfig;

        $this->attributecache = [];
        $this->attributeLabelCache = [];
        $this->groupCache = [];
        $this->optionCache = [];
        $this->optionTextCache = [];

        $ebayGroup = $groupFactory->create();
        $ebayGroup->load('eBay', 'customer_group_code');

        $this->ebayGroupId = $ebayGroup->getId();
        if (!$this->ebayGroupId) {
            $this->ebayGroupId = \Magento\Customer\Model\GroupManagement::NOT_LOGGED_IN_ID;
        }

        $this->taxIncluded = $this->scopeConfig->getValue(
            'tax/calculation/price_includes_tax',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            \Magento\Store\Model\Store::DEFAULT_STORE_ID
        );

        $productSelectArray = [
            'entity_id',
            'sku',
            'name',
            'image',
            'description',
            'short_description',
            'price',
            'special_price',
            'special_from_date',
            'special_to_date',
            'status',
            'tax_class_id',
            'weight'
        ];
        $this->availableProductFields = $this->_availableProductFields($productSelectArray);
    }

    private function _availableProductFields($selectArr)
    {
        $attributes = [ 'entity_id' ];

        $productAttrs = $this->productAttributeCollectionFactory->create();

        foreach ($productAttrs as $productAttr) {
            if (in_array($productAttr->getAttributeCode(), $selectArr)) {
                $attributes[] = $productAttr->getAttributeCode();
            }
        }

        return $attributes;
    }

    private function _tablePrefix()
    {
        $deploymentConfig = $this->deploymentConfigFactory->create();

        $tablePrefix = (string)$deploymentConfig->get(
            \Magento\Framework\Config\ConfigOptionsListConstants::CONFIG_PATH_DB_PREFIX
        );

        $deploymentConfig = null;

        return $tablePrefix;
    }

    public function templateRead($templateDb)
    {
        $ebayDesignDir = $this->dirList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::APP).'/design/ebay/';

        try {
            $db = $this->_getTemplateDb($templateDb);

            $insert = $db->prepare('INSERT OR IGNORE INTO File(Name, Content, LastModified) VALUES (?, ?, ?)');
            $update = $db->prepare('UPDATE File SET Content = ?, Changed = -1 WHERE Name = ? AND LastModified != ?');

            $filelist = $this->_filesInDir($ebayDesignDir);

            $db->exec('BEGIN EXCLUSIVE TRANSACTION');

            foreach ($filelist as $key => $name) {
                try {
                    $fileName = $ebayDesignDir.$name;

                    if (in_array($name, [ 'README' ])) {
                        continue;
                    }

                    $content = @file_get_contents($fileName); // @codingStandardsIgnoreLine
                    if ($content === false) {
                        continue;
                    }

                    $stat = stat($fileName); // @codingStandardsIgnoreLine

                    $lastModified = strftime('%Y-%m-%d %H:%M:%S', $stat['mtime']);

                    $update->bindParam(1, $content);
                    $update->bindParam(2, $name);
                    $update->bindParam(3, $lastModified);
                    $update->execute();

                    if ($update->rowCount() == 0) {
                        $insert->bindParam(1, $name);
                        $insert->bindParam(2, $content);
                        $insert->bindParam(3, $lastModified);
                        $insert->execute();
                    }
                } catch (\Exception $e) {
                    $e;
                    // ignore failure to store a single file
                }
            }
            $db->exec('COMMIT TRANSACTION');
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return 'ok';
    }

    public function templateWrite($templateDb)
    {
        $ebayDesignDir = $this->dirList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::APP).'/design/ebay/';

        try {
            $db = $this->codistoHelper->createSqliteConnection($templateDb);

            $this->codistoHelper->prepareSqliteDatabase($db, 60);

            $files = $db->prepare('SELECT Name, Content FROM File');
            $files->execute();

            $files->bindColumn(1, $name);
            $files->bindColumn(2, $content);

            while ($files->fetch()) {
                $fileName = $ebayDesignDir.$name;

                if (strpos($name, '..') === false) {
                    if (!file_exists($fileName)) { // @codingStandardsIgnoreLine
                        $dir = dirname($fileName);

                        if (!is_dir($dir)) { // @codingStandardsIgnoreLine
                            mkdir($dir.'/', 0755, true);
                        }

                        @file_put_contents($fileName, $content); // @codingStandardsIgnoreLine
                    }
                }
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return 'ok';
    }

    public function updateCategory($syncDb, $id, $storeId)
    {
        $store = $this->storeManager->getStore($storeId);

        $db = $this->_getSyncDb($syncDb, 60);

        $insertCategory = $db->prepare(
            'INSERT OR REPLACE INTO Category('.
            'ExternalReference, Name, ParentExternalReference, LastModified, Enabled, Sequence'.
            ') VALUES(?,?,?,?,?,?)'
        );

        $categoryFlatState = $this->categoryFlatState->create([ 'isAvailable' => false ]);

        $categories = $this->categoryFactory->create([ 'flatState' => $categoryFlatState ])->getCollection()
            ->addAttributeToSelect(['name', 'image', 'is_active', 'updated_at', 'parent_id', 'position'], 'left')
            ->addAttributeToFilter('entity_id', ['eq' => $id]);

        $iterator = $this->iteratorFactory->create();

        $db->exec('BEGIN EXCLUSIVE TRANSACTION');

        $iterator->walk(
            $categories->getSelect(),
            [[$this, 'syncCategoryData']],
            ['db' => $db, 'preparedStatement' => $insertCategory, 'store' => $store ]
        );

        $db->exec('COMMIT TRANSACTION');
    }

    public function deleteCategory($syncDb, $id, $storeId)
    {
        $storeId;

        $db = $this->_getSyncDb($syncDb, 60);

        $db->exec('BEGIN EXCLUSIVE TRANSACTION');

        $db->exec(
            'CREATE TABLE IF NOT EXISTS CategoryDelete(ExternalReference text NOT NULL PRIMARY KEY);'.
            'INSERT OR IGNORE INTO CategoryDelete VALUES('.$id.');'.
            'DELETE FROM Category WHERE ExternalReference = '.$id.';'.
            'DELETE FROM CategoryProduct WHERE CategoryExternalReference = '.$id
        );

        $db->exec('COMMIT TRANSACTION');
    }

    public function updateProducts($syncDb, $ids, $storeId)
    {
        $store = $this->storeManager->getStore($storeId);

        $db = $this->_getSyncDb($syncDb, 60);

        $this->productsProcessed = [];

        $coreResource = $this->resourceConnection;

        $catalogWebsiteName = $coreResource->getTableName('catalog_product_website');
        $storeName = $coreResource->getTableName('store');
        $superLinkName = $coreResource->getTableName('catalog_product_super_link');

        $idscsv = implode(',', $ids);

        // Configurable products
        $productFlatState = $this->productFlatState->create([ 'isAvailable' => false ]);

        $configurableProducts = $this->productCollectionFactory
            ->create(['catalogProductFlatState' => $productFlatState])
            ->addAttributeToSelect($this->availableProductFields, 'left')
            ->addAttributeToFilter('type_id', ['eq' => 'configurable']);

        $sqlCheckModified = '(`e`.entity_id IN ('.$idscsv.') OR `e`.entity_id IN ('.
            'SELECT parent_id FROM `'.$superLinkName.'` WHERE product_id IN ('.$idscsv.')))';

        $configurableProducts->getSelect()
            ->columns( // @codingStandardsIgnoreLine
                [
                    'codisto_in_store'=> new \Zend_Db_Expr( // @codingStandardsIgnoreLine
                        'CASE WHEN `e`.entity_id IN ('.
                        'SELECT product_id FROM `'.$catalogWebsiteName.'` WHERE website_id IN ('.
                        'SELECT website_id FROM `'.$storeName.'` WHERE store_id = '.$storeId.' '.
                        'OR EXISTS(SELECT 1 FROM `'.$storeName.'` WHERE store_id = '.$storeId.' AND website_id = 0))'.
                        ') THEN -1 ELSE 0 END'
                    )
                ]
            )
            ->where($sqlCheckModified); // @codingStandardsIgnoreLine

        // Simple Products not participating as configurable skus
        $simpleProducts = $this->productCollectionFactory->create(['catalogProductFlatState' => $productFlatState])
            ->addAttributeToSelect($this->availableProductFields, 'left')
            ->addAttributeToFilter('type_id', ['eq' => 'simple'])
            ->addAttributeToFilter('entity_id', ['in' => $ids]);

        $simpleProducts->getSelect()
            ->columns( // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
                [
                    'codisto_in_store'=> new \Zend_Db_Expr( // @codingStandardsIgnoreLine MEQP2.Classes.ObjectInstantiation.FoundDirectInstantiation
                        'CASE WHEN `e`.entity_id IN '.
                        '(SELECT product_id FROM `'.$catalogWebsiteName.'` WHERE website_id IN '.
                        '(SELECT website_id FROM `'.$storeName.'` WHERE store_id = '.$storeId.' '.
                        'OR EXISTS(SELECT 1 FROM `'.$storeName.'` WHERE store_id = '.$storeId.' AND website_id = 0))'.
                        ') THEN -1 ELSE 0 END'
                    )
                ]
            );

        // Virtual Products not participating as configurable skus
        $virtualProducts = $this->productCollectionFactory->create(['catalogProductFlatState' => $productFlatState])
            ->addAttributeToSelect($this->availableProductFields, 'left')
            ->addAttributeToFilter('type_id', ['eq' => 'virtual'])
            ->addAttributeToFilter('entity_id', ['in' => $ids]);

        $virtualProducts->getSelect()
            ->columns( // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
                [
                    'codisto_in_store'=> new \Zend_Db_Expr( // @codingStandardsIgnoreLine MEQP2.Classes.ObjectInstantiation.FoundDirectInstantiation
                        'CASE WHEN `e`.entity_id IN '.
                        '(SELECT product_id FROM `'.$catalogWebsiteName.'` WHERE website_id IN '.
                        '(SELECT website_id FROM `'.$storeName.'` WHERE store_id = '.$storeId.' '.
                        'OR EXISTS(SELECT 1 FROM `'.$storeName.'` WHERE store_id = '.$storeId.' AND website_id = 0))'.
                        ') THEN -1 ELSE 0 END'
                    )
                ]
            );

        // Grouped products
        $groupedProducts = $this->productCollectionFactory
            ->create(['catalogProductFlatState' => $productFlatState])
            ->addAttributeToSelect($this->availableProductFields, 'left')
            ->addAttributeToFilter('type_id', ['eq' => 'grouped'])
            ->addAttributeToFilter('entity_id', ['in' => $ids]);

        $groupedProducts->getSelect()
            ->columns( // @codingStandardsIgnoreLine
                [
                    'codisto_in_store' => new \Zend_Db_Expr( // @codingStandardsIgnoreLine MEQP2.Classes.ObjectInstantiation.FoundDirectInstantiation
                        'CASE WHEN `e`.entity_id IN ('.
                        'SELECT product_id FROM `'.$catalogWebsiteName.'` WHERE website_id IN '.
                        '(SELECT website_id FROM `'.$storeName.'` WHERE store_id = '.$storeId.' '.
                        'OR EXISTS(SELECT 1 FROM `'.$storeName.'` WHERE store_id = '.$storeId.' AND website_id = 0))'.
                        ') THEN -1 ELSE 0 END'
                    )
                ]
            );

        $db->exec('BEGIN EXCLUSIVE TRANSACTION');

        $db->exec('CREATE TEMPORARY TABLE TmpChanged (entity_id text NOT NULL PRIMARY KEY)');
        foreach ($ids as $id) {
            $db->exec('INSERT INTO TmpChanged (entity_id) VALUES('.$id.')');
        }

        try {
            $db->exec('DELETE FROM ProductDelete WHERE ExternalReference IN (SELECT entity_id FROM TmpChanged)');
        } catch (\Exception $e) {
            $e;
            // if productdelete table is not create ignore
        }
        $db->exec(
            'DELETE FROM Product WHERE ExternalReference IN '.
            '(SELECT entity_id FROM TmpChanged)'
        );
        $db->exec(
            'DELETE FROM ProductImage WHERE ProductExternalReference IN '.
            '(SELECT entity_id FROM TmpChanged)'
        );
        $db->exec(
            'DELETE FROM ProductHTML WHERE ProductExternalReference IN '.
            '(SELECT entity_id FROM TmpChanged)'
        );
        $db->exec(
            'DELETE FROM ProductRelated WHERE ProductExternalReference IN '.
            '(SELECT entity_id FROM TmpChanged)'
        );
        $db->exec(
            'DELETE FROM ProductAttributeValue WHERE ProductExternalReference IN '.
            '(SELECT entity_id FROM TmpChanged)'
        );
        $db->exec(
            'DELETE FROM ProductQuestionAnswer WHERE ProductQuestionExternalReference IN '.
            '(SELECT ExternalReference FROM ProductQuestion WHERE ProductExternalReference IN '.
            '(SELECT entity_id FROM TmpChanged))'
        );
        $db->exec(
            'DELETE FROM ProductQuestion WHERE ProductExternalReference IN '.
            '(SELECT entity_id FROM TmpChanged)'
        );
        $db->exec(
            'DELETE FROM SKUMatrix WHERE ProductExternalReference IN '.
            '(SELECT entity_id FROM TmpChanged)'
        );
        $db->exec(
            'DELETE FROM SKULink WHERE ProductExternalReference IN '.
            '(SELECT entity_id FROM TmpChanged)'
        );
        $db->exec(
            'DELETE FROM SKU WHERE ProductExternalReference IN '.
            '(SELECT entity_id FROM TmpChanged)'
        );
        $db->exec(
            'DELETE FROM CategoryProduct WHERE ProductExternalReference IN '.
            '(SELECT entity_id FROM TmpChanged)'
        );

        $db->exec('DROP TABLE TmpChanged');

        $insertCategoryProduct = $db->prepare(
            'INSERT OR IGNORE INTO CategoryProduct'.
            '(ProductExternalReference, CategoryExternalReference, Sequence) '.
            'VALUES(?,?,?)'
        );
        $insertProduct = $db->prepare(
            'INSERT INTO Product'.
            '(ExternalReference, Type, Code, Name, Price, ListPrice, TaxClass, '.
            'Description, Enabled, StockControl, StockLevel, Weight, InStore) '.
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $checkProduct = $db->prepare(
            'SELECT CASE WHEN EXISTS(SELECT 1 FROM Product WHERE ExternalReference = ?) THEN 1 ELSE 0 END'
        );
        $insertSKU = $db->prepare(
            'INSERT OR IGNORE INTO SKU'.
            '(ExternalReference, Code, ProductExternalReference, Name, '.
            'StockControl, StockLevel, Price, Enabled, InStore) '.
            'VALUES(?,?,?,?,?,?,?,?,?)'
        );
        $insertSKULink = $db->prepare(
            'INSERT OR REPLACE INTO SKULink'.
            '(SKUExternalReference, ProductExternalReference, Price) '.
            'VALUES(?, ?, ?)'
        );
        $insertSKUMatrix = $db->prepare(
            'INSERT INTO SKUMatrix'.
            '(SKUExternalReference, ProductExternalReference, Code, OptionName, OptionValue, '.
            'ProductOptionExternalReference, ProductOptionValueExternalReference) '.
            'VALUES(?,?,?,?,?,?,?)'
        );
        $insertImage = $db->prepare(
            'INSERT INTO ProductImage'.
            '(ProductExternalReference, URL, Tag, Sequence, Enabled) '.
            'VALUES(?,?,?,?,?)'
        );
        $insertProductHTML = $db->prepare(
            'INSERT OR IGNORE INTO ProductHTML'.
            '(ProductExternalReference, Tag, HTML) '.
            'VALUES (?, ?, ?)'
        );
        $insertProductRelated = $db->prepare(
            'INSERT OR IGNORE INTO ProductRelated'.
            '(RelatedProductExternalReference, ProductExternalReference) '.
            'VALUES (?, ?)'
        );
        $insertAttribute = $db->prepare(
            'INSERT OR REPLACE INTO Attribute'.
            '(ID, Code, Label, Type, Input) '.
            'VALUES (?, ?, ?, ?, ?)'
        );
        $insertAttributeGroup = $db->prepare(
            'INSERT OR IGNORE INTO AttributeGroup'.
            '(ID, Name) '.
            'VALUES(?, ?)'
        );
        $insertAttributeGroupMap = $db->prepare(
            'INSERT OR IGNORE INTO AttributeGroupMap'.
            '(GroupID, AttributeID) '.
            'VALUES(?,?)'
        );
        $insertProductAttribute = $db->prepare(
            'INSERT OR IGNORE INTO ProductAttributeValue'.
            '(ProductExternalReference, AttributeID, Value) '.
            'VALUES (?, ?, ?)'
        );
        $insertProductAttributeDefault = $db->prepare(
            'INSERT OR IGNORE INTO ProductAttributeDefaultValue'.
            '(ProductExternalReference, AttributeID, Value) '.
            'VALUES (?, ?, ?)'
        );
        $insertProductQuestion = $db->prepare(
            'INSERT OR REPLACE INTO ProductQuestion'.
            '(ExternalReference, ProductExternalReference, Name, Type, Sequence) '.
            'VALUES (?, ?, ?, ?, ?)'
        );
        $insertProductAnswer = $db->prepare(
            'INSERT INTO ProductQuestionAnswer'.
            '(ProductQuestionExternalReference, Value, PriceModifier, SKUModifier, Sequence) '.
            'VALUES (?, ?, ?, ?, ?)'
        );

        $iterator = $this->iteratorFactory->create();

        $iterator->walk(
            $configurableProducts->getSelect(),
            [[$this, 'syncConfigurableProductData']],
            [
                'type' => 'configurable',
                'db' => $db,
                'preparedStatement' => $insertProduct,
                'preparedcheckproductStatement' => $checkProduct,
                'preparedskuStatement' => $insertSKU,
                'preparedskulinkStatement' => $insertSKULink,
                'preparedskumatrixStatement' => $insertSKUMatrix,
                'preparedcategoryproductStatement' => $insertCategoryProduct,
                'preparedimageStatement' => $insertImage,
                'preparedproducthtmlStatement' => $insertProductHTML,
                'preparedproductrelatedStatement' => $insertProductRelated,
                'preparedattributeStatement' => $insertAttribute,
                'preparedattributegroupStatement' => $insertAttributeGroup,
                'preparedattributegroupmapStatement' => $insertAttributeGroupMap,
                'preparedproductattributeStatement' => $insertProductAttribute,
                'preparedproductattributedefaultStatement' => $insertProductAttributeDefault,
                'preparedproductquestionStatement' => $insertProductQuestion,
                'preparedproductanswerStatement' => $insertProductAnswer,
                'store' => $store
            ]
        );

        $iterator->walk(
            $simpleProducts->getSelect(),
            [[$this, 'syncSimpleProductData']],
            [
                'type' => 'simple',
                'db' => $db,
                'preparedStatement' => $insertProduct,
                'preparedcheckproductStatement' => $checkProduct,
                'preparedcategoryproductStatement' => $insertCategoryProduct,
                'preparedimageStatement' => $insertImage,
                'preparedproducthtmlStatement' => $insertProductHTML,
                'preparedproductrelatedStatement' => $insertProductRelated,
                'preparedattributeStatement' => $insertAttribute,
                'preparedattributegroupStatement' => $insertAttributeGroup,
                'preparedattributegroupmapStatement' => $insertAttributeGroupMap,
                'preparedproductattributeStatement' => $insertProductAttribute,
                'preparedproductattributedefaultStatement' => $insertProductAttributeDefault,
                'preparedproductquestionStatement' => $insertProductQuestion,
                'preparedproductanswerStatement' => $insertProductAnswer,
                'store' => $store
            ]
        );

        $iterator->walk(
            $virtualProducts->getSelect(),
            [[$this, 'syncSimpleProductData']],
            [
                'type' => 'virtual',
                'db' => $db,
                'preparedStatement' => $insertProduct,
                'preparedcheckproductStatement' => $checkProduct,
                'preparedcategoryproductStatement' => $insertCategoryProduct,
                'preparedimageStatement' => $insertImage,
                'preparedproducthtmlStatement' => $insertProductHTML,
                'preparedproductrelatedStatement' => $insertProductRelated,
                'preparedattributeStatement' => $insertAttribute,
                'preparedattributegroupStatement' => $insertAttributeGroup,
                'preparedattributegroupmapStatement' => $insertAttributeGroupMap,
                'preparedproductattributeStatement' => $insertProductAttribute,
                'preparedproductattributedefaultStatement' => $insertProductAttributeDefault,
                'preparedproductquestionStatement' => $insertProductQuestion,
                'preparedproductanswerStatement' => $insertProductAnswer,
                'store' => $store
            ]
        );

        $iterator->walk(
            $groupedProducts->getSelect(),
            [[$this, 'syncGroupedProductData']],
            [
                'type' => 'grouped',
                'db' => $db,
                'preparedStatement' => $insertProduct,
                'preparedcheckproductStatement' => $checkProduct,
                'preparedskuStatement' => $insertSKU,
                'preparedskulinkStatement' => $insertSKULink,
                'preparedskumatrixStatement' => $insertSKUMatrix,
                'preparedcategoryproductStatement' => $insertCategoryProduct,
                'preparedimageStatement' => $insertImage,
                'preparedproducthtmlStatement' => $insertProductHTML,
                'preparedproductrelatedStatement' => $insertProductRelated,
                'preparedattributeStatement' => $insertAttribute,
                'preparedattributegroupStatement' => $insertAttributeGroup,
                'preparedattributegroupmapStatement' => $insertAttributeGroupMap,
                'preparedproductattributeStatement' => $insertProductAttribute,
                'preparedproductattributedefaultStatement' => $insertProductAttributeDefault,
                'preparedproductquestionStatement' => $insertProductQuestion,
                'preparedproductanswerStatement' => $insertProductAnswer,
                'store' => $store
            ]
        );

        $db->exec(
            'DELETE FROM ProductOptionValue'
        );

        $insertProductOptionValue = $db->prepare(
            'INSERT INTO ProductOptionValue '.
            '(ExternalReference, Sequence) '.
            'VALUES (?,?)'
        );

        $options = $this->eavAttributeCollectionFactory->create()
            ->setPositionOrder('asc', true)
            ->load();

        foreach ($options as $opt) {
            $sequence = $opt->getSortOrder();
            $optId = $opt->getId();
            $insertProductOptionValue->execute([$optId, $sequence]);
        }

        $db->exec('COMMIT TRANSACTION');
    }

    public function deleteProducts($syncDb, $ids, $storeId)
    {
        $storeId;

        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $db = $this->_getSyncDb($syncDb, 60);

        $db->exec('BEGIN EXCLUSIVE TRANSACTION');

        $db->exec('CREATE TABLE IF NOT EXISTS ProductDelete(ExternalReference text NOT NULL PRIMARY KEY)');

        $db->exec('CREATE TEMPORARY TABLE TmpDeleted(entity_id text NOT NULL PRIMARY KEY)');

        foreach ($ids as $id) {
            $db->exec('INSERT OR IGNORE INTO TmpDeleted (entity_id) VALUES ('.$id.')');
            $db->exec('INSERT OR IGNORE INTO ProductDelete VALUES('.$id.')');
        }

        $db->exec(
            'DELETE FROM Product WHERE ExternalReference IN (SELECT entity_id FROM TmpProduct);'.
            'DELETE FROM ProductImage WHERE ProductExternalReference IN (SELECT entity_id FROM TmpProduct);'.
            'DELETE FROM ProductHTML WHERE ProductExternalReference IN (SELECT entity_id FROM TmpProduct);'.
            'DELETE FROM ProductRelated WHERE ProductExternalReference IN (SELECT entity_id FROM TmpProduct);'.
            'DELETE FROM ProductAttributeValue WHERE ProductExternalReference IN (SELECT entity_id FROM TmpProduct);'.
            'DELETE FROM ProductQuestionAnswer WHERE ProductQuestionExternalReference IN '.
                '(SELECT ExternalReference FROM ProductQuestion WHERE ProductExternalReference IN '.
                    '(SELECT entity_id FROM TmpProduct));'.
            'DELETE FROM ProductQuestion WHERE ProductExternalReference IN (SELECT entity_id FROM TmpProduct);'.
            'DELETE FROM SKULink WHERE ProductExternalReference IN (SELECT entity_id FROM TmpProduct);'.
            'DELETE FROM SKUMatrix WHERE ProductExternalReference IN (SELECT entity_id FROM TmpProduct);'.
            'DELETE FROM SKU WHERE ProductExternalReference IN (SELECT entity_id FROM TmpProduct);'.
            'DELETE FROM CategoryProduct WHERE ProductExternalReference IN (SELECT entity_id FROM TmpProduct)'
        );

        $db->exec('DROP TABLE TmpDeleted');

        $db->exec('COMMIT TRANSACTION');
    }

    public function syncCategoryData($args) // @codingStandardsIgnoreLine Generic.Metrics.CyclomaticComplexity.TooHigh
    {
        $categoryData = $args['row'];

        if ($categoryData['level'] < 2) {
            return;
        }

        if ($categoryData['level'] == 2) {
            $categoryData['parent_id'] = 0;
        }

        $insertSQL = $args['preparedStatement'];
        $insertFields = ['entity_id', 'name', 'parent_id', 'updated_at', 'is_active', 'position'];

        $data = [];
        foreach ($insertFields as $key) {
            $value = $categoryData[$key];

            if (!$value) {
                switch ($key) {
                    case 'entity_id':
                        return;

                    case 'name':
                        $value = '';
                        break;

                    case 'parent_id':
                    case 'is_active':
                    case 'position':
                        $value = 0;
                        break;

                    case 'updated_at':
                        $value = '1970-01-01 00:00:00';
                        break;
                }
            }

            $data[] = $value;
        }

        $insertSQL->execute($data);
    }

    private function _syncConfigurableInvalidOptionState($type, $product)
    {
        $badoptiondata = false;

        if ($type == 'configurable') {
            $attributes = null;
            try {
                $configurableData = $this->configurableTypeFactory->create();
                $attributes = $configurableData->getConfigurableAttributes($product);
            } catch (\Exception $e) {
                $badoptiondata = true;
            }

            if ($attributes) {
                foreach ($attributes as $attribute) {
                    $prodAttr = $attribute->getProductAttribute();
                    if (!is_object($prodAttr) || !$prodAttr->getAttributeCode()) {
                        $badoptiondata = true;
                    }
                }
            }
        }

        return $badoptiondata;
    }

    private function _syncProductPrice($store, $parentProduct, $options = null)
    {
        $addInfo = new \Magento\Framework\DataObject(); // @codingStandardsIgnoreLine

        if (is_array($options)) {
            $addInfo->setData(
                [
                    'product' => $parentProduct->getId(),
                    'qty' => 1,
                    'super_attribute' => $options
                ]
            );
        } else {
            $addInfo->setQty(1);
        }

        $parentProduct->unsetData('final_price');

        $parentProduct->getTypeInstance(true)->processConfiguration(
            $addInfo,
            $parentProduct,
            \Magento\Catalog\Model\Product\Type\AbstractType::PROCESS_MODE_LITE
        );

        try {
            $finalPrice = $parentProduct->getFinalPrice();
        } catch (\Exception $e) {
            $finalPrice = 0.0;
        }

        $rate = 0;

        if ((int)$this->taxIncluded === 1) {
            $taxAttribute = $parentProduct->getCustomAttribute('tax_class_id');

            if ($taxAttribute) {
                $productRateId = $taxAttribute->getValue();
                $rate = $this->taxCalc->getCalculatedRate($productRateId);
            }

            $price = $finalPrice / (1 + ($rate / 100));
        } else {
            $price = $finalPrice;
        }

        return $price;
    }

    private function _syncProductListPrice($store, $product, $price)
    {

        $rate = 0;

        if ((int)$this->taxIncluded === 1) {
            $taxAttribute = $product->getCustomAttribute('tax_class_id');

            if ($taxAttribute) {
                $productRateId = $taxAttribute->getValue();
                $rate = $this->taxCalc->getCalculatedRate($productRateId);
            }

            $listPrice = $product->getPrice() / (1 + ($rate / 100));
        } else {
            $listPrice = $product->getPrice();
        }

        if (!is_numeric($listPrice)) {
            $listPrice = $price;
        }

        return $listPrice;
    }

    private function _syncStockData($product, $productId, $stockId)
    {
        $stockItem = $this->stockItemFactory->create();
        $stockItem->setStockId($stockId)
                    ->setProduct($product);

        $stockItem->getResource()->loadByProductId($stockItem, $productId, $stockItem->getStockId());

        $qty = $stockItem->getQty();
        if (!is_numeric($qty)) {
            $qty = 0;
        }

        $manageStock = $stockItem->getManageStock() ? -1 : 0;

        return ['qty' => (int)$qty, 'managestock' => $manageStock];
    }

    private function _syncProductName($productData)
    {
        $productName = $productData['name'];
        if (!$productName) {
            $productName = '';
        }

        return html_entity_decode($productName, ENT_QUOTES | ENT_HTML5, 'UTF-8'); // @codingStandardsIgnoreLine
    }

    private function _syncProductCode($productData)
    {
        $productCode = $productData['sku'];
        if (!$productCode) {
            $productCode = '';
        }

        return html_entity_decode($productCode, ENT_QUOTES | ENT_HTML5, 'UTF-8'); // @codingStandardsIgnoreLine
    }

    private function _syncProductParentIds($productId, &$parentIds)
    {
        if (!is_array($parentIds)) {
            $configurableparentids = $this->configurableTypeFactory->create()->getParentIdsByChild($productId);
            $groupedparentids = $this->groupedTypeFactory->create()->getParentIdsByChild($productId);
            $bundleparentids = $this->bundleTypeFactory->create()->getParentIdsByChild($productId);

            $parentIds = array_unique(array_merge($configurableparentids, $groupedparentids, $bundleparentids));
        }

        return $parentIds;
    }

    private function _syncProductDescription($store, $storeId, $product, $productId, $type, $productData, &$parentids)
    {
        $store;

        // work around for description not appearing via collection
        if (!isset($productData['description'])) {
            $description = $product->getResource()->getAttributeRawValue(
                $productId,
                'description',
                $storeId
            );
        } else {
            $description = $productData['description'];
        }

        if(is_array($description)) {
            $description = implode('', $description);
        }

        $description = $this->codistoHelper->processCmsContent($description, $storeId);
        if (($type == 'simple' || $type == 'virtual')
            && $description == '') {
            $this->_syncProductParentIds($productId, $parentids);

            foreach ($parentids as $parentid) {
                $description = $product->getResource()->getAttributeRawValue($parentid, 'description', $storeId);
                if ($description) {
                    $description = $this->codistoHelper->processCmsContent($description, $storeId);
                    break;
                }
            }

            if (!$description) {
                $description = '';
            }
        }

        return $description;
    }

    private function _syncProductShortDescription($storeId, $productData)
    {
        $shortDescription = '';

        if (isset($productData['short_description']) && $productData['short_description'] != '') {

            $shortDescription = $productData['short_description'];
            if(is_array($shortDescription)) {
                $shortDescription = implode('', $shortDescription);
            }

            $shortDescription =
                $this->codistoHelper->processCmsContent($shortDescription, $storeId);
        }

        return $shortDescription;
    }

    private function _syncSKUAttributes($storeId, $product, $productId, $attributes)
    {
        $attributeCodes = [];
        $attributeValues = [];
        $productAttributes = [];
        $productOptions = [];

        foreach ($attributes as $attribute) {
            $prodAttr = $attribute->getProductAttribute();
            if ($prodAttr) {
                $attributeCodes[] = $prodAttr->getAttributeCode();
                $productAttributes[] = $prodAttr;
            }
        }

        if (!empty($attributeCodes)) {
            $attributeValues = $product->getResource()->getAttributeRawValue(
                $productId,
                $attributeCodes,
                $storeId
            );
            if (!is_array($attributeValues)) {
                $attributeValues = [$attributeCodes[0] => $attributeValues];
            }

            $options = [];
            foreach ($productAttributes as $attribute) {
                try {
                    $options[$attribute->getId()] = $attributeValues[$attribute->getAttributeCode()];
                } catch (\Exception $e) {
                }
            }
        }

        return [
            'attributeValues' => $attributeValues,
            'productOptions' => $productOptions
        ];
    }

    public function syncSKUData($args)
    {
        $skuData = $args['row'];
        $skuEntityId = $skuData['entity_id'];
        $db = $args['db'];

        $store = $args['store'];
        $storeId = $store->getId();

        $insertSKULinkSQL = $args['preparedskulinkStatement'];
        $insertSKUMatrixSQL = $args['preparedskumatrixStatement'];

        $attributes = $args['attributes'];

        $productParent = $args['parent_product'];
        $productParentId = $args['parent_id'];

        $product = $this->productFactory->create();
        $product->setData($skuData)
            ->setStore($store)
            ->setStoreId($storeId)
            ->setWebsiteId($store->getWebsiteId())
            ->setCustomerGroupId($this->ebayGroupId);

        $attributeData = $this->_syncSKUAttributes($storeId, $product, $skuEntityId, $attributes);

        $productOptions = $attributeData['productOptions'];

        if (!empty($productOptions)) {
            $price = $this->_syncProductPrice($store, $productParent, $productOptions);
            if (!$price) {
                $price = $this->_syncProductPrice($store, $product);
            }
        } else {
            $price = $this->_syncProductPrice($store, $product);
        }

        $insertSKULinkSQL->execute([$skuEntityId, $productParentId, $price]);

        $attributeValues = $attributeData['attributeValues'];

        // SKU Matrix
        foreach ($attributes as $attribute) {
            $productAttribute = $attribute->getProductAttribute();

            if ($productAttribute) {
                $productAttribute->setStoreId($storeId);
                $productAttribute->setStore($store);

                $productOptionId = $productAttribute->getId();
                $productOptionValueId = isset($attributeValues[$productAttribute->getAttributeCode()]) ?
                                            $attributeValues[$productAttribute->getAttributeCode()] : null;

                if ($productOptionValueId != null) {
                    $attributeName = $attribute->getLabel();
                    $attributeName = $attributeName ? $attributeName : '';
                    $attributeName = html_entity_decode($attributeName, ENT_QUOTES | ENT_HTML5, 'UTF-8'); // @codingStandardsIgnoreLine
                    $attributeValue = $productAttribute->getSource()->getOptionText($productOptionValueId);

                    $insertSKUMatrixSQL->execute(
                        [
                            $skuEntityId,
                            $productParentId,
                            '',
                            $attributeName,
                            $attributeValue,
                            $productOptionId,
                            $productOptionValueId
                        ]
                    );
                }
            }
        }
    }

    public function syncConfigurableProductData($args)
    {
        $productData = $args['row'];

        $store = $args['store'];
        $db = $args['db'];

        $insertSQL = $args['preparedskuStatement'];
        $insertSKULinkSQL = $args['preparedskulinkStatement'];
        $insertCategorySQL = $args['preparedcategoryproductStatement'];
        $insertSKUMatrixSQL = $args['preparedskumatrixStatement'];

        $this->syncSimpleProductData(array_merge($args, ['row' => $productData]));

        $product = $this->productFactory->create()
            ->setData($productData)
            ->setStore($store)
            ->setStoreId($store->getId())
            ->setWebsiteId($store->getWebsiteId())
            ->setCustomerGroupId($this->ebayGroupId)
            ->setIsSuperMode(true);

        $configurableData = $this->configurableTypeFactory->create();

        $configurableAttributes = $configurableData->getConfigurableAttributes($product);

        $childProducts = $configurableData->getUsedProductCollection($product)
            ->addAttributeToSelect(
                ['price', 'special_price', 'special_from_date', 'special_to_date', 'tax_class_id'],
                'left'
            );

        $childProductsSelect = $childProducts->getSelect();
        $childProductsSelect->where('link_table.parent_id=?', $productData['entity_id']);

        $iterator = $this->iteratorFactory->create();

        $iterator->walk(
            $childProductsSelect,
            [[$this, 'syncSKUData']],
            [
                'parent_id' => $productData['entity_id'],
                'parent_product' => $product,
                'attributes' => $configurableAttributes,
                'db' => $db,
                'preparedStatement' => $insertSQL,
                'preparedskulinkStatement' => $insertSKULinkSQL,
                'preparedskumatrixStatement' => $insertSKUMatrixSQL,
                'preparedcategoryproductStatement' => $insertCategorySQL,
                'store' => $store
            ]
        );

        $this->productsProcessed[] = $productData['entity_id'];

        if ($productData['entity_id'] > $this->currentEntityId) {
            $this->currentEntityId = $productData['entity_id'];
        }
    }

    public function syncGroupedProductData($args)
    {
        $productData = $args['row'];

        $store = $args['store'];
        $db = $args['db'];

        $insertSQL = $args['preparedskuStatement'];
        $insertSKULinkSQL = $args['preparedskulinkStatement'];
        $insertSKUMatrixSQL = $args['preparedskumatrixStatement'];

        $product = $this->productFactory->create()
            ->setData($productData)
            ->setStore($store)
            ->setStoreId($store->getId())
            ->setWebsiteId($store->getWebsiteId())
            ->setCustomerGroupId($this->ebayGroupId)
            ->setIsSuperMode(true);

        $groupedData = $this->groupedTypeFactory->create();

        $childProducts = $groupedData->getAssociatedProductCollection($product);
        $childProducts->addAttributeToSelect(
            [
                'sku',
                'name',
                'price',
                'special_price',
                'special_from_date',
                'special_to_date'
            ]
        );

        $skulinkArgs = [];
        $skumatrixArgs = [];

        $minPrice = 0;

        $optionValues = [];

        foreach ($childProducts as $childProduct) {
            $childProduct
                ->setStore($store)
                ->setStoreId($store->getId())
                ->setWebsiteId($store->getWebsiteId())
                ->setCustomerGroupId($this->ebayGroupId)
                ->setIsSuperMode(true);

            $price = $this->_syncProductPrice($store, $childProduct);

            if ($minPrice == 0) {
                $minPrice = $price;
            } else {
                $minPrice = min($minPrice, $price);
            }

            $skulinkArgs[] = [$childProduct->getId(), $productData['entity_id'], $price];
            $skumatrixArgs[] = [
                $childProduct->getId(),
                $productData['entity_id'],
                '',
                'Option',
                $childProduct->getName(),
                0,
                0
            ];

            if (isset($optionValues[$childProduct->getName()])) {
                $optionValues[$childProduct->getName()]++;
            } else {
                $optionValues[$childProduct->getName()] = 1;
            }
        }

        foreach ($optionValues as $key => $count) {
            if ($count > 1) {
                $i = 0;

                foreach ($childProducts as $childProduct) {
                    if ($childProduct->getName() == $key) {
                        $skumatrixArg = &$skumatrixArgs[$i];
                        $skumatrixArg[4] = $childProduct->getSku().' - '.$childProduct->getName();
                    }

                    $i++;
                }
            }
        }

        $productData['price'] = $minPrice;
        $productData['final_price'] = $minPrice;
        $productData['minimal_price'] = $minPrice;
        $productData['min_price'] = $minPrice;
        $productData['max_price'] = $minPrice;

        $this->syncSimpleProductData(array_merge($args, ['row' => $productData]));

        $skulinkArgCount = count($skulinkArgs);

        for ($i = 0; $i < $skulinkArgCount; $i++) {
            $insertSKULinkSQL->execute($skulinkArgs[$i]);
            $insertSKUMatrixSQL->execute($skumatrixArgs[$i]);
        }

        $this->productsProcessed[] = $productData['entity_id'];

        if ($productData['entity_id'] > $this->currentEntityId) {
            $this->currentEntityId = $productData['entity_id'];
        }
    }

    private function _syncProductImage($image)
    {
        if (!$this->mediaConfig) {
            $this->mediaConfig = $this->mediaConfigFactory->create();
        }

        $mediaConfig = $this->mediaConfig;

        if (preg_match('/^https?:\/\//', $image['file'])) {
            $imgUrl = $image['file'];
        } else {
            $imgUrl = $mediaConfig->getMediaUrl($image['file']);
        }

        $enabled = ($image['disabled'] == 0 ? -1 : 0);
        $tag = $image['label'];
        if (!$tag) {
            $tag = '';
        }

        $sequence = $image['position'];
        if (!$sequence) {
            $sequence = 1;
        } else {
            $sequence++;
        }

        return [
            'url' => $imgUrl,
            'enabled' => $enabled,
            'tag' => $tag,
            'sequence' => $sequence
        ];
    }

    private function _syncProductImages($storeId, $product, $productId, $type, $productData, $args, &$parentids)
    {
        $insertImageSQL = $args['preparedimageStatement'];

        $hasImage = false;

        $product->load('media_gallery');
        $primaryImage = isset($productData['image']) ? $productData['image'] : '';
        $galleryImages = $product->getMediaGalleryImages();

        if ($primaryImage && $galleryImages->getSize() == 0) {
            $imageData = $this->_syncProductImage([
                'file' => $primaryImage,
                'disabled' => 0,
                'label' => '',
                'position' => 0
            ]);

            $insertImageSQL->execute(
                [
                    $productId,
                    $imageData['url'],
                    $imageData['tag'],
                    $imageData['sequence'],
                    -1
                ]
            );

            $hasImage = true;
        } else {
            $imagesVisited = [];

            foreach ($galleryImages as $image) {
                $imagesVisited[$image['file']] = true;

                $imageData = $this->_syncProductImage($image);

                if ($image['file'] == $primaryImage) {
                    $imageData['tag'] = '';
                    $imageData['sequence'] = 0;
                }

                $insertImageSQL->execute(
                    [
                        $productId,
                        $imageData['url'],
                        $imageData['tag'],
                        $imageData['sequence'],
                        -1
                    ]
                );

                $hasImage = true;
            }

            $product->load('media_gallery');

            foreach ($product->getMediaGallery('images') as $image) {
                if (isset($image['disabled']) && $image['disabled'] == 0) {
                    continue;
                }

                if (isset($imagesVisited[$image['file']])) {
                    continue;
                }

                $imageData = $this->_syncProductImage($image);

                if ($image['file'] == $primaryImage) {
                    $imageData['tag'] = '';
                    $imageData['sequence'] = 0;
                }

                $insertImageSQL->execute(
                    [
                        $productId,
                        $imageData['url'],
                        $imageData['tag'],
                        $imageData['sequence'],
                        0
                    ]
                );

                $hasImage = true;
            }
        }

        if (($type == 'simple' || $type == 'virtual')
            && !$hasImage) {
            $this->_syncProductParentIds($productId, $parentids);

            $baseSequence = 0;

            foreach ($parentids as $parentid) {
                $baseImagePath = $product->getResource()->getAttributeRawValue($parentid, 'image', $storeId);

                $parentProduct = $this->productFactory->create()
                                    ->setData(['entity_id' => $parentid, 'type_id' => 'simple']);

                $parentProduct->load('media_gallery'); // @codingStandardsIgnoreLine

                $mediaGallery = $parentProduct->getMediaGallery('images');

                $maxSequence = 0;
                $baseImageFound = false;

                if ($mediaGallery) {
                    foreach ($mediaGallery as $image) {
                        $imageData = $this->_syncProductImage($image);
                        if (!$baseImageFound &&
                            ($image['file'] == $baseImagePath)) {
                            $imageData['tag'] = '';
                            $imageData['sequence'] = 0;
                            $baseImageFound = true;
                        } else {
                            $imageData['sequence'] += $baseSequence;
                            $maxSequence = max($imageData['sequence'], $maxSequence);
                        }

                        $insertImageSQL->execute(
                            [
                                $productId,
                                $imageData['url'],
                                $imageData['tag'],
                                $imageData['sequence'],
                                $imageData['enabled']
                            ]
                        );
                    }
                }

                $baseSequence = $maxSequence;

                if ($baseImageFound) {
                    break;
                }
            }
        }
    }

    private function _syncProductRelatedProducts($product, $productId, $args)
    {
        $insertRelatedSQL = $args['preparedproductrelatedStatement'];

        $relatedProductIds = $product->getRelatedProductIds();
        foreach ($relatedProductIds as $relatedProductId) {
            $insertRelatedSQL->execute([$relatedProductId, $productId]);
        }
    }

    private function _syncProductQuestionOption($option)
    {
        $id = $option->getOptionId();
        $name = $option->getTitle();
        $type = $option->getType();
        $sort = $option->getSortOrder();

        if ($id && $name) {
            if (!$type) {
                $type = '';
            }

            if (!$sort) {
                $sort = 0;
            }

            return [
                'id' => $id,
                'name' => $name,
                'type' => $type,
                'sort' => $sort
            ];
        }

        return null;
    }

    private function _syncProductQuestionValue($value)
    {
        $name = $value->getTitle();
        if (!$name) {
            $name = '';
        }

        $priceMod = '';
        if ($value->getPriceType() == 'fixed') {
            $priceMod = 'Price + '.$value->getPrice();
        }

        if ($value->getPriceType() == 'percent') {
            $priceMod = 'Price * '.($value->getPrice() / 100.0);
        }

        $skuMod = $value->getSku();
        if (!$skuMod) {
            $skuMod = '';
        }

        $sort = $value->getSortOrder();
        if (!$sort) {
            $sort = 0;
        }

        return [
            'name' => $name,
            'pricemod' => $priceMod,
            'skumod' => $skuMod,
            'sort' => $sort
        ];
    }

    private function _syncProductQuestions($product, $productId, $args)
    {
        $insertProductQuestionSQL = $args['preparedproductquestionStatement'];
        $insertProductAnswerSQL = $args['preparedproductanswerStatement'];

        $options = $product->getProductOptionsCollection();

        foreach ($options as $option) {
            $optionData = $this->_syncProductQuestionOption($option);
            if ($optionData) {
                $insertProductQuestionSQL->execute(
                    [
                        $optionData['id'],
                        $productId,
                        $optionData['name'],
                        $optionData['type'],
                        $optionData['sort']
                    ]
                );

                $values = $option->getValuesCollection();

                foreach ($values as $value) {
                    $valueData = $this->_syncProductQuestionValue($value);

                    $insertProductAnswerSQL->execute(
                        [
                            $optionData['id'],
                            $valueData['name'],
                            $valueData['pricemod'],
                            $valueData['skumod'],
                            $valueData['sort']
                        ]
                    );
                }
            }
        }
    }

    private function _syncProductAttributeSet($product, $attributeSetId)
    {
        if (isset($this->attributeCache[$attributeSetId])) {
            $attributes = $this->attributeCache[$attributeSetId];
        } else {
            $attributes = $product->getAttributes();

            $this->attributeCache[$attributeSetId] = $attributes;
        }

        return $attributes;
    }

    private function _syncProductAttributeLabel($connection, $storeId, $attribute)
    {
        // @codingStandardsIgnoreStart
        if (!isset($this->attributeLabelCache[$storeId])) {
            $bind = [':store_id' => $storeId];
            $select = $connection->select()->from(
               $this->resourceConnection->getTableName('eav_attribute_label'),
               ['attribute_id', 'value']
            )->where(
               'store_id = :store_id'
            );

            $this->attributeLabelCache[$storeId] = $connection->fetchPairs($select, $bind);
        }

        if (!isset($this->attributeLabelCache[\Magento\Store\Model\Store::DEFAULT_STORE_ID])) {
            $bind = [':store_id' => \Magento\Store\Model\Store::DEFAULT_STORE_ID];
            $select = $connection->select()->from(
               $this->resourceConnection->getTableName('eav_attribute_label'),
               ['attribute_id', 'value']
            )->where(
               'store_id = :store_id'
            );

            $this->attributeLabelCache[\Magento\Store\Model\Store::DEFAULT_STORE_ID] = $connection->fetchPairs($select, $bind);
        }
        // @codingStandardsIgnoreEnd

        $attributeId = $attribute->getId();

        if (isset($this->attributeLabelCache[$storeId][$attributeId]) &&
            $this->attributeLabelCache[$storeId][$attributeId]) {
            $attributeLabel = $this->attributeLabelCache[$storeId][$attributeId];
        } elseif (isset($this->attributeLabelCache[\Magento\Store\Model\Store::DEFAULT_STORE_ID][$attributeId]) &&
            $this->attributeLabelCache[\Magento\Store\Model\Store::DEFAULT_STORE_ID][$attributeId]) {
            $attributeLabel = $this->attributeLabelCache[\Magento\Store\Model\Store::DEFAULT_STORE_ID][$attributeId];
        } else {
            $attributeLabel = $attribute->getFrontendLabel();
            if (!isset($attributeLabel) || $attributeLabel === null || !$attributeLabel) {
                $attributeLabel = $attribute->getName();
                if (!isset($attributeLabel) || $attributeLabel === null || !$attributeLabel) {
                    $attributeLabel = '';
                }
            }
        }

        return $attributeLabel;
    }

    private function _syncProductAttributeGroupId($attribute, $attributeSetId)
    {
        $attributeSetInfo = $attribute->getAttributeSetInfo();

        $attributeGroupId = is_array($attributeSetInfo)
                                && array_key_exists($attributeSetId, $attributeSetInfo)
                                && is_array($attributeSetInfo[$attributeSetId])
                                && array_key_exists('group_id', $attributeSetInfo[$attributeSetId]) ?
                                $attributeSetInfo[$attributeSetId]['group_id'] : null;

        return $attributeGroupId;
    }

    private function _syncProductAttributeGroupName($attributeGroupId)
    {
        $attributeGroupName = '';
        if ($attributeGroupId) {
            if (isset($this->groupCache[$attributeGroupId])) {
                $attributeGroupName = $this->groupCache[$attributeGroupId];
            } else {
                $attributeGroup = $this->productAttributeGroupFactory->create();
                $attributeGroup->load($attributeGroupId);

                $attributeGroupName = $attributeGroup->getAttributeGroupName();
                $attributeGroupName = $attributeGroupName ? $attributeGroupName : '';
                $attributeGroupName = html_entity_decode($attributeGroupName, ENT_QUOTES | ENT_HTML5, 'UTF-8'); // @codingStandardsIgnoreLine

                $this->groupCache[$attributeGroupId] = $attributeGroupName;
            }
        }

        return $attributeGroupName;
    }

    private function _syncProductAttributeFrontendType($attribute)
    {
        $attributeFrontend = $attribute->getFrontend();

        $type = $attributeFrontend->getInputType();

        if (!isset($type) || $type === null) {
            $type = '';
        }

        return $type;
    }

    private function _syncProductAttributeSource($storeId, $attribute, $attributeId, $sourceModel)
    {
        $source = null;
        if ($sourceModel) {
            if (isset($this->optionCache[$storeId.'-'.$attributeId])) {
                $source = $this->optionCache[$storeId.'-'.$attributeId];
            } else {
                try {
                    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

                    $source = $objectManager->create($sourceModel); // @codingStandardsIgnoreLine

                    if ($source) {
                        $source->setAttribute($attribute);

                        $this->optionCache[$storeId.'-'.$attributeId] = $source;
                    }
                } catch (\Exception $e) {
                    $e;
                    // if we can't retrieve an attribute source model - skip it in sync
                }
            }
        } else {
            $source = $attribute->getSource();
        }

        return $source;
    }

    private function _syncProductAttributeTableSelects($storeId, $adapter, $attributeTypes)
    {
        $attrTypeSelects = [];

        $useEntityId = true;
        $columns = null;

        // @codingStandardsIgnoreStart
        foreach ($attributeTypes as $table => $_attributes) {

            if(!$columns) {
                $columns = $this->resourceConnection->getConnection()->describeTable($table);
                $useEntityId = array_key_exists('entity_id', $columns);
            }

            if ($useEntityId) {
                $attrTypeSelect = $adapter->select()
                        ->from(['default_value' => $table], ['attribute_id'])
                        ->where('default_value.attribute_id IN (?)', array_keys($_attributes))
                        ->where('default_value.entity_id = :entity_id')
                        ->where('default_value.store_id = 0');
                $entitySql = 'AND store_value.entity_id = default_value.entity_id ';
            } else {
                $attrTypeSelect = $adapter->select()
                        ->from(['default_value' => $table], ['attribute_id'])
                        ->where('default_value.attribute_id IN (?)', array_keys($_attributes))
                        ->where('default_value.row_id = (SELECT row_id FROM catalog_product_entity WHERE entity_id = :entity_id)')
                        ->where('default_value.store_id = 0');
                $entitySql = 'AND store_value.row_id = default_value.row_id ';
            }

            if ($storeId == \Magento\Store\Model\Store::DEFAULT_STORE_ID) {
                $attrTypeSelect->columns(['attr_value' => new \Zend_Db_Expr('CAST(value AS CHAR)')], 'default_value');
                $attrTypeSelect->columns(['default_value' => 'value'], 'default_value');
                $attrTypeSelect->where('default_value.value IS NOT NULL');
            } else {
                $attrTypeSelect->columns(['default_value' => 'value'], 'default_value');
                $attrTypeSelect->joinLeft(
                    ['store_value' => $table],
                    'store_value.attribute_id = default_value.attribute_id '.
                    'AND store_value.attribute_id IN '.
                        '(SELECT attribute_id FROM `'.
                            $this->resourceConnection->getTableName('catalog_eav_attribute').'` WHERE is_global != 0) '.
                    $entitySql .
                    'AND store_value.store_id = :store_id ',
                    ['attr_value' =>
                        new \Zend_Db_Expr('CAST(COALESCE(store_value.value, default_value.value) AS CHAR)')]
                );
                $attrTypeSelect->where('store_value.value IS NOT NULL OR default_value.value IS NOT NULL');
            }

            $attrTypeSelects[] = $attrTypeSelect;
        }

        return $attrTypeSelects;
    }

    private function _syncProductAttributeValues($storeId, $productId, $adapter, $attrTypeSelects, $attributeCodeIDMap)
    {
        $attributeValues = [];

        if (!empty($attrTypeSelects)) {
            // @codingStandardsIgnoreStart
            $attrSelect = $adapter->select()->union($attrTypeSelects, \Zend_Db_Select::SQL_UNION_ALL);

            $attrArgs = [
                'entity_id' => $productId,
                'store_id' => $storeId
            ];

            try {
                foreach ($adapter->fetchAll($attrSelect, $attrArgs, \Zend_Db::FETCH_NUM) as $attributeRow) {
                    $attributeId = $attributeRow[0];
                    $attributeCode = $attributeCodeIDMap[$attributeId];
                    $attributeValues[$attributeCode] = $attributeRow;
                }
            } catch (\Exception $e) {
                $e;
                //skip attribute data if the execution fails
            }
            // @codingStandardsIgnoreEnd
        }

        return $attributeValues;
    }

    private function _syncProductAttributeOptionArray($source, $storeId, $attributeData, $attributeValue)
    {
        $attributeValueSet = [];

        $sourceAttribute = $source->getAttribute();

        $currentStoreId = $sourceAttribute->getStoreId();
        if ($currentStoreId != $storeId) {
            $sourceAttribute->setStoreId($storeId);
        }

        foreach ($attributeValue as $attributeOptionId) {
            if (isset($this->optionTextCache[$storeId.'-'.$attributeData['id'].'-'.$attributeOptionId])) {
                $attributeValueSet[] =
                    $this->optionTextCache[$storeId.'-'.$attributeData['id'].'-'.$attributeOptionId];
            } else {
                try {
                    $attributeText = $source->getOptionText($attributeOptionId);
                    $attributeText = $attributeText ? $attributeText : '';
                    $attributeText = html_entity_decode($attributeText, ENT_QUOTES | ENT_HTML5, 'UTF-8'); // @codingStandardsIgnoreLine

                    $this->optionTextCache[$storeId.'-'.$attributeData['id'].'-'.$attributeOptionId] =
                        $attributeText;

                    $attributeValueSet[] = $attributeText;
                } catch (\Exception $e) {
                    $e;
                    // ignore errors retrieving attribute option value
                    $attributeValueSet[] = '';
                }
            }
        }

        if ($currentStoreId != $storeId) {
            $sourceAttribute->setStoreId($currentStoreId);
        }

        return $attributeValueSet;
    }

    private function _syncProductAttributeOptionScalar($source, $storeId, $attributeData, $attributeValue)
    {
        if (isset($this->optionTextCache[$storeId.'-'.$attributeData['id'].'-'.$attributeValue])) {
            $attributeValue = $this->optionTextCache[$storeId.'-'.$attributeData['id'].'-'.$attributeValue];
        } else {
            try {
                $currentStoreId = $source->getAttribute()->getStoreId();

                if ($currentStoreId != $storeId) {
                    $source->getAttribute()->setStoreId($storeId);
                    $attributeText = $source->getOptionText($attributeValue);
                    $source->getAttribute()->setStoreId($currentStoreId);
                } else {
                    $attributeText = $source->getOptionText($attributeValue);
                }

                $attributeText = $attributeText ? $attributeText : '';
                $attributeText = html_entity_decode($attributeText, ENT_QUOTES | ENT_HTML5, 'UTF-8'); // @codingStandardsIgnoreLine

                $this->optionTextCache[$storeId.'-'.$attributeData['id'].'-'.$attributeValue] =
                    $attributeText;

                $attributeValue = $attributeText;
            } catch (\Exception $e) {
                $attributeValue = null;
            }
        }

        return $attributeValue;
    }

    private function _syncProductAttributeOptionText($source, $storeId, $attributeData, $attributeValue)
    {
        if (isset($source) && is_object($source) &&
            method_exists($source, 'getOptionText')) {
            if (is_array($attributeValue)) {
                $attributeValue = $this->_syncProductAttributeOptionArray(
                    $source,
                    $storeId,
                    $attributeData,
                    $attributeValue
                );
            } else {
                $attributeValue = $this->_syncProductAttributeOptionScalar(
                    $source,
                    $storeId,
                    $attributeData,
                    $attributeValue
                );
            }
        }

        return $attributeValue;
    }

    private function _syncProductAttributeValueData($storeId, $attributeData, $attributeValues)
    {
        if (isset($attributeValues[$attributeData['code']])) {
            $attributeRow = $attributeValues[$attributeData['code']];

            $defaultValue = $attributeRow[1];
            $attributeValue = $attributeRow[2];
        } else {
            $defaultValue = null;
            $attributeValue = null;
        }

        if (isset($attributeData['source']) &&
            $attributeData['source_model'] == 'eav/entity_attribute_source_boolean') {
            $attributeData['backend_type'] = 'boolean';

            $defaultValue = isset($defaultValue) && $defaultValue ? -1 : 0;
            $attributeValue = isset($attributeValue) && $attributeValue ? -1 : 0;
        } elseif ($attributeData['html']) {
            if ($defaultValue == $attributeValue) {

                if(is_array($attributeValue)) {
                    $attributeValue = implode('', $attributeValue);
                }

                $defaultValue =
                    $attributeValue =
                        $this->codistoHelper->processCmsContent($attributeValue, $storeId);
            } else {

                if(is_array($defaultValue)) {
                    $defaultValue = implode('', $defaultValue);
                }
                $defaultValue = $this->codistoHelper->processCmsContent($defaultValue, $storeId);

                if(is_array($attributeValue)) {
                    $attributeValue = implode('', $attributeValue);
                }
                $attributeValue = $this->codistoHelper->processCmsContent($attributeValue, $storeId);
            }
        } elseif (in_array($attributeData['frontend_type'], ['select', 'multiselect'])) {
            $defaultValue = $this->_syncProductAttributeOptionText(
                $attributeData['source'],
                \Magento\Store\Model\Store::DEFAULT_STORE_ID,
                $attributeData,
                $attributeValue
            );
            $attributeValue = $this->_syncProductAttributeOptionText(
                $attributeData['source'],
                $storeId,
                $attributeData,
                $attributeValue
            );
        }

        if (is_array($attributeValue)) {
            $attributeValue = implode(',', $attributeValue);
        }

        if (is_array($defaultValue)) {
            $defaultValue = implode(',', $defaultValue);
        }

        return [
            'value' => $attributeValue,
            'defaultvalue' => $defaultValue
        ];
    }

    private function _syncProductAttributes(
        $store,
        $storeId,
        $product,
        $productId,
        $args
    ) {
        $connection = $this->resourceConnection->getConnection();

        $insertHTMLSQL = $args['preparedproducthtmlStatement'];
        $insertAttributeSQL = $args['preparedattributeStatement'];
        $insertAttributeGroupSQL = $args['preparedattributegroupStatement'];
        $insertAttributeGroupMapSQL = $args['preparedattributegroupmapStatement'];
        $insertProductAttributeSQL = $args['preparedproductattributeStatement'];
        $insertProductAttributeDefaultSQL = $args['preparedproductattributedefaultStatement'];

        $attributeSet = [];
        $attributeCodes = [];
        $attributeTypes = [];
        $attributeCodeIDMap = [];

        $attributeSetId = $product->getAttributeSetId();
        $attributes = $this->_syncProductAttributeSet($product, $attributeSetId);

        foreach ($attributes as $attribute) {
            $attribute->setStoreId($storeId);
            $attribute->setStore($store);

            $backend = $attribute->getBackEnd();
            if (!$backend->isStatic()) {
                $attributeId = $attribute->getId();
                $attributeCode = $attribute->getAttributeCode();
                $attributeName = $attribute->getName();
                $attributeName = $attributeName ? $attributeName : '';
                $attributeName = html_entity_decode($attributeName, ENT_QUOTES | ENT_HTML5, 'UTF-8'); // @codingStandardsIgnoreLine
                $attributeTable = $backend->getTable();
                $attributeLabel = $this->_syncProductAttributeLabel($connection, $storeId, $attribute);
                $attributeBackendType = $attribute->getBackendType();
                $attributeFrontendType = $this->_syncProductAttributeFrontendType($attribute);

                $attributeGroupId = $this->_syncProductAttributeGroupId($attribute, $attributeSetId);
                $attributeGroupName = $this->_syncProductAttributeGroupName($attributeGroupId);

                $attributeIsHtml = ($attribute->getIsHtmlAllowedOnFront()
                    && $attribute->getIsWysiwygEnabled()) ? true : false;

                $attributeSourceModel = $attribute->getSourceModel();

                $attributeData = [
                        'id' => $attributeId,
                        'code' => $attributeCode,
                        'name' => $attributeName,
                        'label' => $attributeLabel,
                        'backend_type' => $attributeBackendType,
                        'frontend_type' => $attributeFrontendType,
                        'groupid' => $attributeGroupId,
                        'groupname' => $attributeGroupName,
                        'html' => $attributeIsHtml,
                        'source_model' => $attributeSourceModel
                ];

                $attributeData['source'] = $this->_syncProductAttributeSource(
                    $storeId,
                    $attribute,
                    $attributeId,
                    $attributeSourceModel
                );

                $attributeCodeIDMap[$attributeId] = $attributeCode;
                $attributeTypes[$attributeTable][$attributeId] = $attributeCode;

                $attributeSet[] = $attributeData;
                $attributeCodes[] = $attributeCode;
            }
        }

        $attrTypeSelects = $this->_syncProductAttributeTableSelects(
            $storeId,
            $connection,
            $attributeTypes
        );
        $attributeValues = $this->_syncProductAttributeValues(
            $storeId,
            $productId,
            $connection,
            $attrTypeSelects,
            $attributeCodeIDMap
        );

        foreach ($attributeSet as $attributeData) {
            $attributeValueData = $this->_syncProductAttributeValueData($storeId, $attributeData, $attributeValues);

            if (isset($attributeValueData['value']) && $attributeValueData['value'] !== null) {
                if ($attributeData['html']) {
                    $insertHTMLSQL->execute([$productId, $attributeData['label'], $attributeValueData['value']]);
                }

                $insertAttributeSQL->execute(
                    [
                        $attributeData['id'],
                        $attributeData['name'],
                        $attributeData['label'],
                        $attributeData['backend_type'],
                        $attributeData['frontend_type']
                    ]
                );

                if ($attributeData['groupid']) {
                    $insertAttributeGroupSQL->execute([$attributeData['groupid'], $attributeData['groupname']]);
                    $insertAttributeGroupMapSQL->execute([$attributeData['groupid'], $attributeData['id']]);
                }

                $insertProductAttributeSQL->execute([$productId, $attributeData['id'], $attributeValueData['value']]);
            }

            if (isset($attributeValueData['defaultvalue']) && $attributeValueData['defaultvalue'] !== null) {
                if ($attributeValueData['defaultvalue'] != $attributeValueData['value']) {
                    $insertProductAttributeDefaultSQL->execute(
                        [
                            $productId,
                            $attributeData['id'],
                            $attributeValueData['defaultvalue']
                        ]
                    );
                }
            }
        }
    }

    public function syncSimpleProductData($args)
    {
        $type = $args['type'];

        $db = $args['db'];

        $store = $args['store'];
        $storeId = $store->getId();

        $productData = $args['row'];

        $productId = $productData['entity_id'];

        if (isset($args['preparedcheckproductStatement'])) {
            $checkProductSQL = $args['preparedcheckproductStatement'];
            $checkProductSQL->execute([$productId]);
            if ($checkProductSQL->fetchColumn()) {
                $checkProductSQL->closeCursor();
                return;
            }
            $checkProductSQL->closeCursor();
        }

        $parentids = null;

        $product = $this->productFactory->create();
        $product->setData($productData)
                ->setStore($store)
                ->setStoreId($storeId)
                ->setWebsiteId($store->getWebsiteId())
                ->setCustomerGroupId($this->ebayGroupId)
                ->setIsSuperMode(true);

        $insertSQL = $args['preparedStatement'];
        $insertCategorySQL = $args['preparedcategoryproductStatement'];
        $insertHTMLSQL = $args['preparedproducthtmlStatement'];

        $invalidoptionstate = $this->_syncConfigurableInvalidOptionState($type, $product);

        $productName = $this->_syncProductName($productData);
        $productCode = $this->_syncProductCode($productData);
        $description = $this->_syncProductDescription(
            $store,
            $storeId,
            $product,
            $productId,
            $type,
            $productData,
            $parentids
        );

        $price = !$invalidoptionstate ? $this->_syncProductPrice($store, $product) : 0;

        $listPrice = $this->_syncProductListPrice(
            $store,
            $product,
            $price
        );

        $stockData = $this->_syncStockData(
            $product,
            $productId,
            \Magento\CatalogInventory\Model\Stock::DEFAULT_STOCK_ID
        );

        $data = [];
        $data[] = $productId;
        $data[] = $type == 'configurable' ? 'c' : ($type == 'grouped' ? 'g' : ($type == 'virtual' ? 'v' : 's'));
        $data[] = $productCode;
        $data[] = $productName;
        $data[] = $price;
        $data[] = $listPrice;
        $data[] = isset($productData['tax_class_id'])
            && $productData['tax_class_id'] ?
                $productData['tax_class_id'] : '';
        $data[] = $description;
        $data[] = $productData['status'] != 1 ? 0 : -1;
        $data[] = $stockData['managestock'];
        $data[] = $stockData['qty'];
        $data[] = isset($productData['weight'])
            && is_numeric($productData['weight']) ?
                (float)$productData['weight'] : $productData['weight'];
        $data[] = $productData['codisto_in_store'];

        $insertSQL->execute($data);

        $categoryIds = $product->getCategoryIds();
        foreach ($categoryIds as $categoryId) {
            $insertCategorySQL->execute([$productId, $categoryId, 0]);
        }

        $shortDescription = $this->_syncProductShortDescription($storeId, $productData);
        if ($shortDescription) {
            $insertHTMLSQL->execute([$productId, 'Short Description', $shortDescription]);
        }

        $this->_syncProductAttributes($store, $storeId, $product, $productId, $args);

        $this->_syncProductImages($storeId, $product, $productId, $type, $productData, $args, $parentids);

        // process related products
        $this->_syncProductRelatedProducts($product, $productId, $args);

        // process simple product question/answers
        $this->_syncProductQuestions($product, $productId, $args);

        if ($type == 'simple' || $type == 'virtual') {
            $this->productsProcessed[] = $productId;

            if ($productData['entity_id'] > $this->currentEntityId) {
                $this->currentEntityId = $productId;
            }
        }
    }

    public function syncOrderData($args)
    {
        $insertOrdersSQL = $args['preparedStatement'];

        $orderData = $args['row'];

        $insertOrdersSQL->execute(
            [
                $orderData['codisto_orderid'],
                ($orderData['status'])?$orderData['status']:'processing',
                $orderData['pay_date'],
                $orderData['ship_date'],
                $orderData['carrier'],
                $orderData['track_number']
            ]
        );

        $this->ordersProcessed[] = $orderData['entity_id'];
        $this->currentEntityId = $orderData['entity_id'];
    }

    private function _syncChunkConfig($db, $store, &$state)
    {
        // Configuration
        $config = [
            'baseurl' => $store->getBaseUrl(),
            'mediaurl' => $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
            'staticurl' => $store->getBaseUrl(UrlInterface::URL_TYPE_STATIC),
            'storeurl' => $store->getBaseUrl(UrlInterface::URL_TYPE_WEB)
        ];

        $imagepdf = $store->getConfig('sales/identity/logo');
        $imagehtml = $store->getConfig('sales/identity/logo_html');

        $path = null;
        if ($imagepdf) {
            $path = $this->dirList->
                getPath(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA). '/sales/store/logo/' . $imagepdf;
        }
        if ($imagehtml) {
            $path = $this->dirList->
                getPath(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA). '/sales/store/logo_html/' . $imagehtml;
        }

        if ($path) {
            //Invoice and Packing Slip image location isn't accessible from frontend place into DB
            $data = @file_get_contents($path); // @codingStandardsIgnoreLine
            if ($data) {
                $base64 = base64_encode($data);

                $config['logobase64'] = $base64;
                //still stuff url in so we can get the MIME type to determine extra conversion on the other side
                $config['logourl'] = $path;

                $data = null;
            }
        }

        if (!isset($config['logobase64'])) {
            $logo_src = $store->getConfig('design/header/logo_src');
            if ($logo_src) {
                $logoUploadFolder= \Magento\Config\Model\Config\Backend\Image\Logo::UPLOAD_DIR;
                $logoPath = $logoUploadFolder . '/' . $logo_src;

                $config['logourl'] = $this->urlBuilder
                    ->getBaseUrl(['_type' => \Magento\Framework\UrlInterface::URL_TYPE_MEDIA]) . $logoPath;
            }
        }

        $config['currency'] = $store->getBaseCurrencyCode();
        $config['defaultcountry'] = $store->getConfig('tax/defaults/country');
        $config['shippingtaxclass'] = $store->getConfig('tax/classes/shipping_tax_class');

        $insertConfiguration = $db
            ->prepare('INSERT INTO Configuration(configuration_key, configuration_value) VALUES(?,?)');

        // build configuration table
        foreach ($config as $key => $value) {
            $insertConfiguration->execute([$key, $value]);
        }

        $state = 'simple';
    }

    private function _syncChunkProductSimple(
        $iterator,
        $db,
        $store,
        $storeId,
        $storeName,
        $catalogWebsiteName,
        $count,
        $preparedStatements,
        &$state
    ) {
        $productFlatState = $this->productFlatState->create(['isAvailable' => false]);

        // Simple Products not participating as configurable skus
        $simpleProducts = $this->productCollectionFactory
            ->create(['catalogProductFlatState' => $productFlatState])
            ->addAttributeToSelect($this->availableProductFields, 'left')
            ->addAttributeToFilter('type_id', ['eq' => 'simple'])
            ->addAttributeToFilter('entity_id', ['gt' => (int)$this->currentEntityId]);

        // @codingStandardsIgnoreStart
        $simpleProducts->getSelect()
                            ->columns(['codisto_in_store' => new \Zend_Db_Expr('CASE WHEN `e`.entity_id IN ('.
                            'SELECT product_id FROM `'.$catalogWebsiteName.'` '.
                            'WHERE website_id IN ('.
                            'SELECT website_id FROM `'.$storeName.'` WHERE '.
                            'store_id = '.$storeId.' OR EXISTS('.
                            'SELECT 1 FROM `'.$storeName.'` WHERE store_id = '.$storeId.' AND website_id = 0'.
                            '))) THEN -1 ELSE 0 END')])
                            ->order('entity_id')
                            ->limit($count);
        $simpleProducts->setOrder('entity_id', 'ASC');
        // @codingStandardsIgnoreEnd

        $iterator->walk(
            $simpleProducts->getSelect(),
            [[$this, 'syncSimpleProductData']],
            [
                'type' => 'simple',
                'db' => $db,
                'preparedStatement' => $preparedStatements['insertproduct'],
                'preparedcheckproductStatement' => $preparedStatements['checkproduct'],
                'preparedcategoryproductStatement' => $preparedStatements['insertcategoryproduct'],
                'preparedimageStatement' => $preparedStatements['insertproductimage'],
                'preparedproducthtmlStatement' => $preparedStatements['insertproducthtml'],
                'preparedproductrelatedStatement' => $preparedStatements['insertproductrelated'],
                'preparedattributeStatement' => $preparedStatements['insertattribute'],
                'preparedattributegroupStatement' => $preparedStatements['insertattributegroup'],
                'preparedattributegroupmapStatement' => $preparedStatements['insertattributegroupmap'],
                'preparedproductattributeStatement' => $preparedStatements['insertproductattributevalue'],
                'preparedproductattributedefaultStatement' => $preparedStatements['insertproductattributedefaultvalue'],
                'preparedproductquestionStatement' => $preparedStatements['insertproductquestion'],
                'preparedproductanswerStatement' => $preparedStatements['insertproductquestionanswer'],
                'store' => $store
            ]
        );

        if (!empty($this->productsProcessed)) {
            $db->exec(
                'INSERT OR REPLACE INTO Progress (Sentinel, State, entity_id) '.
                'VALUES (1, \'simple\', '.$this->currentEntityId.')'
            );
        } else {
            $state = 'virtual';
            $this->currentEntityId = 0;
        }
    }

    private function _syncChunkProductVirtual(
        $iterator,
        $db,
        $store,
        $storeId,
        $storeName,
        $catalogWebsiteName,
        $count,
        $preparedStatements,
        &$state
    ) {
        $productFlatState = $this->productFlatState->create(['isAvailable' => false]);

        // Simple Products not participating as configurable skus
        $virtualProducts = $this->productCollectionFactory
            ->create(['catalogProductFlatState' => $productFlatState])
            ->addAttributeToSelect($this->availableProductFields, 'left')
            ->addAttributeToFilter('type_id', ['eq' => 'virtual'])
            ->addAttributeToFilter('entity_id', ['gt' => (int)$this->currentEntityId]);

        // @codingStandardsIgnoreStart
        $virtualProducts->getSelect()
                            ->columns(['codisto_in_store' => new \Zend_Db_Expr('CASE WHEN `e`.entity_id IN ('.
                            'SELECT product_id FROM `'.$catalogWebsiteName.'` '.
                            'WHERE website_id IN ('.
                            'SELECT website_id FROM `'.$storeName.'` WHERE '.
                            'store_id = '.$storeId.' OR EXISTS('.
                            'SELECT 1 FROM `'.$storeName.'` WHERE store_id = '.$storeId.' AND website_id = 0'.
                            '))) THEN -1 ELSE 0 END')])
                            ->order('entity_id')
                            ->limit($count);
        $virtualProducts->setOrder('entity_id', 'ASC');
        // @codingStandardsIgnoreEnd

        $iterator->walk(
            $virtualProducts->getSelect(),
            [[$this, 'syncSimpleProductData']],
            [
                'type' => 'virtual',
                'db' => $db,
                'preparedStatement' => $preparedStatements['insertproduct'],
                'preparedcheckproductStatement' => $preparedStatements['checkproduct'],
                'preparedcategoryproductStatement' => $preparedStatements['insertcategoryproduct'],
                'preparedimageStatement' => $preparedStatements['insertproductimage'],
                'preparedproducthtmlStatement' => $preparedStatements['insertproducthtml'],
                'preparedproductrelatedStatement' => $preparedStatements['insertproductrelated'],
                'preparedattributeStatement' => $preparedStatements['insertattribute'],
                'preparedattributegroupStatement' => $preparedStatements['insertattributegroup'],
                'preparedattributegroupmapStatement' => $preparedStatements['insertattributegroupmap'],
                'preparedproductattributeStatement' => $preparedStatements['insertproductattributevalue'],
                'preparedproductattributedefaultStatement' => $preparedStatements['insertproductattributedefaultvalue'],
                'preparedproductquestionStatement' => $preparedStatements['insertproductquestion'],
                'preparedproductanswerStatement' => $preparedStatements['insertproductquestionanswer'],
                'store' => $store
            ]
        );

        if (!empty($this->productsProcessed)) {
            $db->exec(
                'INSERT OR REPLACE INTO Progress (Sentinel, State, entity_id) '.
                'VALUES (1, \'virtual\', '.$this->currentEntityId.')'
            );
        } else {
            $state = 'configurable';
            $this->currentEntityId = 0;
        }
    }

    private function _syncChunkProductConfigurable(
        $iterator,
        $db,
        $store,
        $storeId,
        $storeName,
        $catalogWebsiteName,
        $count,
        $preparedStatements,
        &$state
    ) {
        $productFlatState = $this->productFlatState->create(['isAvailable' => false]);

        // Configurable products
        // @codingStandardsIgnoreStart
        $configurableProducts = $this->productCollectionFactory
            ->create(['catalogProductFlatState' => $productFlatState])
            ->addAttributeToSelect($this->availableProductFields, 'left')
            ->addAttributeToFilter('type_id', ['eq' => 'configurable'])
            ->addAttributeToFilter('entity_id', ['gt' => (int)$this->currentEntityId]);

        $configurableProducts->getSelect()
            ->columns(['codisto_in_store' => new \Zend_Db_Expr('CASE WHEN `e`.entity_id IN ('.
                'SELECT product_id FROM `'.$catalogWebsiteName.'` WHERE website_id IN ('.
                'SELECT website_id FROM `'.$storeName.'` WHERE store_id = '.$storeId.' OR EXISTS('.
                'SELECT 1 FROM `'.$storeName.'` WHERE store_id = '.$storeId.' AND website_id = 0))) '.
                'THEN -1 ELSE 0 END')])
            ->order('entity_id')
            ->limit($count);
        $configurableProducts->setOrder('entity_id', 'ASC');
        // @codingStandardsIgnoreEnd

        $iterator->walk(
            $configurableProducts->getSelect(),
            [[$this, 'syncConfigurableProductData']],
            [
                'type' => 'configurable',
                'db' => $db,
                'preparedStatement' => $preparedStatements['insertproduct'],
                'preparedcheckproductStatement' => $preparedStatements['checkproduct'],
                'preparedskuStatement' => $preparedStatements['insertsku'],
                'preparedskulinkStatement' => $preparedStatements['insertskulink'],
                'preparedskumatrixStatement' => $preparedStatements['insertskumatrix'],
                'preparedcategoryproductStatement' => $preparedStatements['insertcategoryproduct'],
                'preparedimageStatement' => $preparedStatements['insertproductimage'],
                'preparedproducthtmlStatement' => $preparedStatements['insertproducthtml'],
                'preparedproductrelatedStatement' => $preparedStatements['insertproductrelated'],
                'preparedattributeStatement' => $preparedStatements['insertattribute'],
                'preparedattributegroupStatement' => $preparedStatements['insertattributegroup'],
                'preparedattributegroupmapStatement' => $preparedStatements['insertattributegroupmap'],
                'preparedproductattributeStatement' => $preparedStatements['insertproductattributevalue'],
                'preparedproductattributedefaultStatement' => $preparedStatements['insertproductattributedefaultvalue'],
                'preparedproductquestionStatement' => $preparedStatements['insertproductquestion'],
                'preparedproductanswerStatement' => $preparedStatements['insertproductquestionanswer'],
                'store' => $store
            ]
        );

        if (!empty($this->productsProcessed)) {
            $db->exec('INSERT OR REPLACE INTO Progress (Sentinel, State, entity_id) '.
            'VALUES (1, \'configurable\', '.$this->currentEntityId.')');
        } else {
            $state = 'grouped';
            $this->currentEntityId = 0;
        }
    }

    private function _syncChunkProductGrouped(
        $iterator,
        $db,
        $store,
        $storeId,
        $storeName,
        $catalogWebsiteName,
        $count,
        $preparedStatements,
        &$state
    ) {
        $productFlatState = $this->productFlatState->create(['isAvailable' => false]);
        // Grouped products
        // @codingStandardsIgnoreStart
        $groupedProducts = $this->productCollectionFactory
            ->create(['catalogProductFlatState' => $productFlatState])
            ->addAttributeToSelect($this->availableProductFields, 'left')
            ->addAttributeToFilter('type_id', ['eq' => 'grouped'])
            ->addAttributeToFilter('entity_id', ['gt' => (int)$this->currentEntityId]);

        $groupedProducts->getSelect()
            ->columns(['codisto_in_store' => new \Zend_Db_Expr('CASE WHEN `e`.entity_id IN ('.
            'SELECT product_id FROM `'.$catalogWebsiteName.'` WHERE website_id IN '.
            '(SELECT website_id FROM `'.$storeName.'` WHERE store_id = '.$storeId.' OR EXISTS'.
            '(SELECT 1 FROM `'.$storeName.'` WHERE store_id = '.$storeId.' AND website_id = 0))) '.
            'THEN -1 ELSE 0 END')])
            ->order('entity_id')
            ->limit($count);
        $groupedProducts->setOrder('entity_id', 'ASC');
        // @codingStandardsIgnoreEnd

        $iterator->walk(
            $groupedProducts->getSelect(),
            [[$this, 'syncGroupedProductData']],
            [
                'type' => 'grouped',
                'db' => $db,
                'preparedStatement' => $preparedStatements['insertproduct'],
                'preparedcheckproductStatement' => $preparedStatements['checkproduct'],
                'preparedskuStatement' => $preparedStatements['insertsku'],
                'preparedskulinkStatement' => $preparedStatements['insertskulink'],
                'preparedskumatrixStatement' => $preparedStatements['insertskumatrix'],
                'preparedcategoryproductStatement' => $preparedStatements['insertcategoryproduct'],
                'preparedimageStatement' => $preparedStatements['insertproductimage'],
                'preparedproducthtmlStatement' => $preparedStatements['insertproducthtml'],
                'preparedproductrelatedStatement' => $preparedStatements['insertproductrelated'],
                'preparedattributeStatement' => $preparedStatements['insertattribute'],
                'preparedattributegroupStatement' => $preparedStatements['insertattributegroup'],
                'preparedattributegroupmapStatement' => $preparedStatements['insertattributegroupmap'],
                'preparedproductattributeStatement' => $preparedStatements['insertproductattributevalue'],
                'preparedproductattributedefaultStatement' => $preparedStatements['insertproductattributedefaultvalue'],
                'preparedproductquestionStatement' => $preparedStatements['insertproductquestion'],
                'preparedproductanswerStatement' => $preparedStatements['insertproductquestionanswer'],
                'store' => $store
            ]
        );

        if (!empty($this->productsProcessed)) {
            $db->exec('INSERT OR REPLACE INTO Progress (Sentinel, State, entity_id) '.
            'VALUES (1, \'grouped\', '.$this->currentEntityId.')');
        } else {
            $state = 'orders';
            $this->currentEntityId = 0;
        }
    }

    private function _syncChunkOrders($iterator, $db, $store, $storeId, $preparedStatements, &$state)
    {
        $coreResource = $this->resourceConnection;

        if ($this->currentEntityId == 0) {
            $connection = $coreResource->getConnection();
            try {
                $connection->addColumn('sales_order', 'codisto_orderid', [
                    'type' => Table::TYPE_TEXT,
                    'length' => '10',
                    'comment' => 'Codisto Order Id'
                ]);
            } catch (\Exception $e) {
                $e;
                // ignore if column already exists
            }

            try {
                $connection->addColumn('sales_order', 'codisto_merchantid', [
                    'type' => Table::TYPE_TEXT,
                    'length' => '10',
                    'comment' => 'Codisto Merchant Id'
                ]);
            } catch (\Exception $e) {
                $e;
                // ignore if column already exists
            }
        }

        $orderStoreId = $storeId;
        if ($storeId == 0) {
            foreach ($this->storeManager->getStores(false) as $store) {
                $orderStoreId = $orderStoreId == 0 ? $store->getId() : min($store->getId(), $orderStoreId);
            }
        }

        $invoiceName = $coreResource->getTableName('sales_invoice');
        $shipmentName = $coreResource->getTableName('sales_shipment');
        $shipmentTrackName = $coreResource->getTableName('sales_shipment_track');

        $ts = $this->dateTime->gmtTimestamp();
        $ts -= 7776000; // 90 days

        // query has grouping and left joins but is limited to 1000
        // to avoid performance issues
        // @codingStandardsIgnoreStart
        $orders = $this->salesOrderCollectionFactory->create()
                    ->addFieldToSelect(['codisto_orderid', 'status'])
                    ->addFieldToSelect('entity_id', 'externalreference')
                    ->addAttributeToFilter('entity_id', ['gt' => (int)$this->currentEntityId])
                    ->addAttributeToFilter('main_table.store_id', ['eq' => $orderStoreId])
                    ->addAttributeToFilter('main_table.updated_at', ['gteq' => date('Y-m-d H:i:s', $ts)])
                    ->addAttributeToFilter('main_table.codisto_orderid', ['notnull' => true]);
        $orders->getSelect()->joinLeft(
            ['i' => $invoiceName],
            'i.order_id = main_table.entity_id AND i.state = 2',
            ['pay_date' => 'MIN(i.created_at)']
        );
        $orders->getSelect()->joinLeft(
            ['s' => $shipmentName],
            's.order_id = main_table.entity_id',
            ['ship_date' => 'MIN(s.created_at)']
        );
        $orders->getSelect()->joinLeft(
            ['t' => $shipmentTrackName],
            't.order_id = main_table.entity_id',
            [
                'carrier' => 'GROUP_CONCAT(COALESCE(t.title, \'\') SEPARATOR \',\')',
                'track_number' => 'GROUP_CONCAT(COALESCE(t.track_number, \'\') SEPARATOR \',\')'
            ]
        );
        $orders->getSelect()->group(['main_table.entity_id', 'main_table.codisto_orderid', 'main_table.status']);
        $orders->getSelect()->limit(1000);
        $orders->setOrder('entity_id', 'ASC');
        // @codingStandardsIgnoreEnd

        $iterator->walk(
            $orders->getSelect(),
            [[$this, 'syncOrderData']],
            [
                'db' => $db,
                'preparedStatement' => $preparedStatements['insertorder'],
                'store' => $store
            ]
        );

        if (!empty($this->ordersProcessed)) {
            $db->exec('INSERT OR REPLACE INTO Progress (Sentinel, State, entity_id) '.
            'VALUES (1, \'orders\', '.$this->currentEntityId.')');
        } else {
            $state = 'productoption';
            $this->currentEntityId = 0;
        }
    }

    private function _syncChunkProductOptions($db, &$state)
    {
        $db->exec('DELETE FROM ProductOptionValue');

        $insertProductOptionValue = $db->prepare(
            'INSERT INTO ProductOptionValue (ExternalReference, Sequence) VALUES (?,?)'
        );

        $options = $this->eavAttributeCollectionFactory->create()
            ->setPositionOrder('asc', true)
            ->load();

        $insertProductOptionValue->execute([0, 0]);

        foreach ($options as $opt) {
            $sequence = $opt->getSortOrder();
            $optId = $opt->getId();
            $insertProductOptionValue->execute([$optId, $sequence]);
        }

        $state = 'categories';
    }

    private function _syncChunkCategories($iterator, $db, $store, $preparedStatements)
    {
        $categoryFlatState = $this->categoryFlatState->create(['isAvailable' => false]);

        // Categories
        $categories = $this->categoryFactory->create(['flatState' => $categoryFlatState])->getCollection()
            ->addAttributeToSelect(
                ['name', 'image', 'is_active', 'updated_at', 'parent_id', 'position'],
                'left'
            );

        $iterator->walk(
            $categories->getSelect(),
            [[$this, 'syncCategoryData']],
            [
                'db' => $db,
                'preparedStatement' => $preparedStatements['insertcategory'],
                'store' => $store
            ]
        );

        $db->exec('INSERT OR REPLACE INTO Progress (Sentinel, State, entity_id) VALUES (1, \'complete\', 0)');
    }

    public function syncChunk($syncDb, $simpleCount, $configurableCount, $storeId, $first)
    {
        $store = $this->storeManager->getStore($storeId);

        $db = $this->_getSyncDb($syncDb, 5);

        $preparedStatements = [];

        $preparedStatements['insertcategory'] = $db->prepare(
            'INSERT OR REPLACE INTO Category'.
            '(ExternalReference, Name, ParentExternalReference, LastModified, Enabled, Sequence) '.
            'VALUES(?,?,?,?,?,?)'
        );
        $preparedStatements['insertcategoryproduct'] = $db->prepare(
            'INSERT OR IGNORE INTO CategoryProduct'.
            '(ProductExternalReference, CategoryExternalReference, Sequence) '.
            'VALUES(?,?,?)'
        );
        $preparedStatements['insertproduct'] = $db->prepare(
            'INSERT INTO Product'.
            '(ExternalReference, Type, Code, Name, Price, ListPrice, TaxClass, '.
                'Description, Enabled, StockControl, StockLevel, Weight, InStore) '.
            'VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $preparedStatements['checkproduct'] = $db->prepare(
            'SELECT CASE WHEN EXISTS('.
                'SELECT 1 FROM Product WHERE ExternalReference = ?) THEN 1 ELSE 0 END'
        );
        $preparedStatements['insertsku'] = $db->prepare(
            'INSERT OR IGNORE INTO SKU'.
            '(ExternalReference, Code, ProductExternalReference, Name, '.
            'StockControl, StockLevel, Price, Enabled, InStore) '.
            'VALUES(?,?,?,?,?,?,?,?,?)'
        );
        $preparedStatements['insertskulink'] = $db->prepare(
            'INSERT OR REPLACE INTO SKULink '.
            '(SKUExternalReference, ProductExternalReference, Price) '.
            'VALUES(?, ?, ?)'
        );
        $preparedStatements['insertskumatrix'] = $db->prepare(
            'INSERT INTO SKUMatrix'.
            '(SKUExternalReference, ProductExternalReference, Code, OptionName, '.
            'OptionValue, ProductOptionExternalReference, ProductOptionValueExternalReference) '.
            'VALUES(?,?,?,?,?,?,?)'
        );
        $preparedStatements['insertproductimage'] = $db->prepare(
            'INSERT INTO ProductImage'.
            '(ProductExternalReference, URL, Tag, Sequence, Enabled) '.
            'VALUES(?,?,?,?,?)'
        );
        $preparedStatements['insertproducthtml'] = $db->prepare(
            'INSERT OR IGNORE INTO ProductHTML'.
            '(ProductExternalReference, Tag, HTML) '.
            'VALUES(?, ?, ?)'
        );
        $preparedStatements['insertproductrelated'] = $db->prepare(
            'INSERT OR IGNORE INTO ProductRelated'.
            '(RelatedProductExternalReference, ProductExternalReference) '.
            'VALUES(?, ?)'
        );
        $preparedStatements['insertattribute'] = $db->prepare(
            'INSERT OR REPLACE INTO Attribute'.
            '(ID, Code, Label, Type, Input) '.
            'VALUES(?, ?, ?, ?, ?)'
        );
        $preparedStatements['insertattributegroup'] = $db->prepare(
            'INSERT OR REPLACE INTO AttributeGroup'.
            '(ID, Name) '.
            'VALUES(?, ?)'
        );
        $preparedStatements['insertattributegroupmap'] = $db->prepare(
            'INSERT OR IGNORE INTO AttributeGroupMap'.
            '(GroupID, AttributeID) '.
            'VALUES(?,?)'
        );
        $preparedStatements['insertproductattributevalue'] = $db->prepare(
            'INSERT OR IGNORE INTO ProductAttributeValue'.
            '(ProductExternalReference, AttributeID, Value) '.
            'VALUES(?, ?, ?)'
        );
        $preparedStatements['insertproductattributedefaultvalue'] = $db->prepare(
            'INSERT OR IGNORE INTO ProductAttributeDefaultValue'.
            '(ProductExternalReference, AttributeID, Value) '.
            'VALUES(?, ?, ?)'
        );
        $preparedStatements['insertproductquestion'] = $db->prepare(
            'INSERT OR REPLACE INTO ProductQuestion'.
            '(ExternalReference, ProductExternalReference, Name, Type, Sequence) '.
            'VALUES(?, ?, ?, ?, ?)'
        );
        $preparedStatements['insertproductquestionanswer'] = $db->prepare(
            'INSERT INTO ProductQuestionAnswer'.
            '(ProductQuestionExternalReference, Value, PriceModifier, SKUModifier, Sequence) '.
            'VALUES(?, ?, ?, ?, ?)'
        );
        $preparedStatements['insertorder'] = $db->prepare(
            'INSERT OR REPLACE INTO [Order] '.
            '(ID, Status, PaymentDate, ShipmentDate, Carrier, TrackingNumber, ExternalReference) '.
            'VALUES(?, ?, ?, ?, ?, ?, ?)'
        );

        $db->exec('BEGIN EXCLUSIVE TRANSACTION');

        $qry = $db->query('SELECT entity_id FROM Progress'); // @codingStandardsIgnoreLine

        $this->currentEntityId = $qry->fetchColumn();
        $this->currentEntityId = !$this->currentEntityId ? 0 : $this->currentEntityId;

        $qry->closeCursor();

        $qry = $db->query('SELECT State FROM Progress'); // @codingStandardsIgnoreLine

        $state = $qry->fetchColumn();

        $qry->closeCursor();

        if (!$state) {
            $this->_syncChunkConfig($db, $store, $state);
        }

        $this->productsProcessed = [];
        $this->ordersProcessed = [];

        $coreResource = $this->resourceConnection;

        $catalogWebsiteName = $coreResource->getTableName('catalog_product_website');
        $storeName = $coreResource->getTableName('store');
        $iterator = $this->iteratorFactory->create();

        if ('simple' == $state) {
            $this->_syncChunkProductSimple(
                $iterator,
                $db,
                $store,
                $storeId,
                $storeName,
                $catalogWebsiteName,
                $simpleCount,
                $preparedStatements,
                $state
            );
        }

        if ('virtual' == $state) {
            $this->_syncChunkProductVirtual(
                $iterator,
                $db,
                $store,
                $storeId,
                $storeName,
                $catalogWebsiteName,
                $simpleCount,
                $preparedStatements,
                $state
            );
        }

        if ('configurable' == $state) {
            $this->_syncChunkProductConfigurable(
                $iterator,
                $db,
                $store,
                $storeId,
                $storeName,
                $catalogWebsiteName,
                $configurableCount,
                $preparedStatements,
                $state
            );
        }

        if ('grouped' == $state) {
            $this->_syncChunkProductGrouped(
                $iterator,
                $db,
                $store,
                $storeId,
                $storeName,
                $catalogWebsiteName,
                $configurableCount,
                $preparedStatements,
                $state
            );
        }

        if ('orders' == $state) {
            $this->_syncChunkOrders(
                $iterator,
                $db,
                $store,
                $storeId,
                $preparedStatements,
                $state
            );
        }

        if ('productoption' == $state) {
            $this->_syncChunkProductOptions($db, $state);
        }

        if ('categories' == $state) {
            $this->_syncChunkCategories($iterator, $db, $store, $preparedStatements);
        }

        if ((empty($this->productsProcessed)
            && empty($this->ordersProcessed)) || $first) {
            $this->_syncIncrementalSyncToken(
                $coreResource->getConnection(),
                $this->_tablePrefix(),
                $storeId,
                $db
            );
            $db->exec('COMMIT TRANSACTION');
            return 'complete';
        } else {
            $db->exec('COMMIT TRANSACTION');
            return 'pending';
        }
    }

    public function syncIncrementalStores($storeId)
    {
        $helper = $this->codistoHelper;

        $syncDbPath = $helper->getSyncPath('sync-'.$storeId.'.db');

        $syncDb = null;

        if (file_exists($syncDbPath)) { // @codingStandardsIgnoreLine
            $syncDb = $this->_getSyncDb($syncDbPath, 5);
        }

        return ['id' => $storeId, 'path' => $syncDbPath, 'db' => $syncDb];
    }

    private function _syncIncrementalProducts($store, $storeId, $productUpdateIds, $iterator, $db)
    {
        if (empty($productUpdateIds)) {
            return;
        }

        $catalogWebsiteName = $this->resourceConnection->getTableName('catalog_product_website');
        $storeName = $this->resourceConnection->getTableName('store');

        $productFlatState = $this->productFlatState->create(['isAvailable' => false]);

        $productUpdateIdList = implode(',', $productUpdateIds);

        $db->exec(
            'DELETE FROM Product WHERE ExternalReference IN ('.$productUpdateIdList.');'.
            'DELETE FROM ProductImage WHERE ProductExternalReference IN ('.$productUpdateIdList.');'.
            'DELETE FROM ProductHTML WHERE ProductExternalReference IN ('.$productUpdateIdList.');'.
            'DELETE FROM ProductRelated WHERE ProductExternalReference IN ('.$productUpdateIdList.');'.
            'DELETE FROM ProductAttributeValue WHERE ProductExternalReference IN ('.$productUpdateIdList.');'.
            'DELETE FROM ProductQuestionAnswer WHERE ProductQuestionExternalReference IN '.
                '(SELECT ExternalReference FROM ProductQuestion WHERE ProductExternalReference '.
                    'IN ('.$productUpdateIdList.'));'.
            'DELETE FROM ProductQuestion WHERE ProductExternalReference IN ('.$productUpdateIdList.');'.
            'DELETE FROM SKULink WHERE ProductExternalReference IN ('.$productUpdateIdList.');'.
            'DELETE FROM SKUMatrix WHERE ProductExternalReference IN ('.$productUpdateIdList.');'.
            'DELETE FROM SKU WHERE ProductExternalReference IN ('.$productUpdateIdList.');'.
            'DELETE FROM CategoryProduct WHERE ProductExternalReference IN ('.$productUpdateIdList.')'
        );

        $insertCategoryProduct = $db->prepare(
            'INSERT OR IGNORE INTO CategoryProduct(ProductExternalReference, CategoryExternalReference, Sequence) '.
            'VALUES(?,?,?)'
        );
        $insertProduct = $db->prepare(
            'INSERT INTO Product '.
                '(ExternalReference, Type, Code, Name, Price, ListPrice, TaxClass, '.
                    'Description, Enabled, StockControl, StockLevel, Weight, InStore) '.
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $checkProduct = $db->prepare(
            'SELECT CASE WHEN EXISTS(SELECT 1 FROM Product WHERE ExternalReference = ?) THEN 1 ELSE 0 END'
        );
        $insertSKU = $db->prepare(
            'INSERT OR IGNORE INTO SKU'.
                '(ExternalReference, Code, ProductExternalReference, Name, '.
                    'StockControl, StockLevel, Price, Enabled, InStore) '.
            'VALUES(?,?,?,?,?,?,?,?,?)'
        );
        $insertSKULink = $db->prepare(
            'INSERT OR REPLACE INTO SKULink (SKUExternalReference, ProductExternalReference, Price) '.
            'VALUES (?, ?, ?)'
        );
        $insertSKUMatrix = $db->prepare(
            'INSERT INTO SKUMatrix'.
                '(SKUExternalReference, ProductExternalReference, Code, OptionName, OptionValue, '.
                    'ProductOptionExternalReference, ProductOptionValueExternalReference) '.
            'VALUES(?,?,?,?,?,?,?)'
        );
        $insertImage = $db->prepare(
            'INSERT INTO ProductImage(ProductExternalReference, URL, Tag, Sequence, Enabled) '.
            'VALUES(?,?,?,?,?)'
        );
        $insertProductHTML = $db->prepare(
            'INSERT OR IGNORE INTO ProductHTML(ProductExternalReference, Tag, HTML) '.
            'VALUES (?, ?, ?)'
        );
        $insertProductRelated = $db->prepare(
            'INSERT OR IGNORE INTO ProductRelated(RelatedProductExternalReference, ProductExternalReference) '.
            'VALUES (?, ?)'
        );
        $insertAttribute = $db->prepare(
            'INSERT OR REPLACE INTO Attribute(ID, Code, Label, Type, Input) VALUES (?, ?, ?, ?, ?)'
        );
        $insertAttributeGroup = $db->prepare(
            'INSERT OR REPLACE INTO AttributeGroup(ID, Name) VALUES(?, ?)'
        );
        $insertAttributeGroupMap = $db->prepare(
            'INSERT OR IGNORE INTO AttributeGroupMap(GroupID, AttributeID) VALUES(?,?)'
        );
        $insertProductAttribute = $db->prepare(
            'INSERT OR IGNORE INTO ProductAttributeValue(ProductExternalReference, AttributeID, Value) '.
            'VALUES (?, ?, ?)'
        );
        $insertProductAttributeDefault = $db->prepare(
            'INSERT OR IGNORE INTO ProductAttributeDefaultValue(ProductExternalReference, AttributeID, Value) '.
            'VALUES (?, ?, ?)'
        );
        $insertProductQuestion = $db->prepare(
            'INSERT OR REPLACE INTO ProductQuestion'.
                '(ExternalReference, ProductExternalReference, Name, Type, Sequence) '.
            'VALUES (?, ?, ?, ?, ?)'
        );
        $insertProductAnswer = $db->prepare(
            'INSERT INTO ProductQuestionAnswer'.
                '(ProductQuestionExternalReference, Value, PriceModifier, SKUModifier, Sequence) '.
            'VALUES (?, ?, ?, ?, ?)'
        );

        // Simple Products
        $simpleProducts = $this->productCollectionFactory
            ->create(['catalogProductFlatState' => $productFlatState])
            ->addAttributeToSelect($this->availableProductFields, 'left')
            ->addAttributeToFilter('type_id', ['eq' => 'simple'])
            ->addAttributeToFilter('entity_id', ['in' => $productUpdateIds]);
        $simpleProducts->getSelect()
            ->columns( // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
                [
                    'codisto_in_store' => new \Zend_Db_Expr( // @codingStandardsIgnoreLine MEQP2.Classes.ObjectInstantiation.FoundDirectInstantiation
                        'CASE WHEN `e`.entity_id IN '.
                        '(SELECT product_id FROM `'.$catalogWebsiteName.'` WHERE website_id IN '.
                        '(SELECT website_id FROM `'.$storeName.'` WHERE store_id = '.$storeId.' '.
                        'OR EXISTS(SELECT 1 FROM `'.$storeName.'` WHERE store_id = '.$storeId.' AND website_id = 0))'.
                        ') THEN -1 ELSE 0 END'
                    )
                ]
            );

        $iterator->walk(
            $simpleProducts->getSelect(),
            [[$this, 'syncSimpleProductData']],
            [
                'type' => 'simple',
                'db' => $db,
                'preparedStatement' => $insertProduct,
                'preparedcheckproductStatement' => $checkProduct,
                'preparedcategoryproductStatement' => $insertCategoryProduct,
                'preparedimageStatement' => $insertImage,
                'preparedproducthtmlStatement' => $insertProductHTML,
                'preparedproductrelatedStatement' => $insertProductRelated,
                'preparedattributeStatement' => $insertAttribute,
                'preparedattributegroupStatement' => $insertAttributeGroup,
                'preparedattributegroupmapStatement' => $insertAttributeGroupMap,
                'preparedproductattributeStatement' => $insertProductAttribute,
                'preparedproductattributedefaultStatement' => $insertProductAttributeDefault,
                'preparedproductquestionStatement' => $insertProductQuestion,
                'preparedproductanswerStatement' => $insertProductAnswer,
                'store' => $store
            ]
        );

        // Virtual Products
        $virtualProducts = $this->productCollectionFactory
            ->create(['catalogProductFlatState' => $productFlatState])
            ->addAttributeToSelect($this->availableProductFields, 'left')
            ->addAttributeToFilter('type_id', ['eq' => 'virtual'])
            ->addAttributeToFilter('entity_id', ['in' => $productUpdateIds]);
        $virtualProducts->getSelect()
            ->columns( // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
                [
                    'codisto_in_store' => new \Zend_Db_Expr( // @codingStandardsIgnoreLine MEQP2.Classes.ObjectInstantiation.FoundDirectInstantiation
                        'CASE WHEN `e`.entity_id IN '.
                        '(SELECT product_id FROM `'.$catalogWebsiteName.'` WHERE website_id IN '.
                        '(SELECT website_id FROM `'.$storeName.'` WHERE store_id = '.$storeId.' '.
                        'OR EXISTS(SELECT 1 FROM `'.$storeName.'` WHERE store_id = '.$storeId.' AND website_id = 0))'.
                        ') THEN -1 ELSE 0 END'
                    )
                ]
            );

        $iterator->walk(
            $virtualProducts->getSelect(),
            [[$this, 'syncSimpleProductData']],
            [
                'type' => 'virtual',
                'db' => $db,
                'preparedStatement' => $insertProduct,
                'preparedcheckproductStatement' => $checkProduct,
                'preparedcategoryproductStatement' => $insertCategoryProduct,
                'preparedimageStatement' => $insertImage,
                'preparedproducthtmlStatement' => $insertProductHTML,
                'preparedproductrelatedStatement' => $insertProductRelated,
                'preparedattributeStatement' => $insertAttribute,
                'preparedattributegroupStatement' => $insertAttributeGroup,
                'preparedattributegroupmapStatement' => $insertAttributeGroupMap,
                'preparedproductattributeStatement' => $insertProductAttribute,
                'preparedproductattributedefaultStatement' => $insertProductAttributeDefault,
                'preparedproductquestionStatement' => $insertProductQuestion,
                'preparedproductanswerStatement' => $insertProductAnswer,
                'store' => $store
            ]
        );

        // Configurable products
        $configurableProducts = $this->productCollectionFactory
            ->create(['catalogProductFlatState' => $productFlatState])
            ->addAttributeToSelect($this->availableProductFields, 'left')
            ->addAttributeToFilter('type_id', ['eq' => 'configurable'])
            ->addAttributeToFilter('entity_id', ['in' => $productUpdateIds]);
        $configurableProducts->getSelect()
            ->columns( // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
                [
                    'codisto_in_store' => new \Zend_Db_Expr( // @codingStandardsIgnoreLine MEQP2.Classes.ObjectInstantiation.FoundDirectInstantiation
                        'CASE WHEN `e`.entity_id IN '.
                        '(SELECT product_id FROM `'.$catalogWebsiteName.'` WHERE website_id IN '.
                        '(SELECT website_id FROM `'.$storeName.'` WHERE store_id = '.$storeId.' '.
                        'OR EXISTS(SELECT 1 FROM `'.$storeName.'` WHERE store_id = '.$storeId.' AND website_id = 0))'.
                        ') THEN -1 ELSE 0 END'
                    )
                ]
            );

        $iterator->walk(
            $configurableProducts->getSelect(),
            [[$this, 'SyncConfigurableProductData']],
            [
                'type' => 'configurable',
                'db' => $db,
                'preparedStatement' => $insertProduct,
                'preparedcheckproductStatement' => $checkProduct,
                'preparedskuStatement' => $insertSKU,
                'preparedskulinkStatement' => $insertSKULink,
                'preparedskumatrixStatement' => $insertSKUMatrix,
                'preparedcategoryproductStatement' => $insertCategoryProduct,
                'preparedimageStatement' => $insertImage,
                'preparedproducthtmlStatement' => $insertProductHTML,
                'preparedproductrelatedStatement' => $insertProductRelated,
                'preparedattributeStatement' => $insertAttribute,
                'preparedattributegroupStatement' => $insertAttributeGroup,
                'preparedattributegroupmapStatement' => $insertAttributeGroupMap,
                'preparedproductattributeStatement' => $insertProductAttribute,
                'preparedproductattributedefaultStatement' => $insertProductAttributeDefault,
                'preparedproductquestionStatement' => $insertProductQuestion,
                'preparedproductanswerStatement' => $insertProductAnswer,
                'store' => $store
            ]
        );

        // Grouped products
        $groupedProducts = $this->productCollectionFactory
            ->create(['catalogProductFlatState' => $productFlatState])
            ->addAttributeToSelect($this->availableProductFields, 'left')
            ->addAttributeToFilter('type_id', ['eq' => 'grouped'])
            ->addAttributeToFilter('entity_id', ['in' => $productUpdateIds]);
        $groupedProducts->getSelect()
            ->columns( // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
                [
                    'codisto_in_store' => new \Zend_Db_Expr( // @codingStandardsIgnoreLine MEQP2.Classes.ObjectInstantiation.FoundDirectInstantiation
                        'CASE WHEN `e`.entity_id IN '.
                        '(SELECT product_id FROM `'.$catalogWebsiteName.'` WHERE website_id IN '.
                        '(SELECT website_id FROM `'.$storeName.'` WHERE store_id = '.$storeId.' '.
                        'OR EXISTS(SELECT 1 FROM `'.$storeName.'` WHERE store_id = '.$storeId.' AND website_id = 0))'.
                        ') THEN -1 ELSE 0 END'
                    )
                ]
            );

        $iterator->walk(
            $groupedProducts->getSelect(),
            [[$this, 'SyncGroupedProductData']],
            [
                'type' => 'grouped',
                'db' => $db,
                'preparedStatement' => $insertProduct,
                'preparedcheckproductStatement' => $checkProduct,
                'preparedskuStatement' => $insertSKU,
                'preparedskulinkStatement' => $insertSKULink,
                'preparedskumatrixStatement' => $insertSKUMatrix,
                'preparedcategoryproductStatement' => $insertCategoryProduct,
                'preparedimageStatement' => $insertImage,
                'preparedproducthtmlStatement' => $insertProductHTML,
                'preparedproductrelatedStatement' => $insertProductRelated,
                'preparedattributeStatement' => $insertAttribute,
                'preparedattributegroupStatement' => $insertAttributeGroup,
                'preparedattributegroupmapStatement' => $insertAttributeGroupMap,
                'preparedproductattributeStatement' => $insertProductAttribute,
                'preparedproductattributedefaultStatement' => $insertProductAttributeDefault,
                'preparedproductquestionStatement' => $insertProductQuestion,
                'preparedproductanswerStatement' => $insertProductAnswer,
                'store' => $store
            ]
        );
    }

    private function _syncIncrementalCategories($store, $categoryUpdateIds, $iterator, $db)
    {
        if (empty($categoryUpdateIds)) {
            return;
        }

        $categoryFlatState = $this->categoryFlatState->create(['isAvailable' => false]);

        $insertCategory = $db->prepare(
            'INSERT OR REPLACE INTO Category'.
                '(ExternalReference, Name, ParentExternalReference, LastModified, Enabled, Sequence) '.
            'VALUES(?,?,?,?,?,?)'
        );

        $categories = $this->categoryFactory->create(['flatState' => $categoryFlatState])->getCollection()
            ->addAttributeToSelect(['name', 'image', 'is_active', 'updated_at', 'parent_id', 'position'], 'left')
            ->addAttributeToFilter('entity_id', ['in' => $categoryUpdateIds]);

        $iterator->walk(
            $categories->getSelect(),
            [[$this, 'syncCategoryData']],
            [
                'db' => $db,
                'preparedStatement' => $insertCategory,
                'store' => $store
            ]
        );
    }

    private function _syncIncrementalOrders($store, $storeId, $orderUpdateIds, $iterator, $db)
    {
        if (empty($orderUpdateIds)) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();

        try {
            $connection->addColumn(
                $this->resourceConnection->getTableName('sales_order'),
                'codisto_orderid',
                'varchar(10)'
            );
        } catch (\Exception $e) {
            $e;
        }

        try {
            $connection->addColumn(
                $this->resourceConnection->getTableName('sales_order'),
                'codisto_merchantid',
                'varchar(10)'
            );
        } catch (\Exception $e) {
            $e;
        }

        $insertOrders = $db->prepare(
            'INSERT OR REPLACE INTO [Order]'.
                '(ID, Status, PaymentDate, ShipmentDate, Carrier, TrackingNumber, ExternalReference, MerchantID) '.
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $invoiceName = $this->resourceConnection->getTableName('sales_invoice');
        $shipmentName = $this->resourceConnection->getTableName('sales_shipment');
        $shipmentTrackName = $this->resourceConnection->getTableName('sales_shipment_track');

        $ts = $this->dateTime->gmtTimestamp();
        $ts -= 7776000; // 90 days

        $orders = $this->salesOrderCollectionFactory->create()
                    ->addFieldToSelect(['codisto_orderid', 'codisto_merchantid', 'status'])
                    ->addFieldToSelect('entity_id', 'externalreference')
                    ->addAttributeToFilter('entity_id', ['in' => $orderUpdateIds])
                    ->addAttributeToFilter('main_table.store_id', ['eq' => $storeId])
                    ->addAttributeToFilter('main_table.updated_at', ['gteq' => date('Y-m-d H:i:s', $ts)])
                    ->addAttributeToFilter('main_table.codisto_orderid', ['notnull' => true]);
        $orders->getSelect()->joinLeft( // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
            ['i' => $invoiceName],
            'i.order_id = main_table.entity_id AND i.state = 2',
            ['pay_date' => 'MIN(i.created_at)']
        );
        $orders->getSelect()->joinLeft( // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
            ['s' => $shipmentName],
            's.order_id = main_table.entity_id',
            ['ship_date' => 'MIN(s.created_at)']
        );
        $orders->getSelect()->joinLeft( // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
            ['t' => $shipmentTrackName],
            't.order_id = main_table.entity_id',
            [
                'carrier' => 'GROUP_CONCAT(COALESCE(t.title, \'\') SEPARATOR \',\')',
                'track_number' => 'GROUP_CONCAT(COALESCE(t.track_number, \'\') SEPARATOR \',\')'
            ]
        );
        $orders->getSelect()
            ->group( // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
                ['main_table.entity_id', 'main_table.codisto_orderid', 'main_table.codisto_merchantid', 'main_table.status']
            );
        $orders->setOrder('entity_id', 'ASC');

        $iterator->walk(
            $orders->getSelect(),
            [[$this, 'syncOrderData']],
            [
                'db' => $db,
                'preparedStatement' => $insertOrders,
                'store' => $store
            ]
        );
    }

    private function _syncIncrementalCleanChangeData($connection, $entries, $changeTable, $field)
    {
        if (empty($entries)) {
            return;
        }

        $connection->exec('CREATE TEMPORARY TABLE tmp_codisto_change ('.$field.' int(10) unsigned, stamp integer)');
        foreach ($entries as $entityId => $stamp) {
            $connection->insert( // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
                'tmp_codisto_change',
                [$field => $entityId, 'stamp' => $stamp]
            );
        }
        $connection->exec(
            'DELETE FROM `'.$changeTable.'` '.
            'WHERE EXISTS ('.
                'SELECT 1 FROM tmp_codisto_change '.
                'WHERE '.$field.' = `'.$changeTable.'`.entity_id AND '.
                    'stamp >= `'.$changeTable.'`.version_id'.
            ')'
        );
        $connection->exec('DROP TABLE tmp_codisto_change');
    }

    private function _syncIncrementalSyncToken($connection, $tablePrefix, $storeId, $db)
    {
        $uniqueId = uniqid();

        $connection->beginTransaction();
        try {
            $connection->exec(
                'REPLACE INTO `'.$tablePrefix.'codisto_sync` (store_id, token) '.
                'VALUES ('.$storeId.', \''.$uniqueId.'\')'
            );
        } catch (\Exception $e) {
            $connection->exec(
                'CREATE TABLE `'.$tablePrefix.'codisto_sync` '.
                    '(store_id smallint(5) unsigned PRIMARY KEY NOT NULL, token varchar(20) NOT NULL)'
            );
            $connection->insert( // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
                $tablePrefix.'codisto_sync',
                ['token' => $uniqueId, 'store_id' => $storeId]
            );
        }
        $connection->commit();

        $db->exec(
            'CREATE TABLE IF NOT EXISTS Sync '.
                '(token text NOT NULL, sentinel NOT NULL PRIMARY KEY DEFAULT 1, CHECK(sentinel = 1))'
        );
        $db->exec('INSERT OR REPLACE INTO Sync (token) VALUES (\''.$uniqueId.'\')');
    }

    private function _syncIncrementalProductOptions($db)
    {
        $db->exec('DELETE FROM ProductOptionValue');

        $insertProductOptionValue = $db->prepare(
            'INSERT INTO ProductOptionValue (ExternalReference, Sequence) '.
            'VALUES (?,?)'
        );

        $options = $this->eavAttributeCollectionFactory->create()
            ->setPositionOrder('asc', true)
            ->load();

        foreach ($options as $opt) {
            $sequence = $opt->getSortOrder();
            $optId = $opt->getId();
            $insertProductOptionValue->execute([$optId, $sequence]);
        }
    }

    private function _syncIncrementalStores()
    {
        $storeIds = [0];

        $defaultMerchantList = $this->storeManager->getStore(0)->getConfig('codisto/merchantid');

        foreach ($this->storeManager->getStores(false) as $store) {
            $storeMerchantList = $store->getConfig('codisto/merchantid');
            if ($storeMerchantList && $storeMerchantList != $defaultMerchantList) {
                $storeIds[] = $store->getId();
            }
        }

        if ($storeIds === [0]) {
            $storeId = 0;
            foreach ($this->storeManager->getStores(false) as $store) {
                $storeId = $storeId == 0 ? $store->getId() : min($storeId, $store->getId());
            }
            $storeIds[] = $storeId;
        }

        return array_map([$this, 'syncIncrementalStores'], $storeIds);
    }

    public function syncIncremental($simpleCount, $configurableCount)
    {
        $configurableCount; // unused param

        $coreResource = $this->resourceConnection;
        $connection = $coreResource->getConnection();

        $tablePrefix = $this->_tablePrefix();

        $stores = $this->_syncIncrementalStores();

        $productUpdateEntries = $connection->fetchPairs(
            'SELECT entity_id AS product_id, MAX(version_id) AS stamp '.
            'FROM `'.$tablePrefix.'codisto_index_product_cl` '.
            'GROUP BY entity_id '.
            'ORDER BY entity_id '.
            'LIMIT '.(int)$simpleCount
        );
        $categoryUpdateEntries = $connection->fetchPairs(
            'SELECT entity_id AS category_id, MAX(version_id) AS stamp '.
            'FROM `'.$tablePrefix.'codisto_index_category_cl` '.
            'GROUP BY entity_id '.
            'ORDER BY entity_id'
        );
        $orderUpdateEntries = $connection->fetchPairs(
            'SELECT entity_id AS order_id, MAX(version_id) AS stamp '.
            'FROM `'.$tablePrefix.'codisto_index_order_cl` '.
            'GROUP BY entity_id '.
            'ORDER BY entity_id '.
            'LIMIT 1000'
        );

        if (empty($productUpdateEntries) &&
            empty($categoryUpdateEntries) &&
            empty($orderUpdateEntries)) {
            return 'nochange';
        }

        $productUpdateIds = array_keys($productUpdateEntries);
        $categoryUpdateIds = array_keys($categoryUpdateEntries);
        $orderUpdateIds = array_keys($orderUpdateEntries);

        $iterator = $this->iteratorFactory->create();

        $this->productsProcessed = [];
        $this->ordersProcessed = [];

        foreach ($stores as $storeData) {
            $storeId = $storeData['id'];
            if ($storeId === 0) {
                continue;
            }

            $store = $this->storeManager->getStore($storeId);

            $db = $storeData['db'];

            if (is_null($db)) {
                continue;
            }

            $db->exec('BEGIN EXCLUSIVE TRANSACTION');

            $this->_syncIncrementalProducts($store, $storeId, $productUpdateIds, $iterator, $db);
            $this->_syncIncrementalCategories($store, $categoryUpdateIds, $iterator, $db);
            $this->_syncIncrementalOrders($store, $storeId, $orderUpdateIds, $iterator, $db);
            $this->_syncIncrementalSyncToken($connection, $tablePrefix, $storeId, $db);

            if (!empty($productUpdateIds)) {
                $db->exec(
                    'CREATE TABLE IF NOT EXISTS ProductChange '.
                        '(ExternalReference text NOT NULL PRIMARY KEY, '.
                            'stamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP)'
                );
                foreach ($productUpdateIds as $updateId) {
                    $db->exec('INSERT OR REPLACE INTO ProductChange (ExternalReference) VALUES ('.$updateId.')');
                }

                $this->_syncIncrementalProductOptions($db);
            }

            if (!empty($categoryUpdateIds)) {
                $db->exec(
                    'CREATE TABLE IF NOT EXISTS CategoryChange '.
                        '(ExternalReference text NOT NULL PRIMARY KEY, '.
                            'stamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP)'
                );
                foreach ($categoryUpdateIds as $updateId) {
                    $db->exec('INSERT OR REPLACE INTO CategoryChange (ExternalReference) VALUES ('.$updateId.')');
                }
            }

            if (!empty($orderUpdateIds)) {
                $db->exec(
                    'CREATE TABLE IF NOT EXISTS OrderChange '.
                        '(ExternalReference text NOT NULL PRIMARY KEY, '.
                            'stamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP)'
                );
                foreach ($orderUpdateIds as $updateId) {
                    $db->exec('INSERT OR REPLACE INTO OrderChange (ExternalReference) VALUES ('.$updateId.')');
                }
            }

            $db->exec('COMMIT TRANSACTION');
        }

        $connection->beginTransaction();
        $this->_syncIncrementalCleanChangeData(
            $connection,
            $productUpdateEntries,
            $tablePrefix.'codisto_index_product_cl',
            'product_id'
        );
        $this->_syncIncrementalCleanChangeData(
            $connection,
            $categoryUpdateEntries,
            $tablePrefix.'codisto_index_category_cl',
            'category_id'
        );
        $this->_syncIncrementalCleanChangeData(
            $connection,
            $orderUpdateEntries,
            $tablePrefix.'codisto_index_order_cl',
            'order_id'
        );
        $connection->commit();

        return $connection->fetchOne('SELECT CASE WHEN '.
                                'EXISTS(SELECT 1 FROM `'.$tablePrefix.'codisto_index_product_cl`) OR '.
                                'EXISTS(SELECT 1 FROM `'.$tablePrefix.'codisto_index_category_cl`) OR '.
                                'EXISTS(SELECT 1 FROM `'.$tablePrefix.'codisto_index_order_cl`) '.
                                'THEN \'pending\' ELSE \'complete\' END');
    }

    public function syncChangeComplete($syncDb, $changeDb)
    {
        $db = $this->_getSyncDb($syncDb, 5);

        $db->exec('ATTACH DATABASE \''.$changeDb.'\' AS ChangeDb');
        $db->exec('BEGIN EXCLUSIVE TRANSACTION');

        $qry = $db->query( // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
            'SELECT CASE WHEN '.
            'EXISTS(SELECT 1 FROM sqlite_master WHERE type = \'table\' AND name = \'ProductChange\') AND '.
            'EXISTS(SELECT 1 FROM ChangeDb.sqlite_master '.
                'WHERE type = \'table\' AND name = \'ProductChangeProcessed\') '.
            'THEN -1 ELSE 0 END'
        );
        $processProductChange = $qry->fetchColumn();
        $qry->closeCursor();

        $qry = $db->query( // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
            'SELECT CASE WHEN '.
            'EXISTS(SELECT 1 FROM sqlite_master WHERE type = \'table\' AND name = \'CategoryChange\') AND '.
            'EXISTS(SELECT 1 FROM ChangeDb.sqlite_master '.
                'WHERE type = \'table\' AND name = \'CategoryChangeProcessed\') '.
            'THEN -1 ELSE 0 END'
        );
        $processCategoryChange = $qry->fetchColumn();
        $qry->closeCursor();

        $qry = $db->query( // @codingStandardsIgnoreLine // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
            'SELECT CASE WHEN '.
            'EXISTS(SELECT 1 FROM sqlite_master WHERE type = \'table\' AND name = \'OrderChange\') AND '.
            'EXISTS(SELECT 1 FROM ChangeDb.sqlite_master '.
                'WHERE type = \'table\' AND name = \'OrderChangeProcessed\') '.
            'THEN -1 ELSE 0 END'
        );
        $processOrderChange = $qry->fetchColumn();
        $qry->closeCursor();

        if ($processProductChange) {
            $db->exec(
                'DELETE FROM ProductChange '.
                'WHERE EXISTS('.
                'SELECT 1 FROM ProductChangeProcessed '.
                'WHERE ExternalReference = ProductChange.ExternalReference AND '.
                'stamp = ProductChange.stamp'.
                ')'
            );
        }

        if ($processCategoryChange) {
            $db->exec(
                'DELETE FROM CategoryChange '.
                'WHERE EXISTS('.
                'SELECT 1 FROM CategoryChangeProcessed '.
                'WHERE ExternalReference = CategoryChange.ExternalReference AND '.
                'stamp = CategoryChange.stamp'.
                ')'
            );
        }

        if ($processOrderChange) {
            $db->exec(
                'DELETE FROM OrderChange '.
                'WHERE EXISTS('.
                'SELECT 1 FROM OrderChangeProcessed '.
                'WHERE ExternalReference = OrderChange.ExternalReference AND '.
                'stamp = OrderChange.stamp'.
                ')'
            );
        }

        $db->exec('COMMIT');
    }

    public function productTotals($storeId)
    {
        $storeId; // unused param

        $productFlatState = $this->productFlatState->create(['isAvailable' => false]);

        $configurableProducts = $this->productCollectionFactory
            ->create(['catalogProductFlatState' => $productFlatState])
            ->removeAttributeToSelect()
            ->addAttributeToSelect('entity_id')
            ->addAttributeToFilter('type_id', ['eq' => 'configurable']);

        $configurableCount = $configurableProducts->getSize();

        $simpleProducts = $this->productCollectionFactory
            ->create(['catalogProductFlatState' => $productFlatState])
            ->removeAttributeToSelect()
            ->addAttributeToSelect('entity_id')
            ->addAttributeToFilter('type_id', ['in' => ['simple', 'virtual']]);

        $simpleCount = $simpleProducts->getSize();

        $groupedProducts = $this->productCollectionFactory
            ->create(['catalogProductFlatState' => $productFlatState])
            ->removeAttributeToSelect()
            ->addAttributeToSelect('entity_id')
            ->addAttributeToFilter('type_id', ['eq' => 'grouped']);

        $groupCount = $groupedProducts->getSize();

        return [
            'simplecount' => $simpleCount,
            'configurablecount' => $configurableCount,
            'groupcount' => $groupCount
        ];
    }

    public function syncTax($syncDb, $storeId)
    {
        $storeId;

        $db = $this->_getSyncDb($syncDb, 5);

        $db->exec('BEGIN EXCLUSIVE TRANSACTION');

        $db->exec('DELETE FROM TaxClass');
        $db->exec('DELETE FROM TaxCalculation');
        $db->exec('DELETE FROM TaxCalculationRule');
        $db->exec('DELETE FROM TaxCalculationRate');

        $taxClasses = $this->taxClassCollectionFactory->create()
                ->addFieldToSelect(['class_id', 'class_type', 'class_name'])
                ->addFieldToFilter('class_type', ['eq' => 'PRODUCT']);

        $insertTaxClass = $db->prepare('INSERT OR IGNORE INTO TaxClass (ID, Type, Name) VALUES (?, ?, ?)');

        foreach ($taxClasses as $taxClass) {
            $TaxID = $taxClass->getId();
            $TaxName = $taxClass->getClassName();
            $TaxType = $taxClass->getClassType();

            $insertTaxClass->bindParam(1, $TaxID);
            $insertTaxClass->bindParam(2, $TaxType);
            $insertTaxClass->bindParam(3, $TaxName);
            $insertTaxClass->execute();
        }

        $ebayGroup = $this->groupFactory->create();
        $ebayGroup->load('eBay', 'customer_group_code');
        if (!$ebayGroup->getId()) {
            $ebayGroup->load(1);
        }

        $customerTaxClassId = $ebayGroup->getTaxClassId();

        $taxCalcs = $this->taxCalcCollectionFactory->create();
        if ($customerTaxClassId) {
            $taxCalcs->addFieldToFilter('customer_tax_class_id', ['eq' => $customerTaxClassId]);
        }

        $insertTaxCalc = $db->prepare('INSERT OR IGNORE INTO TaxCalculation '.
        '(ID, TaxRateID, TaxRuleID, ProductTaxClassID, CustomerTaxClassID) '.
        'VALUES (?, ?, ?, ?, ?)');

        $TaxRuleIDs = [];

        foreach ($taxCalcs as $taxCalc) {
            $TaxCalcID = $taxCalc->getId();
            $TaxRateID = $taxCalc->getTaxCalculationRateId();
            $TaxRuleID = $taxCalc->getTaxCalculationRuleId();
            $ProductClass = $taxCalc->getProductTaxClassId();
            $CustomerClass = $taxCalc->getCustomerTaxClassId();

            $insertTaxCalc->bindParam(1, $TaxCalcID);
            $insertTaxCalc->bindParam(2, $TaxRateID);
            $insertTaxCalc->bindParam(3, $TaxRuleID);
            $insertTaxCalc->bindParam(4, $ProductClass);
            $insertTaxCalc->bindParam(5, $CustomerClass);
            $insertTaxCalc->execute();

            $TaxRuleIDs[] = $TaxRuleID;
        }

        $taxRules = $this->taxCalcRuleCollectionFactory->create();
        $taxRules->addFieldToFilter('tax_calculation_rule_id', ['in' => $TaxRuleIDs]);

        $insertTaxRule = $db->prepare('INSERT OR IGNORE INTO TaxCalculationRule '.
        '(ID, Code, Priority, Position, CalculateSubTotal) '.
        'VALUES (?, ?, ?, ?, ?)');

        foreach ($taxRules as $taxRule) {
            $TaxRuleID = $taxRule->getId();
            $TaxRuleCode = $taxRule->getCode();
            $TaxRulePriority = $taxRule->getPriority();
            $TaxRulePosition = $taxRule->getPosition();
            $TaxRuleCalcSubTotal = $taxRule->getCalculateSubtotal();

            $insertTaxRule->bindParam(1, $TaxRuleID);
            $insertTaxRule->bindParam(2, $TaxRuleCode);
            $insertTaxRule->bindParam(3, $TaxRulePriority);
            $insertTaxRule->bindParam(4, $TaxRulePosition);
            $insertTaxRule->bindParam(5, $TaxRuleCalcSubTotal);
            $insertTaxRule->execute();
        }

        $regionName = $this->resourceConnection->getTableName('directory_country_region');

        $taxRates = $this->taxCalcRateCollectionFactory->create();
        // @codingStandardsIgnoreStart
        $taxRates->getSelect()->joinLeft(
            ['region' => $regionName],
            'region.region_id = main_table.tax_region_id',
            ['tax_region_code' => 'region.code', 'tax_region_name' => 'region.default_name']
        );
        // @codingStandardsIgnoreEnd

        $insertTaxRate = $db->prepare('INSERT OR IGNORE INTO TaxCalculationRate '.
        '(ID, Country, RegionID, RegionName, RegionCode, PostCode, Code, Rate, IsRange, ZipFrom, ZipTo) '.
        'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ? ,?)');

        foreach ($taxRates as $taxRate) {
            $TaxRateID = $taxRate->getId();
            $TaxCountry = $taxRate->getTaxCountryId();
            $TaxRegionID = $taxRate->getTaxRegionId();
            $TaxRegionName = $taxRate->getTaxRegionName();
            $TaxRegionCode = $taxRate->getTaxRegionCode();
            $TaxPostCode = $taxRate->getTaxPostcode() == null ? '*' : $taxRate->getTaxPostcode();
            $TaxCode = $taxRate->getCode() ? $taxRate->getCode() : '';
            $TaxRate = $taxRate->getRate();
            $TaxZipIsRange = $taxRate->getZipIsRange();
            $TaxZipFrom = $taxRate->getZipFrom();
            $TaxZipTo = $taxRate->getZipTo();

            $insertTaxRate->execute(
                [
                    $TaxRateID,
                    $TaxCountry,
                    $TaxRegionID,
                    $TaxRegionName,
                    $TaxRegionCode,
                    $TaxPostCode,
                    $TaxCode,
                    $TaxRate,
                    $TaxZipIsRange,
                    $TaxZipFrom,
                    $TaxZipTo
                ]
            );
        }

        $db->exec('COMMIT TRANSACTION');
    }

    public function syncStaticBlocks($syncDb, $storeId)
    {
        $db = $this->_getSyncDb($syncDb, 5);

        $db->exec('BEGIN EXCLUSIVE TRANSACTION');

        $db->exec('DELETE FROM StaticBlock');

        $insertStaticBlock = $db->prepare('INSERT OR IGNORE INTO StaticBlock (BlockID, Title, Identifier, Content) '.
        'VALUES (?, ?, ?, ?)');

        $staticBlocks = $this->cmsBlockCollectionFactory->create();
        $staticBlocks->addStoreFilter($storeId);

        foreach ($staticBlocks as $block) {
            $BlockID = $block->getId();
            $Title = $block->getTitle();
            $Identifier = $block->getIdentifier();

            $Content = $block->getContent();
            if(is_array($Content)) {
                $Content = implode('', $Content);
            }
            $Content = $this->codistoHelper->processCmsContent($Content, $storeId);

            $insertStaticBlock->bindParam(1, $BlockID);
            $insertStaticBlock->bindParam(2, $Title);
            $insertStaticBlock->bindParam(3, $Identifier);
            $insertStaticBlock->bindParam(4, $Content);
            $insertStaticBlock->execute();
        }

        $db->exec('COMMIT TRANSACTION');
    }

    public function syncStores($syncDb, $storeId)
    {
        $storeId;

        $db = $this->_getSyncDb($syncDb);

        $db->exec('BEGIN EXCLUSIVE TRANSACTION');
        $db->exec('DELETE FROM Store');
        $db->exec('DELETE FROM StoreMerchant');

        $insertStore = $db->prepare('INSERT OR REPLACE INTO Store (ID, Code, Name, Currency) VALUES (?, ?, ?, ?)');
        $insertStoreMerchant = $db->prepare('INSERT OR REPLACE INTO StoreMerchant (StoreID, MerchantID) VALUES (?, ?)');

        $store = $this->storeManager->getStore(0);

        $StoreID = 0;
        $StoreCode = 'admin';
        $StoreName = '';
        $StoreCurrency = $store->getCurrentCurrencyCode();

        $insertStore->execute([$StoreID, $StoreCode, $StoreName, $StoreCurrency]);

        $defaultMerchantList = $store->getConfig('codisto/merchantid');
        if ($defaultMerchantList) {
            $merchantlist = $this->json->jsonDecode($defaultMerchantList);
            if (!is_array($merchantlist)) {
                $merchantlist = [$merchantlist];
            }

            foreach ($merchantlist as $MerchantID) {
                $insertStoreMerchant->execute([$StoreID, $MerchantID]);
            }
        }

        $stores = $this->storeManager->getStores(false);

        foreach ($stores as $store) {
            $StoreID = $store->getId();

            if ($StoreID == 0) {
                continue;
            }

            $StoreCode = $store->getCode();
            $StoreName = $store->getName();
            $StoreCurrency = $this->storeManager->getStore($StoreID)->getCurrentCurrencyCode();

            $insertStore->execute([$StoreID, $StoreCode, $StoreName, $StoreCurrency]);

            $storeMerchantList = $store->getConfig('codisto/merchantid');
            if ($storeMerchantList && $storeMerchantList != $defaultMerchantList) {
                $merchantlist = $this->json->jsonDecode($storeMerchantList);
                if (!is_array($merchantlist)) {
                    $merchantlist = [$merchantlist];
                }

                foreach ($merchantlist as $MerchantID) {
                    $insertStoreMerchant->execute([$StoreID, $MerchantID]);
                }
            }
        }

        $db->exec('COMMIT TRANSACTION');
    }

    public function syncOrders($syncDb, $orders, $storeId)
    {
        $store = $this->storeManager->getStore($storeId);

        $db = $this->_getSyncDb($syncDb, 5);

        $insertOrders = $db->prepare('INSERT OR REPLACE INTO [Order] '.
        '(ID, Status, PaymentDate, ShipmentDate, Carrier, TrackingNumber, ExternalReference) '.
        'VALUES (?, ?, ?, ?, ?, ?, ?)');

        $coreResource = $this->resourceConnection;

        $invoiceName = $coreResource->getTableName('sales/invoice');
        $shipmentName = $coreResource->getTableName('sales/shipment');
        $shipmentTrackName = $coreResource->getTableName('sales/shipment_track');

        $db->exec('BEGIN EXCLUSIVE TRANSACTION');

        $orders = $this->salesOrderCollectionFactory->create()
                    ->addFieldToSelect(['codisto_orderid', 'status'])
                    ->addFieldToSelect('entity_id', 'externalreference')
                    ->addAttributeToFilter('codisto_orderid', ['in' => $orders]);
        // @codingStandardsIgnoreStart
        $orders->getSelect()->joinLeft(
            ['i' => $invoiceName],
            'i.order_id = main_table.entity_id AND i.state = 2',
            ['pay_date' => 'MIN(i.created_at)']
        );
        $orders->getSelect()->joinLeft(
            ['s' => $shipmentName],
            's.order_id = main_table.entity_id',
            ['ship_date' => 'MIN(s.created_at)']
        );
        $orders->getSelect()->joinLeft(
            ['t' => $shipmentTrackName],
            't.order_id = main_table.entity_id',
            [
                'carrier' => 'GROUP_CONCAT(COALESCE(t.title, \'\') SEPARATOR \',\')',
                'track_number' => 'GROUP_CONCAT(COALESCE(t.track_number, \'\') SEPARATOR \',\')'
            ]
        );
        $orders->getSelect()->group(['main_table.entity_id', 'main_table.codisto_orderid', 'main_table.status']);
        // @codingStandardsIgnoreEnd
        $orders->setOrder('entity_id', 'ASC');

        $iterator = $this->iteratorFactory->create();

        $iterator->walk(
            $orders->getSelect(),
            [[$this, 'syncOrderData']],
            ['db' => $db, 'preparedStatement' => $insertOrders, 'store' => $store]
        );

        $db->exec('COMMIT TRANSACTION');
    }

    private function _getSyncDb($syncDb, $timeout = 60)
    {
        $db = $this->codistoHelper->createSqliteConnection($syncDb);

        $this->codistoHelper->prepareSqliteDatabase($db, $timeout);

        $db->exec('BEGIN EXCLUSIVE TRANSACTION');
        $db->exec(
            'CREATE TABLE IF NOT EXISTS Progress('.
            'entity_id integer NOT NULL, '.
            'State text NOT NULL, '.'
            Sentinel integer NOT NULL PRIMARY KEY AUTOINCREMENT, CHECK(Sentinel=1)'.
            ')'
        );
        $db->exec(
            'CREATE TABLE IF NOT EXISTS Category('.
            'ExternalReference text NOT NULL PRIMARY KEY, ParentExternalReference text NOT NULL, '.
            'Name text NOT NULL, LastModified datetime NOT NULL, Enabled bit NOT NULL, Sequence integer NOT NULL'.
            ')'
        );
        $db->exec(
            'CREATE TABLE IF NOT EXISTS CategoryProduct ('.
            'CategoryExternalReference text NOT NULL, '.
            'ProductExternalReference text NOT NULL, '.
            'Sequence integer NOT NULL, '.
            'PRIMARY KEY(CategoryExternalReference, ProductExternalReference)'.
            ')'
        );
        $db->exec(
            'CREATE TABLE IF NOT EXISTS Product ('.
            'ExternalReference text NOT NULL PRIMARY KEY, '.
            'Type text NOT NULL, '.
            'Code text NULL, '.
            'Name text NOT NULL, '.
            'Price real NOT NULL, '.
            'ListPrice real NOT NULL, '.
            'TaxClass text NOT NULL, '.
            'Description text NOT NULL, '.
            'Enabled bit NOT NULL,  '.
            'StockControl bit NOT NULL, StockLevel integer NOT NULL, '.
            'Weight real NULL, '.
            'InStore bit NOT NULL'.
            ')'
        );

        $db->exec(
            'CREATE TABLE IF NOT EXISTS ProductOptionValue ('.'
            ExternalReference text NOT NULL, Sequence integer NOT NULL)'
        );
        $db->exec(
            'CREATE INDEX IF NOT EXISTS '.'
            IX_ProductOptionValue_ExternalReference ON ProductOptionValue(ExternalReference)'
        );

        $db->exec(
            'CREATE TABLE IF NOT EXISTS ProductQuestion ('.
            'ExternalReference text NOT NULL PRIMARY KEY, '.
            'ProductExternalReference text NOT NULL, '.
            'Name text NOT NULL, '.
            'Type text NOT NULL, '.
            'Sequence integer NOT NULL'.'
            )'
        );
        $db->exec(
            'CREATE INDEX IF NOT EXISTS '.'
            IX_ProductQuestion_ProductExternalReference ON ProductQuestion(ProductExternalReference)'
        );
        $db->exec(
            'CREATE TABLE IF NOT EXISTS ProductQuestionAnswer ('.'
            ProductQuestionExternalReference text NOT NULL, '.
            'Value text NOT NULL, '.
            'PriceModifier text NOT NULL, '.
            'SKUModifier text NOT NULL, '.
            'Sequence integer NOT NULL'.
            ')'
        );
        $db->exec(
            'CREATE INDEX IF NOT EXISTS '.
            'IX_ProductQuestionAnswer_ProductQuestionExternalReference ON '.
            'ProductQuestionAnswer(ProductQuestionExternalReference)'
        );

        $db->exec(
            'CREATE TABLE IF NOT EXISTS SKU ('.
            'ExternalReference text NOT NULL PRIMARY KEY, '.
            'Code text NULL, '.
            'ProductExternalReference text NOT NULL, '.
            'Name text NOT NULL, StockControl bit NOT NULL, '.
            'StockLevel integer NOT NULL, '.
            'Price real NOT NULL, '.
            'Enabled bit NOT NULL, '.
            'InStore bit NOT NULL'.
            ')'
        );
        $db->exec(
            'CREATE INDEX IF NOT EXISTS IX_SKU_ProductExternalReference ON SKU(ProductExternalReference)'
        );
        $db->exec(
            'CREATE TABLE IF NOT EXISTS SKUMatrix ('.
            'SKUExternalReference text NOT NULL, '.
            'ProductExternalReference text NOT NULL, '.
            'Code text NULL, '.
            'OptionName text NOT NULL, '.
            'OptionValue text NOT NULL, '.
            'ProductOptionExternalReference text NOT NULL, '.
            'ProductOptionValueExternalReference text NOT NULL'.
            ')'
        );
        $db->exec(
            'CREATE INDEX IF NOT EXISTS IX_SKUMatrix_SKUExternalReference ON SKUMatrix(SKUExternalReference)'
        );

        $db->exec(
            'CREATE TABLE IF NOT EXISTS SKULink ('.
            'SKUExternalReference text NOT NULL, '.
            'ProductExternalReference text NOT NULL, '.
            'Price real NOT NULL, '.
            'PRIMARY KEY (SKUExternalReference, ProductExternalReference)'.
            ')'
        );

        $db->exec(
            'CREATE TABLE IF NOT EXISTS ProductImage ('.
            'ProductExternalReference text NOT NULL, '.
            'URL text NOT NULL, '.
            'Tag text NOT NULL DEFAULT \'\', '.
            'Sequence integer NOT NULL, '.
            'Enabled bit NOT NULL DEFAULT -1'.
            ')'
        );
        $db->exec(
            'CREATE INDEX IF NOT EXISTS '.
            'IX_ProductImage_ProductExternalReference ON ProductImage(ProductExternalReference)'
        );

        $db->exec(
            'CREATE TABLE IF NOT EXISTS ProductHTML ('.
            'ProductExternalReference text NOT NULL, '.
            'Tag text NOT NULL, HTML text NOT NULL, '.
            'PRIMARY KEY (ProductExternalReference, Tag)'.
            ')'
        );
        $db->exec(
            'CREATE INDEX IF NOT EXISTS '.
            'IX_ProductHTML_ProductExternalReference ON ProductHTML(ProductExternalReference)'
        );

        $db->exec(
            'CREATE TABLE IF NOT EXISTS ProductRelated ('.
            'RelatedProductExternalReference text NOT NULL, '.
            'ProductExternalReference text NOT NULL, '.
            'PRIMARY KEY (ProductExternalReference, RelatedProductExternalReference)'.
            ')'
        );

        $db->exec(
            'CREATE TABLE IF NOT EXISTS Attribute ('.'
            ID integer NOT NULL PRIMARY KEY, '.
            'Code text NOT NULL, '.
            'Label text NOT NULL, '.
            'Type text NOT NULL, Input text NOT NULL'.
            ')'
        );
        $db->exec(
            'CREATE TABLE IF NOT EXISTS AttributeGroupMap ('.
            'AttributeID integer NOT NULL, '.
            'GroupID integer NOT NULL, '.
            'PRIMARY KEY(AttributeID, GroupID)'.
            ')'
        );
        $db->exec(
            'CREATE TABLE IF NOT EXISTS AttributeGroup ('.
            'ID integer NOT NULL PRIMARY KEY, '.
            'Name text NOT NULL'.
            ')'
        );
        $db->exec(
            'CREATE TABLE IF NOT EXISTS ProductAttributeValue ('.
            'ProductExternalReference text NOT NULL, '.
            'AttributeID integer NOT NULL, '.
            'Value text, '.
            'PRIMARY KEY (ProductExternalReference, AttributeID)'.
            ')'
        );
        $db->exec(
            'CREATE TABLE IF NOT EXISTS ProductAttributeDefaultValue ('.
            'ProductExternalReference text NOT NULL, '.
            'AttributeID integer NOT NULL, '.
            'Value text, '.
            'PRIMARY KEY (ProductExternalReference, AttributeID)'.
            ')'
        );

        $db->exec(
            'CREATE TABLE IF NOT EXISTS TaxClass ('.
            'ID integer NOT NULL PRIMARY KEY, '.
            'Type text NOT NULL, '.
            'Name text NOT NULL'.
            ')'
        );
        $db->exec(
            'CREATE TABLE IF NOT EXISTS TaxCalculation('.
            'ID integer NOT NULL PRIMARY KEY, '.
            'TaxRateID integer NOT NULL, '.
            'TaxRuleID integer NOT NULL, '.
            'ProductTaxClassID integer NOT NULL, '.
            'CustomerTaxClassID integer NOT NULL'.
            ')'
        );
        $db->exec(
            'CREATE TABLE IF NOT EXISTS TaxCalculationRule('.
            'ID integer NOT NULL PRIMARY KEY, '.
            'Code text NOT NULL, '.
            'Priority integer NOT NULL, '.
            'Position integer NOT NULL, '.
            'CalculateSubTotal bit NOT NULL'.
            ')'
        );
        $db->exec(
            'CREATE TABLE IF NOT EXISTS TaxCalculationRate('.
            'ID integer NOT NULL PRIMARY KEY, '.
            'Country text NOT NULL, '.
            'RegionID integer NOT NULL, '.
            'RegionName text NULL, '.
            'RegionCode text NULL, '.
            'PostCode text NOT NULL, '.
            'Code text NOT NULL, '.
            'Rate real NOT NULL, '.
            'IsRange bit NULL, '.
            'ZipFrom text NULL, '.
            'ZipTo text NULL'.
            ')'
        );

        $db->exec(
            'CREATE TABLE IF NOT EXISTS Store('.
            'ID integer NOT NULL PRIMARY KEY, '.
            'Code text NOT NULL, '.
            'Name text NOT NULL, '.
            'Currency text NOT NULL'.
            ')'
        );
        $db->exec(
            'CREATE TABLE IF NOT EXISTS StoreMerchant('.
            'StoreID integer NOT NULL, '.
            'MerchantID integer NOT NULL, '.
            'PRIMARY KEY (StoreID, MerchantID)'.
            ')'
        );

        $db->exec(
            'CREATE TABLE IF NOT EXISTS [Order]('.
            'ID integer NOT NULL PRIMARY KEY, '.
            'Status text NOT NULL, '.
            'PaymentDate datetime NULL, '.
            'ShipmentDate datetime NULL, '.
            'Carrier text NOT NULL, '.
            'TrackingNumber text NOT NULL, '.
            'ExternalReference text NOT NULL DEFAULT \'\','.
            'MerchantID text NOT NULL DEFAULT \'\''.
            ')'
        );

        $db->exec(
            'CREATE TABLE IF NOT EXISTS StaticBlock ('.
            'BlockID integer NOT NULL PRIMARY KEY, '.
            'Title text NOT NULL, '.
            'Identifier text NOT NULL, '.
            'Content text NOT NULL'.
            ')'
        );

        $db->exec(
            'CREATE TABLE IF NOT EXISTS Configuration ('.
            'configuration_id integer, '.
            'configuration_title text, '.
            'configuration_key text, '.
            'configuration_value text, '.
            'configuration_description text, '.
            'configuration_group_id integer, '.
            'sort_order integer, '.
            'last_modified datetime, '.
            'date_added datetime, '.
            'use_function text, '.
            'set_function text'.
            ')'
        );

        try {
            $db->exec('SELECT 1 FROM [Order] WHERE Carrier IS NULL LIMIT 1');
        } catch (\Exception $e) {
            $db->exec(
                'CREATE TABLE NewOrder ('.
                'ID integer NOT NULL PRIMARY KEY, '.
                'Status text NOT NULL, '.
                'PaymentDate datetime NULL, '.
                'ShipmentDate datetime NULL, '.
                'Carrier text NOT NULL, '.
                'TrackingNumber text NOT NULL, '.
                'ExternalReference text NOT NULL DEFAULT \'\''.
                ')'
            );
            $db->exec(
                'INSERT INTO NewOrder '.
                'SELECT ID, Status, PaymentDate, ShipmentDate, \'Unknown\', TrackingNumber, \'\' '.
                'FROM [Order]'
            );
            $db->exec(
                'DROP TABLE [Order]'
            );
            $db->exec(
                'ALTER TABLE NewOrder RENAME TO [Order]'
            );
        }

        try {
            $db->exec('SELECT 1 FROM [Order] WHERE ExternalReference IS NULL LIMIT 1');
        } catch (\Exception $e) {
            $db->exec('ALTER TABLE [Order] ADD COLUMN ExternalReference text NOT NULL DEFAULT \'\'');
        }

        try {
            $db->exec('SELECT 1 FROM [Order] WHERE MerchantID IS NULL LIMIT 1');
        } catch (\Exception $e) {
            $db->exec('ALTER TABLE [Order] ADD COLUMN MerchantID text NOT NULL DEFAULT \'\'');
        }

        $db->exec('COMMIT TRANSACTION');

        return $db;
    }

    private function _getTemplateDb($templateDb)
    {
        $db = $this->codistoHelper->createSqliteConnection($templateDb);

        $this->codistoHelper->prepareSqliteDatabase($db, 60);

        $db->exec('BEGIN EXCLUSIVE TRANSACTION');
        $db->exec(
            'CREATE TABLE IF NOT EXISTS File ('.
            'Name text NOT NULL PRIMARY KEY, Content blob NOT NULL, '.
            'LastModified datetime NOT NULL, Changed bit NOT NULL DEFAULT -1'.
            ')'
        );
        $db->exec('COMMIT TRANSACTION');

        return $db;
    }

    private function _filesInDir($dir, $prefix = '')
    {
        $dir = rtrim($dir, '\\/');
        $result = [];

        try {
            if (is_dir($dir)) { // @codingStandardsIgnoreLine
                $scan = @scandir($dir); // @codingStandardsIgnoreLine
                if ($scan === false) {
                    return $result;
                }

                foreach ($scan as $f) {
                    if ($f === '.' or $f === '..') {
                        continue;
                    }

                    if (is_dir("$dir/$f")) { // @codingStandardsIgnoreLine
                        $result = array_merge($result, $this->_filesInDir("$dir/$f", "$f/"));
                        continue;
                    }

                    $result[] = $prefix.$f;
                }
            }
        } catch (\Exception $e) {
            $e;
            // ignore
        }

        return $result;
    }
}
