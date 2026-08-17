<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\Postgres\PgSqlMergeActionKind;
use ZtdQuery\Platform\Postgres\PgSqlMergeClause;
use ZtdQuery\Platform\Postgres\PgSqlMergeMatchKind;
use ZtdQuery\Platform\Postgres\PgSqlMergeParser;
use ZtdQuery\Platform\Postgres\PgSqlMergeStatement;

#[CoversClass(PgSqlMergeParser::class)]
#[UsesClass(PgSqlMergeActionKind::class)]
#[UsesClass(PgSqlMergeClause::class)]
#[UsesClass(PgSqlMergeMatchKind::class)]
#[UsesClass(PgSqlMergeStatement::class)]
final class PgSqlMergeParserTest extends TestCase
{
    public function testParsesMatchedUpdateAndNotMatchedInsert(): void
    {
        $statement = (new PgSqlMergeParser())->parse(
            'MERGE INTO target AS t USING source AS s ON t.id = s.id '
            . 'WHEN MATCHED AND s.enabled THEN UPDATE SET name = s.name, value = s.value '
            . 'WHEN NOT MATCHED THEN INSERT (id, name, value) VALUES (s.id, s.name, $1);',
        );

        self::assertSame('target', $statement->targetTable);
        self::assertSame('target', $statement->targetSql);
        self::assertSame('t', $statement->targetAlias);
        self::assertSame('source AS s', $statement->sourceSql);
        self::assertSame('t.id = s.id', $statement->joinConditionSql);
        self::assertCount(2, $statement->clauses);
        self::assertSame(PgSqlMergeMatchKind::Matched, $statement->clauses[0]->matchKind);
        self::assertSame('s.enabled', $statement->clauses[0]->conditionSql);
        self::assertSame(PgSqlMergeActionKind::Update, $statement->clauses[0]->actionKind);
        self::assertSame(['name' => 's.name', 'value' => 's.value'], $statement->clauses[0]->assignments);
        self::assertSame(PgSqlMergeMatchKind::NotMatched, $statement->clauses[1]->matchKind);
        self::assertSame(PgSqlMergeActionKind::Insert, $statement->clauses[1]->actionKind);
        self::assertSame(['id', 'name', 'value'], $statement->clauses[1]->insertColumns);
        self::assertSame(['s.id', 's.name', '$1'], $statement->clauses[1]->insertValues);
    }

    public function testParsesCteQualifiedTargetJoinSourceAndQuotedAlias(): void
    {
        $statement = (new PgSqlMergeParser())->parse(
            'WITH incoming AS (SELECT 1 AS id) '
            . 'MERGE INTO ONLY public."Target" * AS "T" '
            . 'USING incoming JOIN flags ON flags.id = incoming.id AS source '
            . 'ON "T".id = source.id '
            . 'WHEN MATCHED AND CASE WHEN source.id > 0 THEN TRUE ELSE FALSE END '
            . 'THEN DELETE '
            . 'WHEN MATCHED THEN DO NOTHING',
        );

        self::assertSame('Target', $statement->targetTable);
        self::assertSame('public."Target"', $statement->targetSql);
        self::assertSame('T', $statement->targetAlias);
        self::assertSame('incoming JOIN flags ON flags.id = incoming.id AS source', $statement->sourceSql);
        self::assertSame('"T".id = source.id', $statement->joinConditionSql);
        self::assertSame(PgSqlMergeActionKind::Delete, $statement->clauses[0]->actionKind);
        self::assertSame('CASE WHEN source.id > 0 THEN TRUE ELSE FALSE END', $statement->clauses[0]->conditionSql);
        self::assertSame(PgSqlMergeActionKind::DoNothing, $statement->clauses[1]->actionKind);
    }

    public function testParsesValuesSourceAndDefaultValues(): void
    {
        $statement = (new PgSqlMergeParser())->parse(
            'MERGE users USING (VALUES ($1, $2)) AS s(id, name) ON users.id = s.id '
            . 'WHEN NOT MATCHED AND s.name IS NOT NULL THEN INSERT DEFAULT VALUES',
        );

        self::assertSame('users', $statement->targetAlias);
        self::assertSame('(VALUES ($1, $2)) AS s(id, name)', $statement->sourceSql);
        self::assertSame('s.name IS NOT NULL', $statement->clauses[0]->conditionSql);
        self::assertSame([], $statement->clauses[0]->insertColumns);
        self::assertSame([], $statement->clauses[0]->insertValues);
    }

    public function testParsesDefaultUpdateAndInsertWithoutColumnList(): void
    {
        $statement = (new PgSqlMergeParser())->parse(
            'MERGE INTO users u USING source s ON u.id = s.id '
            . 'WHEN MATCHED THEN UPDATE SET name = DEFAULT '
            . 'WHEN NOT MATCHED THEN INSERT VALUES (s.id, s.name)',
        );

        self::assertSame(['name' => 'DEFAULT'], $statement->clauses[0]->assignments);
        self::assertSame([], $statement->clauses[1]->insertColumns);
        self::assertSame(['s.id', 's.name'], $statement->clauses[1]->insertValues);
    }

    public function testFoldsUnquotedTargetAliasAndAssignmentIdentifiers(): void
    {
        $statement = (new PgSqlMergeParser())->parse(
            'MERGE INTO Users AS Target USING source ON Target.ID = source.id '
            . 'WHEN MATCHED THEN UPDATE SET Name = source.name',
        );

        self::assertSame('users', $statement->targetTable);
        self::assertSame('target', $statement->targetAlias);
        self::assertSame(['name' => 'source.name'], $statement->clauses[0]->assignments);
    }

    #[TestWith(['SELECT 1'])]
    #[TestWith(['MERGE INTO users USING source WHEN MATCHED THEN DELETE'])]
    #[TestWith(['MERGE INTO users USING source ON users.id = source.id'])]
    #[TestWith(['MERGE INTO users USING source ON users.id = source.id WHEN MATCHED BY SOURCE THEN DELETE'])]
    #[TestWith(['MERGE INTO users USING source ON users.id = source.id WHEN MATCHED THEN UPDATE (name, value) = (source.name, source.value)'])]
    #[TestWith(['MERGE INTO users USING source ON users.id = source.id WHEN NOT MATCHED THEN INSERT (id) VALUES (source.id, source.name)'])]
    #[TestWith(['MERGE INTO users USING source ON users.id = source.id WHEN MATCHED THEN INSERT (id) VALUES (source.id)'])]
    #[TestWith(['MERGE INTO users USING source ON users.id = source.id WHEN NOT MATCHED THEN DELETE'])]
    #[TestWith(['MERGE INTO users USING source ON users.id = source.id WHEN MATCHED THEN DELETE RETURNING *'])]
    #[TestWith(['MERGE INTO "" USING source ON TRUE WHEN MATCHED THEN DELETE'])]
    #[TestWith(['MERGE INTO users USING source ON users.id = source.id WHEN MATCHED THEN UPDATE SET name = source.name, Name = source.other_name'])]
    #[TestWith(['MERGE INTO users USING source ON users.id = source.id WHEN NOT MATCHED THEN INSERT (id, ID) VALUES (1, 2)'])]
    public function testRejectsMalformedOrUnsupportedMergeShapes(string $sql): void
    {
        $this->expectException(UnsupportedSqlException::class);

        (new PgSqlMergeParser())->parse($sql);
    }
}
