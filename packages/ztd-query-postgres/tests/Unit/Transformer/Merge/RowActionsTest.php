<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer\Merge;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Transformer\Merge\RowActions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Cte\HeaderParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Cte\IdentifierReferences::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Cte\PrefixMerge::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Cte\ShadowDependencies::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Merge\ActionClause::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Merge\BranchTokens::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Merge\RelationTarget::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Merge\StatementParts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Relation\FromClause::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Relation\RelationReference::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlCteShadowComposer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlIdentifierQuoter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlMergeActionKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlMergeClause::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlMergeMatchKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlMergeParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlMergeStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlSelectRelationParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Transformer\InsertRowRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Transformer\InsertSelectRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Transformer\Merge\MatchConditions::class)]
final class RowActionsTest extends TestCase
{
    public function testUnchangedRows(): void
    {
        $sql = 'MERGE INTO users u USING incoming s ON u.id = s.id WHEN MATCHED AND s.active THEN UPDATE SET name = s.name WHEN NOT MATCHED THEN INSERT (id, name) VALUES (s.id, s.name)';
        $statement = (new \ZtdQuery\Platform\Postgres\PgSqlMergeParser())->parse($sql);

        self::assertSame('SELECT "u"."id" AS "id", "u"."name" AS "name" FROM users AS "u" WHERE NOT (EXISTS (SELECT 1 FROM incoming s WHERE (u.id = s.id) AND (s.active)))', (new \ZtdQuery\Platform\Postgres\Transformer\Merge\RowActions(new \ZtdQuery\Platform\Postgres\Transformer\InsertSelectRenderer(), new \ZtdQuery\Platform\Postgres\PgSqlIdentifierQuoter(), new \ZtdQuery\Platform\Postgres\Transformer\InsertRowRenderer()))->unchangedRows($statement, ['id', 'name'], ['s.active', 'TRUE']));
    }

    public function testUpdatedRows(): void
    {
        $sql = 'MERGE INTO users u USING incoming s ON u.id = s.id WHEN MATCHED AND s.active THEN UPDATE SET name = s.name WHEN NOT MATCHED THEN INSERT (id, name) VALUES (s.id, s.name)';
        $statement = (new \ZtdQuery\Platform\Postgres\PgSqlMergeParser())->parse($sql);

        self::assertSame('SELECT "u"."id" AS "id", s.name AS "name" FROM users AS "u" JOIN incoming s ON (u.id = s.id) WHERE s.active', (new \ZtdQuery\Platform\Postgres\Transformer\Merge\RowActions(new \ZtdQuery\Platform\Postgres\Transformer\InsertSelectRenderer(), new \ZtdQuery\Platform\Postgres\PgSqlIdentifierQuoter(), new \ZtdQuery\Platform\Postgres\Transformer\InsertRowRenderer()))->updatedRows($sql, $statement, $statement->clauses[0], ['id', 'name'], [], 's.active'));
    }

    public function testInsertedRows(): void
    {
        $sql = 'MERGE INTO users u USING incoming s ON u.id = s.id WHEN MATCHED AND s.active THEN UPDATE SET name = s.name WHEN NOT MATCHED THEN INSERT (id, name) VALUES (s.id, s.name)';
        $statement = (new \ZtdQuery\Platform\Postgres\PgSqlMergeParser())->parse($sql);

        self::assertSame('SELECT s.id AS "id", s.name AS "name" FROM incoming s WHERE NOT EXISTS (SELECT 1 FROM users AS "u" WHERE u.id = s.id) AND (TRUE)', (new \ZtdQuery\Platform\Postgres\Transformer\Merge\RowActions(new \ZtdQuery\Platform\Postgres\Transformer\InsertSelectRenderer(), new \ZtdQuery\Platform\Postgres\PgSqlIdentifierQuoter(), new \ZtdQuery\Platform\Postgres\Transformer\InsertRowRenderer()))->insertedRows($sql, $statement, $statement->clauses[1], ['id', 'name'], [], [], [], 'TRUE'));
    }
    public function testModifiedRowsProjectsBothActionKindsInOrder(): void
    {
        $sql = 'MERGE INTO users u USING source s ON u.id = s.id WHEN MATCHED THEN UPDATE SET name = s.name WHEN NOT MATCHED THEN INSERT (id, name) VALUES (s.id, s.name)';
        $statement = (new \ZtdQuery\Platform\Postgres\PgSqlMergeParser())->parse($sql);
        $conditions = (new \ZtdQuery\Platform\Postgres\Transformer\Merge\MatchConditions())->effectiveConditions($statement);
        $actions = new \ZtdQuery\Platform\Postgres\Transformer\Merge\RowActions(new \ZtdQuery\Platform\Postgres\Transformer\InsertSelectRenderer(), new \ZtdQuery\Platform\Postgres\PgSqlIdentifierQuoter(), new \ZtdQuery\Platform\Postgres\Transformer\InsertRowRenderer());
        $rows = $actions->modifiedRows($sql, $statement, ['id', 'name'], [], [], [], $conditions);
        self::assertCount(2, $rows);
        self::assertStringContainsString('s.name AS "name"', $rows[0]);
        self::assertStringContainsString('s.id AS "id"', $rows[1]);
    }
}
