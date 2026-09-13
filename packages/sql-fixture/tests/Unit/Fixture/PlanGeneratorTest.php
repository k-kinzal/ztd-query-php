<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\FixtureSet;
use SqlFixture\Fixture\GenerationRun;
use SqlFixture\Fixture\PlanGenerator;
use SqlFixture\Fixture\PlanSchemaException;
use SqlFixture\Fixture\PlanSchemaValidator;
use SqlFixture\Fixture\RowSpec;
use SqlFixture\Fixture\TableOverrides;
use SqlFixture\FixtureGenerator;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Plan\FixturePlan;
use SqlFixture\Plan\PlanParser;
use SqlFixture\Plan\PlanPrinter;
use SqlFixture\Plan\Relation;
use SqlFixture\Plan\RelationKind;
use SqlFixture\Plan\RelationSide;
use SqlFixture\Platform\MySql\MySqlSchemaParser;
use SqlFixture\Platform\MySql\MySqlTypeMapper;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\SchemaNotFoundException;
use SqlFixture\Schema\StaticSchemaResolver;
use SqlFixture\Schema\TableSchema;

#[CoversClass(PlanGenerator::class)]
#[UsesClass(GenerationRun::class)]
#[UsesClass(PlanSchemaValidator::class)]
#[UsesClass(PlanSchemaException::class)]
#[UsesClass(RowSpec::class)]
#[UsesClass(FixtureSet::class)]
#[UsesClass(TableOverrides::class)]
#[UsesClass(FixtureGenerator::class)]
#[UsesClass(FixturePlan::class)]
#[UsesClass(PlanParser::class)]
#[UsesClass(PlanPrinter::class)]
#[UsesClass(Relation::class)]
#[UsesClass(ColumnRef::class)]
#[UsesClass(RelationKind::class)]
#[UsesClass(RelationSide::class)]
#[UsesClass(MySqlSchemaParser::class)]
#[UsesClass(MySqlTypeMapper::class)]
#[UsesClass(StaticSchemaResolver::class)]
#[UsesClass(SchemaNotFoundException::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(ColumnDefinition::class)]
#[CoversClass(\SqlFixture\Fixture\Generation\ConnectedTables::class)]
#[CoversClass(\SqlFixture\Fixture\Generation\OverrideSpecs::class)]
#[CoversClass(\SqlFixture\Fixture\Generation\RelationCounts::class)]
#[CoversClass(\SqlFixture\Fixture\Generation\RelationProjection::class)]
#[CoversClass(\SqlFixture\Fixture\Generation\RowMaterializer::class)]
#[UsesClass(\SqlFixture\Fixture\OverrideRows::class)]
#[UsesClass(\SqlFixture\Fixture\RowGeneration::class)]
#[UsesClass(\SqlFixture\Hydrator\HydrationException::class)]
#[UsesClass(\SqlFixture\Hydrator\HydratorInterface::class)]
#[UsesClass(\SqlFixture\Hydrator\ReflectionHydrator::class)]
#[UsesClass(\SqlFixture\Plan\PlanSyntaxException::class)]
#[UsesClass(\SqlFixture\Schema\SchemaParseException::class)]
#[UsesClass(\SqlFixture\Schema\SchemaParserInterface::class)]
#[UsesClass(\SqlFixture\Schema\SchemaResolverInterface::class)]
#[UsesClass(\SqlFixture\Schema\TableIdentifier::class)]
#[UsesClass(\SqlFixture\TypeMapper\TypeMapperInterface::class)]
#[UsesClass(\SqlFixture\Fixture\Validation\EndpointValidator::class)]
#[UsesClass(\SqlFixture\Fixture\Validation\OverrideValidator::class)]
#[UsesClass(\SqlFixture\Hydrator\Reflection\ConstructorHydration::class)]
#[UsesClass(\SqlFixture\Hydrator\Reflection\PropertyHydration::class)]
#[UsesClass(\SqlFixture\Hydrator\Reflection\PropertyNames::class)]
#[UsesClass(\SqlFixture\Hydrator\Reflection\ValueConversion::class)]
#[UsesClass(\SqlFixture\InvalidOverrideException::class)]
#[UsesClass(\SqlFixture\Plan\Parsing\PlanStatements::class)]
#[UsesClass(\SqlFixture\Plan\Parsing\RelationCursor::class)]
#[UsesClass(\SqlFixture\Plan\Parsing\RelationReader::class)]
#[UsesClass(\SqlFixture\Plan\PlanStructureException::class)]
#[UsesClass(\SqlFixture\Plan\Printing\PlanTables::class)]
#[UsesClass(\SqlFixture\Plan\Printing\RelationGroups::class)]
#[UsesClass(\SqlFixture\Plan\Printing\StatementPrinter::class)]
#[UsesClass(\SqlFixture\Plan\Validation\PlanValidation::class)]
#[UsesClass(\SqlFixture\Plan\Validation\TableName::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\ColumnParser::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\DefaultExpression::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\DefinitionIntegrity::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\TableDefinition::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\TypeParameters::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\ColumnGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\DecimalGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\GeometryGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\IntegerGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\NumericGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\SpatialGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\StringGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\TextGenerator::class)]
#[UsesClass(\SqlFixture\Schema\TypeShape::class)]
#[UsesClass(\SqlFixture\TypeMapper\ParagraphGenerator::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\TableDefinitionInput::class)]
#[UsesClass(\SqlFixture\Hydrator\Exception\ClassNotFoundException::class)]
#[UsesClass(\SqlFixture\Hydrator\Exception\MissingConstructorArgumentException::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\UnknownOverrideColumnException::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\NullOverrideException::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\GeneratedColumnOverrideException::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\GeneratedColumnReferenceException::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\MissingRelationValueException::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\UnknownPlanColumnException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\EmptyPlanException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\EmptyTableNameException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\MissingEndpointColumnsException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\InvalidTableNameException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnbalancedBracketsException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnexpectedPlanTokenException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnsupportedManyToManyException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\CompositeArityMismatchException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\DuplicateColumnBindingException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\CyclicDependencyException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnboundedSelfReferenceException::class)]
#[UsesClass(\SqlFixture\Schema\Exception\InvalidSqlException::class)]
#[UsesClass(\SqlFixture\Schema\Exception\ExpectedCreateTableException::class)]
#[UsesClass(\SqlFixture\Schema\Exception\MissingColumnDefinitionsException::class)]
#[UsesClass(\SqlFixture\TypeMapper\IntegerRange::class)]
#[UsesClass(\SqlFixture\TypeMapper\IntegerWidth::class)]
#[UsesClass(\SqlFixture\TypeMapper\DecimalRange::class)]
#[UsesClass(\SqlFixture\Hydrator\Reflection\ConversionTarget::class)]
final class PlanGeneratorTest extends TestCase
{
    #[Test]
    public function testGenerateTheTableThePlanIsAboutGetsOneRow(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_detail', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'product_id' => new ColumnDefinition('product_id', 'INT', nullable: false, unsigned: true),
                'quantity' => new ColumnDefinition('quantity', 'INT', nullable: false),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(
            FixturePlan::from('order.id < order_detail.order_id')
        );

        self::assertArrayHasKey('id', (array) $set->row('order'));
    }

    #[Test]
    public function testTheSubjectIsOneRowEvenWhenSomethingReferencesIt(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('customer', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'name' => new ColumnDefinition('name', 'VARCHAR', nullable: false, length: 255),
                'tier' => new ColumnDefinition('tier', 'ENUM', nullable: false, enumValues: ['gold', 'silver']),
            ], ['id']),
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_detail', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'product_id' => new ColumnDefinition('product_id', 'INT', nullable: false, unsigned: true),
                'quantity' => new ColumnDefinition('quantity', 'INT', nullable: false),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(
            FixturePlan::from('order.id < order_detail.order_id, order.customer_id > customer.id')
        );

        self::assertArrayHasKey('id', (array) $set->row('order'));
        self::assertArrayHasKey('id', (array) $set->row('customer'));
    }

    #[Test]
    public function testChildRowsCarryTheParentKey(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_detail', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'product_id' => new ColumnDefinition('product_id', 'INT', nullable: false, unsigned: true),
                'quantity' => new ColumnDefinition('quantity', 'INT', nullable: false),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(
            FixturePlan::from('order.id < order_detail.order_id'),
            ['order' => ['id' => 100], 'order_detail' => 3]
        );

        self::assertCount(3, $set->rows('order_detail'));
        self::assertSame([100, 100, 100], array_column($set->rows('order_detail'), 'order_id'));
    }

    #[Test]
    public function testAParentIsGeneratedAndLinkedTo(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('customer', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'name' => new ColumnDefinition('name', 'VARCHAR', nullable: false, length: 255),
                'tier' => new ColumnDefinition('tier', 'ENUM', nullable: false, enumValues: ['gold', 'silver']),
            ], ['id']),
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(
            FixturePlan::from('order.customer_id > customer.id')
        );

        self::assertSame($set->rows('customer')[0]['id'], $set->rows('order')[0]['customer_id']);
    }

    #[Test]
    public function testACountOfZeroGeneratesNoRows(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_detail', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'product_id' => new ColumnDefinition('product_id', 'INT', nullable: false, unsigned: true),
                'quantity' => new ColumnDefinition('quantity', 'INT', nullable: false),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(
            FixturePlan::from('order.id < order_detail.order_id'),
            ['order_detail' => []]
        );

        self::assertSame([], $set->rows('order_detail'));
    }

    #[Test]
    public function testOneSetOfValuesAppliesToEveryGeneratedRow(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_detail', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'product_id' => new ColumnDefinition('product_id', 'INT', nullable: false, unsigned: true),
                'quantity' => new ColumnDefinition('quantity', 'INT', nullable: false),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(
            FixturePlan::from('order.id < order_detail.order_id'),
            ['order_detail' => ['quantity' => 2]]
        );

        self::assertNotSame([], $set->rows('order_detail'));
        $quantities = array_column($set->rows('order_detail'), 'quantity');

        self::assertSame([2], array_values(array_unique($quantities, SORT_REGULAR)));
    }

    #[Test]
    public function testAListGivesOneRowPerEntry(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_detail', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'product_id' => new ColumnDefinition('product_id', 'INT', nullable: false, unsigned: true),
                'quantity' => new ColumnDefinition('quantity', 'INT', nullable: false),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(
            FixturePlan::from('order.id < order_detail.order_id'),
            ['order_detail' => [['quantity' => 1], ['quantity' => 2]]]
        );

        self::assertSame([1, 2], array_column($set->rows('order_detail'), 'quantity'));
    }

    #[Test]
    public function testAnUnmentionedChildIsStillGenerated(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_detail', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'product_id' => new ColumnDefinition('product_id', 'INT', nullable: false, unsigned: true),
                'quantity' => new ColumnDefinition('quantity', 'INT', nullable: false),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(
            FixturePlan::from('order.id < order_detail.order_id')
        );

        self::assertGreaterThanOrEqual(1, count($set->rows('order_detail')));
    }

    #[Test]
    public function testAnOptionalChildMayGenerateNoneAtAll(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_detail', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'product_id' => new ColumnDefinition('product_id', 'INT', nullable: false, unsigned: true),
                'quantity' => new ColumnDefinition('quantity', 'INT', nullable: false),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $counts = array_map(static function (int $seed) use ($faker, $generator): int {
            $faker->seed($seed);
            return count($generator->generate(FixturePlan::from('order.id <? order_detail.order_id'))->rows('order_detail'));
        }, range(1, 40));

        self::assertContains(0, $counts);
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('providerGenerationSeeds')]
    public function testARequiredChildNeverGeneratesNone(int $seed): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_detail', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'product_id' => new ColumnDefinition('product_id', 'INT', nullable: false, unsigned: true),
                'quantity' => new ColumnDefinition('quantity', 'INT', nullable: false),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed($seed);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(FixturePlan::from('order.id < order_detail.order_id'));

        self::assertGreaterThanOrEqual(1, count($set->rows('order_detail')));
    }

    #[Test]
    public function testAOneToOneChildIsASingleRow(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_shipping', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'carrier' => new ColumnDefinition('carrier', 'VARCHAR', nullable: false, length: 50),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(
            FixturePlan::from('order.id - order_shipping.order_id'),
            ['order' => ['id' => 5]]
        );

        self::assertSame(5, $set->rows('order_shipping')[0]['order_id']);
    }

    #[Test]
    public function testAnOptionalOneToOneAskedForNoneIsNull(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_shipping', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'carrier' => new ColumnDefinition('carrier', 'VARCHAR', nullable: false, length: 50),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(
            FixturePlan::from('order.id -? order_shipping.order_id'),
            ['order_shipping' => []]
        );

        self::assertNull($set->row('order_shipping'));
    }

    #[Test]
    public function testEveryChildOfAListGetsItsOwnParent(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_detail', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'product_id' => new ColumnDefinition('product_id', 'INT', nullable: false, unsigned: true),
                'quantity' => new ColumnDefinition('quantity', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('product', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'name' => new ColumnDefinition('name', 'VARCHAR', nullable: false, length: 255),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(
            FixturePlan::from('order.id < order_detail.order_id, order_detail.product_id > product.id'),
            ['order_detail' => 3]
        );

        self::assertCount(3, $set->rows('product'));
        self::assertSame(
            array_column($set->rows('product'), 'id'),
            array_column($set->rows('order_detail'), 'product_id')
        );
    }

    #[Test]
    public function testFixingTheLinkingColumnLeavesTheParentAlone(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('customer', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'name' => new ColumnDefinition('name', 'VARCHAR', nullable: false, length: 255),
                'tier' => new ColumnDefinition('tier', 'ENUM', nullable: false, enumValues: ['gold', 'silver']),
            ], ['id']),
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(
            FixturePlan::from('order.customer_id > customer.id'),
            ['order' => ['customer_id' => 77]]
        );

        self::assertSame(77, $set->rows('order')[0]['customer_id']);
        self::assertNull($set->row('customer'));
    }

    #[Test]
    public function testTablesThatStandAloneAreGeneratedToo(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_detail', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'product_id' => new ColumnDefinition('product_id', 'INT', nullable: false, unsigned: true),
                'quantity' => new ColumnDefinition('quantity', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('audit_log', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'message' => new ColumnDefinition('message', 'VARCHAR', nullable: false, length: 255),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(
            FixturePlan::from('order.id < order_detail.order_id, audit_log')
        );

        self::assertArrayHasKey('message', (array) $set->row('audit_log'));
    }

    #[Test]
    public function testEntriesComeBackInTheOrderThePlanNamesThem(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('customer', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'name' => new ColumnDefinition('name', 'VARCHAR', nullable: false, length: 255),
                'tier' => new ColumnDefinition('tier', 'ENUM', nullable: false, enumValues: ['gold', 'silver']),
            ], ['id']),
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_detail', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'product_id' => new ColumnDefinition('product_id', 'INT', nullable: false, unsigned: true),
                'quantity' => new ColumnDefinition('quantity', 'INT', nullable: false),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(
            FixturePlan::from('order.id < order_detail.order_id, order.customer_id > customer.id'),
            ['order' => ['id' => 1], 'order_detail' => 2]
        );

        [$order, $details, $customer] = $set;

        self::assertSame(1, ((array) $order)['id']);
        self::assertCount(2, (array) $details);
        self::assertArrayHasKey('tier', (array) $customer);
    }

    #[Test]
    public function testOverridesMayBeGivenAsTableOverrides(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_detail', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'product_id' => new ColumnDefinition('product_id', 'INT', nullable: false, unsigned: true),
                'quantity' => new ColumnDefinition('quantity', 'INT', nullable: false),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(
            FixturePlan::from('order.id < order_detail.order_id'),
            ['order' => TableOverrides::of(['status' => 'paid'])]
        );

        self::assertSame('paid', $set->rows('order')[0]['status']);
    }

    #[Test]
    public function testAnUnknownTableIsReported(): void
    {
        $schemas = new StaticSchemaResolver([
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $this->expectException(SchemaNotFoundException::class);
        $this->expectExceptionMessage('Schema not found for table: nope');

        $generator->generate(FixturePlan::from('nope'));
    }

    #[Test]
    public function testAPlanNamingAColumnTheTableLacksIsRejectedBeforeGenerating(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_detail', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'product_id' => new ColumnDefinition('product_id', 'INT', nullable: false, unsigned: true),
                'quantity' => new ColumnDefinition('quantity', 'INT', nullable: false),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $this->expectException(\SqlFixture\Fixture\Exception\UnknownPlanColumnException::class);
        $this->expectExceptionMessage('order_detail has no column oder_id');

        $generator->generate(FixturePlan::from('order.id < order_detail.oder_id'));
    }

    #[Test]
    public function testTheSubjectHonoursACountItWasGiven(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_detail', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'product_id' => new ColumnDefinition('product_id', 'INT', nullable: false, unsigned: true),
                'quantity' => new ColumnDefinition('quantity', 'INT', nullable: false),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(
            FixturePlan::from('order.id < order_detail.order_id'),
            ['order' => 2, 'order_detail' => 1]
        );

        self::assertCount(2, $set->rows('order'));
    }

    #[Test]
    public function testARowCarriesBothItsInheritedKeyAndItsOwnParentKey(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_detail', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'product_id' => new ColumnDefinition('product_id', 'INT', nullable: false, unsigned: true),
                'quantity' => new ColumnDefinition('quantity', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('product', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'name' => new ColumnDefinition('name', 'VARCHAR', nullable: false, length: 255),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(
            FixturePlan::from('order.id < order_detail.order_id, order_detail.product_id > product.id'),
            ['order' => ['id' => 42], 'order_detail' => 1]
        );

        $detail = $set->rows('order_detail')[0];

        self::assertSame(42, $detail['order_id']);
        self::assertSame($set->rows('product')[0]['id'], $detail['product_id']);
    }

    #[Test]
    public function testEveryOtherRelationOfAParentIsStillFollowed(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('customer', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'name' => new ColumnDefinition('name', 'VARCHAR', nullable: false, length: 255),
                'tier' => new ColumnDefinition('tier', 'ENUM', nullable: false, enumValues: ['gold', 'silver']),
            ], ['id']),
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('audit_log', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'message' => new ColumnDefinition('message', 'VARCHAR', nullable: false, length: 255),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(
            FixturePlan::from('order.customer_id > customer.id, customer.id < audit_log.customer_id')
        );

        self::assertNotSame([], $set->rows('audit_log'));
    }

    #[Test]
    public function testAnOptionalParentIsGeneratedWhenTheCallerAsksForIt(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('customer', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'name' => new ColumnDefinition('name', 'VARCHAR', nullable: false, length: 255),
                'tier' => new ColumnDefinition('tier', 'ENUM', nullable: false, enumValues: ['gold', 'silver']),
            ], ['id']),
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(
            FixturePlan::from('order.customer_id >? customer.id'),
            ['customer' => ['tier' => 'gold']]
        );

        self::assertSame('gold', $set->rows('customer')[0]['tier']);
        self::assertSame($set->rows('customer')[0]['id'], $set->rows('order')[0]['customer_id']);
    }

    #[Test]
    public function testACompositeRelationCarriesEveryColumn(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('shop_order', [
                'shop_id' => new ColumnDefinition('shop_id', 'INT', nullable: false, unsigned: true),
                'no' => new ColumnDefinition('no', 'INT', nullable: false, unsigned: true),
            ], ['shop_id', 'no']),
            new TableSchema('shop_order_line', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'shop_id' => new ColumnDefinition('shop_id', 'INT', nullable: false, unsigned: true),
                'order_no' => new ColumnDefinition('order_no', 'INT', nullable: false, unsigned: true),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(
            FixturePlan::from('shop_order.(shop_id, no) < shop_order_line.(shop_id, order_no)'),
            ['shop_order' => ['shop_id' => 7, 'no' => 9], 'shop_order_line' => 1]
        );

        $line = $set->rows('shop_order_line')[0];

        self::assertSame(7, $line['shop_id']);
        self::assertSame(9, $line['order_no']);
    }

    #[Test]
    public function testAOneToOneChildIsGeneratedExactlyOnce(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_shipping', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'carrier' => new ColumnDefinition('carrier', 'VARCHAR', nullable: false, length: 50),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(
            FixturePlan::from('order.id - order_shipping.order_id')
        );

        self::assertCount(1, $set->rows('order_shipping'));
    }

    #[Test]
    public function testKeysAreStoodInForEveryColumnARelationReads(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('twin', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'other_id' => new ColumnDefinition('other_id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
            ], ['id']),
            new TableSchema('twin_child', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'twin_id' => new ColumnDefinition('twin_id', 'INT', nullable: false, unsigned: true),
            ], ['id']),
            new TableSchema('twin_other', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'twin_other_id' => new ColumnDefinition('twin_other_id', 'INT', nullable: false, unsigned: true),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(
            FixturePlan::from('twin.id < twin_child.twin_id, twin.other_id < twin_other.twin_other_id')
        );

        self::assertSame(1, $set->rows('twin')[0]['id']);
        self::assertSame(1, $set->rows('twin')[0]['other_id']);
    }

    #[Test]
    public function testARequiredParentIsGeneratedWhetherOrNotItWasAskedFor(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('customer', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'name' => new ColumnDefinition('name', 'VARCHAR', nullable: false, length: 255),
                'tier' => new ColumnDefinition('tier', 'ENUM', nullable: false, enumValues: ['gold', 'silver']),
            ], ['id']),
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(FixturePlan::from('order.customer_id > customer.id'));

        self::assertNotSame([], $set->rows('customer'));
    }

    #[Test]
    public function testAnOptionalParentNobodyAskedForIsLeftOut(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('customer', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'name' => new ColumnDefinition('name', 'VARCHAR', nullable: false, length: 255),
                'tier' => new ColumnDefinition('tier', 'ENUM', nullable: false, enumValues: ['gold', 'silver']),
            ], ['id']),
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(FixturePlan::from('order.customer_id >? customer.id'));

        self::assertSame([], $set->rows('customer'));
    }

    #[Test]
    public function testAnUnboundedChildCountCanExceedItsMinimum(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_detail', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'product_id' => new ColumnDefinition('product_id', 'INT', nullable: false, unsigned: true),
                'quantity' => new ColumnDefinition('quantity', 'INT', nullable: false),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $counts = array_map(static function (int $seed) use ($faker, $generator): int {
            $faker->seed($seed);
            return count($generator->generate(FixturePlan::from('order.id < order_detail.order_id'))->rows('order_detail'));
        }, range(1, 40));

        self::assertGreaterThan(1, max($counts));
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('providerGenerationSeeds')]
    public function testAOneToOneChildIsNeverGeneratedMoreThanOnce(int $seed): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_shipping', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'carrier' => new ColumnDefinition('carrier', 'VARCHAR', nullable: false, length: 50),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed($seed);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(FixturePlan::from('order.id - order_shipping.order_id'));

        self::assertCount(1, $set->rows('order_shipping'));
    }

    #[Test]
    public function testATableReachableOnlyBackwardsAlongARelationIsStillPartOfTheWalk(): void
    {
        $schemas = new StaticSchemaResolver([
            new TableSchema('order', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'customer_id' => new ColumnDefinition('customer_id', 'INT', nullable: false, unsigned: true),
                'status' => new ColumnDefinition('status', 'VARCHAR', nullable: false, length: 20),
                'total' => new ColumnDefinition('total', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('order_detail', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'order_id' => new ColumnDefinition('order_id', 'INT', nullable: false, unsigned: true),
                'product_id' => new ColumnDefinition('product_id', 'INT', nullable: false, unsigned: true),
                'quantity' => new ColumnDefinition('quantity', 'INT', nullable: false),
            ], ['id']),
            new TableSchema('product', [
                'id' => new ColumnDefinition('id', 'INT', nullable: false, unsigned: true, autoIncrement: true),
                'name' => new ColumnDefinition('name', 'VARCHAR', nullable: false, length: 255),
            ], ['id']),
        ]);
        $faker = \Faker\Factory::create();
        $faker->seed(20260101);
        $generator = new PlanGenerator($schemas, new FixtureGenerator($faker), $faker);
        $set = $generator->generate(
            FixturePlan::from('order.id < order_detail.order_id, product.id ?< order_detail.product_id')
        );

        self::assertSame([], $set->rows('product'));
    }
    /**
     * @return list<array{int}>
     */
    public static function providerGenerationSeeds(): array
    {
        return array_map(static fn (int $seed): array => [$seed], range(1, 40));
    }
}
