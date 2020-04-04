<?php

/**
 * Codisto LINQ Sync Extension
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
 *
 */

// bootstrap magento environment for cmscontent sub-process
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

try {
    $response = $om->create('Magento\Framework\App\Console\Response'); // @codingStandardsIgnoreLine MEQP2.Classes.ObjectManager.ObjectManagerFound
    $response->terminateOnSend(true);

    $contents = file_get_contents('php://stdin'); // @codingStandardsIgnoreLine MEQP1.Security.DiscouragedFunction.Found

    $filterProvider = $om->create('Magento\Cms\Model\Template\FilterProvider'); // @codingStandardsIgnoreLine MEQP2.Classes.ObjectManager.ObjectManagerFound
    $blockFilter = $filterProvider->getBlockFilter();

    $storeId = 0;
    foreach ($argv as $idx => $arg) {
        if ($arg == '-storeid') {
            $storeId = (int)($argv[$idx + 1]);
            break;
        }
    }

    $blockFilter->setStoreId($storeId);

    $response->setBody($blockFilter->filter($contents));
} catch (\Exception $e) {
    $response->setBody($e->getMessage());
}

$response->sendResponse();
