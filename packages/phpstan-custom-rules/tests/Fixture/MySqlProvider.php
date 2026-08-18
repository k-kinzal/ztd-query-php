<?php

declare(strict_types=1);

namespace Tests\Fixture;

final class MySqlProvider
{
    public function statement(): string
    {
        return 'SELECT id FROM users';
    }
}
