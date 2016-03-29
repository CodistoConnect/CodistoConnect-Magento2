<?php
namespace Codisto\Connect\Controller\Index;

use Psr\Log\LoggerInterface as Logger;

class Index extends \Magento\Framework\App\Action\Action
{
	protected $context;
	protected $logger;

	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		Logger $logger
		
	) {
		$this->context = $context;
		$this->logger = $logger;
		parent::__construct($context);
	}

	public function execute()
	{
		$rawResult = $this->context->getResultFactory()->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
		$rawResult->setHttpResponseCode(200);
		$rawResult->setHeader('Cache-Control', 'no-cache', true);
		$rawResult->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
		$rawResult->setHeader('Pragma', 'no-cache', true);
		$rawResult->setContents('hi89');

		$this->logger->critical('test19');
		return $rawResult;
	}
}
