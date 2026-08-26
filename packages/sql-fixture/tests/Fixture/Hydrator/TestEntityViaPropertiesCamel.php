<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

/**
 * An entity whose property is spelled in camelCase where its column is not.
 */
class TestEntityViaPropertiesCamel
{
    /**
     * Name the fixture was given, spelled as the entity spells it
     */
    public string $userName = '';
}
