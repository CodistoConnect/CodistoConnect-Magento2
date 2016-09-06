<?php

/**
 * Codisto eBay Sync Extension
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
 * @category	Codisto
 * @package	 codisto/codisto-connect
 * @copyright   Copyright (c) 2016 On Technology Pty. Ltd. (http://codisto.com/)
 * @license	 http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Codisto\Connect\Helper;

if (!function_exists('hash_equals')) {

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
}

class Data
{
	private $storeManager;
	private $dirList;
    private $file;
	private $syncFactory;

	private $filterProvider;
	private $cmsProcessorStoreId;
	private $cmsProcessor;

	private $client;
	private $phpInterpreter;

	public function __construct(
		\Magento\Store\Model\StoreManager $storeManager,
		\Magento\Framework\Filesystem\DirectoryList $dirList,
		\Magento\Cms\Model\Template\FilterProvider $filterProvider,
        \Magento\Framework\Filesystem\Io\File $file,
		\Codisto\Connect\Model\SyncFactory $syncFactory
	) {
		$this->storeManager = $storeManager;
		$this->dirList = $dirList;
		$this->filterProvider = $filterProvider;
        $this->file = $file;
		$this->syncFactory = $syncFactory;
	}

	public function checkRequestHash($key, $server)
	{
		if(!isset($server['HTTP_X_NONCE']))
			return false;

		if(!isset($server['HTTP_X_HASH']))
			return false;

		$nonce = $server['HTTP_X_NONCE'];
		$hash = $server['HTTP_X_HASH'];

		try
		{
			$nonceDbPath = $this->getSyncPath('nonce.db');

			$nonceDb = new \PDO('sqlite:' . $nonceDbPath);
			$nonceDb->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
			$nonceDb->exec('CREATE TABLE IF NOT EXISTS nonce (value text NOT NULL PRIMARY KEY)');
			$qry = $nonceDb->prepare('INSERT OR IGNORE INTO nonce (value) VALUES(?)');
			$qry->execute( array( $nonce ) );
			if($qry->rowCount() !== 1)
				return false;
		}
		catch(\Exception $e)
		{
            if(property_exists($e, 'errorInfo') &&
                    $e->errorInfo[0] == 'HY000' &&
                    $e->errorInfo[1] == 8 &&
                    $e->errorInfo[2] == 'attempt to write a readonly database')
            {
                if(file_exists($nonceDbPath))
                    unlink($nonceDbPath);
            }
			$this->logExceptionCodisto($e, 'https://ui.codisto.com/installed');
		}

		return $this->checkHash($key, $nonce, $hash);
	}

	public function checkHash($Key, $Nonce, $Hash)
	{
		$Sig = base64_encode( hash('sha256', $Key . $Nonce, true) );

		return hash_equals( $Hash, $Sig );
	}

	public function getConfig($storeId)
	{
		$store = $this->storeManager->getStore($storeId);

		$merchantID = $store->getConfig('codisto/merchantid');
		$hostKey = $store->getConfig('codisto/hostkey');

		return isset($merchantID) && $merchantID != ""	&&	isset($hostKey) && $hostKey != "";
	}

	public function signal($merchants, $msg, $eventtype = null, $productids = null)
	{
		register_shutdown_function(array($this, 'signalOnShutdown'), $merchants, $msg, $eventtype, $productids);
	}

	public function signalOnShutdown($merchants, $msg, $eventtype, $productids)
	{
		try
		{
			if(is_array($productids))
			{
				$sync = $this->syncFactory->create();

				$storeVisited = array();

				$varDir = $this->dirList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR);

				foreach($merchants as $merchant)
				{
					$storeId = $merchant['storeid'];

					if(!isset($storeVisited[$storeId]))
					{
						$syncDb = $varDir . '/codisto-ebay-sync-'.$storeId.'.db';

						if($eventtype == 'delete')
						{
							$sync->DeleteProducts($syncDb, $productids, $storeId);
						}
						else
						{
							$sync->UpdateProducts($syncDb, $productids, $storeId);
						}

						$storeVisited[$storeId] = 1;
					}
				}
			}

			$backgroundSignal = $this->runProcessBackground('app/code/Codisto/Connect/Helper/Signal.php', array(serialize($merchants), $msg), array('pdo', 'curl', 'simplexml'));
			if($backgroundSignal)
				return;

			if(!$this->client)
			{
				$this->client = new \Zend_Http_Client();
				$this->client->setConfig(array( 'adapter' => 'Zend_Http_Client_Adapter_Curl', 'curloptions' => array(CURLOPT_TIMEOUT => 4, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0), 'keepalive' => true, 'maxredirects' => 0 ));
				$this->client->setStream();
			}

			foreach($merchants as $merchant)
			{
				try
				{
					$this->client->setUri('https://api.codisto.com/'.$merchant['merchantid']);
					$this->client->setHeaders('X-HostKey', $merchant['hostkey']);
					$this->client->setRawData($msg)->request('POST');
				}
				catch(Exception $e)
				{

				}
			}
		}
		catch(Exception $e)
		{

		}
	}

	public function runProcessBackground($script, $args, $extensions)
	{
		if(function_exists('proc_open'))
		{
			$interpreter = $this->phpPath($extensions);
			if($interpreter)
			{
				$curl_cainfo = ini_get('curl.cainfo');
				if(!$curl_cainfo && isset($_SERVER['CURL_CA_BUNDLE']))
				{
					$curl_cainfo = $_SERVER['CURL_CA_BUNDLE'];
				}
				if(!$curl_cainfo && isset($_SERVER['SSL_CERT_FILE']))
				{
					$curl_cainfo = $_SERVER['SSL_CERT_FILE'];
				}
				if(!$curl_cainfo && getenv('CURL_CA_BUNDLE'))
				{
					$curl_cainfo = getenv('CURL_CA_BUNDLE');
				}
				if(!$curl_cainfo && getenv('SSL_CERT_FILE'))
				{
					$curl_cainfo = getenv('SSL_CERT_FILE');
				}

				$cmdline = '';
				foreach($args as $arg)
				{
					$cmdline .= '\''.$arg.'\' ';
				}

				if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
				{
					$process = proc_open('start /b '.$interpreter.' "'.$script.'" '.$cmdline, array(), $pipes, $this->dirList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::ROOT), array( 'CURL_CA_BUNDLE' => $curl_cainfo ));
				}
				else
				{
					$process = proc_open($interpreter.' "'.$script.'" '.$cmdline.' &', array(), $pipes, $this->dirList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::ROOT), array( 'CURL_CA_BUNDLE' => $curl_cainfo ));
				}

				if(is_resource($process))
				{
					@proc_close($process);
					return true;
				}
			}
		}

		return false;
	}

	public function runProcess($script, $args, $extensions, $stdin)
	{
		if(function_exists('proc_open')
			&& function_exists('proc_close'))
		{
			$interpreter = $this->phpPath($extensions);
			if($interpreter)
			{
				$curl_cainfo = ini_get('curl.cainfo');
				if(!$curl_cainfo && isset($_SERVER['CURL_CA_BUNDLE']))
				{
					$curl_cainfo = $_SERVER['CURL_CA_BUNDLE'];
				}
				if(!$curl_cainfo && isset($_SERVER['SSL_CERT_FILE']))
				{
					$curl_cainfo = $_SERVER['SSL_CERT_FILE'];
				}

				if(!$curl_cainfo && getenv('CURL_CA_BUNDLE'))
				{
					$curl_cainfo = getenv('CURL_CA_BUNDLE');
				}
				if(!$curl_cainfo && getenv('SSL_CERT_FILE'))
				{
					$curl_cainfo = getenv('SSL_CERT_FILE');
				}

				$cmdline = '';
				if(is_array($args))
				{
					foreach($args as $arg)
					{
						$cmdline .= '\''.$arg.'\' ';
					}
				}
				else if(isset($args) && $args != null)
				{
					$cmdline .= $args;
				}

				$descriptors = array(
						1 => array('pipe', 'w')
				);

				if(is_string($stdin))
				{
					$descriptors[0] = array('pipe', 'r');
				}

				$process = proc_open($interpreter.' "'.$script.'" '.$cmdline,
							$descriptors, $pipes, $this->dirList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::ROOT), array( 'CURL_CA_BUNDLE' => $curl_cainfo ));
				if(is_resource($process))
				{
                    stream_set_blocking( $pipes[0], 0 );
                    stream_set_blocking( $pipes[1], 0 );

                    stream_set_timeout( $pipes[0], 5 );
                    stream_set_timeout( $pipes[1], 30 );

					if(is_string($stdin))
					{
						for($written = 0; $written < strlen($stdin); )
						{
							$writecount = fwrite($pipes[0], substr($stdin, $written));
							if($writecount === false)
                            {
                                @fclose( $pipes[0] );
                                @fclose( $pipes[1] );
                                @proc_terminate( $process, 9 );
                                @proc_close( $process );
								return null;
                            }

							$written += $writecount;
						}

						@fclose($pipes[0]);
					}

					$result = stream_get_contents($pipes[1]);
                    if($result === false)
                    {
                        @fclose( $pipes[1] );
                        @proc_terminate( $process, 9 );
                        @proc_close( $process );
                        return null;
                    }

					@fclose($pipes[1]);
					@proc_close($process);
					return $result;
				}
			}
		}

		return null;
	}

	private function phpTest($interpreter, $args, $script)
	{
		$process = proc_open('"'.$interpreter.'" '.$args, array(
			array('pipe', 'r'),
			array('pipe', 'w')
		), $pipes);

        stream_set_blocking( $pipes[0], 0 );
        stream_set_blocking( $pipes[1], 0 );

        stream_set_timeout( $pipes[0], 5 );
        stream_set_timeout( $pipes[1], 10 );

        $write_total = strlen( $script );
        $written = 0;

        while($write_total > 0)
        {
            $write_count = @fwrite($pipes[0], substr( $script, $written ) );
            if($write_count === false)
            {
                @fclose( $pipes[0] );
                @fclose( $pipes[1] );
                @proc_terminate( $process, 9 );
                @proc_close( $process );

                return '';
            }

            $write_total -= $write_count;
            $written += $write_count;
        }

        @fclose( $pipes[0] );

		$result = @stream_get_contents($pipes[1]);
        if($result === false)
        {
            @fclose( $pipes[1] );
            @proc_terminate( $process, 9 );
            @proc_close( $process );

            return '';
        }

		@fclose($pipes[1]);
		@proc_close($process);

		return $result;
	}

	private function phpCheck($interpreter, $requiredVersion, $requiredExtensions)
	{
		if(function_exists('proc_open') &&
			function_exists('proc_close'))
		{
			if(is_array($requiredExtensions))
			{
				$extensionScript = '<?php echo serialize(array('.implode(',',
										array_map(create_function('$ext',
											'return \'\\\'\'.$ext.\'\\\' => extension_loaded(\\\'\'.$ext.\'\\\')\';'),
										$requiredExtensions)).'));';

				$extensionSet = array();
				foreach ($requiredExtensions as $extension)
				{
					$extensionSet[$extension] = 1;
				}
			}
			else
			{
				$extensionScript = '';
				$extensionSet = array();
			}

			$php_version = $this->phpTest($interpreter, '-n', '<?php echo phpversion();');

			if(!preg_match('/^\d+\.\d+\.\d+/', $php_version))
				return '';

			if(version_compare($php_version, $requiredVersion, 'lt'))
				return '';

			if($extensionScript)
			{
				$extensions = $this->phpTest($interpreter, '-n', $extensionScript);
				$extensions = @unserialize($extensions);
				if(!is_array($extensions))
					$extensions = array();

				if($extensionSet == $extensions)
				{
					return '"'.$interpreter.'" -n';
				}
				else
				{
					$php_ini = php_ini_loaded_file();
					if($php_ini)
					{
						$extensions = $this->phpTest($interpreter, '-c "'.$php_ini.'"', $extensionScript);
						$extensions = @unserialize($extensions);
						if(!is_array($extensions))
							$extensions = array();
					}

					if($extensionSet == $extensions)
					{
						return '"'.$interpreter.'" -c "'.$php_ini.'"';
					}
					else
					{
						$extensions = $this->phpTest($interpreter, '', $extensionScript);
						$extensions = @unserialize($extensions);
						if(!is_array($extensions))
							$extensions = array();

						if($extensionSet == $extensions)
						{
							return '"'.$interpreter.'"';
						}
					}
				}
			}
		}
		else
		{
			return '"'.$interpreter.'"';
		}

		return '';
	}

	private function phpPath($requiredExtensions)
	{
		if(isset($this->phpInterpreter))
			return $this->phpInterpreter;

		$interpreterName = array( 'php', 'php5', 'php-cli', 'hhvm' );
		$extension = '';
		if('\\' === DIRECTORY_SEPARATOR)
		{
			$extension = '.exe';
		}

		$dirs = array(PHP_BINDIR);
		if ('\\' === DIRECTORY_SEPARATOR)
		{
			$dirs[] = getenv('SYSTEMDRIVE').'\\xampp\\php\\';
		}

		$open_basedir = ini_get('open_basedir');
		if($open_basedir)
		{
			$basedirs = explode(PATH_SEPARATOR, ini_get('open_basedir'));
			foreach($basedirs as $dir)
			{
				if(@is_dir($dir))
				{
					$dirs[] = $dir;
				}
			}
		}
		else
		{
			$dirs = array_merge(explode(PATH_SEPARATOR, getenv('PATH')), $dirs);
		}

		foreach ($dirs as $dir)
		{
			foreach ($interpreterName as $fileName)
			{
				$file = $dir.DIRECTORY_SEPARATOR.$fileName.$extension;

				if(@is_file($file) && ('\\' === DIRECTORY_SEPARATOR || @is_executable($file)))
				{
					$file = $this->phpCheck($file, '5.0.0', $requiredExtensions);
					if(!$file)
						continue;

					$this->phpInterpreter = $file;

					return $file;
				}
			}
		}

		if(function_exists('shell_exec'))
		{
			foreach ($interpreterName as $fileName)
			{
				$file = shell_exec('which '.$fileName.$extension);
				if($file)
				{
					$file = trim($file);
					if(@is_file($file) && ('\\' === DIRECTORY_SEPARATOR || @is_executable($file)))
					{
						$file = $this->phpCheck($file, '5.0.0', $requiredExtensions);
						if(!$file)
							continue;

						$this->phpInterpreter = $file;

						return $file;
					}
				}
			}
		}

		$this->phpInterpreter = null;

		return null;
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
		if(strpos($content, '{{') === false)
			return trim($content);

		$result = $this->runProcess(realpath(dirname(__FILE__)).'/CmsContent.php', '-storeid '.$storeId, array('pdo', 'curl', 'simplexml'), $content);
		if($result != null)
			return $result;

		if($this->cmsProcessorStoreId != $storeId)
		{
			$this->cmsProcessor = $this->filterProvider->getBlockFilter()->setStoreId($storeId);
			$this->cmsProcessorStoreId = $storeId;
		}

		return $this->cmsProcessor->filter(trim($content));
	}

    public function getSyncPath($path)
	{
		$base_path = $this->dirList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR) . '/codisto/';

		try {

			$this->file->checkAndCreateFolder( $base_path, 0777 );

		} catch (\Exception $e) {

			return preg_replace( '/\/+/', '/', Mage::getBaseDir('var') . '/' . $path );

		}

		return preg_replace( '/\/+/', '/', $base_path . $path );
	}

	public function getSyncPathTemp($path)
	{
		$base_path = $this->getSyncPath('');

		return tempnam( $base_path , $path . '-' );
	}

}
