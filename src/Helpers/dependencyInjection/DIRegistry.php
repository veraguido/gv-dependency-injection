<?php

namespace Gvera\Helpers\dependencyInjection;

use Gvera\Cache\Cache;
use Gvera\Exceptions\InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

class DIRegistry
{
    const DI_KEY = 'gv_di';

    private DIContainer $container;
    private string $iocFilePath;

    public function __construct(
        DIContainer $container,
        string $iocFilePath = __DIR__ . "/../../../config/ioc.yml"
    ) {
        $this->iocFilePath = $iocFilePath;
        $this->container = $container;
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     */
    public function registerObjects()
    {
        $diObjects = $this->getDIObjects();
        foreach ($diObjects as $categoryKey => $category) {
            $this->registerByCategory($category);
            $this->registerCategoryMap($categoryKey, $category);
        }
    }

    /**
     * @param $category
     */
    private function registerByCategory($category)
    {
        $classPath = $category['classPath'];

        foreach ($category['objects'] as $diKey => $diObject) {
            $singleton = isset($diObject['singleton']) ? $diObject['singleton'] : false;
            $className = $classPath . $diObject['class'];
            $arguments = isset($diObject['arguments']) ? array($diObject['arguments']) : [];
            $this->registerObject($diKey, $className, $singleton, $arguments);
        }
    }

    private function registerCategoryMap(string $categoryKey, array $category)
    {
        $this->container->mapCategory(array_keys($category['objects']), $categoryKey);
    }

    /**
     * @param $objectKey
     * @param string $className
     * @param bool $singleton
     * @param array $arguments
     */
    private function registerObject($objectKey, string $className, bool $singleton, array $arguments)
    {
        if ($singleton) {
            $this->container->mapClassAsSingleton(
                $objectKey,
                $className,
                $arguments
            );
            return;
        }

        $this->container->mapClass(
            $objectKey,
            $className,
            $arguments
        );
    }

    /**
     * @return mixed
     * @throws InvalidArgumentException
     * @throws \Exception
     */
    private function getDIObjects()
    {
        if (Cache::getCache()->exists(self::DI_KEY)) {
            return Cache::getCache()->load(self::DI_KEY);
        }

        $ioc = Yaml::parse(file_get_contents($this->iocFilePath));
        Cache::getCache()->save(self::DI_KEY, $ioc);

        return $ioc;
    }
}
