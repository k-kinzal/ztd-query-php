<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer\Cte;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Transformer\Cte\RowSourceRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlCastRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlGeneratedColumnProjector::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlIdentifierQuoter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlValueRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Sql\CastTypeMapper::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Sql\NativeCastTarget::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Sql\Value\BinaryStream::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Sql\Value\LiteralText::class)]
final class RowSourceRendererTest extends TestCase
{
    public function testGenerateCte(): void
    {
        self::assertSame('"users" AS MATERIALIZED (SELECT CAST(1 AS INTEGER) AS "id", CAST(\'Ada\' AS TEXT) AS "name")', (new \ZtdQuery\Platform\Postgres\Transformer\Cte\RowSourceRenderer(new \ZtdQuery\Platform\Postgres\PgSqlCastRenderer(), new \ZtdQuery\Platform\Postgres\PgSqlGeneratedColumnProjector(), new \ZtdQuery\Platform\Postgres\PgSqlIdentifierQuoter(), new \ZtdQuery\Platform\Postgres\PgSqlValueRenderer()))->generateCte('users', [['id' => 1, 'name' => 'Ada']], ['id', 'name'], [], []));

        self::assertSame('"users" AS MATERIALIZED (SELECT CAST(NULL AS INTEGER) AS "id" WHERE FALSE)', (new \ZtdQuery\Platform\Postgres\Transformer\Cte\RowSourceRenderer(new \ZtdQuery\Platform\Postgres\PgSqlCastRenderer(), new \ZtdQuery\Platform\Postgres\PgSqlGeneratedColumnProjector(), new \ZtdQuery\Platform\Postgres\PgSqlIdentifierQuoter(), new \ZtdQuery\Platform\Postgres\PgSqlValueRenderer()))->generateCte('users', [], ['id'], ['id' => new \ZtdQuery\Schema\ColumnType(\ZtdQuery\Schema\ColumnTypeFamily::INTEGER, 'INTEGER')], []));
    }

    public function testGenerateMultiRowSource(): void
    {
        self::assertSame('
  SELECT * FROM (VALUES
    (CAST(1 AS INTEGER)),
    (CAST(2 AS INTEGER))
  ) AS t("id")
', (new \ZtdQuery\Platform\Postgres\Transformer\Cte\RowSourceRenderer(new \ZtdQuery\Platform\Postgres\PgSqlCastRenderer(), new \ZtdQuery\Platform\Postgres\PgSqlGeneratedColumnProjector(), new \ZtdQuery\Platform\Postgres\PgSqlIdentifierQuoter(), new \ZtdQuery\Platform\Postgres\PgSqlValueRenderer()))->generateMultiRowSource([['id' => 1], ['id' => 2]], ['id'], []));
    }

    public function testWrapCte(): void
    {
        self::assertSame('"users" AS MATERIALIZED (SELECT 1 AS id)', (new \ZtdQuery\Platform\Postgres\Transformer\Cte\RowSourceRenderer(new \ZtdQuery\Platform\Postgres\PgSqlCastRenderer(), new \ZtdQuery\Platform\Postgres\PgSqlGeneratedColumnProjector(), new \ZtdQuery\Platform\Postgres\PgSqlIdentifierQuoter(), new \ZtdQuery\Platform\Postgres\PgSqlValueRenderer()))->wrapCte('"users"', 'SELECT 1 AS id', ['id'], []));
    }

    public function testFormatValue(): void
    {
        self::assertSame('NULL', (new \ZtdQuery\Platform\Postgres\Transformer\Cte\RowSourceRenderer(new \ZtdQuery\Platform\Postgres\PgSqlCastRenderer(), new \ZtdQuery\Platform\Postgres\PgSqlGeneratedColumnProjector(), new \ZtdQuery\Platform\Postgres\PgSqlIdentifierQuoter(), new \ZtdQuery\Platform\Postgres\PgSqlValueRenderer()))->formatValue(null));

        self::assertSame('TRUE', (new \ZtdQuery\Platform\Postgres\Transformer\Cte\RowSourceRenderer(new \ZtdQuery\Platform\Postgres\PgSqlCastRenderer(), new \ZtdQuery\Platform\Postgres\PgSqlGeneratedColumnProjector(), new \ZtdQuery\Platform\Postgres\PgSqlIdentifierQuoter(), new \ZtdQuery\Platform\Postgres\PgSqlValueRenderer()))->formatValue(true));

        self::assertSame('CAST(\'O\'\'Reilly\' AS TEXT)', (new \ZtdQuery\Platform\Postgres\Transformer\Cte\RowSourceRenderer(new \ZtdQuery\Platform\Postgres\PgSqlCastRenderer(), new \ZtdQuery\Platform\Postgres\PgSqlGeneratedColumnProjector(), new \ZtdQuery\Platform\Postgres\PgSqlIdentifierQuoter(), new \ZtdQuery\Platform\Postgres\PgSqlValueRenderer()))->formatValue('O\'Reilly'));

        self::assertSame('CAST(42 AS INTEGER)', (new \ZtdQuery\Platform\Postgres\Transformer\Cte\RowSourceRenderer(new \ZtdQuery\Platform\Postgres\PgSqlCastRenderer(), new \ZtdQuery\Platform\Postgres\PgSqlGeneratedColumnProjector(), new \ZtdQuery\Platform\Postgres\PgSqlIdentifierQuoter(), new \ZtdQuery\Platform\Postgres\PgSqlValueRenderer()))->formatValue(42));
    }

    public function testRenderFallbackNullCast(): void
    {
        self::assertSame('CAST(NULL AS TEXT)', (new \ZtdQuery\Platform\Postgres\Transformer\Cte\RowSourceRenderer(new \ZtdQuery\Platform\Postgres\PgSqlCastRenderer(), new \ZtdQuery\Platform\Postgres\PgSqlGeneratedColumnProjector(), new \ZtdQuery\Platform\Postgres\PgSqlIdentifierQuoter(), new \ZtdQuery\Platform\Postgres\PgSqlValueRenderer()))->renderFallbackNullCast());
    }
    public function testDeclaredSourceRendersEmptyTypedAndSingleRowSources(): void
    {
        $renderer = new \ZtdQuery\Platform\Postgres\Transformer\Cte\RowSourceRenderer(new \ZtdQuery\Platform\Postgres\PgSqlCastRenderer(), new \ZtdQuery\Platform\Postgres\PgSqlGeneratedColumnProjector(), new \ZtdQuery\Platform\Postgres\PgSqlIdentifierQuoter(), new \ZtdQuery\Platform\Postgres\PgSqlValueRenderer());
        self::assertSame('SELECT CAST(NULL AS TEXT) AS "name" WHERE FALSE', $renderer->declaredSource([], ['name'], []));
        self::assertSame("SELECT CAST('Ada' AS TEXT) AS \"name\"", $renderer->declaredSource([['name' => 'Ada']], ['name'], []));
    }
}
