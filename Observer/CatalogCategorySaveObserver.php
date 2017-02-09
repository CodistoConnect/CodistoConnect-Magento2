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

namespace Codisto\Connect\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;

class CatalogCategorySaveObserver implements ObserverInterface
{
	private $storeManager;
	private $json;
	private $sync;
	private $codistoHelper;

	public function __construct(
		\Magento\Store\Model\StoreManager $storeManager,
		\Magento\Framework\Json\Helper\Data $json,
		\Codisto\Connect\Model\Sync $sync,
		\Codisto\Connect\Helper\Data $codistoHelper
	) {
		$this->storeManager = $storeManager;
		$this->json = $json;
		$this->sync = $sync;
		$this->codistoHelper = $codistoHelper;
	}

	public function execute(EventObserver $observer)
	{
		$category = $observer->getEvent()->getCategory();
		$storeId = $category->getStoreId();

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

					if($defaultMerchantId != $storeMerchantId ||
						(count($syncStores) == 1 && $syncStores[0] == 0))
					{
						$syncStores[] = $store->getId();
					}
				}
			}
		}

		$categoryId = $category->getId();

		$merchants = array();
		$merchantSignalled = array();

		foreach($syncStores as $storeId)
		{
			$store = $this->storeManager->getStore($storeId);

			$merchantid = $store->getConfig('codisto/merchantid');
			$hostkey = $store->getConfig('codisto/hostkey');

			if($merchantid && $merchantid != '') {
				$merchantlist = $this->json->jsonDecode($merchantid);
				if(!is_array($merchantlist))
					$merchantlist = array($merchantlist);

				foreach($merchantlist as $merchantid)
				{
					if(!in_array($merchantid, $merchantSignalled, true))
					{
						$syncDb = $this->codistoHelper->getSyncPath('sync-'.$storeId.'.db');

						$this->sync->UpdateCategory($syncDb, $categoryId, $storeId);

						$merchantSignalled[] = $merchantid;
						$merchants[] = array('merchantid' => $merchantid, 'hostkey' => $hostkey, 'storeid' => $storeId );
					}
				}
			}
		}

		$this->codistoHelper->signal($merchants, 'action=sync&categoryid='.$categoryId);

		return $this;
	}
}
