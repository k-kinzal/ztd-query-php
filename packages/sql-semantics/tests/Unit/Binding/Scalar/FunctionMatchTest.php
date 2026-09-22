<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\FunctionSignature;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\SemanticException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(SchemaBuilder::class)]
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
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Scalar\FunctionMatch::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(FunctionSignature::class)]
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
#[\PHPUnit\Framework\Attributes\UsesClass(Nullability::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(TypeDescriptor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Expression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Join::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Diagnostic::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ExpressionKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundQuery::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\TableUse::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ColumnBinding::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Ordering::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\OutputColumn::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\JoinKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\MergeAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Destination::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Insertion::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Merge::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Assignment::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\ConflictAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Configuration\Setting::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\IndexDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\TableDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Traversal\Expressions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\ExpressionInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\InvalidStructure::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\StatementInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\Collections::class)]
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
#[\PHPUnit\Framework\Attributes\Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Serializer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\StatementFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\SimpleSerializer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\StatementContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\ValueList::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\ClauseEditor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundSelect::class)]
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
final class FunctionMatchTest extends TestCase
{
    public function testArityHandlesOptionalAndVariadicArguments(): void
    {
        $type = new TypeDescriptor(Dialect::PostgreSql, 'integer');
        $signature = new FunctionSignature('f', [$type, $type], $type, variadic: true, optionalParameters: 1);
        self::assertFalse(\SqlSemantics\Binding\Scalar\FunctionMatch::arity($signature, 0));
        self::assertTrue(\SqlSemantics\Binding\Scalar\FunctionMatch::arity($signature, 1));
        self::assertTrue(\SqlSemantics\Binding\Scalar\FunctionMatch::arity($signature, 5));
        self::assertFalse(\SqlSemantics\Binding\Scalar\FunctionMatch::arity(new FunctionSignature('f', [], $type), 1));
    }

    public function testParameterRepeatsOnlyTheFinalDeclaredType(): void
    {
        $integer = new TypeDescriptor(Dialect::PostgreSql, 'integer');
        $text = new TypeDescriptor(Dialect::PostgreSql, 'text');
        $signature = new FunctionSignature('f', [$integer, $text], $text, variadic: true);
        self::assertSame($integer, \SqlSemantics\Binding\Scalar\FunctionMatch::parameter($signature, 0));
        self::assertSame($text, \SqlSemantics\Binding\Scalar\FunctionMatch::parameter($signature, 10));
        self::assertNull(\SqlSemantics\Binding\Scalar\FunctionMatch::parameter(new FunctionSignature('f', null, $text), 0));
    }

    public function testScorePrefersAnExactOverloadToWidening(): void
    {
        $integer = new TypeDescriptor(Dialect::PostgreSql, 'integer');
        $bigint = new TypeDescriptor(Dialect::PostgreSql, 'bigint');
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build()->withFunctions(new FunctionSignature('f', [$bigint], $bigint), new FunctionSignature('f', [$integer], $integer));
        self::assertSame('integer', (new Binder($schema))->bind('SELECT f(1)')->outputs[0]->expression->type->name);
    }

    public function testCompatibleDoesNotNarrowNumericInputs(): void
    {
        self::assertTrue(\SqlSemantics\Binding\Scalar\FunctionMatch::compatible('integer', 'bigint'));
        self::assertFalse(\SqlSemantics\Binding\Scalar\FunctionMatch::compatible('bigint', 'integer'));
        self::assertTrue(\SqlSemantics\Binding\Scalar\FunctionMatch::compatible('varchar', 'text'));
        self::assertFalse(\SqlSemantics\Binding\Scalar\FunctionMatch::compatible('boolean', 'integer'));
    }


    #[\PHPUnit\Framework\Attributes\DataProvider('providerConversions')]
    public function testScoreBindsPermittedArgumentConversions(Dialect $dialect, string $input, string $parameter): void
    {
        $type = new TypeDescriptor($dialect, $parameter);
        $schema = (new SchemaBuilder($dialect))->build()->withFunctions(new FunctionSignature('convert_input', [$type], $type));
        $expression = (new Binder($schema))->bind('SELECT convert_input(' . $input . ')')->outputs[0]->expression;
        self::assertSame($parameter, $expression->operands[0]->type->name);
        self::assertSame($parameter, $expression->type->name);
        self::assertSame([], (new Binder($schema))->bind('SELECT convert_input(' . $input . ')')->diagnostics);
    }

    /**

     * @return iterable<array{Dialect, string, string}>

     */
    public static function providerConversions(): iterable
    {
        yield [Dialect::PostgreSql, '1', 'bigint'];
        yield [Dialect::PostgreSql, '$1', 'integer'];
        yield [Dialect::PostgreSql, "CAST('x' AS VARCHAR)", 'text'];
        yield [Dialect::MySql, '1', 'text'];
        yield [Dialect::Sqlite, '1', 'text'];
    }

    public function testScoreDoesNotIgnoreLaterArgumentsWhenAnEarlierTypeIsUnspecified(): void
    {
        $unknown = new TypeDescriptor(Dialect::PostgreSql, 'unknown');
        $integer = new TypeDescriptor(Dialect::PostgreSql, 'integer');
        $text = new TypeDescriptor(Dialect::PostgreSql, 'text');
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build()->withFunctions(new FunctionSignature('choose', [$unknown, $integer], $integer), new FunctionSignature('choose', [$unknown, $text], $text));
        self::assertSame('integer', (new Binder($schema))->bind('SELECT choose(NULL, 1)')->outputs[0]->expression->type->name);
        self::assertSame('text', (new Binder($schema))->bind("SELECT choose(NULL, CAST('x' AS TEXT))")->outputs[0]->expression->type->name);
    }

}
