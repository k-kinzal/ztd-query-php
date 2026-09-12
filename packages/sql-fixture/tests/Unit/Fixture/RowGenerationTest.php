<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\RowGeneration as Subject;
use SqlFixture\FixtureGenerator;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(FixtureGenerator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\HydrationException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\HydratorInterface::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\ReflectionHydrator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\MySqlSchemaParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\MySqlTypeMapper::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\SchemaParseException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\SchemaParserInterface::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(TableSchema::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\TypeMapper\TypeMapperInterface::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Fixture\Validation\OverrideValidator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\Reflection\ConstructorHydration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\Reflection\PropertyHydration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\Reflection\PropertyNames::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\Reflection\ValueConversion::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\InvalidOverrideException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\ColumnParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\DefaultExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\DefinitionIntegrity::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\TableDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\TypeParameters::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Value\ColumnGenerator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Value\DecimalGenerator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Value\GeometryGenerator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Value\IntegerGenerator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Value\NumericGenerator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Value\SpatialGenerator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Value\StringGenerator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Value\TextGenerator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TypeShape::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\TypeMapper\ParagraphGenerator::class)]
final class RowGenerationTest extends TestCase
{
    public function testGenerateAppliesExplicitValuesAndOmitsGeneratedColumns(): void
    {
        $generator = new FixtureGenerator(Factory::create());
        $schema = new TableSchema('users', ['id' => new ColumnDefinition('id', 'INT', autoIncrement: true), 'name' => new ColumnDefinition('name', 'TEXT')]);
        self::assertSame(['name' => 'Alice'], $generator->generate($schema, ['name' => 'Alice']));
    }
}
