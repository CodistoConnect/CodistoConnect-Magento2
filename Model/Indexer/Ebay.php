<?php

namespace Codisto\Connect\Model\Indexer;

class Ebay implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
	private $storeManager;
	private $json;
	private $codistoHelper;

	public function __construct(
		\Magento\Store\Model\StoreManager $storeManager,
		\Magento\Framework\Json\Helper\Data $json,
		\Codisto\Connect\Helper\Data $codistoHelper
	){
		$this->storeManager = $storeManager;
		$this->json = $json;
		$this->codistoHelper = $codistoHelper;
	}

	public function execute($ids)
	{

	}

	public function executeFull()
	{
		$merchants = array();
		$visited = array();

		$stores = $this->storeManager->getStores(true);

		foreach($stores as $store)
		{
			$merchantlist = $this->json->jsonDecode($store->getConfig('codisto/merchantid'));
			if($merchantlist)
			{
				if(!is_array($merchantlist))
					$merchantlist = array($merchantlist);

				foreach($merchantlist as $merchantId)
				{
					if(!in_array($merchantId, $visited, true))
					{
						$merchants[] = array( 'merchantid' => $merchantId, 'hostkey' => $store->getConfig('codisto/hostkey'), 'storeid' => $store->getId() );
						$visited[] = $merchantId;
					}
				}
			}
		}

		unset($visited);

		$this->codistoHelper->signal($merchants, 'action=sync');
	}

	public function executeList(array $ids)
	{

	}

	public function executeRow($id)
	{

	}
}
