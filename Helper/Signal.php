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

// bootstrap magento environment for signal sub-process
require 'app/bootstrap.php'; // @codingStandardsIgnoreLine MEQP1.Security.IncludeFile.FoundIncludeFile

// use superglobal here instead of request object as we aren't in http context
$params = $_SERVER; // @codingStandardsIgnoreLine MEQP2.Security.Superglobal.SuperglobalUsageWarning
$params[\Magento\Framework\App\Bootstrap::PARAM_REQUIRE_MAINTENANCE] = false;
$params[\Magento\Framework\App\Bootstrap::PARAM_REQUIRE_IS_INSTALLED] = false;

$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $params);

$om = $bootstrap->getObjectManager();

$om
    ->get('Magento\Framework\App\State') // @codingStandardsIgnoreLine MEQP2.Classes.ObjectManager.ObjectManagerFound
    ->setAreaCode('adminhtml');

$helper = $om->create('Codisto\Connect\Helper\Data'); // @codingStandardsIgnoreLine MEQP2.Classes.ObjectManager.ObjectManagerFound

// using unserialize purely as IPC messaging format between parent
// and child process
$merchants = unserialize($argv[1]); // @codingStandardsIgnoreLine
$msg = $argv[2];
$eventtype = $argv[3];
$productids = unserialize($argv[4]); // @codingStandardsIgnoreLine

$helper->registerProductChanges($merchants, $eventtype, $productids);

$curlOptions = [ CURLOPT_TIMEOUT => 20 ];

// using getenv to receive CURL certificate bundle from parent process without
// instantiating all of a HTTP request object to retrieve 'server' state
if (getenv('CURL_CA_BUNDLE')) { // @codingStandardsIgnoreLine
    $curlOptions[CURLOPT_CAINFO] = getenv('CURL_CA_BUNDLE'); // @codingStandardsIgnoreLine
}

// using zend http client directly in sub process
$client = new \Zend_Http_Client(); // @codingStandardsIgnoreLine
$client->setConfig(
    [
        'adapter' => 'Zend_Http_Client_Adapter_Curl',
        'curloptions' => $curlOptions,
        'keepalive' => true,
        'maxredirects' => 0
    ]
);
$client->setStream();

foreach ($merchants as $merchant) {
    for ($Retry = 0;; $Retry++) {
        try {
            $client->setUri('https://api.codisto.com/'.$merchant['merchantid']);
            $client->setHeaders('X-HostKey', $merchant['hostkey']);
            $client->setRawData($msg)->request('POST');
            break;
        } catch (\Exception $e) {
            if ($Retry >= 3) {
                break;
            }
            usleep(100000);
            continue;
        }
    }
}
