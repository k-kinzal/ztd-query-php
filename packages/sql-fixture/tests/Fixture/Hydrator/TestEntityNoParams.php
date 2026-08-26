<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

/**
 * An entity whose constructor takes nothing, so it is built by assigning properties.
 */
class TestEntityNoParams
{
    /**
     * Identifier the fixture was given
     */
    public int $id = 0;
    /**
     * Name the fixture was given
     */
    public string $name = '';

    /**
     * Builds one, taking nothing, which is what the hydrator has to notice.
     */
    public function __construct()
    {
    }
}
