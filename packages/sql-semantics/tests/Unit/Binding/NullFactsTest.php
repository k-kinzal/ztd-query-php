<?php

declare(strict_types=1);

namespace Tests\Unit\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SemanticException;
use SqlSemantics\Type\Nullability;

#[CoversClass(\SqlSemantics\Binding\NullFacts::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Binding\FromBinder::class)]
#[CoversClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[CoversClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[CoversClass(\SqlSemantics\Binding\SelectBinder::class)]
#[CoversClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[CoversClass(\SqlSemantics\Binding\TypeResolution::class)]
#[CoversClass(Binder::class)]
#[CoversClass(SchemaBuilder::class)]
#[CoversClass(\SqlSemantics\Ast\DialectParser::class)]
#[CoversClass(\SqlSemantics\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Ast\Identifiers::class)]
#[CoversClass(\SqlSemantics\Ast\SchemaReader::class)]
#[CoversClass(\SqlSemantics\Ast\StatementList::class)]
#[CoversClass(\SqlSemantics\Ast\TokenGroups::class)]
#[CoversClass(\SqlSemantics\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Ast\TypeReader::class)]
#[CoversClass(\SqlSemantics\Binding\BoundRelation::class)]
#[CoversClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[CoversClass(\SqlSemantics\Binding\Scope::class)]
#[CoversClass(\SqlSemantics\Binding\TableResolver::class)]
#[CoversClass(\SqlSemantics\Model\ColumnBinding::class)]
#[CoversClass(\SqlSemantics\Model\Expression::class)]
#[CoversClass(\SqlSemantics\Model\Join::class)]
#[CoversClass(\SqlSemantics\Model\Ordering::class)]
#[CoversClass(\SqlSemantics\Model\OutputColumn::class)]
#[CoversClass(\SqlSemantics\Model\BoundQuery::class)]
#[CoversClass(\SqlSemantics\Model\TableUse::class)]
#[CoversClass(\SqlSemantics\Schema::class)]
#[CoversClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Schema\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Schema\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Analysis\Diagnostics::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Diagnostic::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\IndirectionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\ConflictBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\InsertionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\TransactionSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SpecialSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingTokens::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Insertion::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Assignment::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\ConflictAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Configuration\Setting::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Traversal\Expressions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\ExpressionInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\StatementInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\Collections::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\InvalidStructure::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\TableDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\DefinitionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Destination::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Merge::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\MergeAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\MergeBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\ReferenceReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\OptionReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\IndexReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\IndexKeys::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionMatch::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\IndexEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\IndexBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\FunctionSignature::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\Builtins::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\BuiltinResult::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\SignatureInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\IndexDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\IndexElement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\IndexDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Serializer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\StatementFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\SimpleSerializer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\StatementContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\ValueList::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\ClauseEditor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ReferentialAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Nullability::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ExpressionKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundSelect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\JoinKind::class)]
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Literal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\ExpressionFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Build::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Parts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Atom::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Tree::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Source::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Format::class)]
final class NullFactsTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testCoalesceRequiresANonNullOperandToGuaranteeAValue(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT COALESCE(parent_id, NULL) AS a, COALESCE(NULL, NULL) AS b, COALESCE(parent_id, score) AS c FROM users');
        self::assertSame(Nullability::MaybeNull, $statement->outputs[0]->expression->nullability);
        self::assertSame(Nullability::AlwaysNull, $statement->outputs[1]->expression->nullability);
        self::assertSame(Nullability::NotNull, $statement->outputs[2]->expression->nullability);
    }

    public function testStrictComparisonPropagatesNullButBooleanOrDoesNot(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build();
        $statement = (new Binder($schema))->bind('SELECT NULL = 1 AS a, NULL OR TRUE AS b');
        self::assertSame(Nullability::AlwaysNull, $statement->outputs[0]->expression->nullability);
        self::assertSame(Nullability::MaybeNull, $statement->outputs[1]->expression->nullability);
    }

    public function testExtensionsRetainsAllNullableOperandCauses(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT a.id+b.id FROM users a FULL JOIN users b ON a.id=b.id');
        self::assertSame(['j0'], $statement->outputs[0]->expression->nullExtendedBy);
        self::assertSame(Nullability::MaybeNull, $statement->outputs[0]->expression->nullability);
    }

    #[DataProvider('providerFacts')]
    public function testStrictAndCoalesceFacts(Nullability $left, Nullability $right, Nullability $strict, Nullability $coalesce): void
    {
        $type = new \SqlSemantics\Type\TypeDescriptor(Dialect::PostgreSql, 'integer');
        $source = new \SqlParser\Parser\Node('expr', 0, []);
        $operands = [
            new \SqlSemantics\Model\Expression(\SqlSemantics\Model\ExpressionKind::Function, $type, $left, $source),
            new \SqlSemantics\Model\Expression(\SqlSemantics\Model\ExpressionKind::Function, $type, $right, $source),
        ];
        self::assertSame($strict, \SqlSemantics\Binding\NullFacts::strict($operands));
        self::assertSame($coalesce, \SqlSemantics\Binding\NullFacts::coalesce($operands));
    }

    /**
     * @return iterable<string, array{Nullability, Nullability, Nullability, Nullability}>
     */
    public static function providerFacts(): iterable
    {
        yield 'NotNull-NotNull' => [Nullability::NotNull, Nullability::NotNull, Nullability::NotNull, Nullability::NotNull];
        yield 'NotNull-MaybeNull' => [Nullability::NotNull, Nullability::MaybeNull, Nullability::MaybeNull, Nullability::NotNull];
        yield 'NotNull-AlwaysNull' => [Nullability::NotNull, Nullability::AlwaysNull, Nullability::AlwaysNull, Nullability::NotNull];
        yield 'NotNull-Unknown' => [Nullability::NotNull, Nullability::Unknown, Nullability::Unknown, Nullability::NotNull];
        yield 'MaybeNull-NotNull' => [Nullability::MaybeNull, Nullability::NotNull, Nullability::MaybeNull, Nullability::NotNull];
        yield 'MaybeNull-MaybeNull' => [Nullability::MaybeNull, Nullability::MaybeNull, Nullability::MaybeNull, Nullability::MaybeNull];
        yield 'MaybeNull-AlwaysNull' => [Nullability::MaybeNull, Nullability::AlwaysNull, Nullability::AlwaysNull, Nullability::MaybeNull];
        yield 'MaybeNull-Unknown' => [Nullability::MaybeNull, Nullability::Unknown, Nullability::Unknown, Nullability::Unknown];
        yield 'AlwaysNull-NotNull' => [Nullability::AlwaysNull, Nullability::NotNull, Nullability::AlwaysNull, Nullability::NotNull];
        yield 'AlwaysNull-MaybeNull' => [Nullability::AlwaysNull, Nullability::MaybeNull, Nullability::AlwaysNull, Nullability::MaybeNull];
        yield 'AlwaysNull-AlwaysNull' => [Nullability::AlwaysNull, Nullability::AlwaysNull, Nullability::AlwaysNull, Nullability::AlwaysNull];
        yield 'AlwaysNull-Unknown' => [Nullability::AlwaysNull, Nullability::Unknown, Nullability::AlwaysNull, Nullability::Unknown];
        yield 'Unknown-NotNull' => [Nullability::Unknown, Nullability::NotNull, Nullability::Unknown, Nullability::NotNull];
        yield 'Unknown-MaybeNull' => [Nullability::Unknown, Nullability::MaybeNull, Nullability::Unknown, Nullability::Unknown];
        yield 'Unknown-AlwaysNull' => [Nullability::Unknown, Nullability::AlwaysNull, Nullability::AlwaysNull, Nullability::Unknown];
        yield 'Unknown-Unknown' => [Nullability::Unknown, Nullability::Unknown, Nullability::Unknown, Nullability::Unknown];
    }
    public function testAlternativesCombinesValuesWithoutStrictPropagation(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $nonnull = $binder->bind('SELECT 1')->outputs[0]->expression;
        $nullable = $binder->bind('SELECT NULL')->outputs[0]->expression;
        self::assertSame(Nullability::NotNull, \SqlSemantics\Binding\NullFacts::alternatives([$nonnull]));
        self::assertSame(Nullability::AlwaysNull, \SqlSemantics\Binding\NullFacts::alternatives([$nullable]));
        self::assertSame(Nullability::MaybeNull, \SqlSemantics\Binding\NullFacts::alternatives([$nonnull, $nullable]));
        self::assertSame(Nullability::Unknown, \SqlSemantics\Binding\NullFacts::alternatives([]));
    }

}
