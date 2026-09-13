<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Transformer\Insert\ExpressionCast::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlCastRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Sql\CastTypeMapper::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Sql\NativeCastTarget::class)]
final class ExpressionCastTest extends TestCase
{
    public function testCastInsertExpression(): void
    {
        self::assertSame('CAST(COALESCE(NULLIF(CAST(? AS TEXT), \'\'), \'false\') AS BOOLEAN)', (new \ZtdQuery\Platform\Postgres\Transformer\Insert\ExpressionCast(new \ZtdQuery\Platform\Postgres\PgSqlCastRenderer()))->castInsertExpression('?', new \ZtdQuery\Schema\ColumnType(\ZtdQuery\Schema\ColumnTypeFamily::BOOLEAN, 'BOOLEAN')));

        self::assertSame('CAST(1 AS INTEGER)', (new \ZtdQuery\Platform\Postgres\Transformer\Insert\ExpressionCast(new \ZtdQuery\Platform\Postgres\PgSqlCastRenderer()))->castInsertExpression('1', new \ZtdQuery\Schema\ColumnType(\ZtdQuery\Schema\ColumnTypeFamily::INTEGER, 'INTEGER')));
    }
    public function testProjectionCastsDeclaredColumnsAndPreservesRawExpressions(): void
    {
        $cast = new \ZtdQuery\Platform\Postgres\Transformer\Insert\ExpressionCast(new \ZtdQuery\Platform\Postgres\PgSqlCastRenderer());
        self::assertSame('SELECT CAST(1 AS INTEGER) AS "id", NOW() AS "created_at"', $cast->projection(['id' => '1', 'created_at' => 'NOW()'], ['id' => new \ZtdQuery\Schema\ColumnType(\ZtdQuery\Schema\ColumnTypeFamily::INTEGER, 'INTEGER')]));
    }
}
