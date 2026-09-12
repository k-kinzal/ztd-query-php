<?php

declare(strict_types=1);

namespace Tests\Unit;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\FixtureGenerator;
use SqlFixture\Hydrator\ReflectionHydrator;
use SqlFixture\InvalidOverrideException;
use SqlFixture\Platform\MySql\MySqlSchemaParser;
use SqlFixture\Platform\MySql\MySqlTypeMapper;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;
use Tests\Fixture\GeneratorTestUser;

#[CoversClass(FixtureGenerator::class)]
#[UsesClass(InvalidOverrideException::class)]
#[UsesClass(MySqlTypeMapper::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(ReflectionHydrator::class)]
#[CoversClass(\SqlFixture\Fixture\Validation\OverrideValidator::class)]
#[UsesClass(\SqlFixture\Hydrator\HydrationException::class)]
#[UsesClass(\SqlFixture\Hydrator\HydratorInterface::class)]
#[UsesClass(MySqlSchemaParser::class)]
#[UsesClass(\SqlFixture\Schema\SchemaParseException::class)]
#[UsesClass(\SqlFixture\Schema\SchemaParserInterface::class)]
#[UsesClass(\SqlFixture\TypeMapper\TypeMapperInterface::class)]
#[UsesClass(\SqlFixture\Fixture\RowGeneration::class)]
#[UsesClass(\SqlFixture\Hydrator\Reflection\ConstructorHydration::class)]
#[UsesClass(\SqlFixture\Hydrator\Reflection\PropertyHydration::class)]
#[UsesClass(\SqlFixture\Hydrator\Reflection\PropertyNames::class)]
#[UsesClass(\SqlFixture\Hydrator\Reflection\ValueConversion::class)]
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
final class FixtureGeneratorTest extends TestCase
{
    #[Test]
    public function testGenerateWithSchema(): void
    {
        $schema = new TableSchema('users', [
            'id' => new ColumnDefinition('id', 'INT'),
            'name' => new ColumnDefinition('name', 'VARCHAR', length: 255),
        ], ['id']);

        $faker = Factory::create();
        $faker->seed(12345);
        $data = (new FixtureGenerator($faker))->generate($schema);

        self::assertArrayHasKey('id', $data);
        self::assertArrayHasKey('name', $data);
    }

    #[Test]
    public function testGenerateWithOverrides(): void
    {
        $schema = new TableSchema('users', [
            'id' => new ColumnDefinition('id', 'INT'),
            'name' => new ColumnDefinition('name', 'VARCHAR', length: 255),
        ], ['id']);

        $faker = Factory::create();
        $faker->seed(12345);
        $data = (new FixtureGenerator($faker))->generate($schema, ['name' => 'Override']);

        self::assertSame('Override', $data['name']);
    }

    #[Test]
    public function testGenerateSkipsAutoIncrement(): void
    {
        $schema = new TableSchema('users', [
            'id' => new ColumnDefinition('id', 'INT', autoIncrement: true),
            'name' => new ColumnDefinition('name', 'VARCHAR', length: 255),
        ], ['id']);

        $faker = Factory::create();
        $faker->seed(12345);
        $data = (new FixtureGenerator($faker))->generate($schema);

        self::assertArrayNotHasKey('id', $data);
        self::assertArrayHasKey('name', $data);
        self::assertIsString($data['name']);
    }

    #[Test]
    public function testGenerateSkipsGeneratedColumns(): void
    {
        $schema = new TableSchema('users', [
            'id' => new ColumnDefinition('id', 'INT'),
            'computed' => new ColumnDefinition('computed', 'INT', generated: true),
            'name' => new ColumnDefinition('name', 'VARCHAR', length: 255),
        ], ['id']);

        $faker = Factory::create();
        $faker->seed(12345);
        $data = (new FixtureGenerator($faker))->generate($schema);

        self::assertArrayNotHasKey('computed', $data);
        self::assertArrayHasKey('id', $data);
        self::assertArrayHasKey('name', $data);
    }

    #[Test]
    public function testGenerateWithHydration(): void
    {
        $schema = new TableSchema('users', [
            'id' => new ColumnDefinition('id', 'INT'),
            'name' => new ColumnDefinition('name', 'VARCHAR', length: 255),
        ], ['id']);

        $faker = Factory::create();
        $faker->seed(12345);
        $user = (new FixtureGenerator($faker))->generate($schema, ['id' => 1, 'name' => 'Test'], GeneratorTestUser::class);

        self::assertSame(1, $user->id);
        self::assertSame('Test', $user->name);
    }

    #[Test]
    public function testGetSchemaParser(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $parser = (new FixtureGenerator($faker))->getSchemaParser();
        self::assertInstanceOf(MySqlSchemaParser::class, $parser);
    }

    #[Test]
    public function testGetTypeMapper(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = (new FixtureGenerator($faker))->getTypeMapper();
        self::assertInstanceOf(MySqlTypeMapper::class, $mapper);
    }

    #[Test]
    public function testGetHydrator(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $hydrator = (new FixtureGenerator($faker))->getHydrator();
        self::assertInstanceOf(ReflectionHydrator::class, $hydrator);
    }

    #[Test]
    public function testConfigureWithCustomDependencies(): void
    {
        $faker = Factory::create();
        $customMapper = new MySqlTypeMapper();
        $customHydrator = new ReflectionHydrator();
        $customParser = new MySqlSchemaParser();

        $generator = new FixtureGenerator(
            $faker,
            $customMapper,
            $customHydrator,
            $customParser
        );

        self::assertSame($customMapper, $generator->getTypeMapper());
        self::assertSame($customHydrator, $generator->getHydrator());
        self::assertSame($customParser, $generator->getSchemaParser());
    }





    #[Test]
    public function testNullIsAcceptedForANullableColumn(): void
    {
        $schema = new TableSchema('users', [
            'note' => new ColumnDefinition('note', 'VARCHAR', length: 255, nullable: true),
        ]);

        $data = (new FixtureGenerator(Factory::create()))->generate($schema, ['note' => null]);

        self::assertNull($data['note']);
    }



    #[Test]
    public function testAnOverrideForAnAutoIncrementColumnIsStillAllowed(): void
    {
        $schema = new TableSchema('users', [
            'id' => new ColumnDefinition('id', 'INT', autoIncrement: true),
        ], ['id']);

        $data = (new FixtureGenerator(Factory::create()))->generate($schema, ['id' => 100]);

        self::assertSame(100, $data['id']);
    }
}
