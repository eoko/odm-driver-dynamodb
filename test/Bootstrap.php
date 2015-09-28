<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace Eoko\ODM\Driver\DynamoDB\Test;

use Aws\DynamoDb\DynamoDbClient;
use Aws\Sdk;
use Mockery;
use RuntimeException;
use Zend\Loader\AutoloaderFactory;
use Zend\Log\Logger;
use Zend\Mvc\Service\ServiceManagerConfig;
use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\ArrayUtils;

error_reporting(E_ALL | E_STRICT);
chdir(__DIR__);

class Bootstrap
{
    /**
     * @var ServiceManager
     */
    protected static $serviceManager;
    protected static $config;
    protected static $bootstrap;

    public static function init()
    {
        // Load the user-defined test configuration file, if it exists; otherwise, load
        if (is_readable(__DIR__ . '/config/testConfig.php')) {
            $testConfig = include __DIR__ . '/config/testConfig.php';
        } else {
            $testConfig = include __DIR__ . '/TestConfig.php.dist';
        }

        $zf2ModulePaths = [];

        if (isset($testConfig['module_listener_options']['module_paths'])) {
            $modulePaths = $testConfig['module_listener_options']['module_paths'];
            foreach ($modulePaths as $modulePath) {
                if (($path = static::findParentPath($modulePath))) {
                    $zf2ModulePaths[] = $path;
                }
            }
        }

        $zf2ModulePaths = implode(PATH_SEPARATOR, $zf2ModulePaths) . PATH_SEPARATOR;
        $zf2ModulePaths .= getenv('ZF2_MODULES_TEST_PATHS') ?: (defined('ZF2_MODULES_TEST_PATHS') ? ZF2_MODULES_TEST_PATHS : '');

        static::initAutoloader();

        // use ModuleManager to load this module and it's dependencies
        $baseConfig = [
            'module_listener_options' => [
                'module_paths' => explode(PATH_SEPARATOR, $zf2ModulePaths),
            ],
        ];

        $config = ArrayUtils::merge($baseConfig, $testConfig);

        $serviceManager = new ServiceManager(new ServiceManagerConfig());
        $serviceManager->setService('ApplicationConfig', $config);
        $serviceManager->get('ModuleManager')->loadModules();

        $serviceManager->setService('Log\App', new Logger(
                ['writers' =>
                    [
                        [
                            'name' => 'stream',
                            'priority' => 1000,
                            'options' => [
                                'stream' => __DIR__ . '/config/app.log',
                            ],
                            'filter' => Logger::DEBUG,
                        ],
                    ],
                ]
            )
        );

        $client = $serviceManager->get(Sdk::class);

        try {
            // Check the client is configure
            $dynamodbClient = $client->createDynamoDb();
        } catch (\InvalidArgumentException $e) {

            // If the client is not configure, we create a mock one
            $dynamodbClient = Mockery::mock(DynamoDbClient::class);

            // And we load mock
            $mocks = include(__DIR__ . '/config/AwsFixture.php');

            foreach ($mocks as $key => $item) {
                foreach ($item as $mock) {
                    $request = $mock['request'];

                    // We mock every expected request with the according response
                    $on = Mockery::on(function ($value) use ($request) {
                        return $value === $request;
                    });
                    $dynamodbClient->shouldReceive('createTable')->with($on)->andReturn($mock['response']);
                }
            }

            $serviceManager->setAllowOverride(true);

            $client = Mockery::mock('\Aws\DynamoDb\DynamoDbClient');
            $client->shouldReceive('createDynamoDb')->andReturn($dynamodbClient);
            $serviceManager->setService(Sdk::class, $client);
        }

        static::$serviceManager = $serviceManager;
        static::$config = $config;
    }

    protected static function findParentPath($path)
    {
        $dir = __DIR__;
        $previousDir = '.';
        while (!is_dir($dir . '/' . $path)) {
            $dir = dirname($dir);
            if ($previousDir === $dir) {
                return false;
            }
            $previousDir = $dir;
        }
        return $dir . '/' . $path;
    }

    protected static function initAutoloader()
    {
        $vendorPath = static::findParentPath('vendor');

        if (is_readable($vendorPath . '/autoload.php')) {
            $loader = include $vendorPath . '/autoload.php';
        } else {
            $zf2Path = getenv('ZF2_PATH') ?: (defined('ZF2_PATH') ? ZF2_PATH : (is_dir($vendorPath . '/ZF2/library') ? $vendorPath . '/ZF2/library' : false));

            if (!$zf2Path) {
                throw new RuntimeException('Unable to load ZF2. Run `php composer.phar install` or define a ZF2_PATH environment variable.');
            }

            include $zf2Path . '/Zend/Loader/AutoloaderFactory.php';
        }

        AutoloaderFactory::factory([
            'Zend\Loader\StandardAutoloader' => [
                'autoregister_zf' => true,
                'namespaces' => [
                    __NAMESPACE__ => __DIR__ . '/' . __NAMESPACE__,
                ],
            ],
        ]);
    }

    /**
     * @return ServiceManager
     */
    public static function getServiceManager()
    {
        return static::$serviceManager;
    }

    public static function getConfig()
    {
        return static::$config;
    }
}

Bootstrap::init();
