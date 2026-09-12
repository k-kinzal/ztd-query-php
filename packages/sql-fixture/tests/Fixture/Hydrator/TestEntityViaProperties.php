<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

/**
 * Fixture DTO used to verify object hydration.
 */
class TestEntityViaProperties
{
    /**
     * Id.
     */
    public int $id = 0;
    /**
     * Name.
     */
    public string $name = '';
    /**
     * Amount.
     */
    public float $amount = 0.0;
    /**
     * Active.
     */
    public bool $active = false;
}
