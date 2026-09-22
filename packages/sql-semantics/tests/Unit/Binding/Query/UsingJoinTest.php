<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
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
#[UsesClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[UsesClass(\SqlSemantics\Binding\FromBinder::class)]
#[UsesClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[UsesClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[UsesClass(\SqlSemantics\Binding\NullFacts::class)]
#[CoversClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[CoversClass(\SqlSemantics\Binding\Scope::class)]
#[UsesClass(\SqlSemantics\Binding\SelectBinder::class)]
#[UsesClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[UsesClass(\SqlSemantics\Binding\TableResolver::class)]
#[UsesClass(\SqlSemantics\Binding\TypeResolution::class)]
#[UsesClass(Dialect::class)]
#[UsesClass(\SqlSemantics\Model\BoundSelect::class)]
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
#[UsesClass(\SqlSemantics\Binding\Editing\ExpressionEdit::class)]
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
final class UsingJoinTest extends TestCase
{
    public function testBindProducesMergedColumnsAndMatchPredicate(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE a (id INTEGER, x INTEGER)', 'CREATE TABLE b (id INTEGER, y INTEGER)');
        $query = (new Binder($schema))->bind('SELECT * FROM a FULL JOIN b USING (id)');
        self::assertSame(['id', 'x', 'y'], array_column($query->outputs, 'name'));
        self::assertInstanceOf(\SqlSemantics\Model\Join::class, $query->from);
        self::assertSame('=', $query->from->condition?->symbol);
        self::assertSame(\SqlSemantics\Model\ExpressionKind::Coalesce, $query->outputs[0]->expression->kind);
    }
    public function testBindPreservesRightJoinKeysAndMultipleConditions(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE a (id INTEGER NOT NULL, k INTEGER, x INTEGER)', 'CREATE TABLE b (id INTEGER NOT NULL, k INTEGER, x INTEGER)');
        $query = (new Binder($schema))->bind('SELECT * FROM a RIGHT JOIN b USING (id,k)');
        self::assertSame(['id', 'k', 'x', 'x'], array_column($query->outputs, 'name'));
        self::assertSame('r1', $query->outputs[0]->expression->binding?->relationId);
        self::assertSame('not-null', $query->outputs[0]->expression->nullability->value);
        self::assertSame(['j0'], $query->outputs[2]->expression->nullExtendedBy);
        self::assertSame([], $query->outputs[3]->expression->nullExtendedBy);
        self::assertInstanceOf(\SqlSemantics\Model\Join::class, $query->from);
        self::assertSame('AND', $query->from->condition?->symbol);
        self::assertCount(2, $query->from->condition->operands);
    }

    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, '"'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, '`'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite, '"'])]
    public function testBindPreservesNumericColumnNamesInMergedNamespaces(Dialect $dialect, string $quote): void
    {
        $column = $quote . '1' . $quote;
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE a (' . $column . ' INTEGER)', 'CREATE TABLE b (' . $column . ' INTEGER)', 'CREATE TABLE c (' . $column . ' INTEGER)');
        $query = (new Binder($schema))->bind('SELECT *, ' . $column . ' FROM a NATURAL JOIN b JOIN c USING (' . $column . ')');
        self::assertSame(['1', '1'], array_column($query->outputs, 'name'));
        self::assertSame('integer', $query->outputs[0]->expression->type->name);
        self::assertSame('1', $query->outputs[1]->expression->binding?->column->name);
    }

    public function testBindCollectsTypeDiagnosticsForFullUsingJoins(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE a (id INTEGER)', 'CREATE TABLE b (id BOOLEAN)');
        $statement = (new Binder($schema))->bind('SELECT * FROM a FULL JOIN b USING (id)', strict: false);
        self::assertNotSame([], $statement->diagnostics);
        self::assertSame(['id'], array_column($statement->outputs, 'name'));
        self::assertSame('COALESCE', $statement->outputs[0]->expression->symbol);
    }

}
