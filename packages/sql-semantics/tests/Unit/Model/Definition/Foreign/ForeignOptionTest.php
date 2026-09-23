<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Foreign;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(ForeignOption::class)]
#[Medium]
final class ForeignOptionTest extends TestCase
{
    public function testOptionRetainsAnIdentifierAndTextWithoutInterpretingIt(): void
    {
        $value = Expression::literal('not a boolean', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $value);
        $option = new ForeignOption('wrapper_specific', $value);
        self::assertSame('wrapper_specific', $option->name);
        self::assertSame($value, $option->value);
    }

    public function testOptionRejectsAnEmptyName(): void
    {
        $value = Expression::literal('value', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $value);
        $this->expectException(InvalidStructure::class);
        new ForeignOption('', $value);
    }

    public function testOptionRejectsNumericLiterals(): void
    {
        $value = Expression::literal(42, Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $value);
        $this->expectException(InvalidStructure::class);
        new ForeignOption('option', $value);
    }

    public function testOptionRejectsAnotherDatabaseLanguage(): void
    {
        $value = Expression::literal('value', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $value);
        $this->expectException(InvalidStructure::class);
        new ForeignOption('option', $value);
    }

}
