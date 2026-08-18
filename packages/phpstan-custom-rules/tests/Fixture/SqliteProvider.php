<?php

declare(strict_types=1);

namespace Tests\Fixture;

final class SqliteProvider
{
    public function grammarRule(): string
    {
        return 'select_stmt';
    }
}
