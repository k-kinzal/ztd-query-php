<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture\Generation;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\Generation\RowMaterializer as Subject;
use SqlFixture\Fixture\GenerationRun;
use SqlFixture\Fixture\RowSpec;
use SqlFixture\FixtureGenerator;
use SqlFixture\Plan\FixturePlan;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\StaticSchemaResolver;
use SqlFixture\Schema\TableSchema;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(FixtureGenerator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Fixture\FixtureSet::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(GenerationRun::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Fixture\Generation\RelationCounts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Fixture\Generation\RelationProjection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Fixture\OverrideRows::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Fixture\PlanSchemaException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Fixture\RowGeneration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(RowSpec::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Fixture\TableOverrides::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\HydrationException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\HydratorInterface::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\ReflectionHydrator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\ColumnRef::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(FixturePlan::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanPrinter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanSyntaxException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Relation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\RelationKind::class)]
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Fixture\Validation\OverrideValidator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\Reflection\ConstructorHydration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\Reflection\PropertyHydration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\Reflection\PropertyNames::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\Reflection\ValueConversion::class)]
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
final class RowMaterializerTest extends TestCase
{
    public function testMaterializeLinksEveryChildToTheGeneratedParent(): void
    {
        $faker = Factory::create();
        $schemas = new StaticSchemaResolver([
            new TableSchema('a', ['id' => new ColumnDefinition('id', 'INT', autoIncrement: true)]),
            new TableSchema('b', ['a_id' => new ColumnDefinition('a_id', 'INT')]),
        ]);
        $materializer = new Subject($schemas, new FixtureGenerator($faker), $faker);
        $plan = FixturePlan::from('a.id < b.a_id');
        $relation = $plan->relations[0];
        $run = new GenerationRun(['b' => RowSpec::from('b', 2)]);
        $materializer->materialize($plan, 'a', [], 1, false, null, $run);
        self::assertSame(['id' => 1], $run->toSet($plan)->row('a'));
        self::assertSame([['a_id' => 1], ['a_id' => 1]], $run->toSet($plan)->rows('b'));
    }

    public function testMaterializeParentDoesNotWalkBackAlongTheArrivalRelation(): void
    {
        $faker = Factory::create();
        $schemas = new StaticSchemaResolver([
            new TableSchema('a', ['id' => new ColumnDefinition('id', 'INT', autoIncrement: true)]),
            new TableSchema('b', ['a_id' => new ColumnDefinition('a_id', 'INT')]),
        ]);
        $materializer = new Subject($schemas, new FixtureGenerator($faker), $faker);
        $plan = FixturePlan::from('a.id < b.a_id');
        $relation = $plan->relations[0];
        $run = new GenerationRun(['b' => RowSpec::from('b', 2)]);
        self::assertSame(['id' => 1], $materializer->materializeParent($plan, 'a', false, $relation, $run));
        self::assertSame([], $run->toSet($plan)->rows('b'));
    }

    public function testMaterializeRowPreservesExplicitKeys(): void
    {
        $faker = Factory::create();
        $schemas = new StaticSchemaResolver([
            new TableSchema('a', ['id' => new ColumnDefinition('id', 'INT', autoIncrement: true)]),
            new TableSchema('b', ['a_id' => new ColumnDefinition('a_id', 'INT')]),
        ]);
        $materializer = new Subject($schemas, new FixtureGenerator($faker), $faker);
        $plan = FixturePlan::from('a.id < b.a_id');
        $relation = $plan->relations[0];
        $run = new GenerationRun(['b' => RowSpec::from('b', 2)]);
        $materializer->materializeRow($plan, $schemas->resolve('a'), [], RowSpec::from('a', ['id' => 9]), 0, false, null, $run);
        self::assertSame(['id' => 9], $run->lastRow('a'));
        self::assertSame(['a_id' => 9], $run->lastRow('b'));
    }

    public function testToParentProjectsGeneratedKeys(): void
    {
        $faker = Factory::create();
        $schemas = new StaticSchemaResolver([
            new TableSchema('a', ['id' => new ColumnDefinition('id', 'INT', autoIncrement: true)]),
            new TableSchema('b', ['a_id' => new ColumnDefinition('a_id', 'INT')]),
        ]);
        $materializer = new Subject($schemas, new FixtureGenerator($faker), $faker);
        $plan = FixturePlan::from('a.id < b.a_id');
        $relation = $plan->relations[0];
        $run = new GenerationRun(['b' => RowSpec::from('b', 2)]);
        self::assertSame(['a_id' => 1], $materializer->toParent($plan, $relation, [], false, $run));
        self::assertSame(['id' => 1], $run->lastRow('a'));
    }

    public function testToChildrenCopiesTheExistingParentKey(): void
    {
        $faker = Factory::create();
        $schemas = new StaticSchemaResolver([
            new TableSchema('a', ['id' => new ColumnDefinition('id', 'INT', autoIncrement: true)]),
            new TableSchema('b', ['a_id' => new ColumnDefinition('a_id', 'INT')]),
        ]);
        $materializer = new Subject($schemas, new FixtureGenerator($faker), $faker);
        $plan = FixturePlan::from('a.id < b.a_id');
        $relation = $plan->relations[0];
        $run = new GenerationRun(['b' => RowSpec::from('b', 2)]);
        $materializer->toChildren($plan, $relation, ['id' => 9], false, $run);
        self::assertSame([['a_id' => 9], ['a_id' => 9]], $run->toSet($plan)->rows('b'));
    }
}
