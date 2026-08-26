<?php

declare(strict_types=1);

namespace Tests\Unit;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\RowSpec;
use SqlFixture\FixtureGenerator;
use SqlFixture\Hydrator\ConstructorHydration;
use SqlFixture\Hydrator\DeclaredTypeCast;
use SqlFixture\Hydrator\Instantiability;
use SqlFixture\Hydrator\PropertyHydration;
use SqlFixture\Hydrator\PropertyName;
use SqlFixture\Hydrator\ReflectionHydrator;
use SqlFixture\InvalidOverrideException;
use SqlFixture\Platform\MySql\MySqlColumnSample;
use SqlFixture\Platform\MySql\MySqlNumberSample;
use SqlFixture\Platform\MySql\MySqlSchemaParser;
use SqlFixture\Platform\MySql\MySqlTextSample;
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
#[UsesClass(RowSpec::class)]
#[UsesClass(ConstructorHydration::class)]
#[UsesClass(DeclaredTypeCast::class)]
#[UsesClass(Instantiability::class)]
#[UsesClass(PropertyHydration::class)]
#[UsesClass(PropertyName::class)]
#[UsesClass(MySqlColumnSample::class)]
#[UsesClass(MySqlNumberSample::class)]
#[UsesClass(MySqlSchemaParser::class)]
#[UsesClass(MySqlTextSample::class)]
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
    public function generateWithOverrides(): void
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
    public function generateSkipsAutoIncrement(): void
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
    public function generateSkipsGeneratedColumns(): void
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
    public function generateWithHydration(): void
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
    public function constructorWithCustomDependencies(): void
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
    public function anOverrideForAColumnTheTableLacksIsRejected(): void
    {
        $schema = new TableSchema('users', ['name' => new ColumnDefinition('name', 'VARCHAR', length: 255)]);

        $this->expectException(InvalidOverrideException::class);
        $this->expectExceptionMessage('Cannot override users.nmae');

        (new FixtureGenerator(Factory::create()))->generate($schema, ['nmae' => 'Ada']);
    }

    #[Test]
    public function nullIsRejectedForAColumnThatCannotHoldIt(): void
    {
        $schema = new TableSchema('users', [
            'name' => new ColumnDefinition('name', 'VARCHAR', length: 255, nullable: false),
        ]);

        $this->expectException(InvalidOverrideException::class);
        $this->expectExceptionMessage('with null: the column is NOT NULL');

        (new FixtureGenerator(Factory::create()))->generate($schema, ['name' => null]);
    }

    #[Test]
    public function nullIsAcceptedForANullableColumn(): void
    {
        $schema = new TableSchema('users', [
            'note' => new ColumnDefinition('note', 'VARCHAR', length: 255, nullable: true),
        ]);

        $data = (new FixtureGenerator(Factory::create()))->generate($schema, ['note' => null]);

        self::assertNull($data['note']);
    }

    #[Test]
    public function anOverrideForAGeneratedColumnIsRejected(): void
    {
        $schema = new TableSchema('users', [
            'slug' => new ColumnDefinition('slug', 'VARCHAR', length: 255, generated: true),
        ]);

        $this->expectException(InvalidOverrideException::class);
        $this->expectExceptionMessage('the database computes it');

        (new FixtureGenerator(Factory::create()))->generate($schema, ['slug' => 'x']);
    }

    #[Test]
    public function anOverrideForAnAutoIncrementColumnIsStillAllowed(): void
    {
        $schema = new TableSchema('users', [
            'id' => new ColumnDefinition('id', 'INT', autoIncrement: true),
        ], ['id']);

        $data = (new FixtureGenerator(Factory::create()))->generate($schema, ['id' => 100]);

        self::assertSame(100, $data['id']);
    }

    #[Test]
    public function testAssertOverridesFitSchemaPassesColumnsTheTableCanHold(): void
    {
        $schema = new TableSchema('users', [
            'id' => new ColumnDefinition('id', 'INT'),
            'name' => new ColumnDefinition('name', 'VARCHAR', length: 255, nullable: true),
        ], ['id']);

        (new FixtureGenerator(Factory::create()))->assertOverridesFitSchema($schema, ['name' => null]);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function testAssertOverridesFitSchemaRefusesAColumnTheTableDoesNotHave(): void
    {
        $schema = new TableSchema('users', ['id' => new ColumnDefinition('id', 'INT')], ['id']);

        $this->expectException(InvalidOverrideException::class);

        (new FixtureGenerator(Factory::create()))->assertOverridesFitSchema($schema, ['nmae' => 1]);
    }

    #[Test]
    public function testAssertOverridesFitSchemaRefusesAColumnTheServerFillsIn(): void
    {
        $schema = new TableSchema('users', [
            'full_name' => new ColumnDefinition('full_name', 'VARCHAR', length: 255, generated: true),
        ]);

        $this->expectException(InvalidOverrideException::class);

        (new FixtureGenerator(Factory::create()))->assertOverridesFitSchema($schema, ['full_name' => 'Ada']);
    }

    #[Test]
    public function testAssertOverridesFitSchemaRefusesANullInAColumnThatCannotBeNull(): void
    {
        $schema = new TableSchema('users', [
            'name' => new ColumnDefinition('name', 'VARCHAR', length: 255, nullable: false),
        ]);

        $this->expectException(InvalidOverrideException::class);

        (new FixtureGenerator(Factory::create()))->assertOverridesFitSchema($schema, ['name' => null]);
    }
}
