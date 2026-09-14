<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite\Transformer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\MySqlTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\ReplaceTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\SelectTransformer;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\UpdateTransformer;
use ZtdQuery\Platform\MySql\Sql\Dml\DmlWhereClauseExtractor;
use ZtdQuery\Platform\MySql\Sql\Dml\InsertSelectSourceExtractor;
use ZtdQuery\Platform\MySql\Sql\Dml\UpdateSourceExtractor;
use ZtdQuery\Platform\MySql\Sql\MySqlIdentifierQuoter;
use ZtdQuery\Platform\MySql\Sql\MySqlParser;
use ZtdQuery\Platform\MySql\Sql\Upsert\MySqlUpsertAssignmentExtractor;
use ZtdQuery\Platform\MySql\Sql\Value\MySqlCastRenderer;

#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Partition\MySqlPartitionSelectionRewriter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Dml\AssignmentExpression::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Cte\HeaderParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Cte\IdentifierReferences::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\OptionalInsertIntoNormalizer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Relation\ExpressionNames::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Relation\ReferenceReader::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Upsert\AssignmentReader::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\FullText\ExpressionEditor::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Partition\SelectionReader::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Partition\SourceProjection::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Upsert\ConflictPredicate::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Upsert\ExpressionBinder::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Upsert\MetadataColumns::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Upsert\QualifiedColumn::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Delete\ResultSelect::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Delete\TargetProjection::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Insert\InsertTarget::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Insert\ReplaceStatementConverter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Insert\ResultProjection::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Select\ExpressionAliaser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Set\OrderRewriter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Set\ValueNormalizer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Shadow\CteRows::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Update\ResultSelect::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Update\TargetProjection::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\CastTypeResolver::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Type\RankEdits::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\ScalarExpression::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\StringCoercion::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Dml\UpdateAssignmentExtractor::class)]
#[CoversClass(MySqlTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Relation\MySqlSelectRelationParser::class)]
#[UsesClass(MySqlParser::class)]
#[UsesClass(MySqlUpsertAssignmentExtractor::class)]
#[UsesClass(SelectTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\FullText\MySqlFullTextSearchRewriter::class)]
#[UsesClass(InsertTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertRowRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertSelectRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\MySqlSelectListAliaser::class)]
#[UsesClass(InsertSelectSourceExtractor::class)]
#[UsesClass(UpdateTransformer::class)]
#[UsesClass(DeleteTransformer::class)]
#[UsesClass(DmlWhereClauseExtractor::class)]
#[UsesClass(UpdateSourceExtractor::class)]
#[UsesClass(ReplaceTransformer::class)]
#[UsesClass(MySqlCastRenderer::class)]
#[UsesClass(MySqlIdentifierQuoter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\MySqlValueRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Type\MySqlTypeSemantics::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Cte\MySqlCteShadowComposer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Upsert\MySqlNativeUpsertProjector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Rewrite\GeneratedColumn\MySqlGeneratedColumnProjector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\MySqlLexerProfile::class)]
final class MySqlTransformerTest extends TestCase
{
    public function testTransformSelectPassthrough(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);

        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);

        $sql = 'SELECT 1';
        $result = $transformer->transform($sql, []);
        self::assertSame($sql, $result);
    }

    public function testTransformSelectWithShadowData(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);

        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);

        $sql = 'SELECT * FROM users';
        $tables = [
            'users' => [
                'rows' => [['id' => '1', 'name' => 'Alice']],
                'columns' => ['id', 'name'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('WITH', $result);
    }

    public function testTransformInsertDelegatesToInsertTransformer(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);

        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);

        $sql = "INSERT INTO users (id, name) VALUES (1, 'Alice')";
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('SELECT 1 AS `id`', $result);
        self::assertStringContainsString('AS `name`', $result);
    }

    public function testTransformDeleteDelegatesToDeleteTransformer(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);

        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);

        $sql = 'DELETE FROM users WHERE id = 1';
        $tables = [
            'users' => [
                'rows' => [['id' => 1]],
                'columns' => ['id'],
                'columnTypes' => [],
            ],
        ];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('SELECT', $result);
        self::assertStringContainsString('FROM', $result);
        self::assertStringContainsString('WHERE', $result);
    }

    public function testTransformReplaceDelegatesToReplaceTransformer(): void
    {
        $parser = new MySqlParser();
        $selectTransformer = new SelectTransformer();
        $insertTransformer = new InsertTransformer($parser, $selectTransformer);
        $updateTransformer = new UpdateTransformer($parser, $selectTransformer);
        $deleteTransformer = new DeleteTransformer($parser, $selectTransformer);
        $replaceTransformer = new ReplaceTransformer($parser, $selectTransformer);

        $transformer = new MySqlTransformer($parser, $selectTransformer, $insertTransformer, $updateTransformer, $deleteTransformer, $replaceTransformer);

        $sql = "REPLACE INTO users (id, name) VALUES (1, 'Bob')";
        $tables = [];

        $result = $transformer->transform($sql, $tables);
        self::assertStringContainsString('SELECT 1 AS `id`', $result);
    }

    public function testCommitRewriteState(): void
    {
        $parser = new MySqlParser();
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $select = new SelectTransformer();
        $insert = new InsertTransformer($parser, $select);
        $update = new UpdateTransformer($parser, $select);
        $delete = new DeleteTransformer($parser, $select);
        $replace = new ReplaceTransformer($parser, $select);
        $transformer = new MySqlTransformer($parser, $select, $insert, $update, $delete, $replace);
        $tables = ['users' => ['rows' => [], 'columns' => ['id', 'name'], 'columnTypes' => [], 'identityStrategies' => ['id' => \ZtdQuery\Schema\Key\IdentityGenerationStrategy::MaxValue]]];
        self::assertSame("SELECT 1 AS `id`, 'a' AS `name`", $transformer->transform("INSERT INTO users (name) VALUES ('a')", $tables));
        self::assertSame("SELECT 1 AS `id`, 'a' AS `name`", $transformer->transform("REPLACE INTO users (name) VALUES ('a')", $tables));
        $transformer->commitRewriteState();
        self::assertSame("SELECT 2 AS `id`, 'b' AS `name`", $transformer->transform("INSERT INTO users (name) VALUES ('b')", $tables));
        self::assertSame("SELECT 2 AS `id`, 'b' AS `name`", $transformer->transform("REPLACE INTO users (name) VALUES ('b')", $tables));
    }
}
