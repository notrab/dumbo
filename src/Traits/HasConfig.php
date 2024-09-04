<?php

namespace Dumbo\Traits;

trait HasConfig
{
    /** @var array<string, mixed> Variables stored in the context */
    private $configs = [];

    /**
     * Set a configuration value
     *
     * @param string $key The config key
     * @param mixed $value The config value
     */
    public function setVar(string $key, $value): void
    {
        $this->configs[$key] = $value;
    }

    /**
     * Get a configuration value
     *
     * @param string|null $key The config key to retrieve (optional)
     * @return mixed The config value or the entire config array if no key is provided
     */
    public function getVar(?string $key = null): mixed
    {
        return $this->configs[$key] ?? $this->configs;
    }
}
