<?php

namespace Assistant\Module\Common\Extension;

use Configula\ConfigFactory;
use Configula\ConfigValues;
use Configula\Exception\ConfigValueNotFoundException as ConfigulaConfigValueNotFoundException;

final class Config
{
    private ConfigValues $values;

    public function __construct(array $values)
    {
        $config = ConfigFactory::fromArray($values);

        $this->values = $config;
    }

    public function get(string $key): mixed
    {
        try {
            $value = $this->values->get($key);
        } catch (ConfigulaConfigValueNotFoundException $e) {
            throw new ConfigValueNotFoundException($e->getMessage());
        }

        return $value;
    }
}
