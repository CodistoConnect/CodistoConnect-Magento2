<?php
namespace Codisto\Connect\Controller\Adminhtml\Listings;

use Psr\Log\LoggerInterface as Logger;
use Magento\Backend\App\Action\Context;

class Index extends \Magento\Backend\App\Action
{
	protected $resultPageFactory;

	protected $logger;

	public function __construct(
		Context $context,
		Logger $logger
	) {
		parent::__construct($context);

		$this->logger = $logger;
	}

	public function execute()
	{
		$page = $this->_view->getPage();

		$page->initLayout();

        $page->setActiveMenu('Codisto_Connect::listings')
            ->addBreadcrumb('Listings', 'Listings');

		$page->getConfig()->getTitle()->prepend('Listings');

		$page->setHttpResponseCode(200);
		$page->setHeader('Cache-Control', 'no-cache', true);
		$page->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
		$page->setHeader('Pragma', 'no-cache', true);
		

		$page->addContent(
			$page->getLayout()->createBlock('Codisto\Connect\Block\Adminhtml\Listings\Index', 'codisto.listings.index')
		);

		return $page;
	}

	protected function _isAllowed()
	{
		return $this->_authorization->isAllowed('Codisto_Connect::listings');
	}
}
