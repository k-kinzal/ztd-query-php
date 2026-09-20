<?php

declare(strict_types=1);

namespace Tests\Unit\Hydrator\Reflection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlFixture\Fixture\GenerationRun;
use SqlFixture\Fixture\RowSpec;
use SqlFixture\FixtureGenerator;
use SqlFixture\Hydrator\Reflection\PropertyHydration as Subject;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Plan\FixturePlan;
use SqlFixture\Plan\Relation;
use SqlFixture\Plan\RelationKind;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\StaticSchemaResolver;
use SqlFixture\Schema\TableSchema;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(FixtureGenerator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Fixture\FixtureSet::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(GenerationRun::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Fixture\OverrideRows::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(RowSpec::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Fixture\TableOverrides::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\HydrationException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\HydratorInterface::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\ReflectionHydrator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\Reflection\PropertyNames::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\Reflection\ValueConversion::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ColumnRef::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(FixturePlan::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanPrinter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanSyntaxException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Relation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(RelationKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\RelationSide::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\MySqlSchemaParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\MySqlTypeMapper::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\SchemaNotFoundException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\SchemaParseException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\SchemaParserInterface::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\SchemaResolverInterface::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(StaticSchemaResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TableIdentifier::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(TableSchema::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\TypeMapper\TypeMapperInterface::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Fixture\RowGeneration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Fixture\Validation\OverrideValidator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\Reflection\ConstructorHydration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\InvalidOverrideException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Parsing\PlanStatements::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Parsing\RelationCursor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Parsing\RelationReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanStructureException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Printing\PlanTables::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Printing\RelationGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Printing\StatementPrinter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Validation\PlanValidation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Validation\TableName::class)]
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\TableDefinitionInput::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\Exception\ClassNotFoundException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\Exception\MissingConstructorArgumentException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Fixture\Exception\UnknownOverrideColumnException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Fixture\Exception\NullOverrideException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Fixture\Exception\GeneratedColumnOverrideException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\EmptyPlanException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\EmptyTableNameException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\MissingEndpointColumnsException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\InvalidTableNameException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\UnbalancedBracketsException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\UnexpectedPlanTokenException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\UnsupportedManyToManyException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\CompositeArityMismatchException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\DuplicateColumnBindingException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\CyclicDependencyException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\UnboundedSelfReferenceException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\Exception\InvalidSqlException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\Exception\ExpectedCreateTableException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\Exception\MissingColumnDefinitionsException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\TypeMapper\IntegerRange::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\TypeMapper\IntegerWidth::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\TypeMapper\DecimalRange::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\Reflection\ConversionTarget::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Choice\ChoiceValidation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Choice\PlanContents::class)]
final class PropertyHydrationTest extends TestCase
{
    /**
     * @throws ReflectionException
     */
    public function testHydrateViaPropertiesMapsSnakeCaseToCamelCase(): void
    {
        $target = new class () {
            public string $userName = '';
        };
        $object = (new \SqlFixture\Hydrator\ReflectionHydrator())->hydrate(['user_name' => 'Alice', 'unknown' => 'ignored'], $target::class);
        self::assertSame('Alice', $object->userName);
    }

    /**
     * @throws ReflectionException
     */
    public function testSetPropertyConvertsTheDatabaseValue(): void
    {
        $target = new class () {
            public string $userName = '';
        };
        $object = (new \SqlFixture\Hydrator\ReflectionHydrator())->hydrate(['user_name' => 42], $target::class);
        self::assertSame('42', $object->userName);
    }
}
