<?php

declare(strict_types=1);

namespace Requirements\Test;

use Requirements\Input\InvalidInputException;

/**
 * The runner extensions available to a project by name.
 *
 * "phpunit" and "behat" are built in; configured extension classes are added under their
 * names and may replace a built-in one.
 */
final class Registry
{
    /**
     * @var array<string, RunnerExtension>
     */
    private array $extensions;

    /**
     * @param array<string, string> $classes Extension class names by runner extension name
     *
     * @throws InvalidInputException When a class does not implement RunnerExtension
     */
    public function __construct(array $classes = [])
    {
        $this->extensions = ['phpunit' => new PhpUnitRunner(), 'behat' => new BehatRunner()];
        foreach ($classes as $name => $class) {
            if (!is_a($class, RunnerExtension::class, true)) {
                throw new InvalidInputException("$class must implement RunnerExtension.");
            }
            $this->extensions[$name] = new $class();
        }
    }

    /**
     * Returns the extension registered under a name.
     *
     * @param string $name The runner extension name
     *
     * @return RunnerExtension The extension
     *
     * @throws InvalidInputException When no extension has that name
     */
    public function get(string $name): RunnerExtension
    {
        return $this->extensions[$name] ?? throw new InvalidInputException("Unknown runner extension: $name");
    }
}
