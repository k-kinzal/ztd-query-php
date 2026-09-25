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
     */
    public function __construct(public readonly string $runner, public readonly string $target)
    {
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
        Fields::keys($data, ['runner', 'target'], 'test');
        return new self(Fields::text($data, 'runner'), Fields::text($data, 'target'));
    }
}
