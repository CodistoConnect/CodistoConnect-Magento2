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

class AdminLoginObserver implements ObserverInterface
{
     /**
     * @var \Magento\Framework\App\ResponseFactory
     */
    private $responseFactory;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $url;
    private $session;
    private $cookie;
    private $cookieMeta;

    public function __construct(
        \Magento\Framework\App\ResponseFactory $responseFactory,
        \Magento\Framework\UrlInterface $url,
        \Magento\Backend\Model\Auth\Session $authSession,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookie,
        \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMeta
    ) {
        $this->responseFactory = $responseFactory;
        $this->url = $url;
        $this->session = $authSession;
        $this->cookie = $cookie;
        $this->cookieMeta = $cookieMeta;
    }

    public function execute(EventObserver $observer)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $action = $this->cookie->getCookie('codisto_action');
        if ($action && $this->session->isLoggedIn()) {
            $this->cookieMeta->setDuration(time()-43300);
            $this->cookieMeta->setPath('/');
            $this->cookieMeta->setHttpOnly(true);

            $this->cookie->setPublicCookie(
                'codisto_action',
                false,
                $this->cookieMeta
            );
            $myUrl = $this->url->getUrl('codisto/' .$action . '/index' );
            $this->responseFactory->create()->setRedirect($myUrl)->sendResponse();
            exit;
        }
    }
}
