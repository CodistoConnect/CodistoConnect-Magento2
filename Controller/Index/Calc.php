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

namespace Codisto\Connect\Controller\Index;

class Calc extends \Magento\Framework\App\Action\Action
{
	private $context;

	public function __construct(
		\Magento\Framework\App\Action\Context $context
	) {
		parent::__construct($context);

		$this->context = $context;
	}

	public function execute()
	{
		$rawResult = $this->context->getResultFactory()->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
		$rawResult->setHttpResponseCode(200);
		$rawResult->setHeader('Cache-Control', 'no-cache', true);
		$rawResult->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
		$rawResult->setHeader('Pragma', 'no-cache', true);
		$rawResult->setData(array( 'ack' => 'failed' ));
		return $rawResult;
	}
}
