<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Function\Argument;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Function\Argument\ArgumentOrder;
use SqlSemantics\Model\Scalar\Function\Argument\NamedArgument;
use SqlSemantics\Model\Scalar\Function\Argument\VariadicArgument;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(ArgumentOrder::class)]
final class ArgumentOrderTest extends TestCase
{
    public function testValidateAcceptsPositionalThenNamedThenVariadic(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $named = new NamedArgument($value->facts, $value->source, 'a', $value);
        $variadic = new VariadicArgument($value->facts, $value->source, new NamedArgument($value->facts, $value->source, 'b', $value));
        ArgumentOrder::validate([$value, $named, $variadic]);
        $this->addToAssertionCount(1);
    }

    public function testValidateRejectsAPositionalArgumentAfterANamedOne(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        ArgumentOrder::validate([new NamedArgument($value->facts, $value->source, 'a', $value), $value]);
    }

    public function testValidateRejectsARepeatedName(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $named = new NamedArgument($value->facts, $value->source, 'a', $value);
        $this->expectException(InvalidStructure::class);
        ArgumentOrder::validate([$named, $named]);
    }

    public function testValidateRejectsAVariadicArgumentBeforeTheLast(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        ArgumentOrder::validate([new VariadicArgument($value->facts, $value->source, $value), $value]);
    }
}
