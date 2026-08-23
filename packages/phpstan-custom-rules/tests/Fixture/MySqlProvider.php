<?php

declare(strict_types=1);

namespace SqlFaker;

final class MySqlProvider
{
    public function statement(): string
    {
        return 'SELECT id FROM users';
    }
}
