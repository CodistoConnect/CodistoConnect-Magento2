<?php

/**
 * Codisto Marketplace Sync Extension
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

namespace Codisto\Connect\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;

class PreDispatchObserver implements ObserverInterface
{
    public function execute(EventObserver $observer)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $requestInterface = $objectManager->get('Magento\Framework\App\RequestInterface');
        $cookie = $objectManager->get('\Magento\Framework\Stdlib\CookieManagerInterface');
        $responseFactory = $objectManager->get('\Magento\Framework\App\ResponseFactory');
        $controllerName = $requestInterface->getControllerName();
        $action = $cookie->getCookie('codisto_action');
        if ($controllerName == 'redir') {
          $cookieMeta =  $objectManager->create('\Magento\Framework\Stdlib\Cookie\CookieMetadataFactory')->createPublicCookieMetadata();
          $cookie = $objectManager->create('\Magento\Framework\Stdlib\CookieManagerInterface');
          $cookieMeta->setDuration(time()-43300);
          $cookieMeta->setPath('/');
          $cookieMeta->setHttpOnly(true);
          $cookie->setPublicCookie(
              'codisto_action',
              false,
              $cookieMeta
          );
          $url = $objectManager->create('\Magento\Framework\UrlInterface');
          $myUrl = $url->getUrl('codisto/' .$action . '/index' );
          $responseFactory->create()->setRedirect($myUrl)->sendResponse();
          exit;
        }
    }
}
