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
		syslog(LOG_INFO, 'INDEXER LAUNCHED');

		$this->storeManager = $storeManager;
		$this->json = $json;
		$this->codistoHelper = $codistoHelper;
	}

	public function execute($ids)
	{
syslog(LOG_INFO, 'execute mview '.print_r($ids, true));
	}

	public function executeFull()
	{
		$merchants = array();
		$visited = array();
syslog(LOG_INFO, 'executeFull');
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
syslog(LOG_INFO, print_r($merchants, true));
		$this->codistoHelper->signal($merchants, 'action=sync');
	}

	public function executeList(array $ids)
	{
syslog(LOG_INFO, 'execute list '.print_r($ids, true));
	}

	public function executeRow($id)
	{
syslog(LOG_INFO, 'execute row '.print_r($ids, true));
	}
}
