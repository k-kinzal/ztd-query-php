<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Definition;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\Declaration\IndexElement;
use SqlSemantics\Ast\Definition\IndexKeys;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
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
#[\PHPUnit\Framework\Attributes\UsesClass(DialectParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\TokenGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Tree::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ColumnReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\SchemaReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Identifiers::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\StatementList::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\ReferenceReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(IndexKeys::class)]
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
final class IndexKeysTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('providerDialects')]
    public function testReadPreservesKeyOrderAndExpressionsAcrossDialects(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE t(id INTEGER, name TEXT)', 'CREATE INDEX ix ON t(name DESC, (id+1))');
        $keys = $schema->tables[0]->indexes[0]->elements;
        self::assertSame('name', $keys[0]->value()->columnBinding()?->column->name);
        self::assertSame('DESC', $keys[0]->direction?->value);
        self::assertInstanceOf(\SqlSemantics\Schema\Index\ExpressionKey::class, $keys[1]);
    }

    public function testElementReadsCollationOperatorClassAndPrefix(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(name TEXT); CREATE INDEX ix ON t(name COLLATE "C" text_pattern_ops DESC NULLS LAST)');
        $key = $schema->tables[0]->indexes[0]->elements[0];
        self::assertSame(['C'], $key->collation?->parts);
        self::assertSame(['text_pattern_ops'], $key->operatorClass?->parts);
        self::assertSame('LAST', $key->nulls?->value);
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(name VARCHAR(10), KEY ix(name(5) DESC))');
        self::assertInstanceOf(\SqlSemantics\Schema\Index\ColumnKey::class, $schema->tables[0]->indexes[0]->elements[0]);
        self::assertSame(5, $schema->tables[0]->indexes[0]->elements[0]->prefixLength);
    }

    /**
     * @return iterable<array{Dialect}>
     */
    public static function providerDialects(): iterable
    {
        foreach (Dialect::cases() as $dialect) {
            yield [$dialect];
        }
    }

    public function testValueDistinguishesAQuotedColumnFromAStringExpression(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build("CREATE TABLE t(name TEXT); CREATE INDEX ix ON t(name, 'name')");
        $keys = $schema->tables[0]->indexes[0]->elements;
        self::assertSame('name', $keys[0]->value()->columnBinding()?->column->name);
        self::assertInstanceOf(\SqlSemantics\Schema\Index\ExpressionKey::class, $keys[1]);
    }


    public function testValueReadsSqliteCollationAndExpressionKeys(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(name TEXT); CREATE INDEX ix ON t(name COLLATE nocase DESC, length(name))');
        $keys = $schema->tables[0]->indexes[0]->elements;
        self::assertSame(['nocase'], $keys[0]->collation?->parts);
        self::assertSame('name', $keys[0]->value()->columnBinding()?->column->name);
        self::assertSame('DESC', $keys[0]->direction?->value);
        self::assertNull($keys[0]->operatorClass);
        self::assertInstanceOf(\SqlSemantics\Schema\Index\ColumnKey::class, $keys[0]);
        self::assertNull($keys[0]->prefixLength);
        self::assertInstanceOf(\SqlSemantics\Schema\Index\ExpressionKey::class, $keys[1]);
    }

    public function testReadKeepsNestedAggregateOrderingInsideItsExpression(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INTEGER)'));
        $statement = $binder->bind('CREATE INDEX ix ON t((sum(a ORDER BY a ASC NULLS LAST)), (sum(a ORDER BY a DESC) + 1) ASC)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateIndexStatement::class, $statement);
        self::assertNull($statement->index->definition->elements[0]->direction);
        self::assertNull($statement->index->definition->elements[0]->nulls);
        self::assertSame('ASC', $statement->index->definition->elements[1]->direction?->value);
        $rebound = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateIndexStatement::class, $rebound);
        self::assertNull($rebound->index->definition->elements[0]->direction);
        self::assertNull($rebound->index->definition->elements[0]->nulls);
        self::assertSame('ASC', $rebound->index->definition->elements[1]->direction?->value);
    }

    /**
     * @return array<string, array{Dialect, string, list<array{?string, ?string, ?string, ?string, list<string>, list<string>, ?int}>}>
     */
    public static function providerParsedKeys(): array
    {
        return [
            'sqlite sort list' => [Dialect::Sqlite, 'CREATE INDEX i ON t(a collate nocase desc, b asc, c)', [['a', null, 'DESC', null, ['nocase'], [], null], ['b', null, 'ASC', null, [], [], null], ['c', null, null, null, [], [], null]]],
            'mysql prefix and expression' => [Dialect::MySql, 'CREATE INDEX i ON t(a(10) desc, (b + 1), c)', [['a', null, 'DESC', null, [], [], 10], [null, 'b + 1', null, null, [], [], null], ['c', null, null, null, [], [], null]]],
            'postgresql modifiers' => [Dialect::PostgreSql, 'CREATE INDEX i ON t (a collate "C" text_pattern_ops desc nulls first, (b + 1), c)', [['a', null, 'DESC', 'FIRST', ['C'], ['text_pattern_ops'], null], [null, 'b + 1', null, null, [], [], null], ['c', null, null, null, [], [], null]]],
            'no key list' => [Dialect::PostgreSql, 'CREATE TABLE t(a INT)', []],
        ];
    }

    /**
     * @param list<array{?string, ?string, ?string, ?string, list<string>, list<string>, ?int}> $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerParsedKeys')]
    public function testReadReadsEachParsedKey(Dialect $dialect, string $sql, array $expected): void
    {
        $keys = IndexKeys::read((new DialectParser($dialect))->parse($sql), new Identifiers($dialect));
        self::assertSame($expected, array_map(static fn (IndexElement $key): array => [$key->column, $key->expression === null ? null : Tree::text($key->expression), $key->direction, $key->nulls, $key->collation, $key->operatorClass, $key->prefixLength], $keys));
    }

    public function testValueReadsACollatedColumn(): void
    {
        $key = Tree::outer((new DialectParser(Dialect::Sqlite))->parse('CREATE INDEX i ON t(a collate nocase)'), ['sortlist'])[0];
        [$column, $expression, $collation] = IndexKeys::value($key, new Identifiers(Dialect::Sqlite));
        self::assertSame('a', $column);
        self::assertNull($expression);
        self::assertSame(['nocase'], $collation);
    }

    public function testElementReadsAParsedPrefixKey(): void
    {
        $key = Tree::outer((new DialectParser(Dialect::MySql))->parse('CREATE INDEX i ON t(a(10) DESC)'), ['key_part_with_expression'])[0];
        $element = IndexKeys::element($key, new Identifiers(Dialect::MySql));
        self::assertSame(['a', 10, 'DESC'], [$element->column, $element->prefixLength, $element->direction]);
    }


    public function testElementReadsAParameterizedOperatorClass(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a int)'));
        $statement = $binder->bind('CREATE INDEX ON t (a COLLATE "C" int4_ops (x = 1) DESC)');
        self::assertSame('CREATE INDEX ON "public"."t"("a" COLLATE "C" "int4_ops"("x" = 1) DESC)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
