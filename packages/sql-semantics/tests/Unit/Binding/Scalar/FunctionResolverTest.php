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
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Scalar\FunctionResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionMatch::class)]
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\InvalidStructure::class)]
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundSelect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Transformation\Context::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\InsertStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\ConfigurationStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\TableStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\DeleteStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\MergeStatement::class)]
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
final class FunctionResolverTest extends TestCase
{
    public function testResolveQualifiedAndQuotedFunctionNames(): void
    {
        $integer = new TypeDescriptor(Dialect::PostgreSql, \SqlSemantics\Type\Identity\BuiltinIdentity::from('integer'));
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build()->withFunctions(new FunctionSignature('Mixed', [$integer], $integer, schema: 'app'));
        $binder = new Binder($schema);
        $boundQuery1 = $binder->bind('SELECT app."Mixed"(1)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery1);
        self::assertSame('integer', $boundQuery1->outputs[0]->expression->type->name);
        $boundQuery2 = $binder->bind('SELECT app.mixed(1)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery2);
        self::assertSame('unknown', $boundQuery2->outputs[0]->expression->type->name);
        $boundQuery3 = $binder->bind('SELECT other."Mixed"(1)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery3);
        self::assertSame('unknown', $boundQuery3->outputs[0]->expression->type->name);
    }

    public function testOverloadChoosesExactTypesAndDiagnosesAmbiguity(): void
    {
        $integer = new TypeDescriptor(Dialect::PostgreSql, \SqlSemantics\Type\Identity\BuiltinIdentity::from('integer'));
        $text = new TypeDescriptor(Dialect::PostgreSql, \SqlSemantics\Type\Identity\BuiltinIdentity::from('text'));
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build()->withFunctions(new FunctionSignature('pick', [$integer], $integer), new FunctionSignature('pick', [$text], $text));
        $binder = new Binder($schema);
        $query = $binder->bind('SELECT pick(1), pick(CAST(1 AS TEXT))');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame(['integer', 'text'], array_map(static fn ($output): string => $output->expression->type->name, $query->outputs));
        $boundQuery1 = $binder->bind('SELECT pick(NULL)', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery1);
        self::assertSame('ambiguous-function', $boundQuery1->diagnostics[0]->reason);
        $boundQuery3 = $binder->bind('SELECT pick(true)', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery3);
        self::assertSame('incompatible-arguments', $boundQuery3->diagnostics[0]->reason);
        $this->expectException(\SqlSemantics\SemanticException::class);
        $binder->bind('SELECT pick(true)');
    }

    public function testResolveRegisteredFunctionsInNestedQueriesAndDefinitions(): void
    {
        $type = new TypeDescriptor(Dialect::PostgreSql, \SqlSemantics\Type\Identity\BuiltinIdentity::from('integer'));
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build()->withFunctions(new FunctionSignature('twice', [$type], $type, Nullability::NotNull, true));
        $binder = new Binder($schema);
        $boundQuery1 = $binder->bind('SELECT (SELECT twice(2))');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery1);
        self::assertSame('integer', $boundQuery1->outputs[0]->expression->type->name);
        $boundQuery2 = $binder->bind('CREATE TABLE t(id INTEGER DEFAULT twice(2))');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateTableStatement::class, $boundQuery2);
        self::assertInstanceOf(\SqlSemantics\Schema\Column\SuppliedColumn::class, $boundQuery2->definition->table->columns[0]->generation);
        self::assertNotNull($boundQuery2->definition->table->columns[0]->generation->default);
        self::assertSame('integer', $boundQuery2->definition->table->columns[0]->generation->default->type->name);
        $script = $binder->bindAll('SELECT twice(1); SELECT twice(2)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $script[1]);
        self::assertSame('integer', $script[1]->outputs[0]->expression->type->name);
    }


    public function testResolveUsesTheDefaultNamespaceAndExplicitBuiltinNamespace(): void
    {
        $integer = new TypeDescriptor(Dialect::PostgreSql, \SqlSemantics\Type\Identity\BuiltinIdentity::from('integer'));
        $schema = (new SchemaBuilder(Dialect::PostgreSql, 'app'))->build()->withFunctions(new FunctionSignature('f', [], $integer, schema: 'app'));
        $binder = new Binder($schema);
        $boundQuery1 = $binder->bind('SELECT f()');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery1);
        self::assertSame('integer', $boundQuery1->outputs[0]->expression->type->name);
        $boundQuery2 = $binder->bind("SELECT pg_catalog.lower('X')");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery2);
        self::assertSame('text', $boundQuery2->outputs[0]->expression->type->name);
        $boundQuery3 = $binder->bind("SELECT other.lower('X')");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery3);
        self::assertSame('unknown', $boundQuery3->outputs[0]->expression->type->name);
        $boundQuery4 = $binder->bind('SELECT public.f()');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery4);
        self::assertSame('unknown', $boundQuery4->outputs[0]->expression->type->name);
    }

    public function testOverloadReportsTheNameAndResponsibleSource(): void
    {
        $integer = new TypeDescriptor(Dialect::PostgreSql, \SqlSemantics\Type\Identity\BuiltinIdentity::from('integer'));
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build()->withFunctions(new FunctionSignature('takes_integer', [$integer], $integer));
        $statement = (new Binder($schema))->bind('SELECT takes_integer(TRUE)', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame('Cannot resolve a unique function signature for takes_integer', $statement->diagnostics[0]->message);
        self::assertSame($statement->outputs[0]->expression->source, $statement->diagnostics[0]->source);
    }

    public function testOverloadRejectsInvalidArityWithoutCreatingAnUnresolvedCall(): void
    {
        $integer = new TypeDescriptor(Dialect::PostgreSql, \SqlSemantics\Type\Identity\BuiltinIdentity::Integer);
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build()->withFunctions(new FunctionSignature('pick', [$integer], $integer));
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('declared number of arguments');
        (new Binder($schema))->bind('SELECT pick()', strict: false);
    }

}
