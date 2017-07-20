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
 * @copyright 2016-2017 On Technology Pty. Ltd. (http://codisto.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      https://codisto.com/connect/
 */

namespace Codisto\Connect\Router;

class Router implements \Magento\Framework\App\RouterInterface
{
    private $actionFactory;
    private $backendHelper;

    public function __construct(
        \Magento\Backend\Helper\Data $backendHelper,
        \Codisto\Connect\Controller\CodistoActionInstanceFactory $actionFactory
    ) {
        $this->backendHelper = $backendHelper;
        $this->actionFactory = $actionFactory;
    }

    // this is a public method of the RouterInterface
    public function match(\Magento\Framework\App\RequestInterface $request)
    {
        $adminUrl = $this->backendHelper->getAreaFrontName();

        $path = $request->getPathInfo();

        if (preg_match(
            '/^\/'.preg_quote($adminUrl, '/').'\/codisto\/'.
            '(?!listings(?:\/index(?:\/|\?))?|'.
            'orders(?:\/index(?:\/|\?))?|'.
            'categories(?:\/index(?:\/|\?))?|'.
            'attributes(?:\/index(?:\/|\?))?|'.
            'profiles(?:\/index(?:\/|\?))?|'.
            'import(?:\/index(?:\/|\?))?|'.
            'settings\/index(?:\/index(?:\/|\?))?|'.
            'account(?:\/index(?:\/|\?))?)/',
            $path
        )) {
            return $this->actionFactory->create();
        }

        return false;
    }
}
