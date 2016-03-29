<?php

namespace Codisto\Connect\Block\Adminhtml\Attributes;

class Index extends  \Magento\Backend\Block\Template
{
    protected $_template = 'attributes.phtml';

    public function __construct(
            \Magento\Backend\Block\Template\Context $context
    ) { 
syslog(LOG_INFO, __FILE__);
	    
        parent::__construct($context);
    }
}