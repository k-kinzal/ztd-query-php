<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

class TestEntityViaProperties
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
     * Amount the fixture was given
     */
    public float $amount = 0.0;
    /**
     * Whether the fixture is active
     */
    public bool $active = false;
}
