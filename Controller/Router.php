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
 * @category    Codisto
 * @package     codisto/codisto-connect
 * @copyright   Copyright (c) 2016 On Technology Pty. Ltd. (http://codisto.com/)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
 
namespace Codisto\Connect\Controller;

use \Magento\Framework\App\RouterInterface;
use \Magento\Backend\Helper\Data;

class Router implements RouterInterface
{
	private $actionFactory;
	private $backendHelper;

	public function __construct(
						Data $backendHelper,
						CodistoActionInstanceFactory $actionFactory
						)
	{
		$this->backendHelper = $backendHelper;
		$this->actionFactory = $actionFactory;
	}

	public function match(\Magento\Framework\App\RequestInterface $request)
	{
		$adminUrl = $this->backendHelper->getAreaFrontName();

		$path = $request->getPathInfo();

		if(preg_match('/^\/'.preg_quote($adminUrl, '/').'\/codisto\/'.
			'(?!listings\/index\/|orders\/index\/|categories\/index\/|attributes\/index|import\/index|settings\/index)/',
			$path))
		{
			return $this->actionFactory->create();
		}

		return false;
	}

}
