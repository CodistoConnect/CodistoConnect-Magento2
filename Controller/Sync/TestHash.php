<?php

/**
 * Codisto Marketplace Connect Sync Extension
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

namespace Codisto\Connect\Controller\Sync;

class TestHash extends \Magento\Framework\App\Action\Action
{
    private $context;
    private $moduleList;
    private $storeManager;
    private $codistoHelper;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Store\Model\StoreManager $storeManager,
        \Codisto\Connect\Helper\Data $codistoHelper
    ) {
        parent::__construct($context);

        $this->context = $context;
        $this->moduleList = $moduleList;
        $this->storeManager = $storeManager;
        $this->codistoHelper = $codistoHelper;
    }

    public function execute()
    {
        $request = $this->getRequest();
        $request->setDispatched(true);
        $server = $request->getServer();

        $storeId = $request->getQuery('storeid') == null ? 0 : (int)$request->getQuery('storeid');

        $codistoModule = $this->moduleList->getOne('Codisto_Connect');
        $codistoVersion = $codistoModule['setup_version'];

        if (!$this->codistoHelper->getConfig($storeId)) {
            $rawResult = $this->context->getResultFactory()->create(
                \Magento\Framework\Controller\ResultFactory::TYPE_RAW
            );
            $rawResult->setHttpResponseCode(400);
            $rawResult->setHeader('Cache-Control', 'no-cache', true);
            $rawResult->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
            $rawResult->setHeader('Pragma', 'no-cache', true);
            $rawResult->setHeader('Content-Type', 'text/plain');
            $rawResult->setContents('Config Error');
            return $rawResult;
        }

        $store = $this->storeManager->getStore($storeId);

        if ($this->codistoHelper->checkRequestHash($store->getConfig('codisto/hostkey'), $server)) {
            $rawResult = $this->context->getResultFactory()->create(
                \Magento\Framework\Controller\ResultFactory::TYPE_RAW
            );
            $rawResult->setHttpResponseCode(200);
            $rawResult->setHeader('Cache-Control', 'no-cache', true);
            $rawResult->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
            $rawResult->setHeader('Pragma', 'no-cache', true);
            $rawResult->setHeader('X-Codisto-Version', $codistoVersion, true);
            $rawResult->setContents('OK');
            return $rawResult;
        } else {
            $rawResult = $this->context->getResultFactory()->create(
                \Magento\Framework\Controller\ResultFactory::TYPE_RAW
            );
            $rawResult->setHttpResponseCode(400);
            $rawResult->setHeader('Cache-Control', 'no-cache', true);
            $rawResult->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
            $rawResult->setHeader('Pragma', 'no-cache', true);
            $rawResult->setHeader('Content-Type', 'text/plain');
            $rawResult->setContents('Security Error');
            return $rawResult;
        }
    }
}
