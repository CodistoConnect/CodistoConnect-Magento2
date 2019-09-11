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
 */

namespace Codisto\Connect\Controller\Sync;

class TestHash extends \Magento\Framework\App\Action\Action
{
    private $context;
    private $moduleList;
    private $storeManager;
    private $codistoHelper;
    private $visitor;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\Customer\Model\Visitor $visitor,
        \Codisto\Connect\Helper\Data $codistoHelper
    ) {
        parent::__construct($context);

        $this->context = $context;
        $this->moduleList = $moduleList;
        $this->storeManager = $storeManager;
        $this->codistoHelper = $codistoHelper;
        $this->visitor = $visitor;
    }

    private function _createResponse($statusCode, $result)
    {
        $rawResult = $this->context->getResultFactory()->create(
            \Magento\Framework\Controller\ResultFactory::TYPE_RAW
        );
        $rawResult->setHttpResponseCode($statusCode);
        $rawResult->setHeader('Cache-Control', 'no-cache', true);
        $rawResult->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
        $rawResult->setHeader('Pragma', 'no-cache', true);
        $rawResult->setHeader('Content-Type', 'text/plain');
        $rawResult->setContents($result);
        return $rawResult;
    }

    public function execute()
    {
        $this->visitor->setSkipRequestLogging(true);

        $request = $this->getRequest();
        $request->setDispatched(true);
        $server = $request->getServer();

        $storeId = $request->getQuery('storeid') == null ? 0 : (int)$request->getQuery('storeid');

        $codistoModule = $this->moduleList->getOne('Codisto_Connect');
        $codistoVersion = $codistoModule['setup_version'];

        if (!$this->codistoHelper->getConfig($storeId)) {
            return $this->_createResponse(400, 'Config Error');
        }

        $store = $this->storeManager->getStore($storeId);

        if ($this->codistoHelper->checkRequestHash($store->getConfig('codisto/hostkey'), $server)) {
            $result = $this->_createResponse(200, 'OK');
            $result->setHeader('X-Codisto-Version', $codistoVersion, true);
            return $result;
        } else {
            return $this->_createResponse(400, 'Security Error');
        }
    }
}
