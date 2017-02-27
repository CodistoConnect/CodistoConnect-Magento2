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

class Sync
{
    private $resourceConnection;

    private $productCollectionFactory;
    private $productAttributeCollectionFactory;
    private $productAttributeGroupFactory;
    private $cmsBlockCollectionFactory;
    private $taxClassCollectionFactory;
    private $taxCalcCollectionFactory;
    private $taxCalcRuleCollectionFactory;
    private $taxCalcRateCollectionFactory;
    private $storeCollectionFactory;
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

    private $iteratorFactory;

    private $codistoHelper;

    private $currentEntityId;
    private $productsProcessed;
    private $ordersProcessed;

    private $ebayGroupId;

    private $attributeCache;
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
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory $productAttributeCollectionFactory,
        \Magento\Catalog\Model\Product\Attribute\GroupFactory $productAttributeGroupFactory,
        \Magento\Cms\Model\ResourceModel\Block\CollectionFactory $cmsBlockCollectionFactory,
        \Magento\Tax\Model\ResourceModel\TaxClass\CollectionFactory $taxClassCollectionFactory,
        \Magento\Tax\Model\ResourceModel\Calculation\CollectionFactory $taxCalcCollectionFactory,
        \Magento\Tax\Model\ResourceModel\Calculation\Rule\CollectionFactory $taxCalcRuleCollectionFactory,
        \Magento\Tax\Model\ResourceModel\Calculation\Rate\CollectionFactory $taxCalcRateCollectionFactory,
        \Magento\Store\Model\ResourceModel\Store\CollectionFactory $storeCollectionFactory,
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
        \Magento\Catalog\Model\Product\Media\ConfigFactory $mediaConfigFactory,
        \Magento\Framework\Model\ResourceModel\IteratorFactory $iteratorFactory,
        \Magento\Catalog\Model\Indexer\Product\Flat\StateFactory $productFlatState,
        \Magento\Catalog\Model\Indexer\Category\Flat\StateFactory $categoryFlatState,
        \Codisto\Connect\Helper\Data $codistoHelper
    // @codingStandardsIgnoreEnd MEQP2.Classes.CollectionDependency.CollectionDependency
    ) {

        $this->resourceConnection = $resourceConnection;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productAttributeCollectionFactory = $productAttributeCollectionFactory;
        $this->productAttributeGroupFactory = $productAttributeGroupFactory;
        $this->cmsBlockCollectionFactory = $cmsBlockCollectionFactory;
        $this->taxClassCollectionFactory = $taxClassCollectionFactory;
        $this->taxCalcCollectionFactory = $taxCalcCollectionFactory;
        $this->taxCalcRuleCollectionFactory = $taxCalcRuleCollectionFactory;
        $this->taxCalcRateCollectionFactory = $taxCalcRateCollectionFactory;
        $this->storeCollectionFactory = $storeCollectionFactory;
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
        $this->mediaConfigFactory = $mediaConfigFactory;
        $this->iteratorFactory = $iteratorFactory;
        $this->productFlatState = $productFlatState;
        $this->categoryFlatState = $categoryFlatState;
        $this->urlBuilder = $context->getUrl();
        $this->codistoHelper = $codistoHelper;

        $this->attributecache = [];
        $this->groupCache = [];
        $this->optionCache = [];
        $this->optionTextCache = [];

        $ebayGroup = $groupFactory->create();
        $ebayGroup->load('eBay', 'customer_group_code');

        $this->ebayGroupId = $ebayGroup->getId();
        if (!$this->ebayGroupId) {
            $this->ebayGroupId = \Magento\Customer\Model\GroupManagement::NOT_LOGGED_IN_ID;
        }

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

    public function templateRead($templateDb)
    {
        $ebayDesignDir = $this->dirList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::APP).'/design/ebay/';

        try {
            $db = $this->_getTemplateDb($templateDb);

            $insert = $db->prepare('INSERT OR IGNORE INTO File(Name, Content, LastModified) VALUES (?, ?, ?)');
            $update = $db->prepare('UPDATE File SET Content = ?, Changed = -1 WHERE Name = ? AND LastModified != ?');

            $filelist = $this->_fileInDir($ebayDesignDir);

            $db->exec('BEGIN EXCLUSIVE TRANSACTION');

            foreach ($filelist as $key => $name) {
                try {
                    $fileName = $ebayDesignDir.$name;

                    if (in_array($name, [ 'README' ])) {
                        continue;
                    }

                    $content = @file_get_contents($fileName);
                    if ($content === false) {
                        continue;
                    }

                    $stat = stat($fileName);

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
                    if (!file_exists($fileName)) {
                        $dir = dirname($fileName);

                        if (!is_dir($dir)) {
                            mkdir($dir.'/', 0755, true);
                        }

                        @file_put_contents($fileName, $content);
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
            ->columns(
                [
                    'codisto_in_store'=> new \Zend_Db_Expr(
                        'CASE WHEN `e`.entity_id IN ('.
                        'SELECT product_id FROM `'.$catalogWebsiteName.'` WHERE website_id IN ('.
                        'SELECT website_id FROM `'.$storeName.'` WHERE store_id = '.$storeId.' '.
                        'OR EXISTS(SELECT 1 FROM `'.$storeName.'` WHERE store_id = '.$storeId.' AND website_id = 0))'.
                        ') THEN -1 ELSE 0 END'
                    )
                ]
            )
            ->where($sqlCheckModified);

        // Simple Products not participating as configurable skus
        $simpleProducts = $this->productCollectionFactory->create(['catalogProductFlatState' => $productFlatState])
            ->addAttributeToSelect($this->availableProductFields, 'left')
            ->addAttributeToFilter('type_id', ['eq' => 'simple'])
            ->addAttributeToFilter('entity_id', ['in' => $ids]);

        $simpleProducts->getSelect()
            ->columns(
                [
                    'codisto_in_store'=> new \Zend_Db_Expr(
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
            ->columns(
                [
                    'codisto_in_store' => new \Zend_Db_Expr(
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

    public function syncCategoryData($args)
    {
        $categoryData = $args['row'];

        if ($categoryData['level'] < 2) {
            return;
        }

        $insertSQL = $args['preparedStatement'];
        $insertFields = ['entity_id', 'name', 'parent_id', 'updated_at', 'is_active', 'position'];

        if ($categoryData['level'] == 2) {
            $categoryData['parent_id'] = 0;
        }

        $data = [];
        foreach ($insertFields as $key) {
            $value = $categoryData[$key];

            if (!$value) {
                if ($key == 'entity_id') {
                    return;
                } elseif ($key == 'name') {
                    $value = '';
                } elseif ($key == 'parent_id') {
                    $value = 0;
                } elseif ($key == 'updated_at') {
                    $value = '1970-01-01 00:00:00';
                } elseif ($key == 'is_active') {
                    $value = 0;
                } elseif ($key == 'position') {
                    $value = 0;
                }
            }

            $data[] = $value;
        }

        $insertSQL->execute($data);
    }

    private function _syncProductPrice($store, $parentProduct, $options = null)
    {
        $addInfo = new \Magento\Framework\DataObject();

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
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $finalPrice = 0.0;
        }

        $price = $this->taxHelper->getTaxPrice(
            $parentProduct,
            $finalPrice,
            false,
            null,
            null,
            null,
            $store,
            null,
            false
        );

        return $price;
    }

    public function syncSKUData($args)
    {
        $skuData = $args['row'];
        $db = $args['db'];

        $store = $args['store'];

        $insertSKULinkSQL = $args['preparedskulinkStatement'];
        $insertSKUMatrixSQL = $args['preparedskumatrixStatement'];

        $attributes = $args['attributes'];

        $product = $this->productFactory->create();
        $product->setData($skuData)
            ->setStore($store)
            ->setStoreId($store->getId())
            ->setWebsiteId($store->getWebsiteId())
            ->setCustomerGroupId($this->ebayGroupId);

        $stockItem = $this->stockItemFactory->create();
        $stockItem->setStockId(\Magento\CatalogInventory\Model\Stock::DEFAULT_STOCK_ID)
            ->setProduct($product);

        $productParent = $args['parent_product'];

        $attributeCodes = [];
        $productAttributes = [];
        $attributeValues = [];

        foreach ($attributes as $attribute) {
            $prodAttr = $attribute->getProductAttribute();
            if ($prodAttr) {
                $attributeCodes[] = $prodAttr->getAttributeCode();
                $productAttributes[] = $prodAttr;
            }
        }

        if (!empty($attributeCodes)) {
            $attributeValues = $product->getResource()->getAttributeRawValue(
                $skuData['entity_id'],
                $attributeCodes,
                $store->getId()
            );
            if (!is_array($attributeValues)) {
                $attributeValues = [$attributeCodes[0] => $attributeValues];
            }

            $options = [];
            foreach ($productAttributes as $attribute) {
                $options[$attribute->getId()] = $attributeValues[$attribute->getAttributeCode()];
            }
        }

        if (!empty($options)) {
            $price = $this->_syncProductPrice($store, $productParent, $options);
            if (!$price) {
                $price = 0;
            }
        } else {
            $price = 0;
        }

        $insertSKULinkSQL->execute([$skuData['entity_id'], $args['parent_id'], $price]);

        // SKU Matrix
        foreach ($attributes as $attribute) {
            $productAttribute = $attribute->getProductAttribute();

            if ($productAttribute) {
                $productAttribute->setStoreId($store->getId());
                $productAttribute->setStore($store);

                $productOptionId = $productAttribute->getId();
                $productOptionValueId = isset($attributeValues[$productAttribute->getAttributeCode()]) ?
                                            $attributeValues[$productAttribute->getAttributeCode()] : null;

                if ($productOptionValueId != null) {
                    $attributeName = $attribute->getLabel();
                    $attributeValue = $productAttribute->getSource()->getOptionText($productOptionValueId);

                    $insertSKUMatrixSQL->execute(
                        [
                            $skuData['entity_id'],
                            $args['parent_id'],
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

        $iterator = $this->iteratorFactory->create();

        $iterator->walk(
            $childProducts->getSelect(),
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

    public function syncSimpleProductData($args)
    {
        $type = $args['type'];

        $db = $args['db'];

        $parentids;

        $store = $args['store'];
        $productData = $args['row'];

        $product_id = $productData['entity_id'];

        if (isset($args['preparedcheckproductStatement'])) {
            $checkProductSQL = $args['preparedcheckproductStatement'];
            $checkProductSQL->execute([$product_id]);
            if ($checkProductSQL->fetchColumn()) {
                $checkProductSQL->closeCursor();
                return;
            }
            $checkProductSQL->closeCursor();
        }

        $product = $this->productFactory->create();
        $product->setData($productData)
                ->setStore($store)
                ->setStoreId($store->getId())
                ->setWebsiteId($store->getWebsiteId())
                ->setCustomerGroupId($this->ebayGroupId)
                ->setIsSuperMode(true);

        $stockItem = $this->stockItemFactory->create();
        $stockItem->setStockId(\Magento\CatalogInventory\Model\Stock::DEFAULT_STOCK_ID)
                    ->setProduct($product);

        $stockItem->getResource()->loadByProductId($stockItem, $product_id, $stockItem->getStockId());

        $insertSQL = $args['preparedStatement'];
        $insertCategorySQL = $args['preparedcategoryproductStatement'];
        $insertImageSQL = $args['preparedimageStatement'];
        $insertHTMLSQL = $args['preparedproducthtmlStatement'];
        $insertRelatedSQL = $args['preparedproductrelatedStatement'];
        $insertAttributeSQL = $args['preparedattributeStatement'];
        $insertAttributeGroupSQL = $args['preparedattributegroupStatement'];
        $insertAttributeGroupMapSQL = $args['preparedattributegroupmapStatement'];
        $insertProductAttributeSQL = $args['preparedproductattributeStatement'];
        $insertProductAttributeDefaultSQL = $args['preparedproductattributedefaultStatement'];
        $insertProductQuestionSQL = $args['preparedproductquestionStatement'];
        $insertProductAnswerSQL = $args['preparedproductanswerStatement'];

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

        $price = $this->_syncProductPrice($store, $product);

        $listPrice = $this->taxHelper->getTaxPrice(
            $product,
            $product->getPrice(),
            false,
            null,
            null,
            null,
            $store,
            null,
            false
        );
        if (!is_numeric($listPrice)) {
            $listPrice = $price;
        }

        $qty = $stockItem->getQty();
        if (!is_numeric($qty)) {
            $qty = 0;
        }

        // work around for description not appearing via collection
        if (!isset($productData['description'])) {
            $description = $product->getResource()->getAttributeRawValue(
                $product_id,
                'description',
                $store->getId()
            );
        } else {
            $description = $productData['description'];
        }

        $description = $this->codistoHelper->processCmsContent($description, $store->getId());
        if ($type == 'simple' &&
            $description == '') {
            if (!isset($parentids)) {
                $configurableparentids = $this->configurableTypeFactory->create()->getParentIdsByChild($product_id);
                $groupedparentids = $this->groupedTypeFactory->create()->getParentIdsByChild($product_id);
                $bundleparentids = $this->bundleTypeFactory->create()->getParentIdsByChild($product_id);

                $parentids = array_unique(array_merge($configurableparentids, $groupedparentids, $bundleparentids));
            }

            foreach ($parentids as $parentid) {
                $description = $product->getResource()->getAttributeRawValue($parentid, 'description', $store->getId());
                if ($description) {
                    $description = $this->codistoHelper->processCmsContent($description, $store->getId());
                    break;
                }
            }

            if (!$description) {
                $description = '';
            }
        }

        $productName = $productData['name'];
        if (!$productName) {
            $productName = '';
        }

        $data = [];

        $data[] = $product_id;
        $data[] = $type == 'configurable' ? 'c' : ($type == 'grouped' ? 'g' : 's');
        $data[] = $productData['sku'];
        $data[] = html_entity_decode($productName);
        $data[] = $price;
        $data[] = $listPrice;
        $data[] = isset($productData['tax_class_id'])
            && $productData['tax_class_id'] ?
                $productData['tax_class_id'] : '';
        $data[] = $description;
        $data[] = $productData['status'] != 1 ? 0 : -1;
        $data[] = $stockItem->getManageStock() ? -1 : 0;
        $data[] = (int)$qty;
        $data[] = isset($productData['weight'])
            && is_numeric($productData['weight']) ?
                (float)$productData['weight'] : $productData['weight'];
        $data[] = $productData['codisto_in_store'];

        $insertSQL->execute($data);

        $categoryIds = $product->getCategoryIds();
        foreach ($categoryIds as $categoryId) {
            $insertCategorySQL->execute([$product_id, $categoryId, 0]);
        }

        if (isset($productData['short_description']) && $productData['short_description'] != '') {
            $shortDescription =
                $this->codistoHelper->processCmsContent($productData['short_description'], $store->getId());

            $insertHTMLSQL->execute([$product_id, 'Short Description', $shortDescription]);
        }

        $attributeSet = [];
        $attributeCodes = [];
        $attributeTypes = [];
        $attributeCodeIDMap = [];

        $attributeSetID = $product->getAttributeSetId();
        if (isset($this->attributeCache[$attributeSetID])) {
            $attributes = $this->attributeCache[$attributeSetID];
        } else {
            $attributes = $product->getAttributes();

            $this->attributeCache[$attributeSetID] = $attributes;
        }

        foreach ($attributes as $attribute) {
            $attribute->setStoreId($store->getId());
            $attribute->setStore($store);

            $backend = $attribute->getBackEnd();
            if (!$backend->isStatic()) {
                $attributeID = $attribute->getId();
                $attributeCode = $attribute->getAttributeCode();
                $attributeTable = $backend->getTable();

                $attributeLabel = $attribute->getStoreLabel($store->getId());
                if (!isset($attributeLabel) || $attributeLabel === null || !$attributeLabel) {
                    if ($store->getId() != \Magento\Store\Model\Store::DEFAULT_STORE_ID) {
                        $attributeLabel = $attribute->getStoreLabel(\Magento\Store\Model\Store::DEFAULT_STORE_ID);
                    }
                    if (!isset($attributeLabel) || $attributeLabel === null || !$attributeLabel) {
                        $attributeLabel = $attribute->getFrontendLabel();
                        if (!isset($attributeLabel) || $attributeLabel === null || !$attributeLabel) {
                            $attributeLabel = $attribute->getName();
                            if (!isset($attributeLabel) || $attributeLabel === null || !$attributeLabel) {
                                $attributeLabel = '';
                            }
                        }
                    }
                }

                $attributeCodeIDMap[$attributeID] = $attributeCode;

                $attributeTypes[$attributeTable][$attributeID] = $attributeCode;

                $attributeSetInfo = $attribute->getAttributeSetInfo();
                $attributeGroupID = is_array($attributeSetInfo)
                                        && array_key_exists($attributeSetID, $attributeSetInfo)
                                        && is_array($attributeSetInfo[$attributeSetID])
                                        && array_key_exists('group_id', $attributeSetInfo[$attributeSetID]) ?
                                        $attributeSetInfo[$attributeSetID]['group_id'] : null;
                $attributeGroupName = '';

                if ($attributeGroupID) {
                    if (isset($this->groupCache[$attributeGroupID])) {
                        $attributeGroupName = $this->groupCache[$attributeGroupID];
                    } else {
                        $attributeGroup = $this->productAttributeGroupFactory->create();
                        $attributeGroup->load($attributeGroupID);

                        $attributeGroupName = html_entity_decode($attributeGroup->getAttributeGroupName());

                        $this->groupCache[$attributeGroupID] = $attributeGroupName;
                    }
                }

                $attributeFrontEnd = $attribute->getFrontend();

                $attributeData = [
                        'id' => $attributeID,
                        'code' => $attributeCode,
                        'name' => $attribute->getName(),
                        'label' => $attributeLabel,
                        'backend_type' => $attribute->getBackendType(),
                        'frontend_type' => $attributeFrontEnd->getInputType(),
                        'groupid' => $attributeGroupID,
                        'groupname' => $attributeGroupName,
                        'html' =>
                            ($attribute->getIsHtmlAllowedOnFront() && $attribute->getIsWysiwygEnabled()) ? true : false,
                        'source_model' => $attribute->getSourceModel()
                ];

                if (!isset($attributeData['frontend_type']) || $attributeData['frontend_type'] === null) {
                    $attributeData['frontend_type'] = '';
                }

                if ($attributeData['source_model']) {
                    if (isset($this->optionCache[$store->getId().'-'.$attribute->getId()])) {
                        $attributeData['source'] = $this->optionCache[$store->getId().'-'.$attribute->getId()];
                    } else {
                        try {
                            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

                            $attributeData['source'] = $objectManager->create($attributeData['source_model']);

                            if ($attributeData['source']) {
                                $attributeData['source']->setAttribute($attribute);

                                $this->optionCache[$store->getId().'-'.$attribute->getId()] = $attributeData['source'];
                            }
                        } catch (\Exception $e) {
                            $e;
                            // if we can't retrieve an attribute source model - skip it in sync
                        }
                    }
                } else {
                    $attributeData['source'] = $attribute->getSource();
                }

                $attributeSet[] = $attributeData;
                $attributeCodes[] = $attributeCode;
            }
        }

        $adapter = $this->resourceConnection->getConnection();

        $attrTypeSelects = [];

        foreach ($attributeTypes as $table => $_attributes) {
            $attrTypeSelect = $adapter->select()
                        ->from(['default_value' => $table], ['attribute_id'])
                        ->where('default_value.attribute_id IN (?)', array_keys($_attributes))
                        ->where('default_value.entity_id = :entity_id')
                        ->where('default_value.store_id = 0');

            if ($store->getId() == \Magento\Store\Model\Store::DEFAULT_STORE_ID) {
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
                    'AND store_value.entity_id = default_value.entity_id '.
                    'AND store_value.store_id = :store_id ',
                    ['attr_value' =>
                        new \Zend_Db_Expr('CAST(COALESCE(store_value.value, default_value.value) AS CHAR)')]
                );
                $attrTypeSelect->where('store_value.value IS NOT NULL OR default_value.value IS NOT NULL');
            }

            $attrTypeSelects[] = $attrTypeSelect;
        }

        if (!empty($attrTypeSelects)) {
            $attributeValues = [];

            $attrSelect = $adapter->select()->union($attrTypeSelects, \Zend_Db_Select::SQL_UNION_ALL);

            $attrArgs = [
                'entity_id' => $product_id,
                'store_id' => $store->getId()
            ];

            foreach ($adapter->fetchAll($attrSelect, $attrArgs, \Zend_Db::FETCH_NUM) as $attributeRow) {
                $attributeId = $attributeRow[0];

                $attributeCode = $attributeCodeIDMap[$attributeId];
                $attributeValues[$attributeCode] = $attributeRow;
            }

            foreach ($attributeSet as $attributeData) {
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

                    if (isset($defaultValue) && $defaultValue) {
                        $defaultValue = -1;
                    } else {
                        $defaultValue = 0;
                    }

                    if (isset($attributeValue) && $attributeValue) {
                        $attributeValue = -1;
                    } else {
                        $attributeValue = 0;
                    }
                } elseif ($attributeData['html']) {
                    if ($defaultValue == $attributeValue) {
                        $defaultValue =
                            $attributeValue =
                                $this->codistoHelper->processCmsContent($attributeValue, $store->getId());
                    } else {
                        $defaultValue = $this->codistoHelper->processCmsContent($defaultValue, $store->getId());
                        $attributeValue = $this->codistoHelper->processCmsContent($attributeValue, $store->getId());
                    }
                } elseif (in_array($attributeData['frontend_type'], ['select', 'multiselect'])) {
                    if (is_array($attributeValue)) {
                        if (isset($attributeData['source']) &&
                            method_exists($attributeData['source'], 'getOptionText')) {
                            $defaultValueSet = [];

                            foreach ($attributeValue as $attributeOptionId) {
                                if (isset($this->optionTextCache['0-'.$attributeData['id'].'-'.$attributeOptionId])) {
                                    $defaultValueSet[] =
                                        $this->optionTextCache['0-'.$attributeData['id'].'-'.$attributeOptionId];
                                } else {
                                    try {
                                        $attributeData['source']->getAttribute()->setStoreId(0);

                                        $attributeText = $attributeData['source']->getOptionText($attributeOptionId);

                                        $attributeData['source']->getAttribute()->setStoreId($store->getId());

                                        $this->optionTextCache['0-'.$attributeData['id'].'-'.$attributeOptionId] =
                                            $attributeText;

                                        $defaultValueSet[] = $attributeText;
                                    } catch (\Exception $e) {
                                        $e;
                                        // ignore errors retrieving attribute option value
                                    }
                                }
                            }

                            $defaultValue = $defaultValueSet;
                        }
                    } else {
                        if (isset($attributeData['source']) &&
                            method_exists($attributeData['source'], 'getOptionText')) {
                            if (isset($this->optionTextCache['0-'.$attributeData['id'].'-'.$attributeValue])) {
                                $defaultValue = $this->optionTextCache['0-'.$attributeData['id'].'-'.$attributeValue];
                            } else {
                                try {
                                    $attributeData['source']->getAttribute()->setStoreId(0);

                                    $attributeText = $attributeData['source']->getOptionText($attributeValue);

                                    $attributeData['source']->getAttribute()->setStoreId($store->getId());

                                    $this->optionTextCache['0-'.$attributeData['id'].'-'.$attributeValue] =
                                        $attributeText;

                                    $defaultValue = $attributeText;
                                } catch (\Exception $e) {
                                    $defaultValue = null;
                                }
                            }
                        }
                    }

                    if (is_array($attributeValue)) {
                        if (isset($attributeData['source']) &&
                            method_exists($attributeData['source'], 'getOptionText')) {
                            $attributeValueSet = [];

                            foreach ($attributeValue as $attributeOptionId) {
                                $optionTextCacheKey = $store->getId().'-'.$attributeData['id'].'-'.$attributeOptionId;

                                if (isset($this->optionTextCache[$optionTextCacheKey])) {
                                    $attributeValueSet[] = $this->optionTextCache[$optionTextCacheKey];
                                } else {
                                    try {
                                        $attributeText = $attributeData['source']->getOptionText($attributeOptionId);

                                        $this->optionTextCache[$optionTextCacheKey] = $attributeText;

                                        $attributeValueSet[] = $attributeText;
                                    } catch (\Exception $e) {
                                        $e;
                                        // ignore exceptions getting option text
                                    }
                                }
                            }

                            $attributeValue = $attributeValueSet;
                        }
                    } else {
                        if (isset($attributeData['source']) &&
                            method_exists($attributeData['source'], 'getOptionText')) {
                            $optionTextCacheKey = $store->getId().'-'.$attributeData['id'].'-'.$attributeValue;
                            if (isset($this->optionTextCache[$optionTextCacheKey])) {
                                $attributeValue = $this->optionTextCache[$optionTextCacheKey];
                            } else {
                                try {
                                    $attributeText = $attributeData['source']->getOptionText($attributeValue);

                                    $this->optionTextCache[$optionTextCacheKey] = $attributeText;

                                    $attributeValue = $attributeText;
                                } catch (\Exception $e) {
                                    $attributeValue = null;
                                }
                            }
                        }
                    }
                }

                if (isset($attributeValue) && $attributeValue !== null) {
                    if ($attributeData['html']) {
                        $insertHTMLSQL->execute([$product_id, $attributeData['label'], $attributeValue]);
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

                    if (is_array($attributeValue)) {
                        $attributeValue = implode(',', $attributeValue);
                    }

                    $insertProductAttributeSQL->execute([$product_id, $attributeData['id'], $attributeValue]);
                }

                if (isset($defaultValue) && $defaultValue !== null) {
                    if (is_array($defaultValue)) {
                        $defaultValue = implode(',', $defaultValue);
                    }

                    if ($defaultValue != $attributeValue) {
                        $insertProductAttributeDefaultSQL->execute([$product_id, $attributeData['id'], $defaultValue]);
                    }
                }
            }
        }

        $hasImage = false;
        $product->load('media_gallery');

        $primaryImage = isset($productData['image']) ? $productData['image'] : '';

        foreach ($product->getMediaGallery('images') as $image) {
            $imgURL = $product->getMediaConfig()->getMediaUrl($image['file']);

            $enabled = ($image['disabled'] == 0 ? -1 : 0);

            if ($image['file'] == $primaryImage) {
                $tag = '';
                $sequence = 0;
            } else {
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
            }

            $insertImageSQL->execute([$product_id, $imgURL, $tag, $sequence, $enabled]);

            $hasImage = true;
        }

        if ($type == 'simple'
            && !$hasImage) {
            $mediaConfig = $this->mediaConfigFactory->create();

            $baseSequence = 0;

            if (!isset($parentids)) {
                $configurableparentids = $this->configurableTypeFactory->create()->getParentIdsByChild($product_id);
                $groupedparentids = $this->groupedTypeFactory->create()->getParentIdsByChild($product_id);
                $bundleparentids = $this->bundleTypeFactory->create()->getParentIdsByChild($product_id);

                $parentids = array_unique(array_merge($configurableparentids, $groupedparentids, $bundleparentids));
            }

            foreach ($parentids as $parentid) {
                $baseImagePath = $product->getResource()->getAttributeRawValue($parentid, 'image', $store->getId());

                $parentProduct = $this->productFactory->create()
                                    ->setData(['entity_id' => $parentid, 'type_id' => 'simple']);

                $parentProduct->load('media_gallery');

                $mediaGallery = $parentProduct->getMediaGallery('images');

                $maxSequence = 0;
                $baseImageFound = false;

                foreach ($mediaGallery as $image) {
                    $imgURL = $mediaConfig->getMediaUrl($image['file']);

                    $enabled = ($image['disabled'] == 0 ? -1 : 0);

                    if (!$baseImageFound && ($image['file'] == $baseImagePath)) {
                        $tag = '';
                        $sequence = 0;
                        $baseImageFound = true;
                    } else {
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

                        $sequence += $baseSequence;

                        $maxSequence = max($sequence, $maxSequence);
                    }

                    $insertImageSQL->execute([$product_id, $imgURL, $tag, $sequence, $enabled]);
                }

                $baseSequence = $maxSequence;

                if ($baseImageFound) {
                    break;
                }
            }
        }

        // process related products
        $relatedProductIds = $product->getRelatedProductIds();
        foreach ($relatedProductIds as $relatedProductId) {
            $insertRelatedSQL->execute([$relatedProductId, $product_id]);
        }

        // process simple product question/answers

        $options = $product->getProductOptionsCollection();

        foreach ($options as $option) {
            $optionId = $option->getOptionId();
            $optionName = $option->getTitle();
            $optionType = $option->getType();
            $optionSortOrder = $option->getSortOrder();

            if ($optionId && $optionName) {
                if (!$optionType) {
                    $optionType = '';
                }

                if (!$optionSortOrder) {
                    $optionSortOrder = 0;
                }

                $insertProductQuestionSQL->execute(
                    [
                        $optionId,
                        $productData['entity_id'],
                        $optionName,
                        $optionType,
                        $optionSortOrder
                    ]
                );

                $values = $option->getValuesCollection();

                foreach ($values as $value) {
                    $valueName = $value->getTitle();
                    if (!$valueName) {
                        $valueName = '';
                    }

                    $valuePriceModifier = '';
                    if ($value->getPriceType() == 'fixed') {
                        $valuePriceModifier = 'Price + '.$value->getPrice();
                    }

                    if ($value->getPriceType() == 'percent') {
                        $valuePriceModifier = 'Price * '.($value->getPrice() / 100.0);
                    }

                    $valueSkuModifier = $value->getSku();
                    if (!$valueSkuModifier) {
                        $valueSkuModifier = '';
                    }

                    $valueSortOrder = $value->getSortOrder();
                    if (!$valueSortOrder) {
                        $valueSortOrder = 0;
                    }

                    $insertProductAnswerSQL->execute(
                        [
                            $optionId,
                            $valueName,
                            $valuePriceModifier,
                            $valueSkuModifier,
                            $valueSortOrder
                        ]
                    );
                }
            }
        }

        if ($type == 'simple') {
            $this->productsProcessed[] = $product_id;

            if ($productData['entity_id'] > $this->currentEntityId) {
                $this->currentEntityId = $product_id;
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

    public function syncChunk($syncDb, $simpleCount, $configurableCount, $storeId, $first)
    {
        $store = $this->storeManager->getStore($storeId);

        $db = $this->_getSyncDb($syncDb, 5);

        $insertCategory = $db->prepare(
            'INSERT OR REPLACE INTO Category'.
            '(ExternalReference, Name, ParentExternalReference, LastModified, Enabled, Sequence) '.
            'VALUES(?,?,?,?,?,?)'
        );
        $insertCategoryProduct = $db->prepare(
            'INSERT OR IGNORE INTO CategoryProduct'.
            '(ProductExternalReference, CategoryExternalReference, Sequence) '.
            'VALUES(?,?,?)'
        );
        $insertProduct = $db->prepare(
            'INSERT INTO Product'.
            '(ExternalReference, Type, Code, Name, Price, ListPrice, TaxClass, '.
                'Description, Enabled, StockControl, StockLevel, Weight, InStore) '.
            'VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $checkProduct = $db->prepare(
            'SELECT CASE WHEN EXISTS('.
                'SELECT 1 FROM Product WHERE ExternalReference = ?) THEN 1 ELSE 0 END'
        );
        $insertSKU = $db->prepare(
            'INSERT OR IGNORE INTO SKU'.
            '(ExternalReference, Code, ProductExternalReference, Name, '.
            'StockControl, StockLevel, Price, Enabled, InStore) '.
            'VALUES(?,?,?,?,?,?,?,?,?)'
        );
        $insertSKULink = $db->prepare(
            'INSERT OR REPLACE INTO SKULink '.
            '(SKUExternalReference, ProductExternalReference, Price) '.
            'VALUES(?, ?, ?)'
        );
        $insertSKUMatrix = $db->prepare(
            'INSERT INTO SKUMatrix'.
            '(SKUExternalReference, ProductExternalReference, Code, OptionName, '.
            'OptionValue, ProductOptionExternalReference, ProductOptionValueExternalReference) '.
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
            'VALUES(?, ?, ?)'
        );
        $insertProductRelated = $db->prepare(
            'INSERT OR IGNORE INTO ProductRelated'.
            '(RelatedProductExternalReference, ProductExternalReference) '.
            'VALUES(?, ?)'
        );
        $insertAttribute = $db->prepare(
            'INSERT OR REPLACE INTO Attribute'.
            '(ID, Code, Label, Type, Input) '.
            'VALUES(?, ?, ?, ?, ?)'
        );
        $insertAttributeGroup = $db->prepare(
            'INSERT OR REPLACE INTO AttributeGroup'.
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
            'VALUES(?, ?, ?)'
        );
        $insertProductAttributeDefault = $db->prepare(
            'INSERT OR IGNORE INTO ProductAttributeDefaultValue'.
            '(ProductExternalReference, AttributeID, Value) '.
            'VALUES(?, ?, ?)'
        );
        $insertProductQuestion = $db->prepare(
            'INSERT OR REPLACE INTO ProductQuestion'.
            '(ExternalReference, ProductExternalReference, Name, Type, Sequence) '.
            'VALUES(?, ?, ?, ?, ?)'
        );
        $insertProductAnswer = $db->prepare(
            'INSERT INTO ProductQuestionAnswer'.
            '(ProductQuestionExternalReference, Value, PriceModifier, SKUModifier, Sequence) '.
            'VALUES(?, ?, ?, ?, ?)'
        );
        $insertOrders = $db->prepare(
            'INSERT OR REPLACE INTO [Order] '.
            '(ID, Status, PaymentDate, ShipmentDate, Carrier, TrackingNumber, ExternalReference) '.
            'VALUES(?, ?, ?, ?, ?, ?, ?)'
        );

        $db->exec('BEGIN EXCLUSIVE TRANSACTION');

        $qry = $db->query('SELECT entity_id FROM Progress');

        $this->currentEntityId = $qry->fetchColumn();
        if (!$this->currentEntityId) {
            $this->currentEntityId = 0;
        }

        $qry->closeCursor();

        $qry = $db->query('SELECT State FROM Progress');

        $state = $qry->fetchColumn();

        $qry->closeCursor();

        if (!$state) {
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
                $path = $store->getBaseMediaDir() . '/sales/store/logo/' . $imagepdf;
            }
            if ($imagehtml) {
                $path = $store->getBaseMediaDir() . '/sales/store/logo_html/' . $imagehtml;
            }

            if ($path) {
                //Invoice and Packing Slip image location isn't accessible from frontend place into DB
                $data = file_get_contents($path);
                $base64 = base64_encode($data);

                $config['logobase64'] = $base64;
                //still stuff url in so we can get the MIME type to determine extra conversion on the other side
                $config['logourl'] = $path;
            } else {
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

            $insertConfiguration = $db
            ->prepare('INSERT INTO Configuration(configuration_key, configuration_value) VALUES(?,?)');

            // build configuration table
            foreach ($config as $key => $value) {
                $insertConfiguration->execute([$key, $value]);
            }

            $state = 'simple';
        }

        $this->productsProcessed = [];
        $this->ordersProcessed = [];

        $coreResource = $this->resourceConnection;

        $catalogWebsiteName = $coreResource->getTableName('catalog_product_website');
        $storeName = $coreResource->getTableName('store');
        $iterator = $this->iteratorFactory->create();

        if ('simple' == $state) {
            $productFlatState = $this->productFlatState->create(['isAvailable' => false]);

            // Simple Products not participating as configurable skus
            $simpleProducts = $this->productCollectionFactory
                ->create(['catalogProductFlatState' => $productFlatState])
                ->addAttributeToSelect($this->availableProductFields, 'left')
                ->addAttributeToFilter('type_id', ['eq' => 'simple'])
                ->addAttributeToFilter('entity_id', ['gt' => (int)$this->currentEntityId]);

            $simpleProducts->getSelect()
                                ->columns(['codisto_in_store' => new \Zend_Db_Expr('CASE WHEN `e`.entity_id IN ('.
                                'SELECT product_id FROM `'.$catalogWebsiteName.'` '.
                                'WHERE website_id IN ('.
                                'SELECT website_id FROM `'.$storeName.'` WHERE '.
                                'store_id = '.$storeId.' OR EXISTS('.
                                'SELECT 1 FROM `'.$storeName.'` WHERE store_id = '.$storeId.' AND website_id = 0'.
                                '))) THEN -1 ELSE 0 END')])
                                ->order('entity_id')
                                ->limit($simpleCount);
            $simpleProducts->setOrder('entity_id', 'ASC');

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

            if (!empty($this->productsProcessed)) {
                $db->exec(
                    'INSERT OR REPLACE INTO Progress (Sentinel, State, entity_id) '.
                    'VALUES (1, \'simple\', '.$this->currentEntityId.')'
                );
            } else {
                $state = 'configurable';
                $this->currentEntityId = 0;
            }
        }

        if ('configurable' == $state) {
            $productFlatState = $this->productFlatState->create(['isAvailable' => false]);

            // Configurable products
            $configurableProducts = $this->productCollectionFactory->create(['catalogProductFlatState' => $productFlatState])
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
            ->limit($configurableCount);
            $configurableProducts->setOrder('entity_id', 'ASC');

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

            if (!empty($this->productsProcessed)) {
                $db->exec('INSERT OR REPLACE INTO Progress (Sentinel, State, entity_id) '.
                'VALUES (1, \'configurable\', '.$this->currentEntityId.')');
            } else {
                $state = 'grouped';
                $this->currentEntityId = 0;
            }
        }

        if ('grouped' == $state) {
            $productFlatState = $this->productFlatState->create(['isAvailable' => false]);
            // Configurable products
            $groupedProducts = $this->productCollectionFactory->create(['catalogProductFlatState' => $productFlatState])
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
            ->limit($configurableCount);
            $groupedProducts->setOrder('entity_id', 'ASC');

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

            if (!empty($this->productsProcessed)) {
                $db->exec('INSERT OR REPLACE INTO Progress (Sentinel, State, entity_id) '.
                'VALUES (1, \'grouped\', '.$this->currentEntityId.')');
            } else {
                $state = 'orders';
                $this->currentEntityId = 0;
            }
        }

        if ('orders' == $state) {
            if ($this->currentEntityId == 0) {
                $connection = $coreResource->getConnection();
                try {
                    $connection->addColumn(
                        $coreResource->getTableName('sales_order'),
                        'codisto_orderid',
                        'varchar(10)'
                    );
                } catch (\Exception $e) {
                    $e;
                    // ignore if column already exists
                }
            }

            $orderStoreId = $storeId;
            if ($storeId == 0) {
                $stores = $this->storeCollectionFactory->create()
                            ->addFieldToFilter('is_active', ['neq' => 0])
                            ->setOrder('store_id', 'ASC');
                $stores->setPageSize(1)->setCurPage(1);
                $orderStoreId = $stores->getFirstItem()->getId();
            }

            $invoiceName = $coreResource->getTableName('sales_invoice');
            $shipmentName = $coreResource->getTableName('sales_shipment');
            $shipmentTrackName = $coreResource->getTableName('sales_shipment_track');

            $ts = $this->dateTime->gmtTimestamp();
            $ts -= 7776000; // 90 days

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

            $iterator->walk(
                $orders->getSelect(),
                [[$this, 'syncOrderData']],
                [
                    'db' => $db,
                    'preparedStatement' => $insertOrders,
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

        if ('productoption' == $state) {
            $db->exec('DELETE FROM ProductOptionValue');

            $insertProductOptionValue = $db->prepare('INSERT INTO ProductOptionValue (ExternalReference, Sequence) '.
            'VALUES (?,?)');

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

        if ('categories' == $state) {
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
                    'preparedStatement' => $insertCategory,
                    'store' => $store
                ]
            );

            $db->exec('INSERT OR REPLACE INTO Progress (Sentinel, State, entity_id) VALUES (1, \'complete\', 0)');
        }

        $db->exec('COMMIT TRANSACTION');

        if ((empty($this->productsProcessed) && empty($this->ordersProcessed)) || $first) {
            return 'complete';
        } else {
            return 'pending';
        }
    }

    public function SyncIncrementalStores($storeId)
    {
        $helper = Mage::helper('codistosync');

        $syncDbPath = $helper->getSyncPath('sync-'.$storeId.'.db');

        $syncDb = null;

        if(file_exists($syncDbPath))
        {
            $syncDb = $this->GetSyncDb($syncDbPath, 5 );
        }

        return array( 'id' => $storeId, 'path' => $syncDbPath, 'db' => $syncDb );
    }

    public function SyncIncremental($simpleCount, $configurableCount)
    {
        $coreResource = Mage::getSingleton('core/resource');
        $adapter = $coreResource->getConnection(Mage_Core_Model_Resource::DEFAULT_WRITE_RESOURCE);

        $tablePrefix = Mage::getConfig()->getTablePrefix();

        $storeName = $coreResource->getTableName('core/store');

        $storeIds = array( 0 );

        $defaultMerchantList = Mage::getStoreConfig('codisto/merchantid', 0);

        $stores = $adapter->fetchCol('SELECT store_id FROM `'.$storeName.'`');

        foreach($stores as $storeId)
        {
            $storeMerchantList = Mage::getStoreConfig('codisto/merchantid', $storeId);
            if($storeMerchantList && $storeMerchantList != $defaultMerchantList)
            {
                $storeIds[] = $storeId;
            }
        }

        $stores = array_map( array($this, 'SyncIncrementalStores'), $storeIds );

        $productUpdateEntries = $adapter->fetchPairs(
            'SELECT entity_id, version FROM `'.$tablePrefix.'codisto_index_store_cl` ORDER BY entity_id '.
            'LIMIT '.(int)$simpleCount
        );
        $categoryUpdateEntries = $adapter->fetchPairs(
            'SELECT entity_id, version FROM `'.$tablePrefix.'codisto_index_category_store_cl` ORDER BY category_id'
        );
        $orderUpdateEntries = $adapter->fetchPairs(
            'SELECT entity_id, stamp FROM `'.$tablePrefix.'codisto_index_order_store_cl` ORDER BY order_id LIMIT 1000'
        );

        if(empty($productUpdateEntries) &&
            empty($categoryUpdateEntries) &&
            empty($orderUpdateEntries))
        {
            return 'nochange';
        }

        $productUpdateIds = array_keys($productUpdateEntries);
        $categoryUpdateIds = array_keys($categoryUpdateEntries);
        $orderUpdateIds = array_keys($orderUpdateEntries);

        $coreResource = Mage::getSingleton('core/resource');

        $catalogWebsiteName = $coreResource->getTableName('catalog/product_website');
        $storeName = $coreResource->getTableName('core/store');

        $this->productsProcessed = array();
        $this->ordersProcessed = array();

        foreach($stores as $store)
        {
            if($store['db'] != null)
            {
                $storeId = $store['id'];

                if($storeId == 0)
                {
                    // jump the storeid to first non admin store
                    $stores = Mage::getModel('core/store')->getCollection()
                                                ->addFieldToFilter('is_active', array('neq' => 0))
                                                ->addFieldToFilter('store_id', array('gt' => 0))
                                                ->setOrder('store_id', 'ASC');

                    if($stores->getSize() == 1)
                    {
                        $stores->setPageSize(1)->setCurPage(1);
                        $firstStore = $stores->getFirstItem();
                        if(is_object($firstStore) && $firstStore->getId())
                        {
                            $storeId = $firstStore->getId();
                        }
                    }
                }

                $storeObject = Mage::app()->getStore($storeId);

                Mage::app()->setCurrentStore($storeObject);

                $db = $store['db'];

                $db->exec('BEGIN EXCLUSIVE TRANSACTION');

                if(!empty($productUpdateIds))
                {
                    $db->exec(
                        'DELETE FROM Product WHERE ExternalReference IN ('.implode(',', $productUpdateIds).');'.
                        'DELETE FROM ProductImage WHERE ProductExternalReference IN ('.implode(',', $productUpdateIds).');'.
                        'DELETE FROM ProductHTML WHERE ProductExternalReference IN ('.implode(',', $productUpdateIds).');'.
                        'DELETE FROM ProductRelated WHERE ProductExternalReference IN ('.implode(',', $productUpdateIds).');'.
                        'DELETE FROM ProductAttributeValue WHERE ProductExternalReference IN ('.implode(',', $productUpdateIds).');'.
                        'DELETE FROM ProductQuestionAnswer WHERE ProductQuestionExternalReference IN (SELECT ExternalReference FROM ProductQuestion WHERE ProductExternalReference IN ('.implode(',', $productUpdateIds).'));'.
                        'DELETE FROM ProductQuestion WHERE ProductExternalReference IN ('.implode(',', $productUpdateIds).');'.
                        'DELETE FROM SKULink WHERE ProductExternalReference IN ('.implode(',', $productUpdateIds).');'.
                        'DELETE FROM SKUMatrix WHERE ProductExternalReference IN ('.implode(',', $productUpdateIds).');'.
                        'DELETE FROM SKU WHERE ProductExternalReference IN ('.implode(',', $productUpdateIds).');'.
                        'DELETE FROM CategoryProduct WHERE ProductExternalReference IN ('.implode(',', $productUpdateIds).')'
                    );

                    $insertCategoryProduct = $db->prepare('INSERT OR IGNORE INTO CategoryProduct(ProductExternalReference, CategoryExternalReference, Sequence) VALUES(?,?,?)');
                    $insertProduct = $db->prepare('INSERT INTO Product(ExternalReference, Type, Code, Name, Price, ListPrice, TaxClass, Description, Enabled, StockControl, StockLevel, Weight, InStore) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $checkProduct = $db->prepare('SELECT CASE WHEN EXISTS(SELECT 1 FROM Product WHERE ExternalReference = ?) THEN 1 ELSE 0 END');
                    $insertSKU = $db->prepare('INSERT OR IGNORE INTO SKU(ExternalReference, Code, ProductExternalReference, Name, StockControl, StockLevel, Price, Enabled, InStore) VALUES(?,?,?,?,?,?,?,?,?)');
                    $insertSKULink = $db->prepare('INSERT OR REPLACE INTO SKULink (SKUExternalReference, ProductExternalReference, Price) VALUES (?, ?, ?)');
                    $insertSKUMatrix = $db->prepare('INSERT INTO SKUMatrix(SKUExternalReference, ProductExternalReference, Code, OptionName, OptionValue, ProductOptionExternalReference, ProductOptionValueExternalReference) VALUES(?,?,?,?,?,?,?)');
                    $insertImage = $db->prepare('INSERT INTO ProductImage(ProductExternalReference, URL, Tag, Sequence, Enabled) VALUES(?,?,?,?,?)');
                    $insertProductHTML = $db->prepare('INSERT OR IGNORE INTO ProductHTML(ProductExternalReference, Tag, HTML) VALUES (?, ?, ?)');
                    $insertProductRelated = $db->prepare('INSERT OR IGNORE INTO ProductRelated(RelatedProductExternalReference, ProductExternalReference) VALUES (?, ?)');
                    $insertAttribute = $db->prepare('INSERT OR REPLACE INTO Attribute(ID, Code, Label, Type, Input) VALUES (?, ?, ?, ?, ?)');
                    $insertAttributeGroup = $db->prepare('INSERT OR REPLACE INTO AttributeGroup(ID, Name) VALUES(?, ?)');
                    $insertAttributeGroupMap = $db->prepare('INSERT OR IGNORE INTO AttributeGroupMap(GroupID, AttributeID) VALUES(?,?)');
                    $insertProductAttribute = $db->prepare('INSERT OR IGNORE INTO ProductAttributeValue(ProductExternalReference, AttributeID, Value) VALUES (?, ?, ?)');
                    $insertProductAttributeDefault = $db->prepare('INSERT OR IGNORE INTO ProductAttributeDefaultValue(ProductExternalReference, AttributeID, Value) VALUES (?, ?, ?)');
                    $insertProductQuestion = $db->prepare('INSERT OR REPLACE INTO ProductQuestion(ExternalReference, ProductExternalReference, Name, Type, Sequence) VALUES (?, ?, ?, ?, ?)');
                    $insertProductAnswer = $db->prepare('INSERT INTO ProductQuestionAnswer(ProductQuestionExternalReference, Value, PriceModifier, SKUModifier, Sequence) VALUES (?, ?, ?, ?, ?)');

                    // Simple Products
                    $simpleProducts = $this->getProductCollection()
                                        ->addAttributeToSelect($this->availableProductFields, 'left')
                                        ->addAttributeToFilter('type_id', array('eq' => 'simple'))
                                        ->addAttributeToFilter('entity_id', array('in' => $productUpdateIds) );
                    $simpleProducts->getSelect()
                                        ->columns(array('codisto_in_store' => new Zend_Db_Expr('CASE WHEN `e`.entity_id IN (SELECT product_id FROM `'.$catalogWebsiteName.'` WHERE website_id IN (SELECT website_id FROM `'.$storeName.'` WHERE store_id = '.$storeId.' OR EXISTS(SELECT 1 FROM `'.$storeName.'` WHERE store_id = '.$storeId.' AND website_id = 0))) THEN -1 ELSE 0 END')));

                    Mage::getSingleton('core/resource_iterator')->walk($simpleProducts->getSelect(), array(array($this, 'SyncSimpleProductData')),
                        array(
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
                            'store' => $storeObject ));

                    // Virtual Products
                    $virtualProducts = $this->getProductCollection()
                                        ->addAttributeToSelect($this->availableProductFields, 'left')
                                        ->addAttributeToFilter('type_id', array('eq' => 'virtual'))
                                        ->addAttributeToFilter('entity_id', array('in' => $productUpdateIds) );
                    $virtualProducts->getSelect()
                                        ->columns(array('codisto_in_store' => new Zend_Db_Expr('CASE WHEN `e`.entity_id IN (SELECT product_id FROM `'.$catalogWebsiteName.'` WHERE website_id IN (SELECT website_id FROM `'.$storeName.'` WHERE store_id = '.$storeId.' OR EXISTS(SELECT 1 FROM `'.$storeName.'` WHERE store_id = '.$storeId.' AND website_id = 0))) THEN -1 ELSE 0 END')));

                    Mage::getSingleton('core/resource_iterator')->walk($virtualProducts->getSelect(), array(array($this, 'SyncSimpleProductData')),
                        array(
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
                            'store' => $storeObject ));


                    // Configurable products
                    $configurableProducts = $this->getProductCollection()
                                        ->addAttributeToSelect($this->availableProductFields, 'left')
                                        ->addAttributeToFilter('type_id', array('eq' => 'configurable'))
                                        ->addAttributeToFilter('entity_id', array('in' => $productUpdateIds));
                    $configurableProducts->getSelect()
                                                ->columns(array('codisto_in_store' => new Zend_Db_Expr('CASE WHEN `e`.entity_id IN (SELECT product_id FROM `'.$catalogWebsiteName.'` WHERE website_id IN (SELECT website_id FROM `'.$storeName.'` WHERE store_id = '.$storeId.' OR EXISTS(SELECT 1 FROM `'.$storeName.'` WHERE store_id = '.$storeId.' AND website_id = 0))) THEN -1 ELSE 0 END')));

                    Mage::getSingleton('core/resource_iterator')->walk($configurableProducts->getSelect(), array(array($this, 'SyncConfigurableProductData')),
                        array(
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
                            'store' => $storeObject )
                    );

                    // Grouped products
                    $groupedProducts = $this->getProductCollection()
                                        ->addAttributeToSelect($this->availableProductFields, 'left')
                                        ->addAttributeToFilter('type_id', array('eq' => 'grouped'))
                                        ->addAttributeToFilter('entity_id', array('in' => $productUpdateIds ));

                    $groupedProducts->getSelect()
                                                ->columns(array('codisto_in_store' => new Zend_Db_Expr('CASE WHEN `e`.entity_id IN (SELECT product_id FROM `'.$catalogWebsiteName.'` WHERE website_id IN (SELECT website_id FROM `'.$storeName.'` WHERE store_id = '.$storeId.' OR EXISTS(SELECT 1 FROM `'.$storeName.'` WHERE store_id = '.$storeId.' AND website_id = 0))) THEN -1 ELSE 0 END')));

                    Mage::getSingleton('core/resource_iterator')->walk($groupedProducts->getSelect(), array(array($this, 'SyncGroupedProductData')),
                        array(
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
                            'store' => $storeObject )
                    );
                }

                if(!empty($categoryUpdateIds))
                {
                    $insertCategory = $db->prepare('INSERT OR REPLACE INTO Category(ExternalReference, Name, ParentExternalReference, LastModified, Enabled, Sequence) VALUES(?,?,?,?,?,?)');

                    // Categories
                    $categories = Mage::getModel('catalog/category', array('disable_flat' => true))->getCollection()
                                        ->addAttributeToSelect(array('name', 'image', 'is_active', 'updated_at', 'parent_id', 'position'), 'left')
                                        ->addAttributeToFilter('entity_id', array('in' => $categoryUpdateIds ));

                    Mage::getSingleton('core/resource_iterator')->walk($categories->getSelect(), array(array($this, 'SyncCategoryData')), array( 'db' => $db, 'preparedStatement' => $insertCategory, 'store' => $storeObject ));
                }

                if(!empty($orderUpdateIds))
                {
                    $connection = $coreResource->getConnection(Mage_Core_Model_Resource::DEFAULT_WRITE_RESOURCE);
                    try
                    {
                        $connection->addColumn(
                            $coreResource->getTableName('sales_order'),
                            'codisto_orderid',
                            'varchar(10)'
                        );
                    }
                    catch(Exception $e)
                    {
                    }

                    try
                    {
                        $connection->addColumn(
                            $coreResource->getTableName('sales_order'),
                            'codisto_orderid',
                            'varchar(10)'
                        );
                    }
                    catch(Exception $e)
                    {
                    }

                    $insertOrders = $db->prepare('INSERT OR REPLACE INTO [Order] (ID, Status, PaymentDate, ShipmentDate, Carrier, TrackingNumber, ExternalReference, MerchantID) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

                    $orderStoreId = $storeId;
                    if($storeId == 0)
                    {
                        $firstStore = Mage::getModel('core/store')->getCollection()
                                    ->addFieldToFilter('is_active', array('neq' => 0))
                                    ->addFieldToFilter('store_id', array( 'gt' => 0))
                                    ->setOrder('store_id', 'ASC');
                        $firstStore->setPageSize(1)->setCurPage(1);
                        $orderStoreId = $firstStore->getFirstItem()->getId();
                    }

                    $invoiceName = $coreResource->getTableName('sales/invoice');
                    $shipmentName = $coreResource->getTableName('sales/shipment');
                    $shipmentTrackName = $coreResource->getTableName('sales/shipment_track');

                    $ts = Mage::getModel('core/date')->gmtTimestamp();
                    $ts -= 7776000; // 90 days

                    $orders = Mage::getModel('sales/order')->getCollection()
                                ->addFieldToSelect(array('codisto_orderid', 'codisto_merchantid', 'status' ))
                                ->addFieldToSelect('entity_id','externalreference')
                                ->addAttributeToFilter('entity_id', array('in' => $orderUpdateIds ))
                                ->addAttributeToFilter('main_table.store_id', array('eq' => $orderStoreId ))
                                ->addAttributeToFilter('main_table.updated_at', array('gteq' => date('Y-m-d H:i:s', $ts)))
                                ->addAttributeToFilter('main_table.codisto_orderid', array('notnull' => true));
                    $orders->getSelect()->joinLeft( array('i' => $invoiceName), 'i.order_id = main_table.entity_id AND i.state = 2', array('pay_date' => 'MIN(i.created_at)'));
                    $orders->getSelect()->joinLeft( array('s' => $shipmentName), 's.order_id = main_table.entity_id', array('ship_date' => 'MIN(s.created_at)'));
                    $orders->getSelect()->joinLeft( array('t' => $shipmentTrackName), 't.order_id = main_table.entity_id', array('carrier' => 'GROUP_CONCAT(COALESCE(t.title, \'\') SEPARATOR \',\')', 'track_number' => 'GROUP_CONCAT(COALESCE(t.track_number, \'\') SEPARATOR \',\')'));
                    $orders->getSelect()->group(array('main_table.entity_id', 'main_table.codisto_orderid', 'main_table.codisto_merchantid', 'main_table.status'));
                    $orders->setOrder('entity_id', 'ASC');

                    Mage::getSingleton('core/resource_iterator')->walk($orders->getSelect(), array(array($this, 'SyncOrderData')),
                        array(
                            'db' => $db,
                            'preparedStatement' => $insertOrders,
                            'store' => $storeObject )
                    );
                }

                $uniqueId = uniqid();

                $adapter->beginTransaction();
                try
                {
                    $adapter->query('REPLACE INTO `'.$tablePrefix.'codisto_sync` ( store_id, token ) VALUES ('.$storeId.', \''.$uniqueId.'\')');
                }
                catch(Exception $e)
                {
                    $adapter->query('CREATE TABLE `'.$tablePrefix.'codisto_sync` (store_id smallint(5) unsigned PRIMARY KEY NOT NULL, token varchar(20) NOT NULL)');
                    $adapter->insert($tablePrefix.'codisto_sync', array( 'token' => $uniqueId, 'store_id' => $storeId ));
                }
                $adapter->commit();

                $db->exec('CREATE TABLE IF NOT EXISTS Sync (token text NOT NULL, sentinel NOT NULL PRIMARY KEY DEFAULT 1, CHECK(sentinel = 1))');
                $db->exec('INSERT OR REPLACE INTO Sync (token) VALUES (\''.$uniqueId.'\')');

                if(!empty($productUpdateIds))
                {
                    $db->exec('CREATE TABLE IF NOT EXISTS ProductChange (ExternalReference text NOT NULL PRIMARY KEY, stamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP)');
                    foreach($productUpdateIds as $updateId)
                    {
                        $db->exec('INSERT OR REPLACE INTO ProductChange (ExternalReference) VALUES ('.$updateId.')');
                    }

                    $db->exec('DELETE FROM ProductOptionValue');

                    $insertProductOptionValue = $db->prepare('INSERT INTO ProductOptionValue (ExternalReference, Sequence) VALUES (?,?)');

                    $options = Mage::getResourceModel('eav/entity_attribute_option_collection')
                                ->setPositionOrder('asc', true)
                                ->load();

                    foreach($options as $opt){
                        $sequence = $opt->getSortOrder();
                        $optId = $opt->getId();
                        $insertProductOptionValue->execute(array($optId, $sequence));
                    }
                }

                if(!empty($categoryUpdateIds))
                {
                    $db->exec('CREATE TABLE IF NOT EXISTS CategoryChange (ExternalReference text NOT NULL PRIMARY KEY, stamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP)');
                    foreach($categoryUpdateIds as $updateId)
                    {
                        $db->exec('INSERT OR REPLACE INTO CategoryChange (ExternalReference) VALUES ('.$updateId.')');
                    }
                }

                if(!empty($orderUpdateIds))
                {
                    $db->exec('CREATE TABLE IF NOT EXISTS OrderChange (ExternalReference text NOT NULL PRIMARY KEY, stamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP)');
                    foreach($orderUpdateIds as $updateId)
                    {
                        $db->exec('INSERT OR REPLACE INTO OrderChange (ExternalReference) VALUES ('.$updateId.')');
                    }
                }

                $db->exec('COMMIT TRANSACTION');
            }
        }

        if(!empty($productUpdateEntries))
        {
            $adapter->query('CREATE TEMPORARY TABLE tmp_codisto_change (product_id int(10) unsigned, stamp datetime)');
            foreach($productUpdateEntries as $product_id => $stamp)
            {
                $adapter->insert('tmp_codisto_change', array( 'product_id' => $product_id, 'stamp' => $stamp ) );
            }
            $adapter->query('DELETE FROM `'.$tablePrefix.'codisto_product_change` '.
                            'WHERE EXISTS ('.
                                'SELECT 1 FROM tmp_codisto_change '.
                                'WHERE product_id = `'.$tablePrefix.'codisto_product_change`.product_id AND '.
                                    'stamp = `'.$tablePrefix.'codisto_product_change`.stamp'.
                            ')');
            $adapter->query('DROP TABLE tmp_codisto_change');
        }

        if(!empty($categoryUpdateEntries))
        {
            $adapter->query('CREATE TEMPORARY TABLE tmp_codisto_change (category_id int(10) unsigned, stamp datetime)');
            foreach($categoryUpdateEntries as $category_id => $stamp)
            {
                $adapter->insert('tmp_codisto_change', array( 'category_id' => $category_id, 'stamp' => $stamp ) );
            }
            $adapter->query('DELETE FROM `'.$tablePrefix.'codisto_category_change` '.
                            'WHERE EXISTS ('.
                                'SELECT 1 FROM tmp_codisto_change '.
                                'WHERE category_id = `'.$tablePrefix.'codisto_category_change`.category_id AND '.
                                    'stamp = `'.$tablePrefix.'codisto_category_change`.stamp'.
                            ')');
            $adapter->query('DROP TABLE tmp_codisto_change');
        }

        if(!empty($orderUpdateEntries))
        {
            $adapter->query('CREATE TEMPORARY TABLE tmp_codisto_change (order_id int(10) unsigned, stamp datetime)');
            foreach($orderUpdateEntries as $order_id => $stamp)
            {
                $adapter->insert('tmp_codisto_change', array( 'order_id' => $order_id, 'stamp' => $stamp ) );
            }
            $adapter->query('DELETE FROM `'.$tablePrefix.'codisto_order_change` '.
                            'WHERE EXISTS ('.
                                'SELECT 1 FROM tmp_codisto_change '.
                                'WHERE order_id = `'.$tablePrefix.'codisto_order_change`.order_id AND '.
                                    'stamp = `'.$tablePrefix.'codisto_order_change`.stamp'.
                            ')');
            $adapter->query('DROP TABLE tmp_codisto_change');
        }

        return $adapter->fetchOne('SELECT CASE WHEN '.
                                'EXISTS(SELECT 1 FROM `'.$tablePrefix.'codisto_product_change`) OR '.
                                'EXISTS(SELECT 1 FROM `'.$tablePrefix.'codisto_category_change`) OR '.
                                'EXISTS(SELECT 1 FROM `'.$tablePrefix.'codisto_order_change`) '.
                                'THEN \'pending\' ELSE \'complete\' END');
    }

    public function productTotals($storeId) {

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
            ->addAttributeToFilter('type_id', ['eq' => 'simple']);

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
        $taxRates->getSelect()->joinLeft(
            ['region' => $regionName],
            'region.region_id = main_table.tax_region_id',
            ['tax_region_code' => 'region.code', 'tax_region_name' => 'region.default_name']
        );

        $insertTaxRate = $db->prepare('INSERT OR IGNORE INTO TaxCalculationRate '.
        '(ID, Country, RegionID, RegionName, RegionCode, PostCode, Code, Rate, IsRange, ZipFrom, ZipTo) '.
        'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ? ,?)');

        foreach ($taxRates as $taxRate) {
            $TaxRateID = $taxRate->getId();
            $TaxCountry = $taxRate->getTaxCountryId();
            $TaxRegionID = $taxRate->getTaxRegionId();
            $TaxRegionName = $taxRate->getTaxRegionName();
            $TaxRegionCode = $taxRate->getTaxRegionCode();
            $TaxPostCode = $taxRate->getTaxPostcode();
            $TaxCode = $taxRate->getCode();
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
            $Content = $this->codistoHelper->processCmsContent($block->getContent(), $storeId);

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

        $stores = $this->storeCollectionFactory->create();

        foreach ($stores as $store) {
            $StoreID = $store->getId();

            if ($StoreID == 0) {
                continue;
            }

            $StoreCode = $store->getCode();
            $StoreName = $store->getName();
            $StoreCurrency = $store->getCurrentCurrencyCode();

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
        $db->exec('CREATE TABLE IF NOT EXISTS Progress('.
            'entity_id integer NOT NULL, '.
            'State text NOT NULL, '.'
            Sentinel integer NOT NULL PRIMARY KEY AUTOINCREMENT, CHECK(Sentinel=1)'.
        ')');
        $db->exec(
            'CREATE TABLE IF NOT EXISTS Category('.
            'ExternalReference text NOT NULL PRIMARY KEY, ParentExternalReference text NOT NULL, '.
            'Name text NOT NULL, LastModified datetime NOT NULL, Enabled bit NOT NULL, Sequence integer NOT NULL'.
        ')');
        $db->exec('CREATE TABLE IF NOT EXISTS CategoryProduct ('.
            'CategoryExternalReference text NOT NULL, '.
            'ProductExternalReference text NOT NULL, '.
            'Sequence integer NOT NULL, '.
            'PRIMARY KEY(CategoryExternalReference, ProductExternalReference)'.
        ')');
        $db->exec('CREATE TABLE IF NOT EXISTS Product ('.
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
        ')');

        $db->exec('CREATE TABLE IF NOT EXISTS ProductOptionValue ('.'
        ExternalReference text NOT NULL, Sequence integer NOT NULL)');
        $db->exec('CREATE INDEX IF NOT EXISTS '.'
        IX_ProductOptionValue_ExternalReference ON ProductOptionValue(ExternalReference)');

        $db->exec('CREATE TABLE IF NOT EXISTS ProductQuestion ('.
            'ExternalReference text NOT NULL PRIMARY KEY, '.
            'ProductExternalReference text NOT NULL, '.
            'Name text NOT NULL, '.
            'Type text NOT NULL, '.
            'Sequence integer NOT NULL'.'
        )');
        $db->exec('CREATE INDEX IF NOT EXISTS '.'
        IX_ProductQuestion_ProductExternalReference ON ProductQuestion(ProductExternalReference)');
        $db->exec('CREATE TABLE IF NOT EXISTS ProductQuestionAnswer ('.'
            ProductQuestionExternalReference text NOT NULL, '.
            'Value text NOT NULL, '.
            'PriceModifier text NOT NULL, '.
            'SKUModifier text NOT NULL, '.
            'Sequence integer NOT NULL'.
        ')');
        $db->exec('CREATE INDEX IF NOT EXISTS '.
        'IX_ProductQuestionAnswer_ProductQuestionExternalReference ON '.
        'ProductQuestionAnswer(ProductQuestionExternalReference)');

        $db->exec('CREATE TABLE IF NOT EXISTS SKU ('.
            'ExternalReference text NOT NULL PRIMARY KEY, '.
            'Code text NULL, '.
            'ProductExternalReference text NOT NULL, '.
            'Name text NOT NULL, StockControl bit NOT NULL, '.
            'StockLevel integer NOT NULL, '.
            'Price real NOT NULL, '.
            'Enabled bit NOT NULL, '.
            'InStore bit NOT NULL'.
        ')');
        $db->exec('CREATE INDEX IF NOT EXISTS IX_SKU_ProductExternalReference ON SKU(ProductExternalReference)');
        $db->exec('CREATE TABLE IF NOT EXISTS SKUMatrix ('.
            'SKUExternalReference text NOT NULL, '.
            'ProductExternalReference text NOT NULL, '.
            'Code text NULL, '.
            'OptionName text NOT NULL, '.
            'OptionValue text NOT NULL, '.
            'ProductOptionExternalReference text NOT NULL, '.
            'ProductOptionValueExternalReference text NOT NULL'.
        ')');
        $db->exec('CREATE INDEX IF NOT EXISTS IX_SKUMatrix_SKUExternalReference ON SKUMatrix(SKUExternalReference)');

        $db->exec('CREATE TABLE IF NOT EXISTS SKULink ('.
            'SKUExternalReference text NOT NULL, '.
            'ProductExternalReference text NOT NULL, '.
            'Price real NOT NULL, '.
            'PRIMARY KEY (SKUExternalReference, ProductExternalReference)'.
        ')');

        $db->exec('CREATE TABLE IF NOT EXISTS ProductImage ('.
            'ProductExternalReference text NOT NULL, '.
            'URL text NOT NULL, '.
            'Tag text NOT NULL DEFAULT \'\', '.
            'Sequence integer NOT NULL, '.
            'Enabled bit NOT NULL DEFAULT -1'.
        ')');
        $db->exec('CREATE INDEX IF NOT EXISTS '.
        'IX_ProductImage_ProductExternalReference ON ProductImage(ProductExternalReference)');

        $db->exec('CREATE TABLE IF NOT EXISTS ProductHTML ('.
            'ProductExternalReference text NOT NULL, '.
            'Tag text NOT NULL, HTML text NOT NULL, '.
            'PRIMARY KEY (ProductExternalReference, Tag)'.
        ')');
        $db->exec('CREATE INDEX IF NOT EXISTS '.
        'IX_ProductHTML_ProductExternalReference ON ProductHTML(ProductExternalReference)');

        $db->exec('CREATE TABLE IF NOT EXISTS ProductRelated ('.
            'RelatedProductExternalReference text NOT NULL, '.
            'ProductExternalReference text NOT NULL, '.
            'PRIMARY KEY (ProductExternalReference, RelatedProductExternalReference)'.
        ')');

        $db->exec('CREATE TABLE IF NOT EXISTS Attribute ('.'
            ID integer NOT NULL PRIMARY KEY, '.
            'Code text NOT NULL, '.
            'Label text NOT NULL, '.
            'Type text NOT NULL, Input text NOT NULL'.
        ')');
        $db->exec('CREATE TABLE IF NOT EXISTS AttributeGroupMap ('.
            'AttributeID integer NOT NULL, '.
            'GroupID integer NOT NULL, '.
            'PRIMARY KEY(AttributeID, GroupID)'.
        ')');
        $db->exec('CREATE TABLE IF NOT EXISTS AttributeGroup ('.
            'ID integer NOT NULL PRIMARY KEY, '.
            'Name text NOT NULL'.
        ')');
        $db->exec('CREATE TABLE IF NOT EXISTS ProductAttributeValue ('.
            'ProductExternalReference text NOT NULL, '.
            'AttributeID integer NOT NULL, '.
            'Value text, '.
            'PRIMARY KEY (ProductExternalReference, AttributeID)'.
        ')');
        $db->exec('CREATE TABLE IF NOT EXISTS ProductAttributeDefaultValue ('.
            'ProductExternalReference text NOT NULL, '.
            'AttributeID integer NOT NULL, '.
            'Value text, '.
            'PRIMARY KEY (ProductExternalReference, AttributeID)'.
        ')');

        $db->exec('CREATE TABLE IF NOT EXISTS TaxClass ('.
            'ID integer NOT NULL PRIMARY KEY, '.
            'Type text NOT NULL, '.
            'Name text NOT NULL'.
        ')');
        $db->exec('CREATE TABLE IF NOT EXISTS TaxCalculation('.
            'ID integer NOT NULL PRIMARY KEY, '.
            'TaxRateID integer NOT NULL, '.
            'TaxRuleID integer NOT NULL, '.
            'ProductTaxClassID integer NOT NULL, '.
            'CustomerTaxClassID integer NOT NULL'.
        ')');
        $db->exec('CREATE TABLE IF NOT EXISTS TaxCalculationRule('.
            'ID integer NOT NULL PRIMARY KEY, '.
            'Code text NOT NULL, '.
            'Priority integer NOT NULL, '.
            'Position integer NOT NULL, '.
            'CalculateSubTotal bit NOT NULL'.
        ')');
        $db->exec('CREATE TABLE IF NOT EXISTS TaxCalculationRate('.
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
        ')');

        $db->exec('CREATE TABLE IF NOT EXISTS Store('.
            'ID integer NOT NULL PRIMARY KEY, '.
            'Code text NOT NULL, '.
            'Name text NOT NULL, '.
            'Currency text NOT NULL'.
        ')');
        $db->exec('CREATE TABLE IF NOT EXISTS StoreMerchant('.
            'StoreID integer NOT NULL, '.
            'MerchantID integer NOT NULL, '.
            'PRIMARY KEY (StoreID, MerchantID)'.
        ')');

        $db->exec('CREATE TABLE IF NOT EXISTS [Order]('.
            'ID integer NOT NULL PRIMARY KEY, '.
            'Status text NOT NULL, '.
            'PaymentDate datetime NULL, '.
            'ShipmentDate datetime NULL, '.
            'Carrier text NOT NULL, '.
            'TrackingNumber text NOT NULL, '.
        'ExternalReference text NOT NULL DEFAULT \'\''.
        ')');

        $db->exec('CREATE TABLE IF NOT EXISTS StaticBlock ('.
            'BlockID integer NOT NULL PRIMARY KEY, '.
            'Title text NOT NULL, '.
            'Identifier text NOT NULL, '.
            'Content text NOT NULL'.
        ')');

        $db->exec('CREATE TABLE IF NOT EXISTS Configuration ('.
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
            'set_function text'.'
        )');

        try {
            $db->exec('SELECT 1 FROM [Order] WHERE Carrier IS NULL LIMIT 1');
        } catch (\Exception $e) {
            $db->exec('CREATE TABLE NewOrder ('.
            'ID integer NOT NULL PRIMARY KEY, '.
            'Status text NOT NULL, '.
            'PaymentDate datetime NULL, '.
            'ShipmentDate datetime NULL, '.
            'Carrier text NOT NULL, '.
            'TrackingNumber text NOT NULL, '.
            'ExternalReference text NOT NULL DEFAULT \'\''.
            ')');
            $db->exec('INSERT INTO NewOrder '.
                'SELECT ID, Status, PaymentDate, ShipmentDate, \'Unknown\', TrackingNumber, \'\' '.
            'FROM [Order]');
            $db->exec('DROP TABLE [Order]');
            $db->exec('ALTER TABLE NewOrder RENAME TO [Order]');
        }

        try {
            $db->exec('SELECT 1 FROM [Order] WHERE ExternalReference IS NULL LIMIT 1');
        } catch (\Exception $e) {
            $db->exec('ALTER TABLE [Order] ADD COLUMN ExternalReference text NOT NULL DEFAULT \'\'');
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
            if (is_dir($dir)) {
                $scan = @scandir($dir);
                if ($scan === false) {
                    return $result;
                }

                foreach ($scan as $f) {
                    if ($f === '.' or $f === '..') {
                        continue;
                    }

                    if (is_dir("$dir/$f")) {
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
