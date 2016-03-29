<?php
	
namespace Codisto\Connect\Controller;

class DummyActionInstance extends \Magento\Framework\App\Action\AbstractAction
{
	public function __construct(\Magento\Framework\App\Action\Context $context)
	{
		parent::__construct($context);
	}
	
	public function execute()
	{
	}
	
	public function dispatch(\Magento\Framework\App\RequestInterface $request)
	{
		$merchantID = 12822;
		$hostKey = 'x';
		$storeId = 0;
		
		$path = $request->getPathInfo();
		
		$storematch = array();
		if(preg_match('/^\/admin\/codisto\/ebaytab\/(\d+)\/\d+/', $path, $storematch))
		{
			$storeId = (int)$storematch[1];
			$path = preg_replace('/(^\/admin\/codisto\/ebaytab\/)(\d+\/?)/', '$1', $path);
		}
		else
		{
			$storeId = (int)$request->getCookie('storeid', '0');
		}
		
		$path = preg_replace('/(^\/admin\/codisto\/ebaytab\/)(\d+\/?)/', '$1', $path);
		
		$remotePath = preg_replace('/^\/admin\/codisto\/\/?|key\/[a-zA-z0-9]*\//', '', $path);
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
		
		$extensionVersion = '1.1.87';
		
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
					'timeout' => 10
				));
				
		$client->setHeaders('X-Admin-Base-Url', 	$adminBaseURL);
		$client->setHeaders('X-Codisto-Version', 	$extensionVersion);
		$client->setHeaders('X-HostKey',			$hostKey);
		
		foreach($this->getAllHeaders() as $k=>$v)
		{
			if(strtolower($k) != 'host')
				$client->setHeaders($k, $v);
		}
/*		
		$requestBody = $request->getBody();
		if($requestBody)
			$client->setRawData($requestBody);
*/		
		$client->setRawData(file_get_contents('php://input'));

		$remoteResponse = $client->request($request->getMethod());
		
		
		$response = new \Magento\Framework\Controller\Result\Raw();
		
		
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
								$config = Mage::getConfig();
								$storeViewMapping = Zend_Json::decode($v);
								foreach($storeViewMapping as $mapping)
								{
									$storeId = $mapping['storeid'];
									$merchantList = $mapping['merchants'];
									if($storeId == 0)
									{
										$config->saveConfig('codisto/merchantid', $merchantList);
									}
									else
									{
										$config->saveConfig('codisto/merchantid', $merchantList, 'stores', $storeId);
									}
								}
								$config->cleanCache();
								Mage::app()->removeCache('config_store_data');
								Mage::app()->getCacheInstance()->cleanType('config');
								Mage::dispatchEvent('adminhtml_cache_refresh_type', array('type' => 'config'));
								Mage::app()->reinitStores();
							}
						}
					}
					//if(!$response->isRedirect())
					//{
						// set proxied output
						$response->setContents($remoteResponse->getRawBody());
					//}

		
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