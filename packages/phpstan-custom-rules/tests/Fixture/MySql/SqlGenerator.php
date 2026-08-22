<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

final class SqlGenerator
{
    public function statement(): string
    {
        return 'SELECT id FROM users';
    }
}
