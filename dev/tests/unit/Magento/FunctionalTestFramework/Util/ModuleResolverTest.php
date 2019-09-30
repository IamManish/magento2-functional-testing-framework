<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace tests\unit\Magento\FunctionalTestFramework\Test\Util;

use AspectMock\Proxy\Verifier;
use AspectMock\Test as AspectMock;

use Magento\FunctionalTestingFramework\Config\MftfApplicationConfig;
use Magento\FunctionalTestingFramework\Exceptions\TestFrameworkException;
use Magento\FunctionalTestingFramework\ObjectManager;
use Magento\FunctionalTestingFramework\ObjectManagerFactory;
use Magento\FunctionalTestingFramework\Util\Logger\LoggingUtil;
use Magento\FunctionalTestingFramework\Util\ModuleResolver;
use Magento\FunctionalTestingFramework\Util\MagentoTestCase;
use PHPUnit\Runner\Exception;
use tests\unit\Util\TestLoggingUtil;

class ModuleResolverTest extends MagentoTestCase
{
    /**
     * Before test functionality
     * @return void
     */
    public function setUp()
    {
        TestLoggingUtil::getInstance()->setMockLoggingUtil();
    }

    /**
     * After class functionality
     * @return void
     */
    public static function tearDownAfterClass()
    {
        TestLoggingUtil::getInstance()->clearMockLoggingUtil();
    }

    /**
     * Validate that Paths that are already set are returned
     * @throws \Exception
     */
    public function testGetModulePathsAlreadySet()
    {
        $this->setMockResolverClass();
        $resolver = ModuleResolver::getInstance();
        $this->setMockResolverProperties($resolver, ["example" . DIRECTORY_SEPARATOR . "paths"]);
        $this->assertEquals(["example" . DIRECTORY_SEPARATOR . "paths"], $resolver->getModulesPath());
    }

    /**
     * Validate paths are aggregated correctly
     * @throws \Exception
     */
    public function testGetModulePathsAggregate()
    {

        $this->mockForceGenerate(false);
        $this->setMockResolverClass(
            false,
            null,
            null,
            null,
            [
                'some' . DIRECTORY_SEPARATOR . 'path' . DIRECTORY_SEPARATOR . 'example' => ['example'],
                'other' . DIRECTORY_SEPARATOR . 'path' . DIRECTORY_SEPARATOR . 'sample' => ['sample'],
            ],
            null,
            [
                'Magento_example' => 'some' . DIRECTORY_SEPARATOR . 'path' . DIRECTORY_SEPARATOR . 'example',
                'Magento_sample' => 'other' . DIRECTORY_SEPARATOR . 'path' . DIRECTORY_SEPARATOR . 'sample',
            ],
            null,
            null,
            [],
            []
        );
        $resolver = ModuleResolver::getInstance();
        $this->setMockResolverProperties($resolver, null, [0 => 'Magento_example', 1 => 'Magento_sample']);
        $this->assertEquals(
            [
                'some' . DIRECTORY_SEPARATOR . 'path' . DIRECTORY_SEPARATOR . 'example',
                'other' . DIRECTORY_SEPARATOR . 'path' . DIRECTORY_SEPARATOR . 'sample'
            ],
            $resolver->getModulesPath()
        );
    }

    /**
     * Validate correct path locations are fed into globRelevantPaths
     * @throws \Exception
     */
    public function testGetModulePathsLocations()
    {
        // clear test object handler value to inject parsed content
        $property = new \ReflectionProperty(ModuleResolver::class, 'instance');
        $property->setAccessible(true);
        $property->setValue(null);

        $this->mockForceGenerate(false);
        $mockResolver = $this->setMockResolverClass(
            true,
            [],
            null,
            null,
            [],
            [],
            [],
            null,
            null,
            [],
            [],
            null,
            function ($arg) {
                return $arg;
            },
            function ($arg) {
                return $arg;
            }
        );
        $resolver = ModuleResolver::getInstance();
        $this->setMockResolverProperties($resolver, null, null);
        $this->assertEquals(
            [],
            $resolver->getModulesPath()
        );

        // Define the Module paths from app/code
        $magentoBaseCodePath = MAGENTO_BP;

        // Define the Module paths from default TESTS_MODULE_PATH
        $modulePath = defined('TESTS_MODULE_PATH') ? TESTS_MODULE_PATH : TESTS_BP;

        $mockResolver->verifyInvoked('globRelevantPaths', [$modulePath, '']);
        $mockResolver->verifyInvoked(
            'globRelevantPaths',
            [$magentoBaseCodePath . DIRECTORY_SEPARATOR . "vendor" , 'Test' . DIRECTORY_SEPARATOR .'Mftf']
        );
        $mockResolver->verifyInvoked(
            'globRelevantPaths',
            [
                $magentoBaseCodePath . DIRECTORY_SEPARATOR . "app" . DIRECTORY_SEPARATOR . "code",
                'Test' . DIRECTORY_SEPARATOR .'Mftf'
            ]
        );
        $mockResolver->verifyInvoked(
            'globRelevantPaths',
            [
                $magentoBaseCodePath
                    . DIRECTORY_SEPARATOR . "dev"
                    . DIRECTORY_SEPARATOR . "tests"
                    . DIRECTORY_SEPARATOR . "acceptance"
                    . DIRECTORY_SEPARATOR . "tests"
                    . DIRECTORY_SEPARATOR . "functional"
                    . DIRECTORY_SEPARATOR . "Magento"
                    . DIRECTORY_SEPARATOR . "FunctionalTest"
                , ''
            ]
        );
    }

    /**
     * Validate aggregateTestModulePathsFromComposerJson
     *
     * @throws \Exception
     */
    public function testAggregateTestModulePathsFromComposerJson()
    {
        $this->mockForceGenerate(false);
        $this->setMockResolverClass(
            false,
            null, // getEnabledModules
            null, // applyCustomMethods
            null, // globRelevantWrapper
            [], // relevantPath
            null, // getCustomModulePaths
            null, // getRegisteredModuleList
            null, // aggregateTestModulePathsFromComposerJson
            [], // aggregateTestModulePathsFromComposerInstaller
            [
                'composer' . DIRECTORY_SEPARATOR . 'json' . DIRECTORY_SEPARATOR . 'pathA' =>
                    [
                        'Magento_ModuleA',
                        'Magento_ModuleB'
                    ],
                'composer' . DIRECTORY_SEPARATOR . 'json' . DIRECTORY_SEPARATOR . 'pathB' =>
                    [
                        'Magento_ModuleB',
                        'Magento_ModuleC'
                    ],
            ], // getComposerJsonTestModulePaths
            [] // getComposerInstalledTestModulePaths
        );

        $resolver = ModuleResolver::getInstance();
        $this->setMockResolverProperties($resolver, null, [0 => 'Magento_ModuleB', 1 => 'Magento_ModuleC']);
        $this->assertEquals(
            [
                'composer' . DIRECTORY_SEPARATOR . 'json' . DIRECTORY_SEPARATOR . 'pathB'
            ],
            $resolver->getModulesPath()
        );
    }

    /**
     * Validate getComposerJsonTestModulePaths with paths invocation
     *
     * @throws \Exception
     */
    public function testGetComposerJsonTestModulePathsForPathInvocation()
    {
        $this->mockForceGenerate(false);
        $mockResolver = $this->setMockResolverClass(
            false,
            [],
            null,
            null,
            [],
            null,
            null,
            null,
            null,
            [],
            []
        );

        $resolver = ModuleResolver::getInstance();
        $this->setMockResolverProperties($resolver, null, null);
        $this->assertEquals(
            [],
            $resolver->getModulesPath()
        );

        // Expected dev tests path
        $expectedSearchPaths[] = MAGENTO_BP
            . DIRECTORY_SEPARATOR
            . 'dev'
            . DIRECTORY_SEPARATOR
            . 'tests'
            . DIRECTORY_SEPARATOR
            . 'acceptance'
            . DIRECTORY_SEPARATOR
            . 'tests'
            . DIRECTORY_SEPARATOR
            . 'functional';

        // Expected test module path
        $testModulePath = defined('TESTS_MODULE_PATH') ? TESTS_MODULE_PATH : TESTS_BP;

        if (array_search($testModulePath, $expectedSearchPaths) === false) {
            $expectedSearchPaths[] = $testModulePath;
        }

        $mockResolver->verifyInvoked('getComposerJsonTestModulePaths', [$expectedSearchPaths]);
    }

    /**
     * Validate aggregateTestModulePathsFromComposerInstaller
     *
     * @throws \Exception
     */
    public function testAggregateTestModulePathsFromComposerInstaller()
    {
        $this->mockForceGenerate(false);
        $this->setMockResolverClass(
            false,
            null,
            null,
            null,
            [],
            null,
            null,
            null,
            null,
            [],
            [
                'composer' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'pathA' =>
                    [
                        'Magento_ModuleA',
                        'Magento_ModuleB'
                    ],
                'composer' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'pathB' =>
                    [
                        'Magento_ModuleB',
                        'Magento_ModuleC'
                    ],
            ]
        );

        $resolver = ModuleResolver::getInstance();
        $this->setMockResolverProperties(
            $resolver,
            null,
            [0 => 'Magento_ModuleA', 1 => 'Magento_ModuleB', 2 => 'Magento_ModuleC']
        );
        $this->assertEquals(
            [
                'composer' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'pathA',
                'composer' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'pathB'
            ],
            $resolver->getModulesPath()
        );
    }

    /**
     * Validate getComposerInstalledTestModulePaths with paths invocation
     *
     * @throws \Exception
     */
    public function testGetComposerInstalledTestModulePathsForPathInvocation()
    {
        $this->mockForceGenerate(false);
        $mockResolver = $this->setMockResolverClass(
            false,
            [],
            null,
            null,
            [],
            null,
            null,
            null,
            null,
            [],
            []
        );

        $resolver = ModuleResolver::getInstance();
        $this->setMockResolverProperties($resolver, null, null);
        $this->assertEquals(
            [],
            $resolver->getModulesPath()
        );

        // Expected file path
        $expectedSearchPath = MAGENTO_BP . DIRECTORY_SEPARATOR . 'composer.json';

        $mockResolver->verifyInvoked('getComposerInstalledTestModulePaths', [$expectedSearchPath]);
    }

    /**
     * Validate mergeModulePaths
     *
     * @throws \Exception
     */
    public function testMergeModulePaths()
    {
        $this->mockForceGenerate(false);
        $this->setMockResolverClass(
            false,
            null,
            null,
            null,
            [
                'some' . DIRECTORY_SEPARATOR . 'path' . DIRECTORY_SEPARATOR . 'example' => ['example'],
                'other' . DIRECTORY_SEPARATOR . 'path' . DIRECTORY_SEPARATOR . 'sample' => ['sample'],
            ],
            null,
            null,
            null,
            [],
            [
                'composer' . DIRECTORY_SEPARATOR . 'json' . DIRECTORY_SEPARATOR . 'pathA' =>
                    [
                        'Magento_ModuleA',
                        'Magento_ModuleB'
                    ],
                'composer' . DIRECTORY_SEPARATOR . 'json' . DIRECTORY_SEPARATOR . 'pathB' =>
                    [
                        'Magento_ModuleB',
                        'Magento_ModuleC'
                    ],
            ],
            [
                'composer' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'pathA' =>
                    [
                        'Magento_ModuleA',
                        'Magento_ModuleB'
                    ],
                'composer' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'pathB' =>
                    [
                        'Magento_ModuleB',
                        'Magento_ModuleC'
                    ],
            ]
        );

        $resolver = ModuleResolver::getInstance();
        $this->setMockResolverProperties($resolver, null, [0 => 'Magento_ModuleB', 1 => 'Magento_ModuleC']);
        $this->assertEquals(
            [
                'composer' . DIRECTORY_SEPARATOR . 'json' . DIRECTORY_SEPARATOR . 'pathB'
            ],
            $resolver->getModulesPath()
        );
    }

    /**
     * Validate custom modules are added
     * @throws \Exception
     */
    public function testApplyCustomModuleMethods()
    {
        $this->setMockResolverClass(
            false,
            null,
            null,
            null,
            [],
            [ 'otherPath' => ['Magento_Module']],
            null,
            null,
            null,
            [],
            []
        );
        $resolver = ModuleResolver::getInstance();
        $this->setMockResolverProperties($resolver, null, null, null);
        $this->assertEquals(['otherPath'], $resolver->getModulesPath());
        TestLoggingUtil::getInstance()->validateMockLogStatement(
            'info',
            'including custom module',
            [ 'Magento_Module' => 'otherPath']
        );
    }

    /**
     * Validate blacklisted modules are removed
     * @throws \Exception
     */
    public function testGetModulePathsBlacklist()
    {
        $this->setMockResolverClass(
            false,
            null,
            null,
            null,
            [],
            null,
            null,
            null,
            null,
            [],
            [],
            [
                'vendor' => ['vendor'],
                'appCode' => ['appCode'],
                'devTests' => ['devTests'],
                'thisPath' => ['thisPath']
            ],
            function ($arg) {
                return $arg;
            },
            function ($arg) {
                return $arg;
            }
        );
        $resolver = ModuleResolver::getInstance();
        $this->setMockResolverProperties($resolver, null, null, ['devTests']);
        $this->assertEquals(
            ['vendor', 'appCode', 'thisPath'],
            $resolver->getModulesPath()
        );
        TestLoggingUtil::getInstance()->validateMockLogStatement(
            'info',
            'excluding module',
            ['devTests' => 'devTests']
        );
    }

    /**
     * Validate that getEnabledModules errors out when no Admin Token is returned and --force is false
     * @throws \Exception
     */
    public function testGetModulePathsNoAdminToken()
    {
        // Set --force to false
        $this->mockForceGenerate(false);

        // Mock ModuleResolver and $enabledModulesPath
        $this->setMockResolverClass(
            false,
            null,
            ["example" . DIRECTORY_SEPARATOR . "paths"],
            [],
            null,
            null,
            null,
            [],
            [],
            [],
            []
        );
        $resolver = ModuleResolver::getInstance();
        $this->setMockResolverProperties($resolver, null, null);

        // Cannot Generate if no --force was passed in and no Admin Token is returned succesfully
        $this->expectException(TestFrameworkException::class);
        $resolver->getModulesPath();
    }

    /**
     * Validates that getAdminToken is not called when --force is enabled
     */
    public function testGetAdminTokenNotCalledWhenForce()
    {
        // Set --force to true
        $this->mockForceGenerate(true);

        // Mock ModuleResolver and applyCustomModuleMethods()
        $mockResolver = $this->setMockResolverClass();
        $resolver = ModuleResolver::getInstance();
        $this->setMockResolverProperties($resolver, null, null);
        $resolver->getModulesPath();
        $mockResolver->verifyNeverInvoked("getAdminToken");

        // verifyNeverInvoked does not add to assertion count
        $this->addToAssertionCount(1);
    }

    /**
     * Verify the getAdminToken method returns throws an exception if ENV is not fully loaded.
     */
    public function testGetAdminTokenWithMissingEnv()
    {
        // Set --force to true
        $this->mockForceGenerate(false);

        // Unset env
        unset($_ENV['MAGENTO_ADMIN_USERNAME']);

        // Mock ModuleResolver and applyCustomModuleMethods()
        $mockResolver = $this->setMockResolverClass();
        $resolver = ModuleResolver::getInstance();

        // Expect exception
        $this->expectException(TestFrameworkException::class);
        $resolver->getModulesPath();
    }

    /**
     * Verify the getAdminToken method returns throws an exception if Token was bad.
     */
    public function testGetAdminTokenWithBadResponse()
    {
        // Set --force to true
        $this->mockForceGenerate(false);

        // Mock ModuleResolver and applyCustomModuleMethods()
        $mockResolver = $this->setMockResolverClass();
        $resolver = ModuleResolver::getInstance();

        // Expect exception
        $this->expectException(TestFrameworkException::class);
        $resolver->getModulesPath();
    }

    /**
     * Function used to set mock for parser return and force init method to run between tests.
     *
     * @param string $mockToken
     * @param array $mockGetEnabledModules
     * @param string[] $mockApplyCustomMethods
     * @param string[] $mockGlobRelevantWrapper
     * @param string[] $mockRelevantPaths
     * @param string[] $mockGetCustomModulePaths
     * @param string[] $mockGetRegisteredModuleList
     * @param string[] $mockAggregateTestModulePathsFromComposerJson
     * @param string[] $mockAggregateTestModulePathsFromComposerInstaller
     * @param string[] $mockGetComposerJsonTestModulePaths
     * @param string[] $mockGetComposerInstalledTestModulePaths
     * @param string[] $mockAggregateTestModulePaths
     * @param string[] $mockNormalizeModuleNames
     * @throws \Exception
     * @return Verifier ModuleResolver double
     */
    private function setMockResolverClass(
        $mockToken = null,
        $mockGetEnabledModules = null,
        $mockApplyCustomMethods = null,
        $mockGlobRelevantWrapper = null,
        $mockRelevantPaths = null,
        $mockGetCustomModulePaths = null,
        $mockGetRegisteredModuleList = null,
        $mockAggregateTestModulePathsFromComposerJson = null,
        $mockAggregateTestModulePathsFromComposerInstaller = null,
        $mockGetComposerJsonTestModulePaths = null,
        $mockGetComposerInstalledTestModulePaths = null,
        $mockAggregateTestModulePaths = null,
        $mockNormalizeModuleNames = null
    ) {
        $property = new \ReflectionProperty(ModuleResolver::class, 'instance');
        $property->setAccessible(true);
        $property->setValue(null);

        $mockMethods = [];
        if (isset($mockToken)) {
            $mockMethods['getAdminToken'] = $mockToken;
        }
        if (isset($mockGetEnabledModules)) {
            $mockMethods['getEnabledModules'] = $mockGetEnabledModules;
        }
        if (isset($mockApplyCustomMethods)) {
            $mockMethods['applyCustomModuleMethods'] = $mockApplyCustomMethods;
        }
        if (isset($mockGlobRelevantWrapper)) {
            $mockMethods['globRelevantWrapper'] = $mockGlobRelevantWrapper;
        }
        if (isset($mockRelevantPaths)) {
            $mockMethods['globRelevantPaths'] = $mockRelevantPaths;
        }
        if (isset($mockGetCustomModulePaths)) {
            $mockMethods['getCustomModulePaths'] = $mockGetCustomModulePaths;
        }
        if (isset($mockGetRegisteredModuleList)) {
            $mockMethods['getRegisteredModuleList'] = $mockGetRegisteredModuleList;
        }
        if (isset($mockAggregateTestModulePathsFromComposerJson)) {
            $mockMethods['aggregateTestModulePathsFromComposerJson'] = $mockAggregateTestModulePathsFromComposerJson;
        }
        if (isset($mockAggregateTestModulePathsFromComposerInstaller)) {
            $mockMethods['aggregateTestModulePathsFromComposerInstaller'] =
                $mockAggregateTestModulePathsFromComposerInstaller;
        }
        if (isset($mockGetComposerJsonTestModulePaths)) {
            $mockMethods['getComposerJsonTestModulePaths'] = $mockGetComposerJsonTestModulePaths;
        }
        if (isset($mockGetComposerInstalledTestModulePaths)) {
            $mockMethods['getComposerInstalledTestModulePaths'] = $mockGetComposerInstalledTestModulePaths;
        }
        if (isset($mockAggregateTestModulePaths)) {
            $mockMethods['aggregateTestModulePaths'] = $mockAggregateTestModulePaths;
        }
        if (isset($mockNormalizeModuleNames)) {
            $mockMethods['normalizeModuleNames'] = $mockNormalizeModuleNames;
        }

        $mockResolver = AspectMock::double(
            ModuleResolver::class,
            $mockMethods
        );
        $instance = AspectMock::double(
            ObjectManager::class,
            ['create' => $mockResolver->make(), 'get' => null]
        )->make();
        // bypass the private constructor
        AspectMock::double(ObjectManagerFactory::class, ['getObjectManager' => $instance]);

        return $mockResolver;
    }

    /**
     * Function used to set mock for Resolver properties
     *
     * @param ModuleResolver $instance
     * @param array $mockPaths
     * @param array $mockModules
     * @param array $mockBlacklist
     * @throws \Exception
     */
    private function setMockResolverProperties($instance, $mockPaths = null, $mockModules = null, $mockBlacklist = [])
    {
        $property = new \ReflectionProperty(ModuleResolver::class, 'enabledModulePaths');
        $property->setAccessible(true);
        $property->setValue($instance, $mockPaths);

        $property = new \ReflectionProperty(ModuleResolver::class, 'enabledModules');
        $property->setAccessible(true);
        $property->setValue($instance, $mockModules);

        $property = new \ReflectionProperty(ModuleResolver::class, 'moduleBlacklist');
        $property->setAccessible(true);
        $property->setValue($instance, $mockBlacklist);
    }

    /**
     * Mocks MftfApplicationConfig->forceGenerateEnabled()
     * @param $forceGenerate
     * @throws \Exception
     * @return void
     */
    private function mockForceGenerate($forceGenerate)
    {
        $mockConfig = AspectMock::double(
            MftfApplicationConfig::class,
            ['forceGenerateEnabled' => $forceGenerate]
        );
        $instance = AspectMock::double(
            ObjectManager::class,
            ['create' => $mockConfig->make(), 'get' => null]
        )->make();
        AspectMock::double(ObjectManagerFactory::class, ['getObjectManager' => $instance]);
    }

    /**
     * After method functionality
     * @return void
     */
    protected function tearDown()
    {
        // re set env
        if (!isset($_ENV['MAGENTO_ADMIN_USERNAME'])) {
            $_ENV['MAGENTO_ADMIN_USERNAME'] = "admin";
        }

        AspectMock::clean();
    }
}
