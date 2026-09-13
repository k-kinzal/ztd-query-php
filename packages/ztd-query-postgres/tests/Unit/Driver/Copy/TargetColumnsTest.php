<?php

declare(strict_types=1);

namespace Tests\Unit\Driver\Copy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Driver\Copy\TargetColumns::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlLexerProfile::class)]
final class TargetColumnsTest extends TestCase
{
    public function testRelationParts(): void
    {
        self::assertSame(['public', 'Users'], (new \ZtdQuery\Platform\Postgres\Driver\Copy\TargetColumns())->relationParts('public."Users"'));

        self::assertSame(['users'], (new \ZtdQuery\Platform\Postgres\Driver\Copy\TargetColumns())->relationParts('users'));
    }

    public function testColumns(): void
    {
        self::assertSame(['id', 'name'], (new \ZtdQuery\Platform\Postgres\Driver\Copy\TargetColumns())->columns(null, new \ZtdQuery\Schema\TableDefinition(['id', 'name'], ['id' => 'INTEGER', 'name' => 'TEXT'], ['id'], [], [])));

        self::assertSame(['name', 'id'], (new \ZtdQuery\Platform\Postgres\Driver\Copy\TargetColumns())->columns('name,id', new \ZtdQuery\Schema\TableDefinition(['id', 'name'], ['id' => 'INTEGER', 'name' => 'TEXT'], ['id'], [], [])));
    }

    public function testIdentifier(): void
    {
        $sql = '"a""b"';
        $tokens = \ZtdQuery\Sql\SqlTokenStream::tokenize($sql, \ZtdQuery\Platform\Postgres\PgSqlLexerProfile::create())->significantTokens();

        self::assertSame('a"b', (new \ZtdQuery\Platform\Postgres\Driver\Copy\TargetColumns())->identifier($tokens[0], 'relation'));
    }
}
