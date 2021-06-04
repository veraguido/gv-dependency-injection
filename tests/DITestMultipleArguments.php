<?php


namespace Tests;


use Gvera\Helpers\config\Config;

class DITestMultipleArguments
{
    private string $firstValue;
    /**
     * @var Config
     */
    private Config $config;
    /**
     * @var Config
     */
    private Config $newConfig;

    /**
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * @return string
     */
    public function getFirstValue(): string
    {
        return $this->firstValue;
    }

    /**
     * @return string
     */
    public function getSecondValue(): string
    {
        return $this->secondValue;
    }
    private string $secondValue;

    public function __construct(string $firstValue, string $secondValue, Config $config, Config $newConfig)
    {
        $this->firstValue = $firstValue;
        $this->secondValue = $secondValue;
        $this->config = $config;
        $this->newConfig = $newConfig;
    }
}