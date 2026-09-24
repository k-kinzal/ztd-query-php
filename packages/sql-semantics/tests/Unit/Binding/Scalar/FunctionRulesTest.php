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

#[CoversClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
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
#[UsesClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[UsesClass(\SqlSemantics\Binding\FromBinder::class)]
#[UsesClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[UsesClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[CoversClass(\SqlSemantics\Binding\NullFacts::class)]
#[UsesClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[CoversClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
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
#[UsesClass(\SqlSemantics\Schema\ReferentialAction::class)]
#[UsesClass(\SqlSemantics\Model\BoundSelect::class)]
#[UsesClass(\SqlSemantics\Model\Transformation\Context::class)]
#[UsesClass(\SqlSemantics\Model\Statement\InsertStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\ConfigurationStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\TableStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\DeleteStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\MergeStatement::class)]
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
final class FunctionRulesTest extends TestCase
{
    public function testBindSeparatesAggregateAndScalarFacts(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER, name TEXT)');
        $query = (new Binder($schema))->bind('SELECT count(*), sum(n), lower(name), custom_function(n) FROM t GROUP BY name, n');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame(['bigint', 'bigint', 'text', 'unknown'], array_map(static fn ($column): string => $column->expression->type->name, $query->outputs));
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $query->outputs[0]->expression->nullability);
        self::assertSame(\SqlSemantics\Type\Nullability::MaybeNull, $query->outputs[1]->expression->nullability);
        self::assertSame('n', $query->outputs[3]->expression->lineage()[0]->column->name);
    }
    public function testTypePreservesNumericAggregateRules(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT avg(1), count(*)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame('numeric', $query->outputs[0]->expression->type->name);
        self::assertSame('bigint', $query->outputs[1]->expression->type->name);
    }

    public function testNumericAggregatePromotesIntegerSum(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT sum(1), avg(1)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame('bigint', $query->outputs[0]->expression->type->name);
        self::assertSame('numeric', $query->outputs[1]->expression->type->name);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerBuiltins')]
    public function testBindBuiltinResultTypes(string $sql, string $type, string $kind, string $nullable): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('select ' . $sql);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertSame($type, $value->type->name);
        self::assertSame($kind, $value->kind->value);
        self::assertSame($nullable, $value->nullability->value);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function providerBuiltins(): iterable
    {
        yield 'upper' => ["upper('a')", 'text', 'function', 'not-null'];
        yield 'lower null' => ['lower(NULL)', 'text', 'function', 'always-null'];
        yield 'trim' => ["trim(' a ')", 'text', 'trim', 'not-null'];
        yield 'ltrim' => ["ltrim(' a')", 'text', 'function', 'not-null'];
        yield 'rtrim' => ["rtrim('a ')", 'text', 'function', 'not-null'];
        yield 'concat' => ["concat('a','b')", 'text', 'function', 'unknown'];
        yield 'concat ws' => ["concat_ws(',', 'a','b')", 'text', 'function', 'unknown'];
        yield 'substr' => ["substr('abc',1,2)", 'text', 'function', 'unknown'];
        yield 'substring' => ["substring('abc' from 1 for 2)", 'text', 'function', 'unknown'];
        yield 'replace' => ["replace('abc','a','b')", 'text', 'function', 'unknown'];
        yield 'length' => ["length('a')", 'integer', 'function', 'not-null'];
        yield 'char length' => ["char_length('a')", 'integer', 'function', 'not-null'];
        yield 'character length' => ["character_length('a')", 'integer', 'function', 'unknown'];
        yield 'abs' => ['abs(-1)', 'integer', 'function', 'not-null'];
        yield 'round' => ['round(1.5)', 'numeric', 'function', 'not-null'];
        yield 'min' => ['min(1)', 'integer', 'aggregate', 'maybe-null'];
        yield 'max' => ['max(1)', 'integer', 'aggregate', 'maybe-null'];
        yield 'avg real' => ['avg(1::real)', 'double precision', 'aggregate', 'maybe-null'];
        yield 'sum real' => ['sum(1::real)', 'real', 'aggregate', 'maybe-null'];
        yield 'sum bigint' => ['sum(1::bigint)', 'numeric', 'aggregate', 'maybe-null'];
        yield 'string agg' => ["string_agg('a',',')", 'text', 'aggregate', 'maybe-null'];
        yield 'json agg' => ['json_agg(1)', 'json', 'aggregate', 'maybe-null'];
        yield 'jsonb agg' => ['jsonb_agg(1)', 'jsonb', 'aggregate', 'maybe-null'];
        yield 'array agg' => ['array_agg(1)', 'integer[]', 'aggregate', 'maybe-null'];
        yield 'bool and' => ['bool_and(true)', 'boolean', 'aggregate', 'maybe-null'];
        yield 'bool or' => ['bool_or(true)', 'boolean', 'aggregate', 'maybe-null'];
        yield 'every' => ['every(true)', 'boolean', 'aggregate', 'maybe-null'];
        yield 'row number' => ['row_number() over ()', 'bigint', 'window', 'not-null'];
        yield 'rank' => ['rank() over ()', 'bigint', 'window', 'not-null'];
        yield 'dense rank' => ['dense_rank() over ()', 'bigint', 'window', 'not-null'];
        yield 'ntile' => ['ntile(2) over ()', 'bigint', 'window', 'not-null'];
        yield 'lag' => ['lag(1) over ()', 'integer', 'window', 'unknown'];
        yield 'lead' => ['lead(1) over ()', 'integer', 'window', 'unknown'];
        yield 'first' => ['first_value(1) over ()', 'integer', 'window', 'unknown'];
        yield 'last' => ['last_value(1) over ()', 'integer', 'window', 'unknown'];
        yield 'nth' => ['nth_value(1,2) over ()', 'integer', 'window', 'unknown'];
        yield 'percent rank' => ['percent_rank() over ()', 'double precision', 'window', 'unknown'];
        yield 'cume dist' => ['cume_dist() over ()', 'double precision', 'window', 'unknown'];
        yield 'current date' => ['current_date', 'date', 'context-value', 'not-null'];
        yield 'current timestamp' => ['current_timestamp', 'timestamptz', 'context-value', 'not-null'];
        yield 'now' => ['now()', 'timestamp', 'function', 'unknown'];
    }



    public function testArgumentsInferParameterTypesFromRegisteredSignatures(): void
    {
        $integer = new \SqlSemantics\Type\TypeDescriptor(Dialect::PostgreSql, \SqlSemantics\Type\Identity\BuiltinIdentity::from('integer'));
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build()->withFunctions(new \SqlSemantics\Schema\FunctionSignature('f', [$integer], $integer));
        $boundQuery1 = (new Binder($schema))->bind('SELECT f($1)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery1);
        $expression = $boundQuery1->outputs[0]->expression;
        self::assertSame('integer', $expression->type->name);
        self::assertSame('integer', $expression->inputs()[0]->type->name);
        self::assertSame('implicit', $expression->inputs()[0]->spelling());
        self::assertSame('$1', $expression->inputs()[0]->inputs()[0]->spelling());
    }

    public function testNullabilityUsesDeclaredNullPropagationAndPreservesLineage(): void
    {
        $integer = new \SqlSemantics\Type\TypeDescriptor(Dialect::PostgreSql, \SqlSemantics\Type\Identity\BuiltinIdentity::from('integer'));
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')->withFunctions(new \SqlSemantics\Schema\FunctionSignature('f', [$integer], $integer, \SqlSemantics\Type\Nullability::NotNull, true));
        $query = (new Binder($schema))->bind('SELECT f(1), f(NULL), f(id) FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame(['not-null', 'always-null', 'maybe-null'], array_map(static fn ($output): string => $output->expression->nullability->value, $query->outputs));
        self::assertSame('id', $query->outputs[2]->expression->lineage()[0]->column->name);
    }

    public function testBindSupportsCustomAggregatesAndVariadicArguments(): void
    {
        $integer = new \SqlSemantics\Type\TypeDescriptor(Dialect::PostgreSql, \SqlSemantics\Type\Identity\BuiltinIdentity::from('integer'));
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build()->withFunctions(new \SqlSemantics\Schema\FunctionSignature('combine', [$integer], $integer, variadic: true, aggregate: true));
        $query = (new Binder($schema))->bind('SELECT combine(1,2,3), combine(1) OVER ()');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame('aggregate', $query->outputs[0]->expression->kind->value);
        self::assertSame('window', $query->outputs[1]->expression->kind->value);
        self::assertCount(3, $query->outputs[0]->expression->inputs());
    }


    #[\PHPUnit\Framework\Attributes\DataProvider('providerNullContracts')]
    public function testNullabilityHonorsReturnFactsForEveryArgumentState(string $input, \SqlSemantics\Type\Nullability $declared, bool $strict, \SqlSemantics\Type\Nullability $expected): void
    {
        $integer = new \SqlSemantics\Type\TypeDescriptor(Dialect::PostgreSql, \SqlSemantics\Type\Identity\BuiltinIdentity::from('integer'));
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build()->withFunctions(new \SqlSemantics\Schema\FunctionSignature('f', [$integer], $integer, $declared, $strict));
        $boundQuery1 = (new Binder($schema))->bind('SELECT f(' . $input . ')');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery1);
        self::assertSame($expected, $boundQuery1->outputs[0]->expression->nullability);
    }

    /**
     * @return iterable<array{string, \SqlSemantics\Type\Nullability, bool, \SqlSemantics\Type\Nullability}>
     */
    public static function providerNullContracts(): iterable
    {
        yield ['1', \SqlSemantics\Type\Nullability::MaybeNull, true, \SqlSemantics\Type\Nullability::MaybeNull];
        yield ['$1', \SqlSemantics\Type\Nullability::MaybeNull, true, \SqlSemantics\Type\Nullability::Unknown];
        yield ['NULL', \SqlSemantics\Type\Nullability::MaybeNull, true, \SqlSemantics\Type\Nullability::AlwaysNull];
        yield ['NULL', \SqlSemantics\Type\Nullability::NotNull, false, \SqlSemantics\Type\Nullability::NotNull];
        yield ['1', \SqlSemantics\Type\Nullability::AlwaysNull, true, \SqlSemantics\Type\Nullability::AlwaysNull];
        yield ['$1', \SqlSemantics\Type\Nullability::Unknown, true, \SqlSemantics\Type\Nullability::Unknown];
    }

    public function testBindResolvesDefaultsWithoutAQueryContext(): void
    {
        $boundQuery1 = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT lower('X')");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery1);
        $expression = $boundQuery1->outputs[0]->expression;
        self::assertInstanceOf(\SqlParser\Parser\Node::class, $expression->source);
        $bound = (new \SqlSemantics\Binding\Scalar\FunctionRules())->bind('LOWER', $expression->inputs(), $expression->source, new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql)));
        self::assertSame('text', $bound->type->name);
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $bound->nullability);
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.6.51'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.4.7'])]
    public function testBindKeepsTheSchemaOfAQualifiedMySqlFunction(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $query = $binder->bind('SELECT db.fn(1), `d b`.f()', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame('SELECT `db`.`fn`(1), `d b`.`f`()', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString(), strict: false)->toString());
    }

    public function testNameIncludesTheSchemaQualifier(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse('SELECT db.fn(1), fn(2)');
        self::assertSame('db . fn', \SqlSemantics\Ast\Tree::text(\SqlSemantics\Binding\Scalar\FunctionRules::name($tree->find('function_call_generic')[0])));
        self::assertSame('fn', \SqlSemantics\Ast\Tree::text(\SqlSemantics\Binding\Scalar\FunctionRules::name($tree->find('function_call_generic')[1])));
    }
}
