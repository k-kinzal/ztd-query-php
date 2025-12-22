<?php

declare(strict_types=1);

namespace Tests\Unit;

use Faker\Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\FixtureGenerator;
use SqlFixture\Hydrator\HydratorInterface;
use SqlFixture\Hydrator\ReflectionHydrator;
use SqlFixture\Platform\MySql\MySqlSchemaParser;
use SqlFixture\Platform\MySql\MySqlTypeMapper;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\SchemaParserInterface;
use SqlFixture\Schema\TableSchema;
use SqlFixture\TypeMapper\TypeMapperInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Fixture\GeneratorTestUser;

#[CoversClass(FixtureGenerator::class)]
#[UsesClass(MySqlTypeMapper::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(ReflectionHydrator::class)]
final class FixtureGeneratorTest extends TestCase
{
    #[Test]
    public function generateWithSchema(): void
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

        self::assertInstanceOf(GeneratorTestUser::class, $user);
        self::assertSame(1, $user->id);
        self::assertSame('Test', $user->name);
    }

    #[Test]
    public function getSchemaParser(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $parser = (new FixtureGenerator($faker))->getSchemaParser();
        self::assertInstanceOf(SchemaParserInterface::class, $parser);
        self::assertInstanceOf(MySqlSchemaParser::class, $parser);
    }

    #[Test]
    public function getTypeMapper(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $mapper = (new FixtureGenerator($faker))->getTypeMapper();
        self::assertInstanceOf(TypeMapperInterface::class, $mapper);
        self::assertInstanceOf(MySqlTypeMapper::class, $mapper);
    }

    #[Test]
    public function getHydrator(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $hydrator = (new FixtureGenerator($faker))->getHydrator();
        self::assertInstanceOf(HydratorInterface::class, $hydrator);
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
}
