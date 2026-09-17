<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

/**
 * A decoded operation and its bound values.
 */
final class SequenceCommand
{
    /**
     * @param list<int|string|null> $params
     */
    public function __construct(public readonly string $sql, public readonly array $params, public readonly bool $read)
    {
    }
}
