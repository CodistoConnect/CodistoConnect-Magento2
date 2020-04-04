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

namespace Codisto\Connect\Controller\Adminhtml;

use \Magento\Framework\Controller\ResultFactory;

class CodistoController extends \Magento\Backend\App\Action
{
    private $context;
    private $productMetaData;
    private $scopeConfig;
    private $reinitConfig;
    private $assetRepository;
    private $assetCollection;
    private $formKey;
    private $storeManager;
    private $json;
    private $session;

    protected $_publicActions = ['index', 'account', 'attributes', 'categories', 'importlistings', 'settings', 'profiles', 'orders'];

    // protected class members used here so that the only
    // thing needed in each of the descendant controllers
    // is overriding of the member variables
    protected $view;        // @codingStandardsIgnoreLine
    protected $breadCrumb;  // @codingStandardsIgnoreLine
    protected $frameUrl;    // @codingStandardsIgnoreLine

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\App\ProductMetadata $productMetaData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\Config\ReinitableConfigInterface $reinitConfig,
        \Magento\Framework\View\Asset\Repository $assetRepository,
        \Magento\Framework\View\Asset\GroupedCollection $assetCollection,
        \Magento\Framework\Data\Form\FormKey $formKey,
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\Framework\Json\Helper\Data $json,
        \Magento\Config\Model\ResourceModel\ConfigFactory $configFactory,
        \Magento\Backend\Model\Auth\Session\Proxy $session, // @codingStandardsIgnoreLine Magento2.Classes.DiscouragedDependencies.ConstructorProxyInterceptor
        \Magento\Framework\Module\ModuleListInterface $moduleList
    ) {
        parent::__construct($context);

        $this->context = $context;
        $this->productMetaData = $productMetaData;
        $this->scopeConfig = $scopeConfig;
        $this->reinitConfig = $reinitConfig;
        $this->assetRepository = $assetRepository;
        $this->assetCollection = $assetCollection;
        $this->formKey = $formKey;
        $this->urlInterface = $context->getUrl();
        $this->storeManager = $storeManager;
        $this->json = $json;
        $this->configFactory = $configFactory;
        $this->session = $session;
        $this->moduleList = $moduleList;
    }

    private function createAccount()
    {
        $request = $this->getRequest();
        $response = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        if ($request->isPost()) {
            $method = $request->getPost('method');

            if ($method == 'email') {
                $store = $this->storeManager->getStore();

                $storeName = $store->getWebsite()->getName();
                $storeInfo = $this->scopeConfig->getValue(
                    'general/store_information',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                    \Magento\Store\Model\Store::DEFAULT_STORE_ID
                );
                if (is_array($storeInfo) &&
                    isset($storeInfo['name']) &&
                    $storeInfo['name']) {
                    $storeName = $storeInfo['name'];
                }
                $storeCurrency = $store->getBaseCurrencyCode();

                $resellerkey = '';

                $codistoModule = $this->moduleList->getOne('Codisto_Connect');
                $codistoVersion = $codistoModule['setup_version'];

                $curlOptions = [CURLOPT_TIMEOUT => 60, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0];

                // impossible to use the object manager because di on non-namespaced classes is broken
                $client = new \Zend_Http_Client( // @codingStandardsIgnoreLine MEQP2.Classes.ObjectInstantiation.FoundDirectInstantiation
                    'https://ui.codisto.com/create',
                    [
                        'adapter' => 'Zend_Http_Client_Adapter_Curl',
                        'curloptions' => $curlOptions,
                        'keepalive' => false,
                        'strict' => false,
                        'strictredirects' => true,
                        'maxredirects' => 0,
                        'timeout' => 60
                    ]
                );

                $client->setHeaders('Content-Type', 'application/json');
                $client->setRawData(
                    $this->json->jsonEncode(
                        [
                            'type' => 'magento',
                            'version' => $this->productMetaData->getVersion(),
                            'url' => $store->getBaseUrl(),
                            'email' => $request->getPost('email'),
                            'country' => $request->getPost('countrycode'),
                            'storename' => $storeName,
                            'resellerkey' => $resellerkey,
                            'codistoversion' => $codistoVersion
                        ]
                    )
                );

                $remoteResponse = $client->request('POST');

                $regData = $this->json->jsonDecode($remoteResponse->getRawBody());

                $config = $this->configFactory->create();

                $config->saveConfig(
                    'codisto/merchantid',
                    $regData['merchantid'],
                    \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                    \Magento\Store\Model\Store::DEFAULT_STORE_ID
                );
                $config->saveConfig(
                    'codisto/hostkey',
                    $regData['hostkey'],
                    \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                    \Magento\Store\Model\Store::DEFAULT_STORE_ID
                );

                $this->reinitConfig->reinit();
                $this->storeManager->reinitStores();

                $response->setUrl($this->urlInterface->getUrl('codisto/listings/index'));
            } else {
                $store = $this->storeManager->getStore();

                $type = 'magento';
                $version = $this->productMetaData->getVersion();
                $url = $store->getBaseUrl();
                $storeName = $store->getWebsite()->getName();
                $storeInfo = $this->scopeConfig->getValue(
                    'general/store_information',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                    \Magento\Store\Model\Store::DEFAULT_STORE_ID
                );
                if (is_array($storeInfo) &&
                    isset($storeInfo['name']) &&
                    $storeInfo['name']) {
                    $storeName = $storeInfo['name'];
                }
                $storeCurrency = $store->getBaseCurrencyCode();

                $resellerKey = '';

                $codistoModule = $this->moduleList->getOne('Codisto_Connect');
                $codistoVersion = $codistoModule['setup_version'];

                $response->setUrl(
                    'https://ui.codisto.com/register?finalurl='.
                    urlencode($this->urlInterface->getUrl('codisto/listings/index').'?action=codisto_create').
                    '&type='.urlencode($type).
                    '&version='.urlencode($version).
                    '&url='.urlencode($url).
                    '&storename='.urlencode($storeName).
                    '&storecurrency='.urlencode($storeCurrency).
                    '&resellerkey='.urlencode($resellerKey).
                    '&codistoversion='.urlencode($codistoVersion)
                );
            }
        } else {
            $curlOptions = [ CURLOPT_TIMEOUT => 60, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0 ];

            // impossible to use the object manager because di on non-namespaced classes is broken
            $client = new \Zend_Http_Client( // @codingStandardsIgnoreLine MEQP2.Classes.ObjectInstantiation.FoundDirectInstantiation
                'https://ui.codisto.com/create',
                [
                    'adapter' => 'Zend_Http_Client_Adapter_Curl',
                    'curloptions' => $curlOptions,
                    'keepalive' => false,
                    'strict' => false,
                    'strictredirects' => true,
                    'maxredirects' => 0,
                    'timeout' => 60
                ]
            );

            $client->setHeaders('Content-Type', 'application/json');
            $client->setRawData($this->json->jsonEncode([ 'regtoken' => $request->getQuery('regtoken') ]));

            $remoteResponse = $client->request('POST');

            $regData = $this->json->jsonDecode($remoteResponse->getRawBody());

            $config = $this->configFactory->create();

            $config->saveConfig(
                'codisto/merchantid',
                $regData['merchantid'],
                \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                \Magento\Store\Model\Store::DEFAULT_STORE_ID
            );
            $config->saveConfig(
                'codisto/hostkey',
                $regData['hostkey'],
                \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                \Magento\Store\Model\Store::DEFAULT_STORE_ID
            );

            $this->reinitConfig->reinit();
            $this->storeManager->reinitStores();

            $response->setUrl($this->urlInterface->getUrl('codisto/listings/index'));
        }

        return $response;
    }

    public function execute()
    {
        $request = $this->getRequest();

        if ($request->isPost() ||
            $request->getQuery('action') == 'codisto_create') {
            return $this->createAccount();
        }

        $page = $this->_view->getPage();

        $page->initLayout();

        $page->setActiveMenu('Codisto_Connect::'.$this->view)
            ->addBreadcrumb($this->breadCrumb, $this->breadCrumb);

        $page->getConfig()->getTitle()->prepend($this->breadCrumb);

        $page->setHttpResponseCode(200);
        $page->setHeader('Cache-Control', 'no-cache', true);
        $page->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
        $page->setHeader('Pragma', 'no-cache', true);

        $page->getConfig()->addBodyClass("codisto");

        $asset = $this->assetRepository->createAsset('Codisto_Connect::codisto.css');
        $this->assetCollection->add('codisto-css', $asset);

        $asset = $this->assetRepository->createAsset('Codisto_Connect::codisto.js');
        $this->assetCollection->add('codisto-js', $asset);

        if (!$this->scopeConfig->getValue('codisto/merchantid')) {
            $adminUser = $this->session->getUser();

            $block = $page->getLayout()->createBlock(\Magento\Framework\View\Element\Template::class);
            $block->setTemplate('Codisto_Connect::register.phtml');
            $block->assign('form_key', $this->formKey->getFormKey());
            $block->assign('email', $adminUser->getEmail());
        } else {
            $block = $page->getLayout()->createBlock(\Magento\Framework\View\Element\Template::class);
            $block->setTemplate('Codisto_Connect::frame.phtml');
            $block->assign('class', 'codisto-'.$this->view);
            $block->assign('admin_url', $this->urlInterface->turnOffSecretKey()->getUrl($this->frameUrl));
        }

        $page->addContent($block);

        return $page;
    }

    // using protected as it's an override from Magento core
    protected function _isAllowed() // @codingStandardsIgnoreLine MEQP2.PHP.ProtectedClassMember.FoundProtected
    {
        return $this->_authorization->isAllowed('Codisto_Connect::'.$this->view);
    }
}
