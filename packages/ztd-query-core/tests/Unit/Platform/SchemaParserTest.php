<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Contract\SchemaParserContractTest;
use Tests\Fake\FakeSchemaParser;
use ZtdQuery\Platform\SchemaParser;

#[CoversNothing]
final class SchemaParserTest extends SchemaParserContractTest
{
    protected function createParser(): SchemaParser
    {
        return new FakeSchemaParser();
    }

    protected function validCreateTableSql(): string
    {
        return 'CREATE TABLE users ('
            . 'id INTEGER NOT NULL PRIMARY KEY, '
            . 'name VARCHAR(255) NOT NULL, '
            . 'email VARCHAR(255) NOT NULL, '
            . 'age INTEGER, '
            . 'UNIQUE (email)'
            . ')';
    }

    protected function nonCreateTableSql(): string
    {
        return 'SELECT * FROM users';
    }

    /**
     * @return array<int, string>
     */
    #[\Override]
    protected function expectedColumns(): array
    {
        return ['id', 'name', 'email', 'age'];
    }

    /**
     * @return array<int, string>
     */
    #[\Override]
    protected function expectedNotNullColumns(): array
    {
        return ['id', 'name', 'email'];
    }
}
