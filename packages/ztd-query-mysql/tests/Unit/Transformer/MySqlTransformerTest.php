<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\DmlWhereClauseExtractor;
use ZtdQuery\Platform\MySql\InsertSelectSourceExtractor;
use ZtdQuery\Platform\MySql\MySqlCastRenderer;
use ZtdQuery\Platform\MySql\MySqlIdentifierQuoter;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Platform\MySql\MySqlUpsertAssignmentExtractor;
use ZtdQuery\Platform\MySql\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Transformer\InsertTransformer;
use ZtdQuery\Platform\MySql\Transformer\MySqlTransformer;
use ZtdQuery\Platform\MySql\Transformer\ReplaceTransformer;
use ZtdQuery\Platform\MySql\Transformer\SelectTransformer;
use ZtdQuery\Platform\MySql\Transformer\UpdateTransformer;
use ZtdQuery\Platform\MySql\UpdateSourceExtractor;

#[UsesClass(\ZtdQuery\Platform\MySql\MySqlPartitionSelectionRewriter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Parsing\AssignmentExpression::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Parsing\Cte\HeaderParser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Parsing\Cte\IdentifierReferences::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Parsing\OptionalInsertIntoNormalizer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Parsing\Relation\ExpressionNames::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Parsing\Relation\ReferenceReader::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Parsing\Upsert\AssignmentReader::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\FullText\ExpressionEditor::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\Partition\SelectionReader::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\Partition\SourceProjection::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\Upsert\ConflictPredicate::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\Upsert\ExpressionBinder::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\Upsert\MetadataColumns::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Projection\Upsert\QualifiedColumn::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Delete\ResultSelect::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Delete\TargetProjection::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Insert\InsertTarget::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Insert\ReplaceStatementConverter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Insert\ResultProjection::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Select\ExpressionAliaser::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Set\OrderRewriter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Set\ValueNormalizer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Shadow\CteRows::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Update\ResultSelect::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\Update\TargetProjection::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Type\CastTypeResolver::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Type\Enum\RankEdits::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Type\Value\ScalarExpression::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Type\Value\StringCoercion::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\UpdateAssignmentExtractor::class)]
#[CoversClass(MySqlTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlSelectRelationParser::class)]
#[UsesClass(MySqlParser::class)]
#[UsesClass(MySqlUpsertAssignmentExtractor::class)]
#[UsesClass(SelectTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlFullTextSearchRewriter::class)]
#[UsesClass(InsertTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\InsertRowRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\InsertSelectRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Transformer\MySqlSelectListAliaser::class)]
#[UsesClass(InsertSelectSourceExtractor::class)]
#[UsesClass(UpdateTransformer::class)]
#[UsesClass(DeleteTransformer::class)]
#[UsesClass(DmlWhereClauseExtractor::class)]
#[UsesClass(UpdateSourceExtractor::class)]
#[UsesClass(ReplaceTransformer::class)]
#[UsesClass(MySqlCastRenderer::class)]
#[UsesClass(MySqlIdentifierQuoter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlValueRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlTypeSemantics::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlCteShadowComposer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlNativeUpsertProjector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlGeneratedColumnProjector::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
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
        $tables = ['users' => ['rows' => [], 'columns' => ['id', 'name'], 'columnTypes' => [], 'identityStrategies' => ['id' => \ZtdQuery\Schema\IdentityGenerationStrategy::MaxValue]]];
        self::assertSame("SELECT 1 AS `id`, 'a' AS `name`", $transformer->transform("INSERT INTO users (name) VALUES ('a')", $tables));
        self::assertSame("SELECT 1 AS `id`, 'a' AS `name`", $transformer->transform("REPLACE INTO users (name) VALUES ('a')", $tables));
        $transformer->commitRewriteState();
        self::assertSame("SELECT 2 AS `id`, 'b' AS `name`", $transformer->transform("INSERT INTO users (name) VALUES ('b')", $tables));
        self::assertSame("SELECT 2 AS `id`, 'b' AS `name`", $transformer->transform("REPLACE INTO users (name) VALUES ('b')", $tables));
    }
}
