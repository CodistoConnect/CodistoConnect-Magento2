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
 * @copyright 2016-2022 On Technology Pty. Ltd. (https://codisto.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      https://codisto.com/
 *
 */

namespace Codisto\Connect\Helper;

if (!function_exists('hash_equals')) {
    // @codingStandardsIgnoreStart
    function hash_equals($known_string, $user_string)
    {
        /**
        * This file is part of the hash_equals library
        *
        * For the full copyright and license information, please view the LICENSE
        * file that was distributed with this source code.
        *
        * @copyright Copyright (c) 2013-2014 Rouven Weßling <http://rouvenwessling.de>
        * @license http://opensource.org/licenses/MIT MIT
        */

        // We jump trough some hoops to match the internals errors as closely as possible
        $argc = func_num_args();
        $params = func_get_args();

        if ($argc < 2) {
            trigger_error("hash_equals() expects at least 2 parameters, {$argc} given", E_USER_WARNING);
            return null;
        }

        if (!is_string($known_string)) {
            trigger_error("hash_equals(): Expected known_string to be a string, " . gettype($known_string) . " given", E_USER_WARNING);
            return false;
        }
        if (!is_string($user_string)) {
            trigger_error("hash_equals(): Expected user_string to be a string, " . gettype($user_string) . " given", E_USER_WARNING);
            return false;
        }

        if (strlen($known_string) !== strlen($user_string)) {
            return false;
        }
        $len = strlen($known_string);
        $result = 0;
        for ($i = 0; $i < $len; $i++) {
            $result |= (ord($known_string[$i]) ^ ord($user_string[$i]));
        }
        // They are only identical strings if $result is exactly 0...
        return 0 === $result;
    }
    // @codingStandardsIgnoreEnd
}

class Data
{
    private $resourceConnectionFactory;
    private $deploymentConfigFactory;

    private $storeManager;
    private $dirList;
    private $file;
    private $json;
    private $syncFactory;

    private $configurableTypeFactory;
    private $configurableType = null;
    private $groupedTypeFactory;
    private $groupedType = null;
    private $bundleTypeFactory;
    private $bundleType = null;

    private $filterProvider;
    private $cmsProcessorStoreId;
    private $cmsProcessor;

    private $client;
    private $phpInterpreter;
    private $logger;

    private $console;

    public function __construct(
        \Magento\Framework\App\ResourceConnectionFactory $resourceConnectionFactory,
        \Magento\Framework\App\DeploymentConfigFactory $deploymentConfigFactory,
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\Framework\Filesystem\DirectoryList $dirList,
        \Magento\Cms\Model\Template\FilterProvider $filterProvider,
        \Magento\Framework\Filesystem\Io\File $file,
        \Magento\Framework\Json\Helper\Data $json,
        \Magento\ConfigurableProduct\Model\Product\Type\ConfigurableFactory $configurableTypeFactory,
        \Magento\GroupedProduct\Model\Product\Type\GroupedFactory $groupedTypeFactory,
        \Magento\Bundle\Model\Product\TypeFactory $bundleTypeFactory,
        \Codisto\Connect\Model\SyncFactory $syncFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Console\Response\Proxy $console // @codingStandardsIgnoreLine Magento2.Classes.DiscouragedDependencies.ConstructorProxyInterceptor
    ) {
        $this->resourceConnectionFactory = $resourceConnectionFactory;
        $this->deploymentConfigFactory = $deploymentConfigFactory;
        $this->storeManager = $storeManager;

        $this->dirList = $dirList;
        $this->filterProvider = $filterProvider;
        $this->file = $file;
        $this->json = $json;
        $this->syncFactory = $syncFactory;
        $this->logger = $logger;
        $this->console = $console;

        $this->configurableTypeFactory = $configurableTypeFactory;
        $this->groupedTypeFactory = $groupedTypeFactory;
        $this->bundleTypeFactory = $bundleTypeFactory;
    }

    public function checkRequestHash($key, $server)
    {

        if (!isset($server['HTTP_X_NONCE'])) {
            return false;
        }

        if (!isset($server['HTTP_X_HASH'])) {
            return false;
        }

        $nonce = $server['HTTP_X_NONCE'];
        $hash = $server['HTTP_X_HASH'];

        try {
            $nonceDbPath = $this->getSyncPath('nonce.db');

            $nonceDb = $this->createSqliteConnection($nonceDbPath);
            $nonceDb->exec('PRAGMA synchronous=OFF');
            $nonceDb->exec('PRAGMA temp_store=MEMORY');
            $nonceDb->exec('PRAGMA page_size=65536');
            $nonceDb->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $nonceDb->exec('CREATE TABLE IF NOT EXISTS nonce (value text NOT NULL PRIMARY KEY)');
            $qry = $nonceDb->prepare('INSERT OR IGNORE INTO nonce (value) VALUES(?)');
            $qry->execute([$nonce]);

            $countQuery = $nonceDb->query('SELECT changes()');
            $nonceInsertCount = (int)$countQuery->fetchColumn();
            $countQuery->closeCursor();

            if ($nonceInsertCount !== 1) {
                return false;
            }
        } catch (\Exception $e) {
            if (property_exists($e, 'errorInfo') &&
                    $e->errorInfo[0] == 'HY000' &&
                    $e->errorInfo[1] == 8 &&
                    $e->errorInfo[2] == 'attempt to write a readonly database') {
                if ($this->file->fileExists($nonceDbPath)) {
                    $this->file->rm($nonceDbPath);
                }
            } elseif (property_exists($e, 'errorInfo') &&
                    $e->errorInfo[0] == 'HY000' &&
                    $e->errorInfo[1] == 11 &&
                    $e->errorInfo[2] == 'database disk image is malformed') {
                if ($this->file->fileExists($nonceDbPath)) {
                    $this->file->rm($nonceDbPath);
                }
            } else {
                $this->logger->info('Exception: '.$e->getMessage()
                .' on line: '.$e->getLine()
                .' in file: '.$e->getFile());
                return false;
            }
        }

        return $this->checkHash($key, $nonce, $hash);
    }

    public function logger($message = '')
    {
        $this->logger->info($message);
        return false;
    }

    public function checkHash($Key, $Nonce, $Hash)
    {
        $Sig = base64_encode(hash('sha256', $Key . $Nonce, true));

        return hash_equals($Hash, $Sig);
    }

    public function getConfig($storeId)
    {
        $store = $this->storeManager->getStore($storeId);

        $merchantID = $store->getConfig('codisto/merchantid');
        $hostKey = $store->getConfig('codisto/hostkey');

        return isset($merchantID) && $merchantID != "" && isset($hostKey) && $hostKey != "";
    }

    public function signal($merchants, $msg, $eventtype = null, $productids = null)
    {
        // we use register_shutdown_function to defer processing of signalling
        // until all other magento request processing is complete
        register_shutdown_function( // @codingStandardsIgnoreLine MEQP1.Security.DiscouragedFunction.Found
            [$this, 'signalOnShutdown'],
            $merchants,
            $msg,
            $eventtype,
            $productids
        );
    }

    public function registerProductChanges($merchants, $eventtype, $productids)
    {
        if (is_array($productids)) {
            $sync = $this->syncFactory->create();

            $storeVisited = [];

            foreach ($merchants as $merchant) {
                $storeId = $merchant['storeid'];

                if (!isset($storeVisited[$storeId]) &&
                    $storeId == 0) {
                    // jump the storeid to first non admin store
                    $stores = $this->storeManager->getStores(false);
                    if (count($stores) == 1) { //@codingStandardsIgnoreLine MEQP1.Performance.Loop.ArraySize
                        foreach ($stores as $store) {
                            $storeId = $store->getId();
                            break;
                        }
                    }

                    $syncDb = $this->getSyncPath('sync-'.$storeId.'.db');

                    if ($eventtype == 'delete') {
                        $sync->deleteProducts($syncDb, $productids, $storeId);
                    } else {
                        $sync->updateProducts($syncDb, $productids, $storeId);
                    }

                    $storeVisited[$storeId] = 1;
                }
            }
        }
    }

    public function signalOnShutdown($merchants, $msg, $eventtype, $productids)
    {
        try {

            $backgroundSignal = $this->runProcessBackground(
                realpath( // @codingStandardsIgnoreLine MEQP1.Security.DiscouragedFunction.Found
                    $this->file->dirname(__FILE__)
                ).'/Signal.php',
                [
                    serialize($merchants), $msg, $eventtype, serialize($productids) // @codingStandardsIgnoreLine MEQP1.Security.DiscouragedFunction.Found
                ],
                [
                    'pdo',
                    'curl',
                    'simplexml'
                ]
            );
            if ($backgroundSignal) {
                return;
            }

            $this->registerProductChanges($merchants, $eventtype, $productids);

            if (!$this->client) {
                $this->client = new \Zend_Http_Client(); // @codingStandardsIgnoreLine MEQP2.Classes.ObjectInstantiation.FoundDirectInstantiation
                $this->client->setConfig(
                    [
                        'adapter' => 'Zend_Http_Client_Adapter_Curl',
                        'curloptions' =>
                        [
                            CURLOPT_TIMEOUT => 4,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0
                        ],
                        'keepalive' => true,
                        'maxredirects' => 0
                    ]
                );
                $this->client->setStream();
            }

            foreach ($merchants as $merchant) {
                try {
                    $this->client->setUri('https://api.codisto.com/'.$merchant['merchantid']);
                    $this->client->setHeaders('X-HostKey', $merchant['hostkey']);
                    $this->client->setRawData($msg)->request('POST');
                } catch (\Exception $e) {
                    $e;
                    // ignore post failure, we'll pick up the missed signal in polling
                }
            }
        } catch (\Exception $e) {
            $e;
            // ignore post failure, we'll pick up the missed signal in polling
        }
    }

    public function runProcessBackground($script, $args, $extensions)
    {
        // @codingStandardsIgnoreStart
        if ($this->_phpCanExec()) {
            $interpreter = $this->_phpPath($extensions);

            if ($interpreter) {
                $curl_cainfo = $this->getCAInfo();

                $cmdline = '';
                foreach ($args as $arg) {
                    $cmdline .= '\''.$arg.'\' ';
                }

                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    $process = proc_open(
                        'start /b '.$interpreter.' "'.$script.'" '.$cmdline,
                        [],
                        $pipes,
                        $this->dirList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::ROOT),
                        [ 'CURL_CA_BUNDLE' => $curl_cainfo ]
                    );
                } else {
                    $process = proc_open(
                        $interpreter.' "'.$script.'" '.$cmdline.' &',
                        [],
                        $pipes,
                        $this->dirList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::ROOT),
                        [ 'CURL_CA_BUNDLE' => $curl_cainfo ]
                    );
                }

                if (is_resource($process)) {
                    @proc_close($process);
                    return true;
                }
            }
        }
        // @codingStandardsIgnoreEnd
        return false;
    }

    private function getCAInfo()
    {
        // use base php functions to retrieve OS environment
        // variable to determine location of CURL
        // certificate authority bundle
        // @codingStandardsIgnoreStart
        $curl_cainfo = ini_get('curl.cainfo');
        if (!$curl_cainfo && isset($_SERVER['CURL_CA_BUNDLE'])) {
            $curl_cainfo = $_SERVER['CURL_CA_BUNDLE'];
        }
        if (!$curl_cainfo && isset($_SERVER['SSL_CERT_FILE'])) {
            $curl_cainfo = $_SERVER['SSL_CERT_FILE'];
        }

        if (!$curl_cainfo && getenv('CURL_CA_BUNDLE')) {
            $curl_cainfo = getenv('CURL_CA_BUNDLE');
        }
        if (!$curl_cainfo && getenv('SSL_CERT_FILE')) {
            $curl_cainfo = getenv('SSL_CERT_FILE');
        }
        // @codingStandardsIgnoreEnd
        return $curl_cainfo;
    }

    private function getProcessCmdLine($args)
    {
        $cmdline = '';
        if (is_array($args)) {
            foreach ($args as $arg) {
                $cmdline .= '\''.$arg.'\' ';
            }
        } elseif (isset($args) && $args != null) {
            $cmdline .= $args;
        }

        return $cmdline;
    }

    public function runProcess($script, $args, $extensions, $stdin)
    {
        // @codingStandardsIgnoreStart
        if (!function_exists('proc_open')
            || !function_exists('proc_close')) {
            return null;
        }

        $interpreter = $this->_phpPath($extensions);
        if (!$interpreter) {
            return null;
        }

        $curl_cainfo = $this->getCAInfo();

        $cmdline = $this->getProcessCmdLine($args);

        $descriptors = [
            1 => [ 'pipe', 'w' ]
        ];

        if (is_string($stdin)) {
            $descriptors[0] = ['pipe', 'r'];
        }

        $process = proc_open(
            $interpreter.' "'.$script.'" '.$cmdline,
            $descriptors,
            $pipes,
            $this->dirList->getPath(
                \Magento\Framework\App\Filesystem\DirectoryList::ROOT
            ),
            [ 'CURL_CA_BUNDLE' => $curl_cainfo ]
        );
        if (is_resource($process)) {
            stream_set_blocking($pipes[0], 0);
            stream_set_blocking($pipes[1], 0);

            stream_set_timeout($pipes[0], 5);
            stream_set_timeout($pipes[1], 30);

            if (is_string($stdin)) {
                $stdinlength = strlen($stdin);
                for ($written = 0; $written < $stdinlength;) {
                    $writecount = fwrite($pipes[0], substr($stdin, $written));
                    if ($writecount === false) {
                        @fclose($pipes[0]);
                        @fclose($pipes[1]);
                        @proc_terminate($process, 9);
                        @proc_close($process);
                        return null;
                    }

                    $written += $writecount;
                }

                @fclose($pipes[0]);
            }

            $result = '';
            while (!feof($pipes[1])) {
                $result .= @fread($pipes[1], 8192);
                if ($result === false) {
                    @fclose($pipes[1]);
                    @proc_terminate($process, 9);
                    @proc_close($process);

                    return '';
                }
            }

            @fclose($pipes[1]);
            @proc_close($process);
            return $result;
        }
        // @codingStandardsIgnoreEnd
        return null;
    }

    private function _phpTest($interpreter, $args, $script)
    {
        // @codingStandardsIgnoreStart
        $process = proc_open(
            '"'.$interpreter.'" '.$args,
            [
                ['pipe', 'r'],
                ['pipe', 'w']
            ],
            $pipes
        );

        stream_set_blocking($pipes[0], 0);
        stream_set_blocking($pipes[1], 1);

        stream_set_timeout($pipes[0], 5);
        stream_set_timeout($pipes[1], 10);

        $write_total = strlen($script);
        $written = 0;

        while ($write_total > 0) {
            $write_count = @fwrite($pipes[0], substr($script, $written));
            if ($write_count === false) {
                @fclose($pipes[0]);
                @fclose($pipes[1]);
                @proc_terminate($process, 9);
                @proc_close($process);

                return '';
            }

            $write_total -= $write_count;
            $written += $write_count;
        }

        @fclose($pipes[0]);

        $result = '';
        while (!feof($pipes[1])) {
            $result .= @fread($pipes[1], 8192);
            if ($result === false) {
                @fclose($pipes[1]);
                @proc_terminate($process, 9);
                @proc_close($process);

                return '';
            }
        }

        @fclose($pipes[1]);
        @proc_close($process);
        // @codingStandardsIgnoreEnd
        return $result;
    }

    private function _phpCheckVersion($interpreter, $php_version, $requiredVersion)
    {
        $php_version = $this->_phpTest($interpreter, '-n', '<?php echo phpversion();');

        if (!preg_match('/^\d+\.\d+\.\d+/', $php_version)) {
            return false;
        }

        if (version_compare($php_version, $requiredVersion, 'lt')) {
            return false;
        }

        return true;
    }

    private function _phpCanExec()
    {
        return function_exists('proc_open') && function_exists('proc_close');
    }

    private function _phpExtensionScript($extensions)
    {
        if (is_array($extensions)) {
            return '<?php echo serialize(array('.
                implode(
                    ',',
                    array_map(
                        function ($ext) {
                            return '\''.$ext.'\' => extension_loaded(\''.$ext.'\')';
                        },
                        $extensions
                    )
                ).
                '));';
        }

        return '';
    }

    private function _phpExtensionSet($extensions)
    {
        if (is_array($extensions)) {
            $extensionSet = [];
            foreach ($extensions as $extension) {
                $extensionSet[$extension] = 1;
            }

            return $extensionSet;
        }

        return [];
    }

    private function _phpTestExtensions($interpreter, $args, $extensionScript)
    {
        $extensions = $this->_phpTest($interpreter, $args, $extensionScript);
        // ignore deserialization errors here as a value that cannot be deserialized
        // implies a broken sub process that cannot be used
        $extensions = @unserialize($extensions); // @codingStandardsIgnoreLine Generic.PHP.NoSilencedErrors.Discouraged
        if (!is_array($extensions)) {
            $extensions = [];
        }
        return $extensions;
    }

    private function _phpCheck($interpreter, $requiredVersion, $requiredExtensions)
    {
        $requiredVersion;

        // @codingStandardsIgnoreStart
        if(!$this->_phpCanExec()) {
            return '"'.$interpreter.'"';
        }

        $extensionScript = $this->_phpExtensionScript($requiredExtensions);
        $extensionSet = $this->_phpExtensionSet($requiredExtensions);

        $php_version = $this->_phpTest($interpreter, '-n', '<?php echo phpversion();');

        if(!$this->_phpCheckVersion($interpreter, $php_version, $requiredVersion)) {
            return '';
        }

        if ($extensionScript) {
            $extensions = $this->_phpTestExtensions($interpreter, '-n', $extensionScript);
            if ($extensionSet == $extensions) {
                return '"'.$interpreter.'" -n';
            }

            $php_ini = php_ini_loaded_file();
            if ($php_ini) {
                $extensions = $this->_phpTestExtensions($interpreter, '-c "'.addslashes($php_ini).'"', $extensionScript);
            }

            if ($extensionSet == $extensions) {
                return '"'.$interpreter.'" -c "'.$php_ini.'"';
            }

            $extensions = $this->_phpTestExtensions($interpreter, '', $extensionScript);
            if ($extensionSet == $extensions) {
                return '"'.$interpreter.'"';
            }
        }
        // @codingStandardsIgnoreEnd

        return '';
    }

    private function _phpBinPath()
    {
        $dirs = [PHP_BINDIR];
        if ('\\' === DIRECTORY_SEPARATOR) {
            $dirs[] = getenv('SYSTEMDRIVE') // @codingStandardsIgnoreLine MEQP1.Security.DiscouragedFunction.Found
                .'\\xampp\\php\\';
        }

        $open_basedir = ini_get('open_basedir');
        if ($open_basedir) {
            $basedirs = explode(PATH_SEPARATOR, $open_basedir);
            foreach ($basedirs as $dir) {
                if (@is_dir($dir)) { // @codingStandardsIgnoreLine Generic.PHP.NoSilencedErrors.Discouraged
                    $dirs[] = $dir;
                }
            }
        } else {
            $dirs = array_merge(
                explode(
                    PATH_SEPARATOR,
                    getenv('PATH') // @codingStandardsIgnoreLine MEQP1.Security.DiscouragedFunction.Found
                ),
                $dirs
            );
        }

        return $dirs;
    }

    private function _phpPathSearch($dirs, $interpreterName, $extension, $requiredExtensions)
    {
        foreach ($dirs as $dir) {
            foreach ($interpreterName as $fileName) {
                $file = $dir.DIRECTORY_SEPARATOR.$fileName.$extension;

                if (@is_file($file) // @codingStandardsIgnoreLine Generic.PHP.NoSilencedErrors.Discouraged
                    && (
                        '\\' === DIRECTORY_SEPARATOR
                        || @is_executable($file) // @codingStandardsIgnoreLine Generic.PHP.NoSilencedErrors.Discouraged
                    )) {
                    $file = $this->_phpCheck($file, '5.0.0', $requiredExtensions);
                    if (!$file) {
                        continue;
                    }

                    return $file;
                }
            }
        }

        return null;
    }

    private function _phpOsSearch($interpreterName, $extension, $requiredExtensions)
    {
        if (function_exists('shell_exec')) {
            foreach ($interpreterName as $fileName) {
                $file = shell_exec('which '.$fileName.$extension); // @codingStandardsIgnoreLine MEQP1.Security.DiscouragedFunction.Found
                if ($file) {
                    $file = trim($file ?? '');
                    if (@is_file($file) // @codingStandardsIgnoreLine Generic.PHP.NoSilencedErrors.Discouraged
                        && (
                            '\\' === DIRECTORY_SEPARATOR
                            || @is_executable($file) // @codingStandardsIgnoreLine Generic.PHP.NoSilencedErrors.Discouraged
                        )) {
                        $file = $this->_phpCheck($file, '5.0.0', $requiredExtensions);
                        if (!$file) {
                            continue;
                        }

                        return $file;
                    }
                }
            }
        }

        return null;
    }

    private function _phpPath($requiredExtensions)
    {
        // @codingStandardsIgnoreStart
        if (isset($this->phpInterpreter)) {
            return $this->phpInterpreter;
        }

        $interpreterName = [ 'php', 'php5', 'php7', 'php-cli', 'hhvm' ];
        $extension = '';
        if ('\\' === DIRECTORY_SEPARATOR) {
            $extension = '.exe';
        }

        $dirs = $this->_phpBinPath();

        $phpPath = $this->_phpPathSearch($dirs, $interpreterName, $extension, $requiredExtensions);
        if ($phpPath) {
            $this->phpInterpreter = $phpPath;
            return $phpPath;
        }

        $phpPath = $this->_phpOsSearch($interpreterName, $extension, $requiredExtensions);
        if ($phpPath) {
            $this->phpInterpreter = $phpPath;
            return $phpPath;
        }

        $this->phpInterpreter = null;
        // @codingStandardsIgnoreEnd
        return null;
    }

    public function syncAllMerchants()
    {
        $merchants = [];
        $merchantSignalled = [];

        foreach ($this->storeManager->getStores(true) as $store) {
            $merchantid = $store->getConfig('codisto/merchantid');
            $hostkey = $store->getConfig('codisto/hostkey');

            if ($merchantid && $merchantid != '') {
                $merchantlist = $this->json->jsonDecode($merchantid);
                if (!is_array($merchantlist)) {
                    $merchantlist = [$merchantlist];
                }

                foreach ($merchantlist as $merchantid) {
                    if (!in_array($merchantid, $merchantSignalled, true)) {
                        $merchantSignalled[] = $merchantid;
                        $merchants[] =
                            ['merchantid' => $merchantid, 'hostkey' => $hostkey, 'storeid' => $store->getId()];
                    }
                }
            }
        }

        return $merchants;
    }

    private function _syncStoresFromStoreId($storeId)
    {
        $defaultStore = $this->storeManager->getStore(0);
        $currentStore = $this->storeManager->getStore($storeId);

        $syncStores = [0];

        if ($storeId != 0) {
            $defaultMerchantId = $defaultStore->getConfig('codisto/merchantid');
            $storeMerchantId = $currentStore->getConfig('codisto/merchantid');

            // if the default Codisto merchantid is different at this store level
            // explicitly synchronise it as well
            if ($defaultMerchantId != $storeMerchantId) {
                $syncStores[] = $storeId;
            }
        } else {
            $defaultMerchantId = $defaultStore->getConfig('codisto/merchantid');

            $stores = $this->storeManager->getStores();

            foreach ($stores as $store) {
                if ($store->getId() != 0) {
                    $storeMerchantId = $store->getConfig('codisto/merchantid');

                    if ($defaultMerchantId != $storeMerchantId) {
                        $syncStores[] = $store->getId();
                    }
                }
            }
        }

        return $syncStores;
    }

    public function syncMerchantsFromStoreId($storeId)
    {
        $syncStores = $this->_syncStoresFromStoreId($storeId);

        $merchants = [];
        $merchantSignalled = [];

        foreach ($syncStores as $storeId) {
            $store = $this->storeManager->getStore($storeId);

            $merchantid = $store->getConfig('codisto/merchantid');
            $hostkey = $store->getConfig('codisto/hostkey');

            if ($merchantid && $merchantid != '') {
                $merchantlist = $this->json->jsonDecode($merchantid);
                if (!is_array($merchantlist)) {
                    $merchantlist = [$merchantlist];
                }

                foreach ($merchantlist as $merchantid) {
                    if (!in_array($merchantid, $merchantSignalled, true)) {
                        $merchantSignalled[] = $merchantid;
                        $merchants[] = ['merchantid' => $merchantid, 'hostkey' => $hostkey, 'storeid' => $storeId];
                    }
                }
            }
        }

        return $merchants;
    }

    public function addProductToSyncSet($productId, $set)
    {
        if ($this->configurableType === null) {
            $this->configurableType = $this->configurableTypeFactory->create();
        }

        if ($this->groupedType === null) {
            $this->groupedType = $this->groupedTypeFactory->create();
        }

        if ($this->bundleType === null) {
            $this->bundleType = $this->bundleTypeFactory->create();
        }

        $configurableParents = $this->configurableType->getParentIdsByChild($productId);
        if (is_array($configurableParents) && !empty($configurableParents)) {
            $set = array_merge($set, $configurableParents);
        }

        $groupedParents = $this->groupedType->getParentIdsByChild($productId);
        if (is_array($groupedParents) && !empty($groupedParents)) {
            $set = array_merge($set, $groupedParents);
        }

        $set[] = $productId;

        return array_unique($set);
    }

    public function createSqliteConnection($path)
    {
        $db = new \PDO('sqlite:' . $path); // @codingStandardsIgnoreLine
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->file->chmod($path, 0660);

        return $db;
    }

    public function prepareSqliteDatabase($db, $timeout = 60, $pagesize = 65536)
    {
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(\PDO::ATTR_TIMEOUT, $timeout);
        $db->exec('PRAGMA synchronous=OFF');
        $db->exec('PRAGMA temp_store=MEMORY');
        $db->exec('PRAGMA page_size='.$pagesize);
        $db->exec('PRAGMA encoding=\'UTF-8\'');
        $db->exec('PRAGMA cache_size=15000');
        $db->exec('PRAGMA soft_heap_limit=67108864');
        $db->exec('PRAGMA journal_mode=MEMORY');
    }

    public function processCmsContent($content, $storeId)
    {
        if (strpos($content ?? '', '{{') === false) {
            return trim($content ?? '');
        }

        $result = $this->runProcess(
            realpath($this->file->dirname(__FILE__)).'/CmsContent.php', // @codingStandardsIgnoreLine
            '-storeid '.$storeId,
            ['pdo', 'curl', 'simplexml'],
            $content
        );
        if ($result != null) {
            return $result;
        }

        if ($this->cmsProcessorStoreId != $storeId) {
            $this->cmsProcessor = $this->filterProvider->getBlockFilter()->setStoreId($storeId);
            $this->cmsProcessorStoreId = $storeId;
        }

        return $this->cmsProcessor->filter(trim($content));
    }

    public function getSyncPath($path)
    {
        $base_path = $this->dirList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR) . '/codisto/';

        try {
            $this->file->checkAndCreateFolder($base_path, 0777);
        } catch (\Exception $e) {
            return preg_replace(
                '/\/+/',
                '/',
                $this->dirList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR) . '/' . $path
            );
        }

        return preg_replace('/\/+/', '/', $base_path . $path);
    }

    public function getSyncPathTemp($path)
    {
        $base_path = $this->getSyncPath('');

        return tempnam($base_path, $path . '-'); // @codingStandardsIgnoreLine
    }

    public function getTxIsoLevel($connection)
    {
        // quick helper to retrieve current transaction isolation
        // so it can be restored later
        $qry = $connection->query('SELECT @@tx_isolation'); // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel MEQP1.SQL.RawQuery.FoundRawSql
        $txIsoLevel = $qry->fetchColumn();
        $qry->closeCursor();

        return  str_replace("-", " ", $txIsoLevel);
    }

    public function getTriggerMode()
    {
        return true;
    }

    private function _canSyncIncrementallyChangeTablesExist($adapter, $tablePrefix)
    {
        $changeTableDefs = [
            'codisto_index_product_cl',
            'codisto_index_category_cl',
            'codisto_index_order_cl'
        ];

        $changeTablesExist = false;

        $changeTables = $adapter->fetchCol(
            'SELECT TABLE_NAME '.
            'FROM INFORMATION_SCHEMA.TABLES '.
            'WHERE TABLE_NAME LIKE \''.$tablePrefix.'codisto_index_%_cl\'' // @codingStandardsIgnoreLine MEQP1.SQL.SlowQuery.FoundSlowRawSql
        );
        if (is_array($changeTables)) {
            $changeTablesExist = true;

            $changeTables = array_flip($changeTables);
            foreach ($changeTableDefs as $table) {
                if (!isset($changeTables[$tablePrefix.$table])) {
                    $changeTablesExist = false;
                    break;
                }
            }
        }

        return $changeTablesExist;
    }

    private function _canSyncIncrementallyChangeToken($adapter, $tablePrefix, $storeId)
    {
        $changeToken = null;
        try {
            $changeToken = $adapter->fetchOne(
                'SELECT token FROM `'.$tablePrefix.'codisto_sync` WHERE store_id = '.(int)$storeId
            );
        } catch (\Exception $e) {
            $e;
        }

        return $changeToken;
    }

    private function _canSyncIncrementallySyncToken($syncDb)
    {
        $syncToken = null;
        if ($syncDb) {
            $qry = null;
            try {
                try {
                    $qry = $syncDb->query('SELECT token FROM sync'); // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel

                    $syncToken = $qry->fetchColumn();
                } catch (\Exception $e) {
                    if ($qry) {
                        $qry->closeCursor();
                    }
                }
            } catch (\Exception $e) {
                $e;
            }
        }

        return $syncToken;
    }

    public function canSyncIncrementally($syncDbPath, $storeId)
    {
        if (!$this->getTriggerMode()) {
            return false;
        }

        $coreResource = $this->resourceConnectionFactory->create();
        $adapter = $coreResource->getConnection();

        $deploymentConfig = $this->deploymentConfigFactory->create();

        $tablePrefix = (string)$deploymentConfig->get(
            \Magento\Framework\Config\ConfigOptionsListConstants::CONFIG_PATH_DB_PREFIX
        );

        $deploymentConfig = null;

        $changeTablesExist = $this->_canSyncIncrementallyChangeTablesExist(
            $adapter,
            $tablePrefix
        );

        // check sync db exists
        $syncDbExists = false;
        $syncDb = null;

        try {
            $syncDb = $this->createSqliteConnection($syncDbPath);

            $this->prepareSqliteDatabase($syncDb, 60);

            $qry = $syncDb->query( // @codingStandardsIgnoreLine MEQP2.Classes.ResourceModel.OutsideOfResourceModel
                'PRAGMA quick_check'
            );

            $checkResult = $qry->fetchColumn();

            $qry->closeCursor();

            if ($checkResult == 'ok') {
                $syncDbExists = true;
            }
        } catch (\Exception $e) {
            $e;
        }

        // check sync db uuid and magento uuid
        $changeToken = $this->_canSyncIncrementallyChangeToken($adapter, $tablePrefix, $storeId);

        $syncToken = $this->_canSyncIncrementallySyncToken($syncDb);

        return (!($changeToken === null) && $changeToken != '') &&
                    ($changeToken == $syncToken) &&
                    $changeTablesExist &&
                    $syncDbExists;
    }

    public function callExit()
    {
        $this->console->sendResponse();
    }
}
