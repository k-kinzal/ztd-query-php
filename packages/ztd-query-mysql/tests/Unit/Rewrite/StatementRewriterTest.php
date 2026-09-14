<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Rewrite\StatementRewriter;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Dml\DmlWhereClauseExtractor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Dml\InsertSelectSourceExtractor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\AlterTableMutation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\Alter\ColumnAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\Alter\ColumnAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\Alter\ColumnDefinitionParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\Alter\CreateTableRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\Alter\OperationApplier::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\Alter\PrimaryKeyAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\Alter\StoredColumns::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\Alter\UnsupportedKeyword::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Row\RowMutationResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\TableMutationResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Dml\TargetName::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\MySqlCastRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Schema\MySqlColumnTypeMapper::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Cte\MySqlCteShadowComposer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Schema\Key\MySqlForeignKeyDefinitionParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\FullText\MySqlFullTextSearchRewriter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\GeneratedColumn\MySqlGeneratedColumnProjector::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\MySqlIdentifierQuoter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\MySqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\MySqlMutationResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Upsert\MySqlNativeUpsertProjector::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\MySqlParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Partition\MySqlPartitionSelectionRewriter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Schema\Partition\MySqlPartitioningParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\MySqlQueryGuard::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Diagnostic\MySqlReadOnlyDiagnosticStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Schema\MySqlSchemaParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Relation\MySqlSelectRelationParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Type\MySqlTypeSemantics::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Upsert\MySqlUpsertAssignmentExtractor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Upsert\MySqlUpsertExpressionParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\MySqlValueRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\View\MySqlViewShadowRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Alter\OptionList::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Dml\AssignmentExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Cte\HeaderParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Cte\IdentifierReferences::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Diagnostic\DiagnosticKeywords::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\OptionalInsertIntoNormalizer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Relation\ExpressionNames::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Relation\ReferenceReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Upsert\AssignmentReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Upsert\ExpressionReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Upsert\LiteralReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Upsert\StringLiteral::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\FullText\ExpressionEditor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Partition\SelectionReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Partition\SourceProjection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Upsert\ConflictPredicate::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Upsert\ExpressionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Upsert\MetadataColumns::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Upsert\QualifiedColumn::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Classification\CteStatementKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Context\TableContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Validation\AlterTableGuard::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Validation\ReplaceColumns::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Schema\DefinitionBuilder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Schema\Key\DefinitionReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Schema\Key\TokenReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Schema\Partition\PredicateCompiler::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\DeleteTransformer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Delete\ResultSelect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Delete\TargetProjection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertRowRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertSelectRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertTransformer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Insert\InsertTarget::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Insert\ReplaceStatementConverter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Insert\ResultProjection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\MySqlSelectListAliaser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\MySqlTransformer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\ReplaceTransformer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\SelectTransformer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Select\ExpressionAliaser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Set\OrderRewriter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Set\ValueNormalizer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Shadow\CteRows::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\UpdateTransformer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Update\ResultSelect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Update\TargetProjection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\CastTypeResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Type\RankEdits::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\ScalarExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\StringCoercion::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Dml\UpdateAssignmentExtractor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Dml\UpdateSourceExtractor::class)]
#[CoversClass(StatementRewriter::class)]
final class StatementRewriterTest extends TestCase
{
    public function testRewriteStatement(): void
    {
        $parser = new \ZtdQuery\Platform\MySql\Sql\MySqlParser();
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $select = new \ZtdQuery\Platform\MySql\Rewrite\Transformer\SelectTransformer();
        $insert = new \ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertTransformer($parser, $select);
        $update = new \ZtdQuery\Platform\MySql\Rewrite\Transformer\UpdateTransformer($parser, $select);
        $delete = new \ZtdQuery\Platform\MySql\Rewrite\Transformer\DeleteTransformer($parser, $select);
        $replace = new \ZtdQuery\Platform\MySql\Rewrite\Transformer\ReplaceTransformer($parser, $select);
        $transformer = new \ZtdQuery\Platform\MySql\Rewrite\Transformer\MySqlTransformer($parser, $select, $insert, $update, $delete, $replace);
        $resolver = new \ZtdQuery\Platform\MySql\Shadow\MySqlMutationResolver($store, $registry, new \ZtdQuery\Platform\MySql\Schema\MySqlSchemaParser($parser), $update, $delete);
        $rewriter = new StatementRewriter(new \ZtdQuery\Platform\MySql\Rewrite\Cte\MySqlCteShadowComposer(), new \ZtdQuery\Platform\MySql\Rewrite\MySqlQueryGuard($parser), $resolver, $parser, $registry, $store, $transformer, new \ZtdQuery\Schema\ViewDefinitionSet());
        $statement = (new \PhpMyAdmin\SqlParser\Parser('SELECT 1'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\SelectStatement::class, $statement);
        $plan = $rewriter->rewriteStatement($statement, 'SELECT 1');
        self::assertSame('SELECT 1', $plan->sql());
        self::assertSame(\ZtdQuery\Rewrite\QueryKind::READ, $plan->kind());
        self::assertNull($plan->mutation());
    }

    public function testMutationStatement(): void
    {
        $parser = new \ZtdQuery\Platform\MySql\Sql\MySqlParser();
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $select = new \ZtdQuery\Platform\MySql\Rewrite\Transformer\SelectTransformer();
        $insert = new \ZtdQuery\Platform\MySql\Rewrite\Transformer\InsertTransformer($parser, $select);
        $update = new \ZtdQuery\Platform\MySql\Rewrite\Transformer\UpdateTransformer($parser, $select);
        $delete = new \ZtdQuery\Platform\MySql\Rewrite\Transformer\DeleteTransformer($parser, $select);
        $replace = new \ZtdQuery\Platform\MySql\Rewrite\Transformer\ReplaceTransformer($parser, $select);
        $transformer = new \ZtdQuery\Platform\MySql\Rewrite\Transformer\MySqlTransformer($parser, $select, $insert, $update, $delete, $replace);
        $resolver = new \ZtdQuery\Platform\MySql\Shadow\MySqlMutationResolver($store, $registry, new \ZtdQuery\Platform\MySql\Schema\MySqlSchemaParser($parser), $update, $delete);
        $rewriter = new StatementRewriter(new \ZtdQuery\Platform\MySql\Rewrite\Cte\MySqlCteShadowComposer(), new \ZtdQuery\Platform\MySql\Rewrite\MySqlQueryGuard($parser), $resolver, $parser, $registry, $store, $transformer, new \ZtdQuery\Schema\ViewDefinitionSet());
        $statement = (new \PhpMyAdmin\SqlParser\Parser('WITH c AS (SELECT 1) UPDATE users SET id = 2'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\WithStatement::class, $statement);
        $mutation = $rewriter->mutationStatement($statement, 'WITH c AS (SELECT 1) UPDATE users SET id = 2');
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $mutation);
        self::assertSame('UPDATE users SET id = 2', $mutation->build());
    }

}
