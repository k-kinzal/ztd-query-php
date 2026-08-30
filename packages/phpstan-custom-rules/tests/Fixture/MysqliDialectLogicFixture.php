<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli;

use ZtdQuery\Sql\SqlTokenStream;

final class MysqliDialectLogicFixture
{
    public function resolverClass(): string
    {
        return \ZtdQuery\Platform\MySql\Dialect\MySqlMysqliResultColumnTypeResolver::class;
    }

    public function fieldType(): int
    {
        return MYSQLI_TYPE_LONG;
    }

    public function charsetKey(): string
    {
        return 'charsetnr';
    }

    public function tokens(string $sql): SqlTokenStream
    {
        return SqlTokenStream::tokenize($sql);
    }

    /** @param object{type: int} $field */
    public function numericType(object $field): string
    {
        return match ($field->type) {
            3 => 'INTEGER',
            8 => 'BIGINT',
            default => '',
        };
    }

    /** @return array<int, string> */
    public function numericTypeMap(): array
    {
        return [3 => 'INTEGER', 8 => 'BIGINT'];
    }

    public function comparesNumericType(int $type): bool
    {
        return $type === 3;
    }

    public function numericProtocolType(int $protocolCode): string
    {
        return match ($protocolCode) {
            3 => 'INTEGER',
            8 => 'BIGINT',
            default => '',
        };
    }
}
