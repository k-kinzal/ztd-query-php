<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

/**
 * An entity mixing property names that match a column and names that must be converted.
 */
class TestEntityViaPropertiesMixed
{
    /**
     * Identifier the fixture was given
     */
    public mixed $id = null;
    /**
     * Name the fixture was given
     */
    public mixed $name = null;
    /**
     * Amount the fixture was given
     */
    public mixed $amount = null;
}
