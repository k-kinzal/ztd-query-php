<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[UsesClass(\SqlSemantics\Ast\ColumnReader::class)]
#[UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[UsesClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[UsesClass(\SqlSemantics\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Ast\Identifiers::class)]
#[UsesClass(\SqlSemantics\Ast\SchemaReader::class)]
#[UsesClass(\SqlSemantics\Ast\StatementList::class)]
#[UsesClass(\SqlSemantics\Ast\TokenGroups::class)]
#[UsesClass(\SqlSemantics\Ast\Tree::class)]
#[UsesClass(\SqlSemantics\Ast\TypeReader::class)]
#[UsesClass(Binder::class)]
#[UsesClass(\SqlSemantics\Binding\BoundRelation::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[UsesClass(\SqlSemantics\Binding\FromBinder::class)]
#[UsesClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[UsesClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[UsesClass(\SqlSemantics\Binding\NullFacts::class)]
#[UsesClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[CoversClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[UsesClass(\SqlSemantics\Binding\Scope::class)]
#[UsesClass(\SqlSemantics\Binding\SelectBinder::class)]
#[UsesClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[UsesClass(\SqlSemantics\Binding\TableResolver::class)]
#[CoversClass(\SqlSemantics\Binding\TypeResolution::class)]
#[UsesClass(Dialect::class)]
#[UsesClass(\SqlSemantics\Model\BoundQuery::class)]
#[UsesClass(\SqlSemantics\Model\BoundStatement::class)]
#[UsesClass(\SqlSemantics\Model\ColumnBinding::class)]
#[UsesClass(\SqlSemantics\Model\Expression::class)]
#[UsesClass(\SqlSemantics\Model\ExpressionKind::class)]
#[UsesClass(\SqlSemantics\Model\Join::class)]
#[UsesClass(\SqlSemantics\Model\JoinKind::class)]
#[UsesClass(\SqlSemantics\Model\Ordering::class)]
#[UsesClass(\SqlSemantics\Model\OutputColumn::class)]
#[UsesClass(\SqlSemantics\Model\TableUse::class)]
#[UsesClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[UsesClass(\SqlSemantics\Schema\ConstraintKind::class)]
#[UsesClass(\SqlSemantics\Schema\TableConstraint::class)]
#[UsesClass(\SqlSemantics\Schema\TableDefinition::class)]
#[UsesClass(\SqlSemantics\Schema::class)]
#[UsesClass(SchemaBuilder::class)]
#[UsesClass(\SqlSemantics\SemanticException::class)]
#[UsesClass(\SqlSemantics\Type\Nullability::class)]
#[UsesClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[Medium]
#[UsesClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
#[UsesClass(\SqlSemantics\Binding\Analysis\Diagnostics::class)]
#[UsesClass(\SqlSemantics\Model\Diagnostic::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\IndirectionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Write\ConflictBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Write\AssignmentRules::class)]
#[UsesClass(\SqlSemantics\Binding\Write\InsertionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Write\AssignmentBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Configuration\TransactionSettings::class)]
#[UsesClass(\SqlSemantics\Binding\Configuration\SettingBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Configuration\SpecialSettings::class)]
#[UsesClass(\SqlSemantics\Binding\Configuration\SettingTokens::class)]
#[UsesClass(\SqlSemantics\Model\Write\Insertion::class)]
#[UsesClass(\SqlSemantics\Model\Write\Assignment::class)]
#[UsesClass(\SqlSemantics\Model\Write\ConflictAction::class)]
#[UsesClass(\SqlSemantics\Model\Configuration\Setting::class)]
#[UsesClass(\SqlSemantics\Model\Traversal\Expressions::class)]
#[UsesClass(\SqlSemantics\Model\Validation\ExpressionInvariant::class)]
#[UsesClass(\SqlSemantics\Model\Validation\StatementInvariant::class)]
#[UsesClass(\SqlSemantics\Model\Validation\Collections::class)]
#[UsesClass(\SqlSemantics\Model\Validation\InvalidStructure::class)]
#[UsesClass(\SqlSemantics\Model\Definition\TableDeclaration::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\DefinitionBinder::class)]
#[UsesClass(\SqlSemantics\Model\Write\Destination::class)]
#[UsesClass(\SqlSemantics\Model\Write\Merge::class)]
#[UsesClass(\SqlSemantics\Model\Write\MergeAction::class)]
#[UsesClass(\SqlSemantics\Binding\Write\MergeBinder::class)]
#[UsesClass(\SqlSemantics\Ast\Definition\ReferenceReader::class)]
#[UsesClass(\SqlSemantics\Ast\Definition\OptionReader::class)]
#[UsesClass(\SqlSemantics\Ast\Definition\IndexReader::class)]
#[UsesClass(\SqlSemantics\Ast\Definition\IndexKeys::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\FunctionMatch::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\FunctionResolver::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\IndexEvolution::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\IndexBinder::class)]
#[UsesClass(\SqlSemantics\Schema\FunctionSignature::class)]
#[UsesClass(\SqlSemantics\Schema\Functions\Builtins::class)]
#[UsesClass(\SqlSemantics\Schema\Functions\BuiltinResult::class)]
#[UsesClass(\SqlSemantics\Schema\Functions\SignatureInvariant::class)]
#[UsesClass(\SqlSemantics\Schema\IndexDefinition::class)]
#[UsesClass(\SqlSemantics\Schema\IndexElement::class)]
#[UsesClass(\SqlSemantics\Model\Definition\IndexDeclaration::class)]
#[UsesClass(\SqlSemantics\Serializer::class)]
#[UsesClass(\SqlSemantics\StatementFactory::class)]
#[UsesClass(\SqlSemantics\SimpleSerializer::class)]
#[UsesClass(\SqlSemantics\Binding\Editing\StatementContext::class)]
#[UsesClass(\SqlSemantics\Binding\Editing\ValueList::class)]
#[UsesClass(\SqlSemantics\Binding\Editing\ClauseEditor::class)]
#[UsesClass(\SqlSemantics\Schema\ReferentialAction::class)]
#[UsesClass(\SqlSemantics\Model\BoundSelect::class)]
#[UsesClass(\SqlSemantics\Model\Transformation\SourceEdit::class)]
#[UsesClass(\SqlSemantics\Model\Transformation\Context::class)]
#[UsesClass(\SqlSemantics\Model\Transformation\TreeEdit::class)]
#[UsesClass(\SqlSemantics\Model\Statement\CommandStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\InsertStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\ConfigurationStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\TableStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\DeleteStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\MergeStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\RelationQuery::class)]
#[UsesClass(\SqlSemantics\Model\Statement\ValuesStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\UpdateStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\CompoundStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\CreateIndexStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\CreateTableStatement::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Literal::class)]
#[UsesClass(\SqlSemantics\Model\Sql\ExpressionFactory::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Build::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Parts::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Atom::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Tree::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Source::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Format::class)]
final class ScalarBinderTest extends TestCase
{
    public function testConditionalRetainsBranchesAndExplicitCast(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER)');
        $query = (new Binder($schema))->bind("SELECT CASE WHEN n > 0 THEN CAST(n AS TEXT) ELSE 'none' END FROM t");
        self::assertSame(\SqlSemantics\Model\ExpressionKind::CaseExpression, $query->outputs[0]->expression->kind);
        self::assertSame('text', $query->outputs[0]->expression->type->name);
        self::assertSame('n', $query->outputs[0]->expression->lineage()[0]->column->name);
    }

    public function testSubqueryRetainsRowPredicate(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER)');
        $query = (new Binder($schema))->bind('SELECT EXISTS (SELECT 1 FROM t WHERE n > 0)');
        self::assertSame('boolean', $query->outputs[0]->expression->type->name);
        self::assertSame('>', $query->outputs[0]->expression->query?->where?->symbol);
    }
    public function testBindCastSetsTheDeclaredResultType(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT CAST(1 AS NUMERIC(8,2))');
        self::assertSame('numeric', $query->outputs[0]->expression->type->name);
        self::assertSame(['8', '2'], $query->outputs[0]->expression->type->modifiers);
    }

    public function testOperandsRetainsFunctionArguments(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT custom_function(1, 2)');
        self::assertCount(2, $query->outputs[0]->expression->operands);
        self::assertSame('unknown', $query->outputs[0]->expression->type->name);
    }

    public function testOperatorRetainsBetweenBounds(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 2 BETWEEN 1 AND 3');
        self::assertSame('BETWEEN', $query->outputs[0]->expression->symbol);
        self::assertCount(3, $query->outputs[0]->expression->operands);
    }

    public function testNestedQueryDoesNotReplaceTheContainingFunction(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER)');
        $query = (new Binder($schema))->bind('SELECT coalesce((SELECT max(n) FROM t), 0)');
        $expression = $query->outputs[0]->expression;
        self::assertSame(\SqlSemantics\Model\ExpressionKind::Coalesce, $expression->kind);
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $expression->nullability);
        self::assertSame('integer', $expression->type->name);
        self::assertCount(2, $expression->operands);
        self::assertNotNull($expression->operands[0]->query);
        self::assertSame('n', $expression->lineage()[0]->column->name);
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['case when true then 1 else 2 end', 'integer', 'not-null'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['case when true then 1 end', 'integer', 'maybe-null'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['case when true then 1 else NULL end', 'integer', 'maybe-null'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['cast(1 as numeric)', 'numeric', 'not-null'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['NULL::integer', 'integer', 'always-null'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['(select 1)', 'integer', 'maybe-null'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['exists(select 1)', 'boolean', 'not-null'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['1 in (select 2)', 'boolean', 'maybe-null'])]
    public function testBindConditionalCastAndSubqueryFacts(string $sql, string $type, string $nullability): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('select ' . $sql);
        self::assertSame($type, $query->outputs[0]->expression->type->name);
        self::assertSame($nullability, $query->outputs[0]->expression->nullability->value);
    }

    public function testSubqueryMembershipKeepsBothSidesAndSource(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE a (n INTEGER)', 'CREATE TABLE b (n INTEGER)');
        $query = (new Binder($schema))->bind('select n from a where n in (select n from b)');
        self::assertNotNull($query->where);
        self::assertSame('IN', $query->where->symbol);
        self::assertSame('boolean', $query->where->type->name);
        self::assertSame('maybe-null', $query->where->nullability->value);
        self::assertSame(['a', 'b'], array_map(static fn ($binding): string => $binding->table->name, $query->where->lineage()));
        self::assertNotNull($query->where->query);
        self::assertCount(2, $query->where->operands);
    }



    public function testSubqueryRetainsNegationAndQuantifiedComparison(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER,n INTEGER)'));
        $negative = $binder->bind('SELECT id FROM t WHERE id NOT IN (SELECT n FROM t)');
        self::assertNotNull($negative->where);
        self::assertSame('NOT IN', $negative->where->symbol);
        self::assertSame('id', $negative->where->operands[0]->binding?->column->name);
        $all = $binder->bind('SELECT id FROM t WHERE id = ALL (SELECT n FROM t)');
        self::assertNotNull($all->where);
        self::assertSame('= ALL', $all->where->symbol);
        self::assertSame('boolean', $all->where->type->name);
    }
    public function testSubqueryRetainsMysqlComparisonInput(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER,n INTEGER)'));
        $statement = $binder->bind('SELECT id FROM t WHERE id = ANY (SELECT n FROM t)');
        self::assertNotNull($statement->where);
        self::assertSame('= ANY', $statement->where->symbol);
        self::assertSame('id', $statement->where->operands[0]->binding?->column->name);
        self::assertSame('NOT IN', $binder->bind('SELECT id NOT IN (SELECT n FROM t) FROM t')->outputs[0]->expression->symbol);
    }
}
