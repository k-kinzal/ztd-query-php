<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\InsertSelectSourceExtractor;
use ZtdQuery\Platform\MySql\MySqlCastRenderer;
use ZtdQuery\Platform\MySql\MySqlIdentifierQuoter;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\MySqlUpsertAssignmentExtractor;
use ZtdQuery\Platform\MySql\Transformer\InsertTransformer;
use ZtdQuery\Platform\MySql\Transformer\ReplaceTransformer;
use ZtdQuery\Platform\MySql\Transformer\SelectTransformer;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

#[UsesClass(\ZtdQuery\Platform\MySql\MySqlPartitionSelectionRewriter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlSelectRelationParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Parsing\Cte\HeaderParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Parsing\Cte\IdentifierReferences::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Parsing\OptionalInsertIntoNormalizer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Parsing\Relation\ReferenceReader::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Parsing\Upsert\AssignmentReader::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\FullText\ExpressionEditor::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\Partition\SelectionReader::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\Partition\SourceProjection::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\Upsert\ConflictPredicate::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\Upsert\ExpressionBinder::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\Upsert\MetadataColumns::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\Upsert\QualifiedColumn::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Insert\InsertTarget::class)]

#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Insert\ResultProjection::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Select\ExpressionAliaser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Set\OrderRewriter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Set\ValueNormalizer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Shadow\CteRows::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Type\CastTypeResolver::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Type\Enum\RankEdits::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Type\Value\ScalarExpression::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Type\Value\StringCoercion::class)]
#[CoversClass(ReplaceTransformer::class)]
#[UsesClass(InsertTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\InsertRowRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\InsertSelectRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\MySqlSelectListAliaser::class)]
#[UsesClass(InsertSelectSourceExtractor::class)]
#[UsesClass(MySqlParser::class)]
#[UsesClass(MySqlUpsertAssignmentExtractor::class)]
#[UsesClass(SelectTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlFullTextSearchRewriter::class)]
#[UsesClass(MySqlCastRenderer::class)]
#[UsesClass(MySqlIdentifierQuoter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlValueRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlTypeSemantics::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlCteShadowComposer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlNativeUpsertProjector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlGeneratedColumnProjector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[CoversClass(\ZtdQuery\Platform\MySql\Transformer\Insert\ReplaceStatementConverter::class)]
final class ReplaceTransformerTest extends TestCase
{
    public function testTransformReplaceCastsParametersToTargetColumnTypes(): void
    {
        $parser = new MySqlParser();
        $transformer = new ReplaceTransformer($parser, new SelectTransformer());
        $tables = [
            'users' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [
                    'id' => new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
                    'name' => new ColumnType(ColumnTypeFamily::STRING, 'VARCHAR(50)'),
                ],
            ],
        ];

        $result = $transformer->transform('REPLACE INTO users VALUES (?, ?)', $tables);

        self::assertSame('SELECT CAST(? AS SIGNED) AS `id`, CAST(? AS CHAR) AS `name`', $result);
    }

    public function testTransformReplaceWithValues(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO users (id, name) VALUES (1, 'Alice')";
        $tables = [
            'users' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('SELECT', $result);
    }

    public function testTransformReplaceIgnoresLeadingHashComment(): void
    {
        $transformer = new ReplaceTransformer(new MySqlParser(), new SelectTransformer());
        $tables = [
            'users' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform("# REPLACE INTO hidden VALUES (0)\nREPLACE INTO users VALUES (1, 'Alice')", $tables);

        self::assertStringContainsString('1 AS `id`', $result);
        self::assertStringContainsString("'Alice' AS `name`", $result);
    }

    public function testTransformThrowsForNonReplaceStatement(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $this->expectException(UnsupportedSqlException::class);
        $this->expectExceptionMessage('Expected REPLACE statement');
        $transformer->transform('SELECT 1', []);
    }

    public function testTransformThrowsForEmptySql(): void
    {
        $transformer = new ReplaceTransformer(new MySqlParser(), new SelectTransformer());

        $this->expectException(UnsupportedSqlException::class);
        $this->expectExceptionMessage('Expected REPLACE statement');

        $transformer->transform('', []);
    }

    public function testTransformRejectsEmptyReplaceValues(): void
    {
        $transformer = new ReplaceTransformer(new MySqlParser(), new SelectTransformer());

        $this->expectException(UnsupportedSqlException::class);
        $this->expectExceptionMessage('Invalid REPLACE statement');

        $transformer->transform('REPLACE INTO users VALUE ()', [
            'users' => ['rows' => [], 'columns' => ['id'], 'columnTypes' => []],
        ]);
    }

    public function testTransformReplaceWithSetSyntax(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO users SET id = 1, name = 'Alice'";
        $tables = [
            'users' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('1 AS `id`', $result);
        self::assertStringContainsString("'Alice' AS `name`", $result);
    }

    public function testTransformReplaceMultipleRows(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO users (id, name) VALUES (1, 'Alice'), (2, 'Bob')";
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('UNION ALL', $result);
        self::assertStringContainsString('AS `id`', $result);
        self::assertStringContainsString('AS `name`', $result);
    }

    public function testTransformReplaceResolvesColumnsFromContext(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO users VALUES (1, 'Alice')";
        $tables = [
            'users' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('AS `id`', $result);
        self::assertStringContainsString('AS `name`', $result);
    }

    public function testTransformReplaceThrowsWhenNoColumnsResolvable(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO users VALUES (1, 'Alice')";

        $this->expectException(UnsupportedSqlException::class);
        $this->expectExceptionMessage('Cannot determine columns');
        $transformer->transform($sql, []);
    }

    public function testTransformReplaceProjectsCorrectColumnValues(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO users (id, name) VALUES (1, 'Alice')";
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('1 AS `id`', $result);
        self::assertStringContainsString("'Alice' AS `name`", $result);
    }

    public function testTransformReplaceSelectSubquery(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = 'REPLACE INTO archive (id, name) SELECT id, name FROM users';
        $tables = [
            'archive' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('SELECT', $result);
    }

    public function testTransformReplaceValuesExactSelectFormat(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = 'REPLACE INTO t (a, b) VALUES (1, 2)';
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertStringStartsWith('SELECT 1 AS `a`, 2 AS `b`', $result);
    }

    public function testTransformReplaceSetExactFormat(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = 'REPLACE INTO t SET a = 1, b = 2';
        $tables = ['t' => ['columns' => ['a', 'b'], 'columnTypes' => [], 'rows' => []]];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('1 AS `a`', $result);
        self::assertStringContainsString('2 AS `b`', $result);
    }

    public function testTransformReplaceMultiRowsExactUnionFormat(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = 'REPLACE INTO t (a) VALUES (1), (2)';
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('SELECT 1 AS `a` UNION ALL SELECT 2 AS `a`', $result);
    }

    public function testTransformReplaceValuesCountMismatchReturnsInvalidReplace(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = 'REPLACE INTO t (a, b) VALUES (1)';
        $tables = [];

        $this->expectException(UnsupportedSqlException::class);
        $this->expectExceptionMessage('Insert values count does not match column count.');
        $transformer->transform($sql, $tables);
    }

    public function testTransformReplaceSelectSubqueryPassesThrough(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = 'REPLACE INTO archive (id, name) SELECT id, name FROM users WHERE active = 0';
        $tables = [
            'archive' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('SELECT', $result);
        self::assertStringContainsString('FROM users', $result);
        self::assertStringContainsString('WHERE active = 0', $result);
    }

    public function testTransformReplaceSetSelectContainsColumnValues(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = 'REPLACE INTO t SET a = 10, b = 20';
        $tables = ['t' => ['columns' => ['a', 'b'], 'columnTypes' => [], 'rows' => []]];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('10 AS `a`', $result);
        self::assertStringContainsString('20 AS `b`', $result);
    }

    public function testTransformReplaceValuesExactSqlAssertion(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO t (id, name) VALUES (1, 'Alice')";
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertSame("SELECT 1 AS `id`, 'Alice' AS `name`", $result);
    }

    public function testTransformReplaceSetExactSqlAssertion(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO t SET id = 1, name = 'Bob'";
        $tables = [
            't' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('1 AS `id`', $result);
        self::assertStringContainsString("'Bob' AS `name`", $result);
    }

    public function testTransformReplaceMultiRowExactSqlAssertion(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = 'REPLACE INTO t (id) VALUES (1), (2), (3)';
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertSame('SELECT 1 AS `id` UNION ALL SELECT 2 AS `id` UNION ALL SELECT 3 AS `id`', $result);
    }

    public function testTransformReplaceColumnsResolvedFromTableContext(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO t VALUES (1, 'x')";
        $tables = [
            't' => [
                'rows' => [],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('1 AS `id`', $result);
        self::assertStringContainsString("'x' AS `name`", $result);
    }

    public function testTransformReplaceNoColumnsNoContextThrows(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO t VALUES (1, 'x')";

        $this->expectException(UnsupportedSqlException::class);
        $this->expectExceptionMessage('Cannot determine columns');
        $transformer->transform($sql, []);
    }

    public function testTransformReplaceValuesCountMismatchReturnsInvalid(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = 'REPLACE INTO t (id, name) VALUES (1)';

        $this->expectException(UnsupportedSqlException::class);
        $this->expectExceptionMessage('Insert values count does not match column count.');
        $transformer->transform($sql, []);
    }

    public function testTransformReplaceTrimsWhitespaceInValues(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO t (a) VALUES ( 'hello' )";
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertSame("SELECT 'hello' AS `a`", $result);
    }

    public function testTransformReplaceSetExactSqlContainsSelectPrefix(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = 'REPLACE INTO t SET x = 42';
        $tables = ['t' => ['columns' => ['x'], 'columnTypes' => [], 'rows' => []]];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('SELECT 42 AS `x`', $result);
    }

    public function testTransformReplaceValuesTrimsWhitespace(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $sql = "REPLACE INTO t (a, b) VALUES ( 1 , 'hello' )";
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertSame("SELECT 1 AS `a`, 'hello' AS `b`", $result);
    }

    public function testTransformReplaceNullDestThrows(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $transformer = new ReplaceTransformer($parser, $selectTransformer);

        $this->expectException(UnsupportedSqlException::class);
        $this->expectExceptionMessage('Cannot resolve INSERT target');
        $transformer->transform('REPLACE SELECT 1', []);
    }

    public function testCommitRewriteState(): void
    {
        $transformer = new ReplaceTransformer(new MySqlParser(), new SelectTransformer());
        $tables = ['users' => ['rows' => [], 'columns' => ['id', 'name'], 'columnTypes' => [], 'identityStrategies' => ['id' => \ZtdQuery\Schema\IdentityGenerationStrategy::MaxValue]]];
        self::assertSame("SELECT 1 AS `id`, 'a' AS `name`", $transformer->transform("REPLACE INTO users (name) VALUES ('a')", $tables));
        $transformer->commitRewriteState();
        self::assertSame("SELECT 2 AS `id`, 'b' AS `name`", $transformer->transform("REPLACE INTO users (name) VALUES ('b')", $tables));
    }
}
