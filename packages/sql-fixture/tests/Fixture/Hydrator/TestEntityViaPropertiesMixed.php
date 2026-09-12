<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

/**
 * Fixture DTO used to verify object hydration.
 */
class TestEntityViaPropertiesMixed
{
    /**
     * Id.
     */
    public mixed $id = null;
    /**
     * Name.
     */
    public mixed $name = null;
    /**
     * Amount.
     */
    public mixed $amount = null;
}
