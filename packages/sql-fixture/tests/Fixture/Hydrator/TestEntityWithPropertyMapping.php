<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

/**
 * An entity naming its properties differently from the columns they hold.
 */
class TestEntityWithPropertyMapping
{
    /**
     * Builds one from the column the property stands for.
     *
     * @param string $userName Name the row carries under user_name
     */
    public function __construct(
        public string $userName = '',
    ) {
    }
}
