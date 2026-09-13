<?php

declare(strict_types=1);

namespace Tests\Unit\Rewriting\Statement;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Sqlite\Rewriting\Statement\StatementRewriter;

#[CoversClass(StatementRewriter::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Mutation\AlterTableMutation::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Mutation\Alter\AddColumnResolver::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Mutation\Alter\AlteredTableProjection::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Mutation\Alter\ColumnDefinitionEditor::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Mutation\Alter\DropColumnResolver::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Mutation\Alter\RenameResolver::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Mutation\Resolution\InsertMutationResolver::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Mutation\Resolution\MutationTableLookup::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Mutation\Resolution\RowMutationResolver::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Mutation\Resolution\TableMutationResolver::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Alter\AlterOperationParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Attach\AttachTokens::class)]
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
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Returning\ReturningItemParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Statement\StatementClassifier::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Statement\StatementStructure::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Statement\TargetTableParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Update\UpdateClauseParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Upsert\ArithmeticExpressionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Upsert\ComparisonExpressionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Upsert\ExpressionCursor::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Upsert\ExpressionTokenDecoder::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Upsert\LogicalExpressionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Upsert\PrimaryExpressionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Context\ReferencedTableLookup::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Context\TableContextBuilder::class)]
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
#[UsesClass(\ZtdQuery\Platform\Sqlite\Schema\Create\ColumnDefinitionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Schema\Create\TableBodyParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Schema\Create\TableDefinitionBuilder::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Schema\Create\VirtualTableParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Schema\ForeignKey\ForeignKeyEntryParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Schema\ForeignKey\ForeignKeyTokens::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteCastRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteColumnTypeMapper::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteCteShadowComposer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteForeignKeyDefinitionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteFullTextSearchRewriter::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteGeneratedColumnProjector::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteIdentifierQuoter::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteInMemoryAttachStatement::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteIndexHintStripper::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteLexerProfile::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteLexicalMasker::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteMutationResolver::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteNativeUpsertProjector::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteQueryGuard::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteReadOnlyDiagnosticStatement::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteReturningProjectionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteSchemaParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteSelectRelationParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteUpsertExpressionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteValueRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteViewShadowRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Transformer\DeleteTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Transformer\InsertRowRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Transformer\InsertSelectRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Transformer\InsertTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Transformer\Insert\InsertProjectionBuilder::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Transformer\SelectTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Transformer\Select\ShadowCteRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Transformer\SqliteTransformer::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Transformer\UpdateTransformer::class)]
final class StatementRewriterTest extends TestCase
{
    public function testRewriteStatementUsesShadowRowsForReads(): void
    {
        $parser = new \ZtdQuery\Platform\Sqlite\SqliteParser();
        $select = new \ZtdQuery\Platform\Sqlite\Transformer\SelectTransformer();
        $transformer = new \ZtdQuery\Platform\Sqlite\Transformer\SqliteTransformer(
            $parser,
            $select,
            new \ZtdQuery\Platform\Sqlite\Transformer\InsertTransformer($parser, $select),
            new \ZtdQuery\Platform\Sqlite\Transformer\UpdateTransformer($parser, $select),
            new \ZtdQuery\Platform\Sqlite\Transformer\DeleteTransformer($parser, $select),
        );
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $registry->register('users', new \ZtdQuery\Schema\TableDefinition(['id'], ['id' => 'INTEGER'], ['id'], [], []));
        $store->set('users', [['id' => 7]]);
        $rewriter = new StatementRewriter(
            new \ZtdQuery\Platform\Sqlite\SqliteCteShadowComposer(),
            new \ZtdQuery\Platform\Sqlite\SqliteQueryGuard($parser),
            new \ZtdQuery\Platform\Sqlite\SqliteMutationResolver($store, $registry, new \ZtdQuery\Platform\Sqlite\SqliteSchemaParser(), $parser),
            $parser,
            $registry,
            new \ZtdQuery\Platform\Sqlite\SqliteReturningProjectionParser(),
            $store,
            $transformer,
            new \ZtdQuery\Schema\ViewDefinitionSet(),
        );
        $plan = $rewriter->rewriteStatement('SELECT id FROM users', 'SELECT id FROM users');
        self::assertSame(\ZtdQuery\Rewrite\QueryKind::READ, $plan->kind());
        self::assertNull($plan->mutation());
        $result = (new PDO('sqlite::memory:'))->query($plan->sql());
        self::assertNotFalse($result);
        self::assertSame(7, $result->fetchColumn());
    }

    public function testRewriteStatementSimulatesWritesWithoutApplyingThem(): void
    {
        $parser = new \ZtdQuery\Platform\Sqlite\SqliteParser();
        $select = new \ZtdQuery\Platform\Sqlite\Transformer\SelectTransformer();
        $transformer = new \ZtdQuery\Platform\Sqlite\Transformer\SqliteTransformer(
            $parser,
            $select,
            new \ZtdQuery\Platform\Sqlite\Transformer\InsertTransformer($parser, $select),
            new \ZtdQuery\Platform\Sqlite\Transformer\UpdateTransformer($parser, $select),
            new \ZtdQuery\Platform\Sqlite\Transformer\DeleteTransformer($parser, $select),
        );
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $registry->register('users', new \ZtdQuery\Schema\TableDefinition(['id'], ['id' => 'INTEGER'], ['id'], [], []));
        $store->set('users', [['id' => 7]]);
        $rewriter = new StatementRewriter(
            new \ZtdQuery\Platform\Sqlite\SqliteCteShadowComposer(),
            new \ZtdQuery\Platform\Sqlite\SqliteQueryGuard($parser),
            new \ZtdQuery\Platform\Sqlite\SqliteMutationResolver($store, $registry, new \ZtdQuery\Platform\Sqlite\SqliteSchemaParser(), $parser),
            $parser,
            $registry,
            new \ZtdQuery\Platform\Sqlite\SqliteReturningProjectionParser(),
            $store,
            $transformer,
            new \ZtdQuery\Schema\ViewDefinitionSet(),
        );
        $plan = $rewriter->rewriteStatement('INSERT INTO users(id) VALUES (8)', 'INSERT INTO users(id) VALUES (8)');
        self::assertSame(\ZtdQuery\Rewrite\QueryKind::WRITE_SIMULATED, $plan->kind());
        self::assertNotNull($plan->mutation());
        self::assertSame([['id' => 7]], $store->get('users'));
    }

    public function testRewriteStatementRejectsUnknownReadSources(): void
    {
        $parser = new \ZtdQuery\Platform\Sqlite\SqliteParser();
        $select = new \ZtdQuery\Platform\Sqlite\Transformer\SelectTransformer();
        $transformer = new \ZtdQuery\Platform\Sqlite\Transformer\SqliteTransformer(
            $parser,
            $select,
            new \ZtdQuery\Platform\Sqlite\Transformer\InsertTransformer($parser, $select),
            new \ZtdQuery\Platform\Sqlite\Transformer\UpdateTransformer($parser, $select),
            new \ZtdQuery\Platform\Sqlite\Transformer\DeleteTransformer($parser, $select),
        );
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $registry->register('users', new \ZtdQuery\Schema\TableDefinition(['id'], ['id' => 'INTEGER'], ['id'], [], []));
        $store->set('users', [['id' => 7]]);
        $rewriter = new StatementRewriter(
            new \ZtdQuery\Platform\Sqlite\SqliteCteShadowComposer(),
            new \ZtdQuery\Platform\Sqlite\SqliteQueryGuard($parser),
            new \ZtdQuery\Platform\Sqlite\SqliteMutationResolver($store, $registry, new \ZtdQuery\Platform\Sqlite\SqliteSchemaParser(), $parser),
            $parser,
            $registry,
            new \ZtdQuery\Platform\Sqlite\SqliteReturningProjectionParser(),
            $store,
            $transformer,
            new \ZtdQuery\Schema\ViewDefinitionSet(),
        );
        $this->expectException(\ZtdQuery\Exception\UnknownSchemaException::class);
        $rewriter->rewriteStatement('SELECT * FROM missing', 'SELECT * FROM missing');
    }

}
