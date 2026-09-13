<?php

declare(strict_types=1);

namespace Tests\Unit\Rewriting\Transformer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\Sqlite\SqliteCastRenderer;
use ZtdQuery\Platform\Sqlite\SqliteIdentifierQuoter;
use ZtdQuery\Platform\Sqlite\SqliteLexicalMasker;
use ZtdQuery\Platform\Sqlite\SqliteParser;
use ZtdQuery\Platform\Sqlite\Transformer\DeleteTransformer;
use ZtdQuery\Platform\Sqlite\Transformer\InsertTransformer;
use ZtdQuery\Platform\Sqlite\Transformer\SelectTransformer;
use ZtdQuery\Platform\Sqlite\Transformer\SqliteTransformer;
use ZtdQuery\Platform\Sqlite\Transformer\UpdateTransformer;

#[CoversClass(SqliteTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Expression\AssignmentColumnParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Expression\AssignmentParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Expression\ValueListParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Insert\InsertClauseParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Lexing\ExpressionSpan::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Lexing\IdentifierDecoder::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Lexing\LiteralMasker::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Lexing\OpaqueSqlSpan::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Lexing\QuotedSpan::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Lexing\TopLevelKeywordScanner::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Relation\RelationSourceParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Statement\StatementClassifier::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Statement\StatementStructure::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Statement\TargetTableParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Update\UpdateClauseParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Cte\CteDependencies::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Cte\CteHeaderParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Cte\CtePrefixMerger::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Cte\CteReferences::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\FullText\FullTextColumns::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\FullText\MatchExpressionRewriter::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Index\IndexHintTokens::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Rendering\CastTypeMapper::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Rendering\ValueExpressionRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Rendering\ValueLiteralRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\SqlEdits::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Upsert\UpsertConflictPredicate::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Upsert\UpsertExpressionBinder::class)]
#[UsesClass(SqliteCastRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteCteShadowComposer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteFullTextSearchRewriter::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteGeneratedColumnProjector::class)]
#[UsesClass(SqliteIdentifierQuoter::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteIndexHintStripper::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteLexerProfile::class)]
#[UsesClass(SqliteLexicalMasker::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteNativeUpsertProjector::class)]
#[UsesClass(SqliteParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteSelectRelationParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteValueRenderer::class)]
#[UsesClass(DeleteTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Transformer\InsertRowRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Transformer\InsertSelectRenderer::class)]
#[UsesClass(InsertTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Transformer\Insert\InsertProjectionBuilder::class)]
#[UsesClass(SelectTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Transformer\Select\ShadowCteRenderer::class)]
#[UsesClass(UpdateTransformer::class)]
final class SqliteTransformerTest extends TestCase
{
    public function testTransformSelect(): void
    {
        $parser = new SqliteParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);

        $tables = [
            'users' => [
                'rows' => [['id' => 1, 'name' => 'Alice']],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform('SELECT * FROM users', $tables);
        self::assertStringContainsString('WITH', $result);
    }

    public function testTransformInsert(): void
    {
        $parser = new SqliteParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);

        $tables = [
            'users' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform("INSERT INTO users (id, name) VALUES (1, 'Alice')", $tables);
        self::assertStringContainsString('SELECT', $result);
    }

    public function testTransformUpdate(): void
    {
        $parser = new SqliteParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);

        $tables = [
            'users' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform("UPDATE users SET name = 'Bob' WHERE id = 1", $tables);
        self::assertStringContainsString('SELECT', $result);
    }

    public function testTransformDelete(): void
    {
        $parser = new SqliteParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);

        $tables = [
            'users' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform('DELETE FROM users WHERE id = 1', $tables);
        self::assertStringContainsString('SELECT', $result);
    }

    public function testTransformUnsupportedThrows(): void
    {
        $parser = new SqliteParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);

        $this->expectException(UnsupportedSqlException::class);
        $transformer->transform('CREATE TABLE t (id INTEGER)', []);
    }

    public function testTransformEmptyThrows(): void
    {
        $parser = new SqliteParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);

        $this->expectException(UnsupportedSqlException::class);
        $transformer->transform('', []);
    }

    public function testTransformWithEmptyTablesReturnsOriginal(): void
    {
        $parser = new SqliteParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $transformer = new SqliteTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer);

        $result = $transformer->transform('SELECT * FROM users', []);
        self::assertSame('SELECT * FROM users', $result);
    }
    public function testCommitRewriteStateKeepsAllocatedIdentitiesAcrossProjections(): void
    {
        $parser = new SqliteParser();
        $select = new SelectTransformer();
        $transformer = new SqliteTransformer(
            $parser,
            $select,
            new InsertTransformer($parser, $select),
            new UpdateTransformer($parser, $select),
            new DeleteTransformer($parser, $select),
        );
        $tables = ['users' => ['rows' => [], 'columns' => ['id', 'name'], 'columnTypes' => [], 'identityStrategies' => ['id' => \ZtdQuery\Schema\IdentityGenerationStrategy::MaxValue]]];
        $first = $transformer->transform("INSERT INTO users(name) VALUES ('Alice')", $tables);
        $transformer->commitRewriteState();
        $second = $transformer->transform("INSERT INTO users(name) VALUES ('Bob')", $tables);
        self::assertStringContainsString('1 AS "id"', $first);
        self::assertStringContainsString('2 AS "id"', $second);
    }

}
