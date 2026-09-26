<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\RowGenerator;
use SqlFixture\Fixture\Validation\OverrideValidator;
use SqlFixture\Hydrator\HydratorInterface;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;
use SqlFixture\TypeMapper\TypeMapperInterface;
use stdClass;

#[CoversClass(RowGenerator::class)]
#[UsesClass(OverrideValidator::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(TableSchema::class)]
final class RowGeneratorTest extends TestCase
{
    public function testGenerateUsesInjectedMappingAndPreservesOverrides(): void
    {
        $faker = Factory::create();
        $value = new ColumnDefinition('value', 'custom');
        $mapper = $this->createMock(TypeMapperInterface::class);
        $mapper->expects(self::once())->method('generate')->with($faker, $value)->willReturn('mapped');
        $hydrator = $this->createMock(HydratorInterface::class);
        $hydrator->expects(self::never())->method('hydrate');
        $schema = new TableSchema('items', [
            'id' => new ColumnDefinition('id', 'custom', autoIncrement: true),
            'value' => $value,
            'label' => new ColumnDefinition('label', 'custom'),
            'computed' => new ColumnDefinition('computed', 'custom', generated: true),
        ]);

        self::assertSame(['value' => 'mapped', 'label' => 'chosen'], (new RowGenerator($faker, $mapper, $hydrator))->generate($schema, ['label' => 'chosen']));
    }

    public function testGenerateDelegatesObjectHydration(): void
    {
        $faker = Factory::create();
        $mapper = $this->createMock(TypeMapperInterface::class);
        $mapper->expects(self::never())->method('generate');
        $object = new stdClass();
        $object->id = 7;
        $hydrator = $this->createMock(HydratorInterface::class);
        $hydrator->expects(self::once())->method('hydrate')->with(['id' => 7], stdClass::class)->willReturn($object);
        $schema = new TableSchema('items', ['id' => new ColumnDefinition('id', 'custom')]);

        self::assertSame($object, (new RowGenerator($faker, $mapper, $hydrator))->generate($schema, ['id' => 7], stdClass::class));
    }
}
