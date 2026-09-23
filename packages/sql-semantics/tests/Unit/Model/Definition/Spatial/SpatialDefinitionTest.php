<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Spatial;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Spatial\SpatialDefinition;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(SpatialDefinition::class)]
#[Medium]
final class SpatialDefinitionTest extends TestCase
{
    public function testDefinitionRequiresNameAndDefinitionTogether(): void
    {
        $name = Expression::literal('Greek', Dialect::MySql);
        $text = Expression::literal('coordinate-system text', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $name);
        self::assertInstanceOf(Literal::class, $text);
        $definition = new SpatialDefinition($name, $text);
        self::assertSame($name, $definition->name);
        self::assertSame($text, $definition->definition);
        self::assertNull($definition->organization);
        self::assertNull($definition->description);
    }

    public function testDefinitionRejectsANumericMetadataOperand(): void
    {
        $name = Expression::literal('Greek', Dialect::MySql);
        $number = Expression::literal(42, Dialect::MySql);
        self::assertInstanceOf(Literal::class, $name);
        self::assertInstanceOf(Literal::class, $number);
        $this->expectException(InvalidStructure::class);
        new SpatialDefinition($name, $number);
    }

}
