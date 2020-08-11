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
 * @copyright 2020-21 On Technology Pty. Ltd. (http://codisto.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      https://codisto.com/connect/
 */

namespace Codisto\Connect\Controller\Index;

class Redir extends \Magento\Framework\App\Action\Action
{
    private $context;

    public function __construct(
        \Magento\Framework\App\Action\Context $context
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $admin_base_url = $objectManager->create('Magento\Backend\Helper\Data')->getAreaFrontName();
        $base_url = $objectManager->create('\Magento\Store\Model\StoreManagerInterface')->getStore()->getBaseUrl().$admin_base_url."/";

        $request = $objectManager->get('Magento\Framework\App\RequestInterface');

        $cookieMeta =  $objectManager->create('\Magento\Framework\Stdlib\Cookie\CookieMetadataFactory')->createPublicCookieMetadata();

        $cookie = $objectManager->create('\Magento\Framework\Stdlib\CookieManagerInterface');

        $cookieMeta->setDuration(300);
        $cookieMeta->setPath('/');
        $cookieMeta->setHttpOnly(true);
        $cookie->setPublicCookie(
            'codisto_action',
            $request->getQuery('a'),
            $cookieMeta
        );

        $backToUrl = urldecode($request->getQuery('backto'));
        $backToUrl .= "?h=".urlencode($request->getQuery('h'));
        $backToUrl .= "&a=".$request->getQuery('a');
        if($request->getQuery('p')) {
          $backToUrl .= "&p=".$request->getQuery('p');
        }
        $backToUrl .= "&adminurl=".$base_url;
        $this->getResponse()->setRedirect($backToUrl);
    }

}
