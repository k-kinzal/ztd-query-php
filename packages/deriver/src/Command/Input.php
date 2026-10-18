<?php

declare(strict_types=1);

namespace Deriver\Command;

use Deriver\Configuration\Configuration;
use Deriver\Configuration\Loader;
use Deriver\Exception\ConfigurationException;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Applies command-line overrides to an explicitly selected configuration file.
 * @visibility namespace
 */
final class Input
{
    /**
     * Repeated list options replace the corresponding configured list.
     * @throws ConfigurationException If options or configuration are invalid
     */
    public function configuration(InputInterface $input): Configuration
    {
        $path = $input->getOption('config');
        if ($path !== null && !is_string($path)) {
            throw new ConfigurationException('config must be a YAML file path.');
        }
        $configuration = (new Loader())->load($path);
        $settings = get_object_vars($configuration);
        $settings['format'] = $configuration->format->value;
        foreach (['path' => 'paths', 'include' => 'include', 'exclude' => 'exclude', 'php-version' => 'phpVersion', 'format' => 'format'] as $option => $key) {
            $value = $input->getOption($option);
            if ($value !== null && $value !== []) {
                $settings[$key] = $value;
            }
        }
        return Configuration::fromArray($settings);
    }
}
