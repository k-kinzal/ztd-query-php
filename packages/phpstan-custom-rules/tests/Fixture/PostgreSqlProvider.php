<?php

declare(strict_types=1);

namespace SqlFaker;

final class PostgreSqlProvider
{
    public function statement(string $table): string
    {
        return "INSERT INTO $table (id) VALUES (1)";
    }
}
