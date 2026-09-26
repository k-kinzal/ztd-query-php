<?php

declare(strict_types=1);

namespace Requirements\Test;

use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;

/**
 * How a named runner executes: its extension, command, working directory and timeout.
 *
 * @visibility public
 *
 * @example Describing a PHPUnit runner
 *     $config = new \Requirements\Test\RunnerConfig('phpunit', ['php', 'vendor/bin/phpunit'], '/project');
 *     [$config->extension, $config->command, $config->directory, $config->timeout] // => ['phpunit', ['php', 'vendor/bin/phpunit'], '/project', 60.0]
 * @example Reading a runner entry of requirements.yaml
 *     $config = \Requirements\Test\RunnerConfig::from(['extension' => 'behat', 'command' => ['php', 'vendor/bin/behat'], 'cwd' => 'tests', 'timeout' => 5], '/project');
 *     [$config->directory, $config->timeout] // => ['/project/tests', 5.0]
 */
final class RunnerConfig
{
    /**
     * @param string $extension The registered runner extension name
     * @param non-empty-list<string> $command The command and its arguments, run without a shell
     * @param string $directory The absolute working directory
     * @param float $timeout The positive timeout in seconds
     */
    public function __construct(public readonly string $extension, public readonly array $command, public readonly string $directory, public readonly float $timeout = 60.0)
    {
    }

    /**
     * Reads a runner entry of the configuration.
     *
     * @param mixed $value The decoded runner entry
     * @param string $directory The configuration directory that a relative cwd resolves against
     *
     * @return self The runner configuration
     *
     * @throws InvalidInputException When the entry has unknown fields, no command or a nonpositive timeout
     */
    public static function from(mixed $value, string $directory): self
    {
        $data = Fields::mapping($value, 'runner');
        Fields::keys($data, ['extension', 'command', 'cwd', 'timeout'], 'runner');
        $command = Fields::strings($data['command'] ?? [], 'runner.command', false);
        $timeout = $data['timeout'] ?? 60;
        if ($command === [] || (!is_int($timeout) && !is_float($timeout)) || !is_finite((float) $timeout) || $timeout <= 0) {
            throw new InvalidInputException('Runners require a nonempty command and positive timeout.');
        }
        $cwd = Fields::text($data, 'cwd', '.');
        return new self(Fields::text($data, 'extension'), $command, str_starts_with($cwd, '/') ? $cwd : $directory . '/' . $cwd, (float) $timeout);
    }
}
