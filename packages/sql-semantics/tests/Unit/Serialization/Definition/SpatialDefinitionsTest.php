<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Spatial\SpatialDefinition;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\MySql\CreateSpatialReferenceSystemStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Serialization\Definition\SpatialDefinitions::class)]
#[Medium]
final class SpatialDefinitionsTest extends TestCase
{
    public function testWriteReturnsNullForOtherDefinitionFamilies(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP DATABASE app');
        self::assertNull(\SqlSemantics\Serialization\Definition\SpatialDefinitions::write($statement));
    }

    public function testWriteKeepsMetadataInsideLiteralBoundaries(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("CREATE SPATIAL REFERENCE SYSTEM 4120 NAME 'Greek' DEFINITION 'coordinate-system text'");
        self::assertInstanceOf(CreateSpatialReferenceSystemStatement::class, $statement);
        $name = Expression::literal("Greek'; DROP DATABASE app", Dialect::MySql);
        self::assertInstanceOf(Literal::class, $name);
        $changed = $statement->withDefinition(new SpatialDefinition($name, $statement->definition->definition));
        $roundTrip = $binder->bind($changed->toString());
        self::assertInstanceOf(CreateSpatialReferenceSystemStatement::class, $roundTrip);
        self::assertSame($name->text, $roundTrip->definition->name->text);
    }

}
