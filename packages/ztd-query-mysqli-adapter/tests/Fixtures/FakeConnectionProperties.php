<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Override;
use ZtdQuery\Adapter\Mysqli\Driver\ConnectionProperties;

/**
 * A connection's properties, held as whatever a test needs them to be.
 *
 * A mysqli that was never opened refuses to answer any of its properties, so a
 * test cannot say what the driver reported without connecting to one. This says
 * it instead.
 */
final class FakeConnectionProperties implements ConnectionProperties
{
    /**
     * @var array<string, mixed> What the connection answers, keyed by property name
     */
    public array $properties;

    /**
     * Holds the properties the connection is to answer with.
     *
     * @param array<string, mixed> $properties What the connection answers, keyed by property name
     */
    public function __construct(array $properties = [])
    {
        $this->properties = $properties;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $name Property as it was written
     *
     * @return mixed What this was told the connection has under that name, or null
     */
    #[Override]
    public function named(string $name): mixed
    {
        return $this->properties[$name] ?? null;
    }

    /**
     * Says what the connection answers under a name from now on.
     *
     * @param string $name Property to answer under
     * @param mixed $value What to answer with
     */
    public function set(string $name, mixed $value): void
    {
        $this->properties[$name] = $value;
    }
}
