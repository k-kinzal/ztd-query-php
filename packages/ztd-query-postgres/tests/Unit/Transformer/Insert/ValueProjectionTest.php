<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Transformer\Insert\ValueProjection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Conflict\ColumnSet::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Relation\FromClause::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Relation\RelationReference::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\Classification::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\ConflictClause::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\Identifiers::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\InsertSource::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\TableDefinitionClauses::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlCastRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlConflictTarget::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlSelectRelationParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PostgreSqlLexicalMasker::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Sql\CastTypeMapper::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Sql\Lexing\CommentSpan::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Sql\Lexing\QuotedSpan::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Sql\NativeCastTarget::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Transformer\InsertRowRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Transformer\Insert\ExpressionCast::class)]
final class ValueProjectionTest extends TestCase
{
    public function testRenderPreservesSequenceProgressAcrossCommittedProjections(): void
    {
        $allocator = new \ZtdQuery\Rewrite\ShadowIdentityAllocator();
        $allocator->beginProjection();
        $projection = new \ZtdQuery\Platform\Postgres\Transformer\Insert\ValueProjection(new \ZtdQuery\Platform\Postgres\PgSqlParser(), $allocator, new \ZtdQuery\Platform\Postgres\Transformer\InsertRowRenderer(), new \ZtdQuery\Platform\Postgres\PgSqlCastRenderer());
        $table = ['rows' => [['id' => 7]], 'identityStrategies' => ['id' => \ZtdQuery\Schema\IdentityGenerationStrategy::Sequence], 'columnTypes' => ['id' => new \ZtdQuery\Schema\ColumnType(\ZtdQuery\Schema\ColumnTypeFamily::INTEGER, 'INTEGER')]];
        $sql = $projection->render("INSERT INTO users (name) VALUES ('Ada'), ('Bob')", 'users', ['id', 'name'], ['name'], $table);
        self::assertSame("SELECT CAST(1 AS INTEGER) AS \"id\", 'Ada' AS \"name\" UNION ALL SELECT CAST(8 AS INTEGER) AS \"id\", 'Bob' AS \"name\"", $sql);
        $allocator->commitProjection();
        $allocator->beginProjection();
        $next = $projection->render("INSERT INTO users (name) VALUES ('Cy')", 'users', ['id', 'name'], ['name'], $table);
        self::assertStringContainsString('CAST(9 AS INTEGER)', $next);
    }
}
