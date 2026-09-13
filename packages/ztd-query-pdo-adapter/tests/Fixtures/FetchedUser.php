<?php

declare(strict_types=1);

namespace Tests\Fixtures;

/**
 * Receives a fetched row and a caller-supplied constructor argument.
 */
final class FetchedUser
{
    /**
     * Identifier filled from the fetched row.
     */
    public int $id;

    /**
     * Name filled from the fetched row.
     */
    public string $name;

    /**
     * Keep the constructor argument independently of the fetched columns.
     */
    public function __construct(public readonly string $label)
    {
    }
}
