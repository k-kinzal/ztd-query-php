<?php

declare(strict_types=1);

namespace Requirements\Test;

use InvalidArgumentException;

final class Registry
{
    /** @var array<string, RunnerExtension> */
    private array $extensions;

    /** @param array<string, string> $classes */
    public function __construct(array $classes = [])
    {
        $this->extensions = ['phpunit' => new PhpUnitRunner(), 'behat' => new BehatRunner()];
        foreach ($classes as $name => $class) {
            if (!is_a($class, RunnerExtension::class, true)) {
                throw new InvalidArgumentException("$class must implement RunnerExtension.");
            }
            $this->extensions[$name] = new $class();
        }
    }

    public function get(string $name): RunnerExtension
    {
        return $this->extensions[$name] ?? throw new InvalidArgumentException("Unknown runner extension: $name");
    }
}
