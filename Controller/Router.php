<?php
	
namespace Codisto\Connect\Controller;

class Router implements \Magento\Framework\App\RouterInterface
{
	private $_actionFactory;
	
	public function __construct(\Codisto\Connect\Controller\DummyActionInstanceFactory $actionFactory)
	{
		$this->_actionFactory = $actionFactory;
	}
	
	public function match(\Magento\Framework\App\RequestInterface $request)
	{
		$path = $request->getPathInfo();
		
		if(preg_match('/^\/admin\/codisto\/(?!listings\/index\/|orders\/index\/|categories\/index\/|attributes\/index|import\/index|settings\/index)/', $path))
		{
			
			
			return $this->_actionFactory->create();
			
		}
		
		return;
	}
	
}
