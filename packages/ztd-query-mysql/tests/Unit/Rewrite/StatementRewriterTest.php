<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Rewrite\StatementRewriter;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\DmlWhereClauseExtractor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\InsertSelectSourceExtractor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\AlterTableMutation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\ColumnAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\ColumnAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\ColumnDefinitionParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\CreateTableRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\OperationApplier::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\PrimaryKeyAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\StoredColumns::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\UnsupportedKeyword::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Statement\RowMutation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Statement\SchemaMutation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Statement\TargetName::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlCastRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlColumnTypeMapper::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlCteShadowComposer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlForeignKeyDefinitionParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlFullTextSearchRewriter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlGeneratedColumnProjector::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlIdentifierQuoter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlMutationResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlNativeUpsertProjector::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlPartitionSelectionRewriter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlPartitioningParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlQueryGuard::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlReadOnlyDiagnosticStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlSchemaParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlSelectRelationParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlTypeSemantics::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlUpsertAssignmentExtractor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlUpsertExpressionParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlValueRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlViewShadowRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Alter\OptionList::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\AssignmentExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Cte\HeaderParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Cte\IdentifierReferences::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\DiagnosticKeywords::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\OptionalInsertIntoNormalizer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Relation\ExpressionNames::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Relation\ReferenceReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Upsert\AssignmentReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Upsert\ExpressionReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Upsert\LiteralReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Upsert\StringLiteral::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Projection\FullText\ExpressionEditor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Projection\Partition\SelectionReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Projection\Partition\SourceProjection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Projection\Upsert\ConflictPredicate::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Projection\Upsert\ExpressionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Projection\Upsert\MetadataColumns::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Projection\Upsert\QualifiedColumn::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Classification\CteStatementKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Context\TableContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Validation\AlterTableGuard::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Validation\ReplaceColumns::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Schema\DefinitionBuilder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Schema\ForeignKey\DefinitionReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Schema\ForeignKey\TokenReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Schema\Partition\PredicateCompiler::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\DeleteTransformer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\Delete\ResultSelect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\Delete\TargetProjection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\InsertRowRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\InsertSelectRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\InsertTransformer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\Insert\InsertTarget::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\Insert\ReplaceStatementConverter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\Insert\ResultProjection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\MySqlSelectListAliaser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\MySqlTransformer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\ReplaceTransformer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\SelectTransformer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\Select\ExpressionAliaser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\Set\OrderRewriter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\Set\ValueNormalizer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\Shadow\CteRows::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\UpdateTransformer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\Update\ResultSelect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\Update\TargetProjection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Type\CastTypeResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Type\Enum\RankEdits::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Type\Value\ScalarExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Type\Value\StringCoercion::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\UpdateAssignmentExtractor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\UpdateSourceExtractor::class)]
#[CoversClass(StatementRewriter::class)]
final class StatementRewriterTest extends TestCase
{
    public function testRewriteStatement(): void
    {
        $parser = new \ZtdQuery\Platform\MySql\MySqlParser();
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $select = new \ZtdQuery\Platform\MySql\Transformer\SelectTransformer();
        $insert = new \ZtdQuery\Platform\MySql\Transformer\InsertTransformer($parser, $select);
        $update = new \ZtdQuery\Platform\MySql\Transformer\UpdateTransformer($parser, $select);
        $delete = new \ZtdQuery\Platform\MySql\Transformer\DeleteTransformer($parser, $select);
        $replace = new \ZtdQuery\Platform\MySql\Transformer\ReplaceTransformer($parser, $select);
        $transformer = new \ZtdQuery\Platform\MySql\Transformer\MySqlTransformer($parser, $select, $insert, $update, $delete, $replace);
        $resolver = new \ZtdQuery\Platform\MySql\MySqlMutationResolver($store, $registry, new \ZtdQuery\Platform\MySql\MySqlSchemaParser($parser), $update, $delete);
        $rewriter = new StatementRewriter(new \ZtdQuery\Platform\MySql\MySqlCteShadowComposer(), new \ZtdQuery\Platform\MySql\MySqlQueryGuard($parser), $resolver, $parser, $registry, $store, $transformer, new \ZtdQuery\Schema\ViewDefinitionSet());
        $statement = (new \PhpMyAdmin\SqlParser\Parser('SELECT 1'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\SelectStatement::class, $statement);
        $plan = $rewriter->rewriteStatement($statement, 'SELECT 1');
        self::assertSame('SELECT 1', $plan->sql());
        self::assertSame(\ZtdQuery\Rewrite\QueryKind::READ, $plan->kind());
        self::assertNull($plan->mutation());
    }

    public function testMutationStatement(): void
    {
        $parser = new \ZtdQuery\Platform\MySql\MySqlParser();
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $select = new \ZtdQuery\Platform\MySql\Transformer\SelectTransformer();
        $insert = new \ZtdQuery\Platform\MySql\Transformer\InsertTransformer($parser, $select);
        $update = new \ZtdQuery\Platform\MySql\Transformer\UpdateTransformer($parser, $select);
        $delete = new \ZtdQuery\Platform\MySql\Transformer\DeleteTransformer($parser, $select);
        $replace = new \ZtdQuery\Platform\MySql\Transformer\ReplaceTransformer($parser, $select);
        $transformer = new \ZtdQuery\Platform\MySql\Transformer\MySqlTransformer($parser, $select, $insert, $update, $delete, $replace);
        $resolver = new \ZtdQuery\Platform\MySql\MySqlMutationResolver($store, $registry, new \ZtdQuery\Platform\MySql\MySqlSchemaParser($parser), $update, $delete);
        $rewriter = new StatementRewriter(new \ZtdQuery\Platform\MySql\MySqlCteShadowComposer(), new \ZtdQuery\Platform\MySql\MySqlQueryGuard($parser), $resolver, $parser, $registry, $store, $transformer, new \ZtdQuery\Schema\ViewDefinitionSet());
        $statement = (new \PhpMyAdmin\SqlParser\Parser('WITH c AS (SELECT 1) UPDATE users SET id = 2'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\WithStatement::class, $statement);
        $mutation = $rewriter->mutationStatement($statement, 'WITH c AS (SELECT 1) UPDATE users SET id = 2');
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $mutation);
        self::assertSame('UPDATE users SET id = 2', $mutation->build());
    }

}
