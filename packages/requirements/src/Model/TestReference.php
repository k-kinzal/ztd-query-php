<?php

declare(strict_types=1);

namespace Requirements\Model;

use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;

/**
 * A test linked to a specification: the configured runner and the target it runs.
 */
final class TestReference
{
    /**
     * @param string $runner The name of a configured runner
     * @param string $target The test selection passed to that runner
     * @param string $run Whether the test runs automatically or only with --all
     *
     * @throws InvalidInputException When the execution policy is unknown
     */
    public function __construct(
        public readonly string $runner,
        public readonly string $target,
        public readonly string $run = 'auto',
    ) {
        if (!in_array($run, ['auto', 'manual'], true)) {
            throw new InvalidInputException('Test run must be auto or manual.');
        }
    }

    /**
     * Reads a tests entry of a definition.
     *
     * @param mixed $value The decoded tests entry
     *
     * @return self The test reference
     *
     * @throws InvalidInputException When the entry has unknown fields or lacks a runner or target
     */
    public static function from(mixed $value): self
    {
        $data = Fields::mapping($value, 'test');
        Fields::keys($data, ['runner', 'target', 'run'], 'test');
        return new self(Fields::text($data, 'runner'), Fields::text($data, 'target'), Fields::text($data, 'run', 'auto'));
    }
}
