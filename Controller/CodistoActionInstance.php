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

namespace Codisto\Connect\Controller;


class CodistoActionInstance extends \Magento\Framework\App\Action\AbstractAction
{
	private $context;
	private $scopeConfig;
	private $configFactory;
	private $cacheTypeList;
	private $json;
	private $backendHelper;
	private $moduleList;
	private $storeManager;
	private $redirectResponseFactory;
	private $rawResponseFactory;

	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Config\Model\ResourceModel\ConfigFactory $configFactory,
		\Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
		\Magento\Framework\Json\Helper\Data $json,
		\Magento\Backend\Helper\Data $backendHelper,
		\Magento\Framework\Module\ModuleListInterface $moduleList,
		\Magento\Store\Model\StoreManager $storeManager,
		\Magento\Framework\Controller\Result\RawFactory $rawResponseFactory
	) {
		parent::__construct($context);

		$this->context = $context;
		$this->scopeConfig = $scopeConfig;
		$this->configFactory = $configFactory;
		$this->cacheTypeList = $cacheTypeList;
		$this->json = $json;
		$this->backendHelper = $backendHelper;
		$this->moduleList = $moduleList;
		$this->storeManager = $storeManager;
		$this->rawResponseFactory = $rawResponseFactory;
	}

	public function execute()
	{
	}

	public function dispatch(\Magento\Framework\App\RequestInterface $request)
	{
		$request->setDispatched(true);

		$storeId = 0;

		$path = $request->getPathInfo();

		$adminPath = $this->backendHelper->getAreaFrontName();

		// redirect to product page
		if(preg_match('/^\/'.preg_quote($adminPath, '/').'\/codisto\/ebaytab(?:\/|$)/', $path) && $request->getQuery('productid'))
		{
			$productUrl = '/' . $adminPath . '/catalog/product/edit/id/'.$request->getQuery('productid');

			$response = $this->context->getResultRedirectFactory()->create();
			$response->setUrl( $productUrl );

			return $response;
		}

		$storematch = array();
		if(preg_match('/^\/'.preg_quote($adminPath, '/').'\/codisto\/ebaytab\/(\d+)\/\d+/', $path, $storematch))
		{
			$storeId = (int)$storematch[1];
			$path = preg_replace('/(^\/'.preg_quote($adminPath, '/').'\/codisto\/ebaytab\/)(\d+\/?)/', '$1', $path);
		}
		else
		{
			$storeId = (int)$request->getCookie('storeid', '0');
		}

		if($storeId == 0)
		{
			$merchantID = $this->scopeConfig->getValue('codisto/merchantid', \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $storeId);
		}
		else
		{
			$merchantID = $this->scopeConfig->getValue('codisto/merchantid', 'stores', $storeId);
		}
		$merchantID = $this->json->jsonDecode($merchantID);
		if(is_array($merchantID))
		{
			$merchantID = $merchantID[0];
		}

		$hostKey = $this->scopeConfig->getValue('codisto/hostkey', \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $storeId);

		$path = preg_replace('/(^\/'.preg_quote($adminPath, '/').'\/codisto\/ebaytab\/)(\d+\/?)/', '$1', $path);

		$remotePath = preg_replace('/^\/'.preg_quote($adminPath, '/').'\/codisto\/\/?|key\/[a-zA-z0-9]*\//', '', $path);
		if($merchantID)
		{
			$remoteUrl = 'https://ui.codisto.com/' . $merchantID . '/' . $remotePath;
		}
		else
		{
			$remoteUrl = 'https://ui.codisto.com/' . $remotePath;
		}
		$querystring = '?';
		foreach($request->getQuery() as $k=>$v) {
			$querystring .= urlencode($k);
			if($v)
			$querystring .= '='.urlencode($v);
			$querystring .= '&';
		}
		$querystring = rtrim(rtrim($querystring, '&'), '?');
		$remoteUrl.=$querystring;

		$codistoModule = $this->moduleList->getOne('Codisto_Connect');
		$codistoVersion = $codistoModule['setup_version'];

		$curlOptions = array(CURLOPT_TIMEOUT => 60, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0);
		$acceptEncoding = $request->getHeader('Accept-Encoding');
		$zlibEnabled = strtoupper(ini_get('zlib.output_compression'));
		if(!$acceptEncoding || ($zlibEnabled == 1 || $zlibEnabled == 'ON'))
			$curlOptions[CURLOPT_ENCODING] = '';

		$adminBasePort = $request->getServer('SERVER_PORT');
		$adminBasePort = $adminBasePort = '' || $adminBasePort == '80' || $adminBasePort == '443' ? '' : ':'.$adminBasePort;
		$adminBasePath = $request->getServer('REQUEST_URI');
		$adminBasePath = substr($adminBasePath, 0, strpos($adminBasePath, '/codisto/'));
		$adminBaseURL = $request->getScheme() . '://' . $request->getHttpHost() . $adminBasePort . $adminBasePath . '/codisto/ebaytab/'.$storeId.'/'.$merchantID.'/';

		$client = new \Zend_Http_Client($remoteUrl, array(
					'adapter' => 'Zend_Http_Client_Adapter_Curl',
					'curloptions' => $curlOptions,
					'keepalive' => false,
					'strict' => false,
					'strictredirects' => true,
					'maxredirects' => 0,
					'timeout' => 60
				));

		$client->setHeaders('X-Admin-Base-Url', 	$adminBaseURL);
		$client->setHeaders('X-Codisto-Version', 	$codistoVersion);
		$client->setHeaders('X-HostKey',			$hostKey);

		foreach($this->getAllHeaders() as $k=>$v)
		{
			if(strtolower($k) != 'host')
				$client->setHeaders($k, $v);
		}

		$client->setRawData(file_get_contents('php://input'));

		$remoteResponse = $client->request($request->getMethod());


		$response = $this->rawResponseFactory->create();


		// set proxied status and headers
		$response->setHttpResponseCode($remoteResponse->getStatus());
		$response->setHeader('Pragma', '', true);
		$response->setHeader('Cache-Control', '', true);
		$filterHeaders = array('server', 'content-length', 'transfer-encoding', 'date', 'connection', 'x-storeviewmap');
		if(!$acceptEncoding)
			$filterHeaders[] = 'content-encoding';

		foreach($remoteResponse->getHeaders() as $k => $v)
		{
			if(!in_array(strtolower($k), $filterHeaders, true))
			{
				if(is_array($v))
				{
					$response->setHeader($k, $v[0], true);
					for($i = 1; $i < count($v); $i++)
					{
						$response->setHeader($k, $v[$i]);
					}
				}
				else
				{
					$response->setHeader($k, $v, true);
				}
			}
			else
			{
				if(strtolower($k) == 'x-storeviewmap')
				{
					$config = $this->configFactory->create();

					$storeViewMapping = $this->json->jsonDecode($v);
					foreach($storeViewMapping as $mapping)
					{
						$storeId = $mapping['storeid'];
						$merchantList = $mapping['merchants'];

						if($storeId == 0)
						{
							$config->saveConfig('codisto/merchantid', $merchantList, 'default', 0);
						}
						else
						{
							$config->saveConfig('codisto/merchantid', $merchantList, 'stores', $storeId);
						}
					}

					$this->cacheTypeList->cleanType('config');
					$this->storeManager->reinitStores();
				}
			}
		}

		$response->setContents($remoteResponse->getRawBody());

		return $response;
	}

	private function getAllHeaders($extra = false)
	{
		foreach ($_SERVER as $name => $value)
		{
			if (substr($name, 0, 5) == 'HTTP_')
			{
				$name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
				$headers[$name] = $value;
			} else if ($name == 'CONTENT_TYPE') {
				$headers['Content-Type'] = $value;
			} else if ($name == 'CONTENT_LENGTH') {
				$headers['Content-Length'] = $value;
			}
		}
		if($extra)
		{
			$headers = array_merge($headers, $extra);
		}
		return $headers;
	}
}
