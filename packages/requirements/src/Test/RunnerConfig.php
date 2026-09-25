<?php

declare(strict_types=1);

namespace Requirements\Test;

use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;

final class RunnerConfig
{
    /** @param non-empty-list<string> $command */
    public function __construct(public readonly string $extension, public readonly array $command, public readonly string $directory, public readonly float $timeout = 60.0)
    {
    }

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
