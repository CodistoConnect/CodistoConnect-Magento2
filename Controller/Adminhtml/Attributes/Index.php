<?php
namespace Codisto\Connect\Controller\Adminhtml\Attributes;

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

        $page->setActiveMenu('Codisto_Connect::attributes')
            ->addBreadcrumb('Attributes', 'Attributes');

		$page->getConfig()->getTitle()->prepend('Attributes');

		$page->setHttpResponseCode(200);
		$page->setHeader('Cache-Control', 'no-cache', true);
		$page->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
		$page->setHeader('Pragma', 'no-cache', true);
		

		$page->addContent(
			$page->getLayout()->createBlock('Codisto\Connect\Block\Adminhtml\Attributes\Index', 'codisto.attributes.index')
		);

		return $page;
	}

	protected function _isAllowed()
	{
		return $this->_authorization->isAllowed('Codisto_Connect::attributes');
	}
}
