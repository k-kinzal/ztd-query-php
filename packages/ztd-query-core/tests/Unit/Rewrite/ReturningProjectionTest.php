<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Rewrite\ReturningProjection;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(ReturningProjection::class)]
#[UsesClass(SqlToken::class)]
#[UsesClass(SqlTokenStream::class)]
#[UsesClass(UnsupportedSqlException::class)]
final class ReturningProjectionTest extends TestCase
{
    public function testProjectsQualifiedQuotedColumnsAndAliases(): void
    {
        $projection = ReturningProjection::parse(
            'UPDATE users SET name = \'x\' RETURNING users.id, "name" AS display_name',
        );
        self::assertNotNull($projection);

        self::assertSame([
            ['id' => 1, 'display_name' => 'Alice'],
        ], $projection->project([['id' => 1, 'name' => 'Alice', 'ignored' => true]]));
    }

    public function testWildcardPreservesTheWholeMutationRow(): void
    {
        $projection = ReturningProjection::parse('DELETE FROM users RETURNING users.*');
        self::assertNotNull($projection);

        self::assertSame([['id' => 1, 'name' => 'Alice']], $projection->project([
            ['id' => 1, 'name' => 'Alice'],
        ]));
    }

    public function testReturnsNullWhenStatementHasNoReturningClause(): void
    {
        self::assertNull(ReturningProjection::parse('INSERT INTO users VALUES (1)'));
    }

    public function testRejectsExpressionsInsteadOfReturningWrongValues(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        ReturningProjection::parse('INSERT INTO users VALUES (1) RETURNING id + 1');
    }
}
