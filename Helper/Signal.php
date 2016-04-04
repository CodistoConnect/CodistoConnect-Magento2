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

require 'app/bootstrap.php';

$params = $_SERVER;
$params[\Magento\Framework\App\Bootstrap::PARAM_REQUIRE_MAINTENANCE] = false;
$params[\Magento\Framework\App\Bootstrap::PARAM_REQUIRE_IS_INSTALLED] = false;

$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $params);


$merchants = unserialize($argv[1]);
$msg = $argv[2];

$curlOptions = array( CURLOPT_TIMEOUT => 10 );

if(isset($_ENV['CURL_CA_BUNDLE']) && $_ENV['CURL_CA_BUNDLE'])
{
	$curlOptions[CURLOPT_CAINFO] = $_ENV['CURL_CA_BUNDLE'];
}

$client = new \Zend_Http_Client();
$client->setConfig(array( 'adapter' => 'Zend_Http_Client_Adapter_Curl', 'curloptions' => $curlOptions, 'keepalive' => true, 'maxredirects' => 0 ));
$client->setStream();

foreach($merchants as $merchant)
{
	for($Retry = 0; ; $Retry++)
	{
		try
		{
			$client->setUri('https://api.codisto.com/'.$merchant['merchantid']);
			$client->setHeaders('X-HostKey', $merchant['hostkey']);
			$client->setRawData($msg)->request('POST');
			break;
		}
		catch(Exception $e)
		{
			if($Retry >= 3)
			{
				Mage::logException($e);
				break;
			}

			usleep(100000);
			continue;
		}
	}
}
