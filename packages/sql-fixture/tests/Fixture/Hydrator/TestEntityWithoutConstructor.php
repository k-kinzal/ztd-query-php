<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

/**
 * An entity declaring no constructor, so it is built by assigning properties.
 */
class TestEntityWithoutConstructor
{
    /**
     * Builds one, with every parameter defaulted so a row need fill none of them.
     *
     * @param int $id Identifier the row carries
     * @param string $name Name the row carries
     */
    public function __construct(
        public int $id = 0,
        public string $name = '',
    ) {
    }
}
