<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SemanticException;

#[CoversClass(\SqlSemantics\Schema::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Binding\FromBinder::class)]
#[CoversClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[CoversClass(\SqlSemantics\Binding\NullFacts::class)]
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ReferentialAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Type\Nullability::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ExpressionKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundSelect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\JoinKind::class)]
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
final class SchemaTest extends TestCase
{
    public function testRetainsTheDialectAndAllDeclarations(): void
    {
        $builder = new SchemaBuilder(Dialect::PostgreSql);
        $schema = $builder->build('CREATE TABLE a (id INTEGER); CREATE TABLE b (id INTEGER)');
        self::assertSame(Dialect::PostgreSql, $schema->dialect);
        self::assertSame(['a', 'b'], array_column($schema->tables, 'name'));
    }

    public function testWithFunctionsReturnsAnIndependentSnapshot(): void
    {
        $base = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $integer = new \SqlSemantics\Type\TypeDescriptor(Dialect::PostgreSql, \SqlSemantics\Type\Identity\BuiltinIdentity::from('integer'));
        $text = new \SqlSemantics\Type\TypeDescriptor(Dialect::PostgreSql, \SqlSemantics\Type\Identity\BuiltinIdentity::from('text'));
        $added = $base->withFunctions(new \SqlSemantics\Schema\FunctionSignature('f', [$integer], $text));
        $replaced = $added->withFunctions(new \SqlSemantics\Schema\FunctionSignature('f', [new \SqlSemantics\Type\TypeDescriptor(Dialect::PostgreSql, \SqlSemantics\Type\Identity\BuiltinIdentity::from('integer'))], $integer));
        $boundQuery1 = (new Binder($base))->bind('SELECT f(id) FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery1);
        self::assertSame('unknown', $boundQuery1->outputs[0]->expression->type->name);
        $boundQuery2 = (new Binder($added))->bind('SELECT f(id) FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery2);
        self::assertSame('text', $boundQuery2->outputs[0]->expression->type->name);
        $boundQuery3 = (new Binder($replaced))->bind('SELECT f(id) FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery3);
        self::assertSame('integer', $boundQuery3->outputs[0]->expression->type->name);
        self::assertSame($base->tables, $added->tables);
        self::assertCount(count($base->functions) + 1, $replaced->functions);
    }



    #[\PHPUnit\Framework\Attributes\DataProvider('providerCaseInsensitiveDialects')]
    public function testWithFunctionsReplacesCaseInsensitiveNamesWithoutRemovingOtherOverloads(Dialect $dialect): void
    {
        $integer = new \SqlSemantics\Type\TypeDescriptor($dialect, \SqlSemantics\Type\Identity\BuiltinIdentity::from('integer'));
        $text = new \SqlSemantics\Type\TypeDescriptor($dialect, \SqlSemantics\Type\Identity\BuiltinIdentity::from('text'));
        $schema = (new SchemaBuilder($dialect))->build()->withFunctions(
            new \SqlSemantics\Schema\FunctionSignature('a', [], $integer),
            new \SqlSemantics\Schema\FunctionSignature('f', [], $integer),
            new \SqlSemantics\Schema\FunctionSignature('f', [], $integer, schema: 'other'),
            new \SqlSemantics\Schema\FunctionSignature('f', [$integer], $integer),
        );
        $changed = $schema->withFunctions(new \SqlSemantics\Schema\FunctionSignature('F', [], $text));
        self::assertCount(count($schema->functions), $changed->functions);
        self::assertSame(range(0, count($changed->functions) - 1), array_keys($changed->functions));
        $boundQuery1 = (new Binder($changed))->bind('SELECT f()');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery1);
        self::assertSame('text', $boundQuery1->outputs[0]->expression->type->name);
        $boundQuery2 = (new Binder($changed))->bind('SELECT f(1)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery2);
        self::assertSame('integer', $boundQuery2->outputs[0]->expression->type->name);
        $boundQuery3 = (new Binder($changed))->bind('SELECT a()');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery3);
        self::assertSame('integer', $boundQuery3->outputs[0]->expression->type->name);
    }

    /**
     * @return iterable<array{Dialect}>
     */
    public static function providerCaseInsensitiveDialects(): iterable
    {
        yield [Dialect::MySql];
        yield [Dialect::Sqlite];
    }

    public function testWithFunctionsKeepsQuotedPostgresNamesDistinct(): void
    {
        $integer = new \SqlSemantics\Type\TypeDescriptor(Dialect::PostgreSql, \SqlSemantics\Type\Identity\BuiltinIdentity::from('integer'));
        $text = new \SqlSemantics\Type\TypeDescriptor(Dialect::PostgreSql, \SqlSemantics\Type\Identity\BuiltinIdentity::from('text'));
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build()->withFunctions(new \SqlSemantics\Schema\FunctionSignature('F', [], $text), new \SqlSemantics\Schema\FunctionSignature('f', [], $integer));
        $binder = new Binder($schema);
        $boundQuery1 = $binder->bind('SELECT "F"()');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery1);
        self::assertSame('text', $boundQuery1->outputs[0]->expression->type->name);
        $boundQuery2 = $binder->bind('SELECT f()');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery2);
        self::assertSame('integer', $boundQuery2->outputs[0]->expression->type->name);
    }


    public function testRejectsTableDeclarationsFromAnotherDialect(): void
    {
        $pg = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $mysql = (new SchemaBuilder(Dialect::MySql))->build();
        $this->expectException(InvalidArgumentException::class);
        new \SqlSemantics\Schema($mysql->dialect, $pg->tables, $mysql->defaultSchema, $mysql->grammarVersion);
    }

    public function testRejectsForeignFunctionSignaturesAtInitialConstruction(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build();
        $function = new \SqlSemantics\Schema\FunctionSignature('custom', [], \SqlSemantics\Type\TypeDescriptor::builtin(Dialect::MySql, 'integer'));
        $this->expectException(InvalidArgumentException::class);
        new \SqlSemantics\Schema($schema->dialect, [], $schema->defaultSchema, $schema->grammarVersion, functions: [$function]);
    }
}
