<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Sql;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Sql;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[\PHPUnit\Framework\Attributes\CoversClass(Sql\Parts::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Serializer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\SemanticException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(SchemaBuilder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\StatementFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\SimpleSerializer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Binder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\NullFacts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\TypeResolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scope::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\BoundRelation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\FromBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\TableResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\SelectBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\ConflictBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\InsertionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\MergeBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\TransactionSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SpecialSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingTokens::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Analysis\Diagnostics::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\IndexBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\DefinitionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\IndexEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\StatementContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\ValueList::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\ClauseEditor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\IndirectionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionMatch::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\FunctionSignature::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\TableDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\IndexElement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ReferentialAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\TableConstraint::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\IndexDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\BuiltinResult::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\SignatureInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\Builtins::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Type\Nullability::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Expression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Join::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Diagnostic::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ExpressionKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundSelect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundQuery::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\TableUse::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ColumnBinding::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Ordering::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(OutputColumn::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\JoinKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\MergeAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Destination::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Insertion::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Merge::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Assignment::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\ConflictAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Configuration\Setting::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Transformation\SourceEdit::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Transformation\Context::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Transformation\TreeEdit::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\CommandStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\InsertStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\ConfigurationStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\TableStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\DeleteStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\MergeStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\RelationQuery::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\ValuesStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\UpdateStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\CompoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\CreateIndexStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\CreateTableStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\IndexDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\TableDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Traversal\Expressions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\ExpressionInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(InvalidStructure::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\StatementInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\Collections::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Literal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\ExpressionFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Build::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Atom::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Tree::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Source::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Format::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\DialectParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\TokenGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Tree::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ColumnReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\SchemaReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Identifiers::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\StatementList::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\ReferenceReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\IndexKeys::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\OptionReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\IndexReader::class)]
final class PartsTest extends TestCase
{
    public function testOutputsQuotesAliasesAndKeepsValueOrder(): void
    {
        self::assertSame('1 AS "first", 2', Sql\Parts::outputs([new OutputColumn(0, 'first', Expression::literal(1, Dialect::PostgreSql)), new OutputColumn(1, null, Expression::literal(2, Dialect::PostgreSql))], Dialect::PostgreSql)->toString());
    }
    public function testExpressionsCanOmitAnOptionalClause(): void
    {
        self::assertSame('', Sql\Parts::expressions('WHERE', [])->toString());
        self::assertSame('WHERE TRUE', Sql\Parts::expressions('WHERE', [Expression::literal(true, Dialect::PostgreSql)])->toString());
    }
    public function testOrderingRetainsDirectionAndNullPlacement(): void
    {
        $value = Expression::reference(['id'], Dialect::PostgreSql);
        self::assertSame('ORDER BY "id" DESC NULLS FIRST', Sql\Parts::ordering([new \SqlSemantics\Model\Ordering($value, true, true)])->toString());
        self::assertSame('', Sql\Parts::ordering([])->toString());
    }
    public function testRowsPreservesRowAndColumnOrder(): void
    {
        self::assertSame('VALUES (1, 2), (3, 4)', Sql\Parts::rows([[Expression::literal(1, Dialect::PostgreSql), Expression::literal(2, Dialect::PostgreSql)], [Expression::literal(3, Dialect::PostgreSql), Expression::literal(4, Dialect::PostgreSql)]])->toString());
    }

    public function testOutputsRejectsNoncontiguousPositions(): void
    {
        $this->expectException(InvalidStructure::class);
        Sql\Parts::outputs([new OutputColumn(1, 'name', Expression::literal(1, Dialect::PostgreSql))], Dialect::PostgreSql);
    }

    public function testQueryRemovesOnlyTheCommandTerminator(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT ';'; -- end");
        self::assertInstanceOf(\SqlSemantics\Model\BoundQuery::class, $query);
        self::assertSame("(SELECT ';')", Sql\Parts::query($query, Dialect::PostgreSql)->toString());
    }
}
