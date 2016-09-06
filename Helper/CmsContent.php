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
$om = $bootstrap->getObjectManager();

$om->get('Magento\Framework\App\State')->setAreaCode('backend');

try
{
	$contents = file_get_contents('php://stdin');

	$filterProvider = $om->create('Magento\Cms\Model\Template\FilterProvider');
	$blockFilter = $filterProvider->getBlockFilter();

	// TODO: write out argv and find storeid

	$blockFilter->setStoreId(0);

	echo $blockFilter->filter($contents);
}
catch(\Exception $e)
{
	echo $e->getMessage();
}
