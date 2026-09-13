<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Postgres\PgSqlIdentifierQuoter;
use ZtdQuery\Platform\Postgres\Transformer\InsertSelectRenderer;

#[CoversClass(InsertSelectRenderer::class)]
#[UsesClass(PgSqlIdentifierQuoter::class)]
#[CoversClass(\ZtdQuery\Platform\Postgres\Transformer\Insert\SelectProjection::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Conflict\ColumnSet::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Cte\HeaderParser::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Cte\IdentifierReferences::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Cte\PrefixMerge::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Cte\ShadowDependencies::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Relation\FromClause::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Relation\RelationReference::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\Classification::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\ConflictClause::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\Identifiers::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\InsertSource::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\TableDefinitionClauses::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlConflictTarget::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlCteShadowComposer::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlLexerProfile::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlParser::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PgSqlSelectRelationParser::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\PostgreSqlLexicalMasker::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Sql\Lexing\CommentSpan::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Sql\Lexing\QuotedSpan::class)]
final class InsertSelectRendererTest extends TestCase
{
    public function testRendersPostgreSqlProjectionFromDialectNeutralPlan(): void
    {
        $sql = (new InsertSelectRenderer())->render(
            'SELECT DISTINCT name, COUNT(*) FROM users GROUP BY name',
            ['id', 'name', 'count', 'status'],
            ['name', 'count'],
            ['status' => "'active'"],
            ['id' => 8],
        );

        self::assertSame(
            'WITH "__ztd_insert_source" ("__ztd_insert_0", "__ztd_insert_1") AS ('
            . 'SELECT DISTINCT name, COUNT(*) FROM users GROUP BY name) '
            . 'SELECT 8 + ROW_NUMBER() OVER () - 1 AS "id", "__ztd_insert_0" AS "name", '
            . '"__ztd_insert_1" AS "count", \'active\' AS "status" FROM "__ztd_insert_source"',
            $sql,
        );
    }

    public function testRenderGeneratedIdentityRendersGeneratedIdentityExpression(): void
    {
        $renderer = new InsertSelectRenderer();

        self::assertSame('1 + ROW_NUMBER() OVER () - 1', $renderer->renderGeneratedIdentity(1));
    }


}
