<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

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

    public function __construct()
    {
    }
}
