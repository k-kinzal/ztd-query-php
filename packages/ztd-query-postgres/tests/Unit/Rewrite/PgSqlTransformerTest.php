<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlCastRenderer;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlIdentifierQuoter;
use ZtdQuery\Platform\Postgres\Parse\PgSqlParser;
use ZtdQuery\Platform\Postgres\Parse\PgSqlWithPrefix;
use ZtdQuery\Platform\Postgres\Rewrite\PgSqlTransformer;
use ZtdQuery\Platform\Postgres\Transformer\DeleteTransformer;
use ZtdQuery\Platform\Postgres\Transformer\InsertTransformer;
use ZtdQuery\Platform\Postgres\Transformer\MergeTransformer;
use ZtdQuery\Platform\Postgres\Transformer\SelectTransformer;
use ZtdQuery\Platform\Postgres\Transformer\UpdateTransformer;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;
use ZtdQuery\Schema\IdentityGenerationStrategy;

#[CoversClass(PgSqlTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parse\PgSqlSelectRelationParser::class)]
#[UsesClass(PgSqlParser::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Dialect\PostgreSqlLexicalMasker::class)]
#[UsesClass(SelectTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parse\PgSqlTableSampleParser::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Rewrite\PgSqlTableSampleRewriter::class)]
#[UsesClass(InsertTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Transformer\InsertRowRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Transformer\InsertSelectRenderer::class)]
#[UsesClass(MergeTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Parse\PgSqlMergeParser::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Statement\PgSqlMergeStatement::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Statement\PgSqlMergeClause::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Statement\PgSqlMergeMatchKind::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Statement\PgSqlMergeActionKind::class)]
#[UsesClass(UpdateTransformer::class)]
#[UsesClass(DeleteTransformer::class)]
#[UsesClass(PgSqlCastRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Dialect\PgSqlValueRenderer::class)]
#[UsesClass(PgSqlIdentifierQuoter::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Rewrite\PgSqlCteShadowComposer::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Rewrite\PgSqlNativeUpsertProjector::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Rewrite\PgSqlGeneratedColumnProjector::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile::class)]
#[UsesClass(PgSqlWithPrefix::class)]
final class PgSqlTransformerTest extends TestCase
{
    public function testTransformSelectDelegatesToSelectTransformer(): void
    {
        $parser = new PgSqlParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new PgSqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $result = $transformer->transform('SELECT * FROM users', ['users' => ['alias' => '"users"', 'rows' => [['id' => 1, 'name' => 'Alice']], 'columns' => ['id', 'name'], 'columnTypes' => ['id' => new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INTEGER'), 'name' => new ColumnDeclaration(ColumnTypeFamily::STRING, 'TEXT')]]]);
        self::assertStringContainsString('WITH', $result);
    }

    public function testTransformInsertDelegatesToInsertTransformer(): void
    {
        $parser = new PgSqlParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new PgSqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $result = $transformer->transform("INSERT INTO users (id, name) VALUES (1, 'Alice')", ['users' => ['alias' => '"users"', 'rows' => [], 'columns' => ['id', 'name'], 'columnTypes' => ['id' => new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INTEGER'), 'name' => new ColumnDeclaration(ColumnTypeFamily::STRING, 'TEXT')]]]);
        self::assertNotEmpty($result);
    }

    public function testTransformUpdateDelegatesToUpdateTransformer(): void
    {
        $parser = new PgSqlParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new PgSqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $result = $transformer->transform("UPDATE users SET name = 'Bob' WHERE id = 1", ['users' => ['alias' => '"users"', 'rows' => [['id' => 1, 'name' => 'Alice']], 'columns' => ['id', 'name'], 'columnTypes' => ['id' => new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INTEGER'), 'name' => new ColumnDeclaration(ColumnTypeFamily::STRING, 'TEXT')]]]);
        self::assertNotEmpty($result);
    }

    public function testTransformDeleteDelegatesToDeleteTransformer(): void
    {
        $parser = new PgSqlParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new PgSqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $result = $transformer->transform('DELETE FROM users WHERE id = 1', ['users' => ['alias' => '"users"', 'rows' => [['id' => 1, 'name' => 'Alice']], 'columns' => ['id', 'name'], 'columnTypes' => ['id' => new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INTEGER'), 'name' => new ColumnDeclaration(ColumnTypeFamily::STRING, 'TEXT')]]]);
        self::assertNotEmpty($result);
    }

    public function testTransformMergeDelegatesToMergeTransformer(): void
    {
        $parser = new PgSqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new PgSqlTransformer(
            $parser,
            $selectTransformer,
            new InsertTransformer($parser, $selectTransformer),
            new UpdateTransformer($parser, $selectTransformer),
            new DeleteTransformer($parser, $selectTransformer),
        );
        $result = $transformer->transform(
            'MERGE INTO users u USING source s ON u.id = s.id WHEN MATCHED THEN DELETE',
            [
                'users' => [
                    'rows' => [['id' => 1]],
                    'columns' => ['id'],
                    'columnTypes' => ['id' => new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INTEGER')],
                ],
                'source' => [
                    'rows' => [['id' => 1]],
                    'columns' => ['id'],
                    'columnTypes' => ['id' => new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INTEGER')],
                ],
            ],
        );

        self::assertStringContainsString('WHERE NOT (EXISTS', $result);
        $transformer->commitRewriteState();
    }

    public function testCommitRewriteStateCommitsGeneratedIdentityValues(): void
    {
        $parser = new PgSqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new PgSqlTransformer(
            $parser,
            $selectTransformer,
            new InsertTransformer($parser, $selectTransformer),
            new UpdateTransformer($parser, $selectTransformer),
            new DeleteTransformer($parser, $selectTransformer),
        );
        $tables = ['users' => [
            'rows' => [],
            'columns' => ['id', 'name'],
            'columnTypes' => [],
            'identityStrategies' => ['id' => IdentityGenerationStrategy::Sequence],
        ]];

        $first = $transformer->transform("INSERT INTO users (name) VALUES ('first')", $tables);
        $transformer->commitRewriteState();
        $second = $transformer->transform("INSERT INTO users (name) VALUES ('second')", $tables);

        self::assertStringContainsString('1 AS "id"', $first);
        self::assertStringContainsString('2 AS "id"', $second);
    }

    public function testTransformUnsupportedStatementThrows(): void
    {
        $parser = new PgSqlParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new PgSqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);
        $this->expectException(UnsupportedSqlException::class);
        $transformer->transform('CREATE TABLE test (id INTEGER)', []);
    }
    public function testMergeTransformerAnswersTheSameTransformerEveryTime(): void
    {
        $parser = new PgSqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new PgSqlTransformer(
            $parser,
            $selectTransformer,
            new InsertTransformer($parser, $selectTransformer),
            new UpdateTransformer($parser, $selectTransformer),
            new DeleteTransformer($parser, $selectTransformer),
        );

        self::assertSame($transformer->mergeTransformer(), $transformer->mergeTransformer());
    }

}
