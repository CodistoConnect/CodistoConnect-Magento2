<?php
namespace Codisto\Connect\Controller\Adminhtml\Orders;

use Magento\Backend\App\Action\Context;

class Index extends \Magento\Backend\App\Action
{
	public function __construct(
		Context $context
	) {
		parent::__construct($context);
	}

	public function execute()
	{
		$page = $this->_view->getPage();

		$page->initLayout();

        $page->setActiveMenu('Codisto_Connect::orders')
            ->addBreadcrumb('Orders', 'Orders');

		$page->getConfig()->getTitle()->prepend('Orders');

		$page->setHttpResponseCode(200);
		$page->setHeader('Cache-Control', 'no-cache', true);
		$page->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
		$page->setHeader('Pragma', 'no-cache', true);
		

		$page->addContent(
			$page->getLayout()->createBlock('Codisto\Connect\Block\Adminhtml\Orders\Index', 'codisto.orders.index')
		);

		return $page;
	}

	protected function _isAllowed()
	{
		return $this->_authorization->isAllowed('Codisto_Connect::orders');
	}
}
