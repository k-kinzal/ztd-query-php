<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Pragma;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Pragma\NumericArgument;
use SqlSemantics\Model\Configuration\Pragma\Sign;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Configuration\AssignPragmaStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(NumericArgument::class)]
#[Medium]
final class NumericArgumentTest extends TestCase
{
    public function testSeparatesTheSignFromTheUnsignedLiteral(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('PRAGMA cache_size=+100');
        self::assertInstanceOf(AssignPragmaStatement::class, $statement);
        $argument = $statement->value;
        self::assertInstanceOf(NumericArgument::class, $argument);
        self::assertSame(Sign::Positive, $argument->sign);
        self::assertSame('100', $argument->literal->text);
        self::assertSame('PRAGMA "cache_size" = + 100', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testDefaultsToAnUnsignedNumber(): void
    {
        $literal = Expression::literal(7, Dialect::Sqlite);
        self::assertInstanceOf(Literal::class, $literal);
        $argument = new NumericArgument($literal);
        self::assertSame(Sign::Unsigned, $argument->sign);
        self::assertSame($literal, $argument->literal);
    }

    public function testRejectsATextLiteral(): void
    {
        $literal = Expression::literal('wal', Dialect::Sqlite);
        self::assertInstanceOf(Literal::class, $literal);
        $this->expectException(InvalidStructure::class);
        new NumericArgument($literal, Sign::Negative);
    }
}
