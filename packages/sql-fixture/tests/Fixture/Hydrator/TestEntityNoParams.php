<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

class TestEntityNoParams
{
    public int $id = 0;
    public string $name = '';

    public function __construct()
    {
    }
}
