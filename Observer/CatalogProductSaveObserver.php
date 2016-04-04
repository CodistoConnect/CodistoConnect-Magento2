<?php

namespace Codisto\Connect\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;

class CatalogProductSaveObserver implements ObserverInterface
{
    private $storeManager;
    private $configurableTypeFactory;
    private $groupdTypeFactory;
    private $bundleTypeFactory;
    private $json;
    private $sync;
    private $codistoHelper;

    public function __construct(
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\ConfigurableProduct\Model\Product\Type\ConfigurableFactory $configurableTypeFactory,
		\Magento\GroupedProduct\Model\Product\Type\GroupedFactory $groupedTypeFactory,
		\Magento\Bundle\Model\Product\TypeFactory $bundleTypeFactory,
		\Magento\Framework\Json\Helper\Data $json,
        \Codisto\Connect\Model\Sync $sync,
        \Codisto\Connect\Helper\Data $codistoHelper
    ) {
        $this->storeManager = $storeManager;
        $this->configurableTypeFactory = $configurableTypeFactory;
        $this->groupedTypeFactory = $groupedTypeFactory;
        $this->bundleTypeFactory = $bundleTypeFactory;
        $this->json = $json;
        $this->sync = $sync;
        $this->codistoHelper = $codistoHelper;
    }

    public function execute(EventObserver $observer)
    {
        $product = $observer->getEvent()->getProduct();
        $storeId = $product->getStoreId();

        $defaultStore = $this->storeManager->getStore(0);
        $currentStore = $this->storeManager->getStore($storeId);

        $syncStores = array(0);

        if($storeId != 0)
        {
            $defaultMerchantId = $defaultStore->getConfig('codisto/merchantid');
            $storeMerchantId = $currentStore->getConfig('codisto/merchantid');

            // if the default Codisto merchantid is different at this store level
            // explicitly synchronise it as well
            if($defaultMerchantId != $storeMerchantId)
            {
                $syncStores[] = $storeId;
            }
        }
        else
        {
            $defaultMerchantId = $defaultStore->getConfig('codisto/merchantid');

            $stores = $this->storeManager->getStores();

            foreach($stores as $store)
            {
                if($store->getId() != 0)
                {
                    $storeMerchantId = $store->getConfig('codisto/merchantid');

                    if($defaultMerchantId != $storeMerchantId)
                    {
                        $syncStores[] = $store->getId();
                    }
                }
            }
        }

        $syncIds = array();

        $configurableType = $this->configurableTypeFactory->create();
        $configurableParents = $configurableType->getParentIdsByChild($product->getId());

        if(is_array($configurableParents) && !empty($configurableParents))
            $syncIds = array_merge($syncIds, $configurableParents);

        $groupedType = $this->groupedTypeFactory->create();
        $groupedParents = $groupedType->getParentIdsByChild($product->getId());

        if(is_array($groupedParents) && !empty($groupedParents))
            $syncIds = array_merge($syncIds, $groupedParents);

        $syncIds[] = $product->getId();

        $productIds = '';
        if(count($syncIds) == 1)
            $productIds = $syncIds[0];
        else
            $productIds = '['.implode(',', $syncIds).']';

        $merchants = array();
        $merchantSignalled = array();

        foreach($syncStores as $storeId)
        {
            $store = $this->storeManager->getStore($storeId);

            $merchantid = $store->getConfig('codisto/merchantid');
            $hostkey = $store->getConfig('codisto/hostkey');

            $merchantlist = $this->json->jsonDecode($merchantid);
            if(!is_array($merchantlist))
                $merchantlist = array($merchantlist);

            foreach($merchantlist as $merchantid)
            {
                if(!in_array($merchantid, $merchantSignalled, true))
                {
                    $merchantSignalled[] = $merchantid;
                    $merchants[] = array('merchantid' => $merchantid, 'hostkey' => $hostkey, 'storeid' => $storeId );
                }
            }
        }

        $this->codistoHelper->signal($merchants, 'action=sync&productid='.$productIds, 'update', $syncIds);

        return $this;
    }
}
