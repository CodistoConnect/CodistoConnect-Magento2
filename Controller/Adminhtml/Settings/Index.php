<?php
namespace Codisto\Connect\Controller\Adminhtml\Settings;

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

        $page->setActiveMenu('Codisto_Connect::settings')
            ->addBreadcrumb('Settings', 'Settings');

		$page->getConfig()->getTitle()->prepend('Settings');

		$page->setHttpResponseCode(200);
		$page->setHeader('Cache-Control', 'no-cache', true);
		$page->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
		$page->setHeader('Pragma', 'no-cache', true);
		

		$page->addContent(
			$page->getLayout()->createBlock('Codisto\Connect\Block\Adminhtml\Settings\Index', 'codisto.settings.index')
		);

		return $page;
	}

	protected function _isAllowed()
	{
		return $this->_authorization->isAllowed('Codisto_Connect::settings');
	}
}
