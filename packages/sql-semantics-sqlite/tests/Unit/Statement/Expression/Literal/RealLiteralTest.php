<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(RealLiteral::class)]
#[Medium]
final class RealLiteralTest extends TestCase
{
    public function testKeepsTheExactParts(): void
    {
        $literal = new RealLiteral('', '5', '+2');

        self::assertSame('', $literal->whole);
        self::assertSame('5', $literal->fraction);
        self::assertSame('+2', $literal->exponent);
        self::assertNull((new RealLiteral('7', null, '-1'))->fraction);
        self::assertNull((new RealLiteral('7', ''))->exponent);
    }

    public function testDeriveScalarGivesReal(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1.5, .5, 5., 1e2, 1.50E-3, 1E+2', []);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        $literals = array_map(static function (object $column): RealLiteral {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(RealLiteral::class, $column->expression);

            return $column->expression;
        }, $statement->columns);
        self::assertSame(['1', '', '5', '1', '1', '1'], array_map(static fn (RealLiteral $literal): string => $literal->whole, $literals));
        self::assertSame(['5', '5', '', null, '50', null], array_map(static fn (RealLiteral $literal): ?string => $literal->fraction, $literals));
        self::assertSame([null, null, null, '2', '-3', '+2'], array_map(static fn (RealLiteral $literal): ?string => $literal->exponent, $literals));
        $fact = $operation->facts->scalar($literals[4]);
        self::assertEquals(new Known(Storage::Real), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
        self::assertNull($fact->resolution);
        self::assertEquals(new Known(Storage::Real), $operation->field(0)->type);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesThePartsWithALowerCaseExponentMarker(): void
    {
        self::assertSame('SELECT 1.5 AS c1, .5 AS c2, 5. AS c3, 1e2 AS c4, 1.50e-3 AS c5, 1e+2 AS c6', (new Semantics(Dialect::Sqlite))->analyze('select 1.5 AS c1, .5 AS c2, 5. AS c3, 1e2 AS c4, 1.50E-3 AS c5, 1E+2 AS c6')->toString());
    }

    public function testRenderWritesANewlyBuiltLiteral(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = new Operation($semantics->context(), new Select([new ResultColumn(new RealLiteral('', '5', '+2')), new ResultColumn(new RealLiteral('7', null, '-1')), new ResultColumn(new RealLiteral('3', '14'))]));

        self::assertSame('SELECT .5e+2, 7e-1, 3.14', $operation->toString());
    }
}
