<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(IntegerLiteral::class)]
#[Medium]
final class IntegerLiteralTest extends TestCase
{
    public function testFitsAcceptsTheLargestSignedSixtyFourBitValue(): void
    {
        self::assertTrue((new IntegerLiteral('9223372036854775807'))->fits());
        self::assertTrue((new IntegerLiteral('0'))->fits());
        self::assertTrue((new IntegerLiteral('00009223372036854775807'))->fits());
    }

    public function testFitsRefusesOneMore(): void
    {
        self::assertFalse((new IntegerLiteral('9223372036854775808'))->fits());
        self::assertFalse((new IntegerLiteral('09223372036854775808'))->fits());
        self::assertFalse((new IntegerLiteral('10000000000000000000'))->fits());
    }

    public function testDeriveScalarGivesIntegerWhenTheValueFits(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1_000, 9223372036854775807', []);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        self::assertInstanceOf(IntegerLiteral::class, $statement->columns[0]->expression);
        self::assertSame('1000', $statement->columns[0]->expression->digits);
        $fact = $operation->facts->scalar($statement->columns[0]->expression);
        self::assertEquals(new Known(Storage::Integer), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
        self::assertNull($fact->resolution);
        self::assertEquals(new Known(Storage::Integer), $operation->field(1)->type);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveScalarGivesRealForALargerValue(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT 9223372036854775808', []);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        self::assertInstanceOf(IntegerLiteral::class, $statement->columns[0]->expression);
        self::assertSame('9223372036854775808', $statement->columns[0]->expression->digits);
        self::assertEquals(new Known(Storage::Real), $operation->field(0)->type);
        self::assertSame(Nullability::NotNull, $operation->field(0)->nullability);
    }

    public function testRenderWritesTheDigitsWithoutSeparators(): void
    {
        self::assertSame('SELECT 1000, 007, 9223372036854775808', (new Semantics(Dialect::Sqlite))->analyze('select 1_000, 007, 9223372036854775808')->toString());
    }

    public function testRenderWritesANewlyBuiltLiteral(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = new Operation($semantics->context(), new Select([new ResultColumn(new IntegerLiteral('42')), new ResultColumn(new IntegerLiteral('0'))]));

        self::assertSame('SELECT 42, 0', $operation->toString());
    }
}
