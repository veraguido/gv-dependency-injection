<?php

namespace Tests;

use Gvera\Helpers\config\Config;

/**
 * @Inject config
 */
class DITestClass
{
    public Config $config;

    public function getConfig(): Config
    {
        return $this->config;
    }
}