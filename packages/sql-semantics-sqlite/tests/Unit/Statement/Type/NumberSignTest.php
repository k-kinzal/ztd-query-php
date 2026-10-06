<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Cast;
use SqlSemantics\Platform\Sqlite\Statement\Type\NumberSign;

#[CoversClass(NumberSign::class)]
#[Medium]
final class NumberSignTest extends TestCase
{
    public function testEachSignIsReadFromItsSpelling(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT CAST(1 AS DECIMAL(+10, -2)), CAST(1 AS DECIMAL(3))');
        $signed = $query->field(0)->expression;
        $unsigned = $query->field(1)->expression;

        self::assertInstanceOf(Cast::class, $signed);
        self::assertNotNull($signed->target);
        self::assertSame(NumberSign::Plus, $signed->target->arguments[0]->sign);
        self::assertSame(NumberSign::Minus, $signed->target->arguments[1]->sign);
        self::assertInstanceOf(Cast::class, $unsigned);
        self::assertNotNull($unsigned->target);
        self::assertNull($unsigned->target->arguments[0]->sign);
    }

    public function testEachSignCarriesItsSpelling(): void
    {
        self::assertSame('+', NumberSign::Plus->value);
        self::assertSame('-', NumberSign::Minus->value);
        self::assertCount(2, NumberSign::cases());
    }
}
