<?php
namespace Gvera\Helpers\dependencyInjection;

use AllowDynamicProperties;
use Gvera\Exceptions\ClassNotFoundInDIContainerException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;

#[AllowDynamicProperties]
class DIContainer implements ContainerInterface
{
    const SINGLETON_CLASS = 'classSingleton';


    private array $map;
    private $classMap;
    private array $categories = [];

    private function addToMap($key, $obj)
    {
        if (!isset($this->map)) {
            $this->map = [];
        }
        $this->map[$key] = $obj;

        $this->classMap[$key] = $obj['value'];
    }
    public function mapClass($key, $value, $arguments = null)
    {
        $this->addToMap($key, [
            "value" => $value,
            "type" => 'class',
            "arguments" => $arguments
        ]);
    }
    public function mapClassAsSingleton($key, $value, $arguments = null)
    {
        $this->addToMap($key, [
            "value" => $value,
            "type" => self::SINGLETON_CLASS,
            "instance" => null,
            "arguments" => $arguments
        ]);
    }

    public function mapCategory(array $category, $categoryName)
    {
        $this->categories[$categoryName] = $category;
    }

    public function getCategoriesMap(): array
    {
        return $this->categories;
    }

    public function getItemIdsFromCategory(string $key): array
    {
        return $this->categories[$key] ?? [];
    }

    public function getFromCategory(string $key): array
    {
        $items = [];
        foreach ($this->categories[$key] as $itemKey) {
            array_push($items, $this->get($itemKey));
        }

        return $items;
    }

    /**
     * @param $arguments
     * @return object
     * @throws ReflectionException
     * Will instanciate and return the arguments with the instance of classes injected.
     */
    private function getDIarguments($arguments)
    {
        $replacedArguments = $arguments;
        foreach ($arguments as $index => $argument) {
            if (is_array($argument)) {
                return $this->getDIarguments($argument);
            }

            if (str_contains($argument, "@")) {
                $replacedArguments[$index] = $this->get(str_replace("@", "", $argument));
            }
        }

        return $replacedArguments;
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @throws NotFoundExceptionInterface  No entry was found for **this** identifier.
     * @throws ContainerExceptionInterface Error while retrieving the entry.
     * @throws ReflectionException
     *
     */
    public function get($id)
    {
        $className = $this->classMap[$id];
        // checking if the class exists
        $this->checkClassExist($className);
        $singleton = $this->checkIfIsSingleton($id);
        if ($singleton && $this->getItemInstance($id) !== null) {
            return $this->map[$id]['instance'];
        }

        $reflection = new \ReflectionClass($className);
        $arguments = $this->map[$id]['arguments'] ?? [];
        // creating an instance of the class
        $obj = $this->createInstanceOfNewClass($reflection, $className, $arguments);

        $this->checkInjection($reflection, $obj);

        if ($singleton) {
            $this->map[$id]['instance'] = $obj;
        }

        // return the created instance
        return $obj;
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * `has($id)` returning true does not mean that `get($id)` will not throw an exception.
     * It does however mean that `get($id)` will not throw a `NotFoundExceptionInterface`.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return bool
     */
    public function has($id)
    {
        $className = $this->classMap[$id];
        // checking if the class exists
        return class_exists($className);
    }

    /**
     * @param $lines
     * @param $object
     * @throws ReflectionException
     */
    private function checkInjectionLinesInComments($lines, $object)
    {
        foreach ($lines as $line) {
            if (count($parts = explode("@Inject", $line)) > 1) {
                $this->injectDependency($parts, $object);
            }
        }
    }

    /**
     * @param $parts
     * @param $object
     * @return void
     * @throws ReflectionException
     */
    private function injectDependency($parts, $object): void
    {
        $parts = explode(" ", $parts[1]);
        if (count($parts) > 1) {
            $this->generateObjectDependencies($object, $parts);
        }
    }

    /**
     * @param $className
     * @return void
     * @throws ClassNotFoundInDIContainerException
     */
    private function checkClassExist($className): void
    {
        if (!class_exists($className)) {
            throw new ClassNotFoundInDIContainerException("DI: missing class $className", array($className));
        }
    }

    /**
     * @param $object
     * @param $dependencies
     * @throws ReflectionException
     */
    private function generateObjectDependencies($object, $dependencies): void
    {
        $key = $dependencies[1];
        $key = str_replace("\n", "", $key);
        $key = str_replace("\r", "", $key);

        if (!isset($this->map[$key])) {
            return;
        }

        $id = array_search($this->map[$key]['value'], $this->classMap);
        $this->generateResource($id, $key, $object, $this->map[$key]['type']);

        $object->$key = $this->map[$key]['instance'];
    }

    /**
     * @param $id
     * @param $key
     * @param $object
     * @param $type
     * @throws ReflectionException
     */
    private function generateResource($id, $key, $object, $type): void
    {
        if ($type === "value") {
            $object->$key = $this->map[$key]['value'];
            return;
        }

        $this->generateClass($id, $key, $object, $type);
    }

    /**
     * @param $id
     * @param $key
     * @param $object
     * @param $type
     * @throws ReflectionException
     */
    private function generateClass($id, $key, $object, $type)
    {
        if ($type === "class") {
            $object->$key = $this->get($id);
            return;
        }

        if ($type === "classSingleton") {
            $this->generateSingletonDependency($key, $id);
            return;
        }
    }

    /**
     * @param $key
     * @param $id
     * @throws ReflectionException
     */
    private function generateSingletonDependency($key, $id)
    {
        if ($this->map[$key]['instance'] === null) {
            $this->map[$key]['instance'] = $this->get($id);
        }
    }

    /**
     * @param $reflectionClass
     * @param $className
     * @param $arguments
     * @return mixed
     * @throws ReflectionException
     */
    private function createInstanceOfNewClass($reflectionClass, $className, $arguments)
    {
        if ($arguments === null || count($arguments) == 0) {
            return new $className;
        }

        if (!is_array($arguments)) {
            $arguments = array($arguments);
        }

        //convert the DIArguments to actual objects
        $diArguments = $this->getDIarguments($arguments);
        return $reflectionClass->newInstanceArgs($diArguments);
    }

    private function checkIfIsSingleton($id)
    {
        return self::SINGLETON_CLASS === $this->map[$id]['type'];
    }

    /**
     * @param $id
     * @return mixed
     */
    private function getItemInstance($id)
    {
        return $this->map[$id]['instance'];
    }

    /**
     * @param $reflection
     * @param $instance
     */
    private function checkInjection($reflection, $instance)
    {
        // injecting
        if ($doc = $reflection->getDocComment()) {
            $lines = explode("\n", $doc);
            $this->checkInjectionLinesInComments($lines, $instance);
        }
    }
}
