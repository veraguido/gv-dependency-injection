<?php
namespace Tests;

use Gvera\Cache\Cache;
use Gvera\Exceptions\ClassNotFoundInDIContainerException;
use Gvera\Helpers\config\Config;
use Gvera\Helpers\dependencyInjection\DIContainer;
use Gvera\Helpers\dependencyInjection\DIRegistry;

class DITest extends \PHPUnit\Framework\TestCase
{

    private $cache;

    /**
     * @test
     * @throws \Gvera\Exceptions\InvalidArgumentException|\ReflectionException
     * @throws \Exception
     */
    public function testDI()
    {
        $config = new Config();
        $config->overrideKey('cache_type', 'files');
        $config->overrideKey('files_cache_path', __DIR__ . "/../var/cache/files/");
        Cache::setConfig($config);
        $this->cache = Cache::getCache();
        $container = new DIContainer();
        $registry = new DIRegistry(
            $container,
            __DIR__ . "/../config/ioc.yml"
        );
        $registry->registerObjects();

        $registry->registerObjects();

        $config = $container->get('config');
        $newConfig = $container->get('newconfig');
        $this->assertNotNull($config);
        $this->assertNotNull($newConfig);

        $category = $container->getCategoriesMap();
        $this->assertTrue(count($category) == 8);

        $idsFromCategory = $container->getItemIdsFromCategory('config');
        $this->assertTrue(count($idsFromCategory) == 1);
        $this->assertTrue($idsFromCategory[0] == 'config');

        $fromCategory = $container->getFromCategory('config');
        $this->assertTrue(count($idsFromCategory) == 1);

        $this->assertTrue($container->has('config'));

        $testObject = $container->get('testobject');
        $this->assertNotNull($testObject->getConfig());

        $constructorTest = $container->get('testvalueconstructor');
        $this->assertTrue($constructorTest->getValue() === 'asd');

        $constructorTest1 = $container->get('testvalueconstructor');
        $constructorTest2 = $container->get('testvalueconstructor');
        $this->assertFalse($constructorTest1 === $constructorTest2);

        $multipleValue = $container->get('testmultiplearguments');
        $this->assertTrue($multipleValue->getFirstValue() === 'asd');
        $this->assertTrue($multipleValue->getSecondValue() === 'qwe');
        $this->assertNotNull($multipleValue->getConfig());

        $this->expectException(ClassNotFoundInDIContainerException::class);
        $container->get('anothertest');

    }

    public function tearDown(): void
    {
        Cache::getCache()->deleteAll();
        parent::tearDown();
    }
}