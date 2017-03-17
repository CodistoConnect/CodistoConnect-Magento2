<?php

/**
 * Codisto Marketplace Connect Sync Extension
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

namespace Codisto\Connect\Controller;

class CodistoActionInstance extends \Magento\Framework\App\Action\AbstractAction
{
    private $context;
    private $scopeConfig;
    private $configFactory;
    private $cacheTypeList;
    private $json;
    private $backendHelper;
    private $moduleList;
    private $storeManager;
    private $redirectResponseFactory;
    private $auth;
    private $rawResponseFactory;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Config\Model\ResourceModel\ConfigFactory $configFactory,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\Json\Helper\Data $json,
        \Magento\Backend\Helper\Data $backendHelper,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\Backend\Model\Auth $auth,
        \Magento\Framework\Controller\Result\RawFactory $rawResponseFactory
    ) {
        parent::__construct($context);

        $this->context = $context;
        $this->scopeConfig = $scopeConfig;
        $this->configFactory = $configFactory;
        $this->cacheTypeList = $cacheTypeList;
        $this->json = $json;
        $this->backendHelper = $backendHelper;
        $this->moduleList = $moduleList;
        $this->storeManager = $storeManager;
        $this->auth = $auth;
        $this->rawResponseFactory = $rawResponseFactory;
    }

    public function execute() // @codingStandardsIgnoreLine MEQP1.CodeAnalysis.EmptyBlock.DetectedFUNCTION
    {
        // empty function as all work for this action happens in the dispatch
    }

    private function _handleLoggedIn()
    {
        if (!$this->auth->isLoggedIn()) {
            $response = $this->context->getResultRedirectFactory()->create();
            $response->setPath('*/*/');
            return $response;
        }

        return null;
    }

    private function _handleProductPage(\Magento\Framework\App\RequestInterface $request, $path, $adminPath)
    {
        // redirect to product page if matched
        if (preg_match('/^\/'.preg_quote($adminPath, '/').'\/codisto\/ebaytab(?:\/|$)/', $path)
            && $request->getQuery('productid')) {
            $productUrl = '/' . $adminPath . '/catalog/product/edit/id/'.$request->getQuery('productid');

            $response = $this->context->getResultRedirectFactory()->create();
            $response->setUrl($productUrl);

            return $response;
        }

        return null;
    }

    private function _getStoreIdFromRequest(\Magento\Framework\App\RequestInterface $request, &$path, $adminPath)
    {
        if ($request->getQuery('storeid')) {
            return (int)$request->getQuery('storeid');
        }

        $storematch = [];
        if (preg_match('/^\/'.preg_quote($adminPath, '/').'\/codisto\/ebaytab\/(\d+)\/\d+/', $path, $storematch)) {
            $path = preg_replace('/(^\/'.preg_quote($adminPath, '/').'\/codisto\/ebaytab\/)(\d+\/?)/', '$1', $path);
            return (int)$storematch[1];
        }

        return (int)$request->getCookie('storeid', '0');
    }

    private function _getMerchantFromRequest($request, $path, $adminPath, $storeId)
    {
        $request;
        $path;
        $adminPath;

        if ($storeId == 0) {
            $merchantID = $this->scopeConfig->getValue(
                'codisto/merchantid',
                \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                $storeId
            );
        } else {
            $merchantID = $this->scopeConfig->getValue(
                'codisto/merchantid',
                'stores',
                $storeId
            );
        }
        $merchantID = $this->json->jsonDecode($merchantID);
        if (is_array($merchantID)) {
            $merchantID = $merchantID[0];
        }

        $hostKey = $this->scopeConfig->getValue(
            'codisto/hostkey',
            \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $storeId
        );

        return [ 'merchantid' => $merchantID, 'hostkey' => $hostKey ];
    }

    private function _getRemoteURL(\Magento\Framework\App\RequestInterface $request, $path, $adminPath, $merchantID)
    {
        $remoteUrl = 'https://ui.codisto.com/';
        if ($merchantID) {
            $remoteUrl .= $merchantID . '/';
        }

        $remotePath = preg_replace('/^\/'.preg_quote($adminPath, '/').'\/codisto\/\/?|key\/[a-zA-z0-9]*\//', '', $path);

        $remoteUrl .= $remotePath;

        $querystring = '?';
        foreach ($request->getQuery() as $k => $v) {
            $querystring .= urlencode($k);
            if ($v) {
                $querystring .= '='.urlencode($v);
            }
            $querystring .= '&';
        }
        $querystring = rtrim(rtrim($querystring, '&'), '?');
        $remoteUrl .= $querystring;

        return $remoteUrl;
    }

    private function _proxyRequest(\Magento\Framework\App\RequestInterface $request, $client, $headers)
    {
        foreach ($headers as $k => $v) {
            $client->setHeaders($k, $v);
        }

        foreach ($this->_getAllHeaders($request) as $k => $v) {
            if (strtolower($k) != 'host') {
                $client->setHeaders($k, $v);
            }
        }

        // file_get_contents is the best way to pipe input to proxy
        $client->setRawData(file_get_contents('php://input')); // @codingStandardsIgnoreLine MEQP1.Security.DiscouragedFunction.Found

        return $client->request($request->getMethod());
    }

    private function _proxySetResponseHeader($response, $header, $value)
    {
        if (is_array($value)) {
            $response->setHeader($header, $value[0], true);

            $valueCount = count($value);
            for ($i = 1; $i < $valueCount; $i++) {
                $response->setHeader($header, $value[$i]);
            }
        } else {
            $response->setHeader($header, $value, true);
        }
    }

    private function _handleStoreViewMap($storeviewmap)
    {
        $config = $this->configFactory->create();

        $storeViewMapping = $this->json->jsonDecode($storeviewmap);

        $mappedStores = [];

        foreach ($storeViewMapping as $mapping) {
            $storeId = $mapping['storeid'];

            if (count($mapping['merchants']) == 1) { // @codingStandardsIgnoreLine
                $merchantList = (string)$mapping['merchants'][0];
            } else {
                $merchantList = $this->json->jsonEncode($mapping['merchants']);
            }

            if ($storeId == 0) {
                $config->saveConfig('codisto/merchantid', $merchantList, 'default', 0);
            } else {
                $config->saveConfig('codisto/merchantid', $merchantList, 'stores', $storeId);
            }

            $mappedStores[] = $storeId;
        }

        foreach ($this->storeManager->getStores(true) as $store) {
            if (!isset($mappedStores[$store->getId()])) {
                $config->deleteConfig('codisto/merchantid', 'stores', $store->getId());
            }
        }

        $this->cacheTypeList->cleanType('config');
        $this->storeManager->reinitStores();
    }

    private function _proxyResponse($response, $remoteResponse, $acceptEncoding)
    {
        // set proxied status and headers
        $response->setHttpResponseCode($remoteResponse->getStatus());
        $response->setHeader('Pragma', '', true);
        $response->setHeader('Cache-Control', '', true);
        $filterHeaders = [ 'server', 'content-length', 'transfer-encoding', 'date', 'connection', 'x-storeviewmap' ];
        if (!$acceptEncoding) {
            $filterHeaders[] = 'content-encoding';
        }

        foreach ($remoteResponse->getHeaders() as $k => $v) {
            if (!in_array(strtolower($k), $filterHeaders, true)) {
                $this->_proxySetResponseHeader($response, $k, $v);
                continue;
            }

            if (strtolower($k) == 'x-storeviewmap') {
                $this->_handleStoreViewMap($v);
            }
        }

        $response->setContents($remoteResponse->getRawBody());

        return $response;
    }

    public function dispatch(\Magento\Framework\App\RequestInterface $request)
    {
        $request->setDispatched(true);

        $response = $this->_handleLoggedIn();
        if ($response) {
            return $response;
        }

        $path = $request->getPathInfo();
        $adminPath = $this->backendHelper->getAreaFrontName();

        $response = $this->_handleProductPage($request, $path, $adminPath);
        if ($response) {
            return $response;
        }

        $storeId = $this->_getStoreIdFromRequest($request, $path, $adminPath);

        $merchant = $this->_getMerchantFromRequest($request, $path, $adminPath, $storeId);

        $path = preg_replace('/(^\/'.preg_quote($adminPath, '/').'\/codisto\/ebaytab\/)(\d+\/?)/', '$1', $path);

        $remoteUrl = $this->_getRemoteURL($request, $path, $adminPath, $merchant['merchantid']);

        $codistoModule = $this->moduleList->getOne('Codisto_Connect');

        $codistoVersion = $codistoModule['setup_version'];

        $curlOptions = [ CURLOPT_TIMEOUT => 60, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0 ];
        $acceptEncoding = $request->getHeader('Accept-Encoding');
        $zlibEnabled = strtoupper(ini_get('zlib.output_compression'));
        if (!$acceptEncoding || ($zlibEnabled == 1 || $zlibEnabled == 'ON')) {
            $curlOptions[CURLOPT_ENCODING] = '';
        }

        $adminBasePort = $request->getServer('SERVER_PORT');
        $adminBasePort = $adminBasePort = '' || $adminBasePort == '80' || $adminBasePort == '443'
            ? '' : ':'.$adminBasePort;
        $adminBasePath = $request->getServer('REQUEST_URI');
        $adminBasePath = substr($adminBasePath, 0, strpos($adminBasePath, '/codisto/'));
        $adminBaseURL = $request->getScheme() . '://' .
            $request->getHttpHost() . $adminBasePort .
            $adminBasePath . '/codisto/ebaytab/'.$storeId.'/'.$merchant['merchantid'].'/';

        // use Zend_Http_Client directly to have discrete control over compression, http version and keep alive
        $client = new \Zend_Http_Client( // @codingStandardsIgnoreLine MEQP2.Classes.ObjectInstantiation.FoundDirectInstantiation
            $remoteUrl,
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

        $remoteResponse = $this->_proxyRequest(
            $request,
            $client,
            [
                'X-Admin-Base-Url' => $adminBaseURL,
                'X-Codisto-Version' => $codistoVersion,
                'X-HostKey' => $merchant['hostkey']
            ]
        );

        $response = $this->rawResponseFactory->create();

        return $this->_proxyResponse($response, $remoteResponse, $acceptEncoding);
    }

    private function _getAllHeaders($request, $extra = false)
    {
        $server = $request->getServer();

        foreach ($server as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$name] = $value;
            } elseif ($name == 'CONTENT_TYPE') {
                $headers['Content-Type'] = $value;
            } elseif ($name == 'CONTENT_LENGTH') {
                $headers['Content-Length'] = $value;
            }
        }
        if ($extra) {
            $headers = array_merge($headers, $extra);
        }
        return $headers;
    }
}
