<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

use ZtdQuery\Sql\SqlTokenStream;

final class PdoDialectLogicFixture
{
    public function isPostgreSql(string $driver): bool
    {
        return $driver === 'pgsql';
    }

    /** @param array<string, mixed> $metadata */
    public function sqliteType(array $metadata): mixed
    {
        return $metadata['sqlite:decl_type'];
    }

    public function tokens(string $sql): SqlTokenStream
    {
        return SqlTokenStream::tokenize($sql);
    }

    public function sqliteCast(string $value): string
    {
        return 'CAST(' . $value . ' AS INTEGER)';
    }

    public function pgType(): string
    {
        return 'BPCHAR';
    }

    public function dynamicDialectClass(): string
    {
        return 'ZtdQuery\\Platform\\Sqlite\\SqliteParser';
    }
}
