<?php

namespace Tests;

class DITestConstructorClass
{
    private string $value;

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    public function __construct(string $value)
    {
        $this->value = $value;
    }
}