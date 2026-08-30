<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlCastRenderer;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlIdentifierQuoter;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlValueRenderer;
use ZtdQuery\Platform\Postgres\Parse\PgSqlMergeParser;
use ZtdQuery\Platform\Postgres\Parse\PgSqlTableSampleParser;
use ZtdQuery\Platform\Postgres\Parse\PgSqlWithPrefix;
use ZtdQuery\Platform\Postgres\Rewrite\PgSqlTableSampleRewriter;
use ZtdQuery\Platform\Postgres\Statement\PgSqlMergeActionKind;
use ZtdQuery\Platform\Postgres\Statement\PgSqlMergeClause;
use ZtdQuery\Platform\Postgres\Statement\PgSqlMergeMatchKind;
use ZtdQuery\Platform\Postgres\Statement\PgSqlMergeStatement;
use ZtdQuery\Platform\Postgres\Transformer\MergeTransformer;
use ZtdQuery\Platform\Postgres\Transformer\SelectTransformer;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;
use ZtdQuery\Schema\Key\IdentityGenerationStrategy;

#[CoversClass(MergeTransformer::class)]
#[UsesClass(PgSqlCastRenderer::class)]
#[UsesClass(PgSqlIdentifierQuoter::class)]
#[UsesClass(PgSqlMergeActionKind::class)]
#[UsesClass(PgSqlMergeClause::class)]
#[UsesClass(PgSqlMergeMatchKind::class)]
#[UsesClass(PgSqlMergeParser::class)]
#[UsesClass(PgSqlMergeStatement::class)]
#[UsesClass(PgSqlTableSampleParser::class)]
#[UsesClass(PgSqlTableSampleRewriter::class)]
#[UsesClass(PgSqlValueRenderer::class)]
#[UsesClass(SelectTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Rewrite\PgSqlCteShadowComposer::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Rewrite\PgSqlGeneratedColumnProjector::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Transformer\InsertSelectRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Transformer\InsertRowRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parse\PgSqlSelectRelationParser::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile::class)]
#[UsesClass(PgSqlWithPrefix::class)]
final class MergeTransformerTest extends TestCase
{
    public function testTransformsMixedUpdateAndInsertIntoCompleteTargetState(): void
    {
        $transformer = new MergeTransformer(new PgSqlMergeParser(), new SelectTransformer());
        $result = $transformer->transform(
            'MERGE INTO target AS t USING source AS s ON t.id = s.id '
            . 'WHEN MATCHED THEN UPDATE SET name = s.name '
            . 'WHEN NOT MATCHED THEN INSERT (id, name) VALUES (s.id, $1)',
            [
                'target' => [
                    'rows' => [['id' => 1, 'name' => 'old']],
                    'columns' => ['id', 'name'],
                    'columnTypes' => [
                        'id' => new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INTEGER'),
                        'name' => new ColumnDeclaration(ColumnTypeFamily::STRING, 'TEXT'),
                    ],
                ],
                'source' => [
                    'rows' => [['id' => 1, 'name' => 'updated'], ['id' => 2, 'name' => 'inserted']],
                    'columns' => ['id', 'name'],
                    'columnTypes' => [
                        'id' => new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INTEGER'),
                        'name' => new ColumnDeclaration(ColumnTypeFamily::STRING, 'TEXT'),
                    ],
                ],
            ],
        );

        self::assertStringContainsString('"target" AS MATERIALIZED', $result);
        self::assertStringContainsString('"source" AS MATERIALIZED', $result);
        self::assertStringContainsString('WHERE NOT (EXISTS', $result);
        self::assertStringContainsString('JOIN source AS s ON (t.id = s.id)', $result);
        self::assertStringContainsString('$1 AS "name"', $result);
        self::assertStringContainsString('WHERE NOT EXISTS', $result);
        self::assertSame(2, substr_count($result, 'UNION ALL'));
        self::assertSame(
            '8deccc308f3216f4695d5d03e93ee1a0deb7cbfe4ca451102125f6b2b691356c',
            hash('sha256', $result),
        );
    }

    public function testPreservesFirstEligibleClauseOrderingAndCtePrefix(): void
    {
        $transformer = new MergeTransformer(new PgSqlMergeParser(), new SelectTransformer());
        $result = $transformer->transform(
            'WITH incoming AS (SELECT 1 AS id, TRUE AS skip) '
            . 'MERGE INTO target t USING incoming s ON t.id = s.id '
            . 'WHEN MATCHED AND s.skip THEN DO NOTHING '
            . 'WHEN MATCHED THEN DELETE',
            [
                'target' => [
                    'rows' => [['id' => 1]],
                    'columns' => ['id'],
                    'columnTypes' => ['id' => new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INTEGER')],
                ],
            ],
        );

        self::assertStringContainsString('WITH "target" AS MATERIALIZED', $result);
        self::assertStringContainsString('incoming AS (SELECT 1 AS id, TRUE AS skip)', $result);
        self::assertStringContainsString('COALESCE((s.skip), FALSE)', $result);
        self::assertStringContainsString('AND NOT (COALESCE((s.skip), FALSE))', $result);
        self::assertSame(
            '7c6934f735d990a45c2488968dfc74fb82557e87ef5bdaab9cba5e07f9de691e',
            hash('sha256', $result),
        );
    }

    public function testProjectsDefaultsAndGeneratedColumns(): void
    {
        $transformer = new MergeTransformer(new PgSqlMergeParser(), new SelectTransformer());
        $result = $transformer->transform(
            'MERGE INTO totals t USING (VALUES (1, 3)) AS s(id, amount) ON t.id = s.id '
            . 'WHEN MATCHED THEN UPDATE SET amount = DEFAULT '
            . 'WHEN NOT MATCHED THEN INSERT (id, amount) VALUES (s.id, DEFAULT)',
            [
                'totals' => [
                    'rows' => [],
                    'columns' => ['id', 'amount', 'doubled'],
                    'columnTypes' => [
                        'id' => new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INTEGER'),
                        'amount' => new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INTEGER'),
                        'doubled' => new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INTEGER'),
                    ],
                    'columnDefaults' => ['amount' => '7'],
                    'generatedExpressions' => ['doubled' => '"amount" * 2'],
                ],
            ],
        );

        self::assertStringContainsString('7 AS "amount"', $result);
        self::assertStringContainsString('"amount" * 2 AS "doubled"', $result);
        self::assertSame(
            'd743861318ec598cf546902c70cb11b2c0be5b6c42e6fcc341afd99ee9d9fa34',
            hash('sha256', $result),
        );
    }

    public function testRejectsUnknownUpdateColumn(): void
    {
        $transformer = new MergeTransformer(new PgSqlMergeParser(), new SelectTransformer());

        $this->expectException(UnsupportedSqlException::class);

        $transformer->transform(
            'MERGE INTO target t USING source s ON t.id = s.id '
            . 'WHEN MATCHED THEN UPDATE SET missing = s.value',
            [
                'target' => ['rows' => [], 'columns' => ['id'], 'columnTypes' => []],
                'source' => ['rows' => [], 'columns' => ['id', 'value'], 'columnTypes' => []],
            ],
        );
    }

    public function testRejectsUnknownTargetAndViewTarget(): void
    {
        $transformer = new MergeTransformer(new PgSqlMergeParser(), new SelectTransformer());
        $sql = 'MERGE INTO target t USING source s ON t.id = s.id WHEN MATCHED THEN DELETE';

        try {
            $transformer->transform($sql, []);
            self::fail('Unknown target must be rejected');
        } catch (UnsupportedSqlException $exception) {
            self::assertStringContainsString('Cannot resolve MERGE target schema', $exception->getMessage());
        }

        $this->expectException(UnsupportedSqlException::class);
        $this->expectExceptionMessage('Cannot resolve MERGE target schema');
        $transformer->transform($sql, ['target' => ['viewSql' => 'SELECT 1']]);
    }

    public function testRejectsNonListColumnMetadata(): void
    {
        $transformer = new MergeTransformer(new PgSqlMergeParser(), new SelectTransformer());

        $this->expectException(UnsupportedSqlException::class);
        $this->expectExceptionMessage('MERGE target columns must preserve declaration order');

        $transformer->transform(
            'MERGE INTO target USING source ON TRUE WHEN MATCHED THEN DELETE',
            ['target' => ['rows' => [], 'columns' => [2 => 'id'], 'columnTypes' => []]],
        );
    }

    public function testProjectsDefaultValuesAndIdentity(): void
    {
        $transformer = new MergeTransformer(new PgSqlMergeParser(), new SelectTransformer());
        $result = $transformer->transform(
            'MERGE INTO users target USING source ON FALSE '
            . 'WHEN NOT MATCHED THEN INSERT DEFAULT VALUES',
            [
                'users' => [
                    'rows' => [],
                    'columns' => ['id', 'name'],
                    'columnTypes' => [],
                    'columnDefaults' => ['name' => "'anonymous'"],
                    'identityStrategies' => ['id' => IdentityGenerationStrategy::Sequence],
                ],
            ],
        );

        self::assertStringContainsString('1 + ROW_NUMBER() OVER () - 1 AS "id"', $result);
        self::assertStringContainsString("'anonymous' AS \"name\"", $result);
    }

    public function testRejectsUnknownInsertColumn(): void
    {
        $transformer = new MergeTransformer(new PgSqlMergeParser(), new SelectTransformer());

        $this->expectException(UnsupportedSqlException::class);
        $this->expectExceptionMessage('MERGE INSERT references an unknown target column');

        $transformer->transform(
            'MERGE INTO users target USING source ON FALSE '
            . 'WHEN NOT MATCHED THEN INSERT (missing) VALUES (source.value)',
            ['users' => ['rows' => [], 'columns' => ['id'], 'columnTypes' => []]],
        );
    }

    public function testExplicitIdentityValueIsNotGenerated(): void
    {
        $transformer = new MergeTransformer(new PgSqlMergeParser(), new SelectTransformer());
        $result = $transformer->transform(
            'MERGE INTO users target USING source ON FALSE '
            . 'WHEN NOT MATCHED THEN INSERT (id, name) VALUES (source.id, source.name)',
            [
                'users' => [
                    'rows' => [],
                    'columns' => ['id', 'name'],
                    'columnTypes' => [],
                    'identityStrategies' => ['id' => IdentityGenerationStrategy::Sequence],
                ],
            ],
        );

        self::assertStringContainsString('source.id AS "id"', $result);
        self::assertStringNotContainsString('ROW_NUMBER()', $result);
    }

    public function testDefaultIdentityValueIsGenerated(): void
    {
        $transformer = new MergeTransformer(new PgSqlMergeParser(), new SelectTransformer());
        $result = $transformer->transform(
            'MERGE INTO users target USING source ON FALSE '
            . 'WHEN NOT MATCHED THEN INSERT (id, name) VALUES (DEFAULT, source.name)',
            [
                'users' => [
                    'rows' => [],
                    'columns' => ['id', 'name'],
                    'columnTypes' => [],
                    'identityStrategies' => ['id' => IdentityGenerationStrategy::Sequence],
                ],
            ],
        );

        self::assertStringContainsString('1 + ROW_NUMBER() OVER () - 1 AS "id"', $result);
        self::assertStringContainsString('source.name AS "name"', $result);
    }

    public function testRejectsChildPartitionTargetBeforeStateCanBeTruncated(): void
    {
        $transformer = new MergeTransformer(new PgSqlMergeParser(), new SelectTransformer());

        $this->expectException(UnsupportedSqlException::class);

        $transformer->transform(
            'MERGE INTO child c USING source s ON c.id = s.id WHEN MATCHED THEN DELETE',
            [
                'child' => [
                    'rows' => [['id' => 1]],
                    'columns' => ['id'],
                    'columnTypes' => [],
                    'storageTable' => 'parent',
                ],
            ],
        );
    }

    public function testEffectiveConditionsAccountsForTheClausesBeforeEachOne(): void
    {
        $statement = (new PgSqlMergeParser())->parse(
            'MERGE INTO t USING s ON t.id = s.id '
            . 'WHEN MATCHED AND t.x = 1 THEN DELETE '
            . 'WHEN MATCHED THEN DO NOTHING',
        );

        self::assertCount(2, (new MergeTransformer(new PgSqlMergeParser(), new SelectTransformer()))
            ->effectiveConditions($statement));
    }

    public function testUnchangedRowsReadsTheRowsTheStatementLeavesAlone(): void
    {
        $transformer = new MergeTransformer(new PgSqlMergeParser(), new SelectTransformer());
        $statement = (new PgSqlMergeParser())->parse(
            'MERGE INTO t USING s ON t.id = s.id WHEN MATCHED THEN DELETE',
        );

        $sql = $transformer->unchangedRows($statement, ['id'], $transformer->effectiveConditions($statement));

        self::assertStringContainsString('SELECT', $sql);
    }

    public function testUpdatedRowsReadsTheRowsTheStatementChanges(): void
    {
        $transformer = new MergeTransformer(new PgSqlMergeParser(), new SelectTransformer());
        $sql = 'MERGE INTO t USING s ON t.id = s.id WHEN MATCHED THEN UPDATE SET x = 1';
        $statement = (new PgSqlMergeParser())->parse($sql);
        $clause = $statement->clauses[0];

        self::assertStringContainsString(
            'SELECT',
            $transformer->updatedRows($sql, $statement, $clause, ['id', 'x'], [], 'TRUE'),
        );
    }

    public function testInsertedRowsReadsTheRowsTheStatementWritesThatWereNotThere(): void
    {
        $transformer = new MergeTransformer(new PgSqlMergeParser(), new SelectTransformer());
        $sql = 'MERGE INTO t USING s ON t.id = s.id WHEN NOT MATCHED THEN INSERT (id) VALUES (1)';
        $statement = (new PgSqlMergeParser())->parse($sql);
        $clause = $statement->clauses[0];

        self::assertStringContainsString(
            'SELECT',
            $transformer->insertedRows($sql, $statement, $clause, ['id'], [], [], [], 'TRUE'),
        );
    }

}
