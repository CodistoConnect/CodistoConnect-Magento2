<?php

namespace Codisto\Connect\Block\Adminhtml\Listings;

class Index extends  \Magento\Backend\Block\Template
{
    protected $_template = 'listings.phtml';

    public function __construct(
            \Magento\Backend\Block\Template\Context $context
    ) { 
syslog(LOG_INFO, __FILE__);
	    
        parent::__construct($context);
    }
}