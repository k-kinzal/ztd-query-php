<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Literal\Literal;
use SqlSemantics\Statement\Literal\NullLiteral;
use SqlSemantics\Statement\Literal\NumberLiteral;
use SqlSemantics\Statement\Literal\StringLiteral;

#[CoversClass(Literal::class)]
#[Medium]
final class LiteralTest extends TestCase
{
    public function testValueDistinguishesNullFromTextAndNumbers(): void
    {
        $values = [NullLiteral::Null, new StringLiteral('NULL'), new NumberLiteral('0')];
        self::assertSame([null, 'NULL', '0'], array_map(static fn (Literal $literal): string|bool|null => $literal->value(), $values));
    }

}
