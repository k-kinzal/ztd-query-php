<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Call\Window\Counts;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;

#[CoversClass(Counts::class)]
#[Small]
final class CountsTest extends TestCase
{
    public function testInvalidDistinguishesTheCountAndOffsetDomains(): void
    {
        $zero = new NumberLiteral('000');
        $one = new NumberLiteral('1');
        $rule = new Counts();
        self::assertTrue($rule->invalid(WindowFunctionKind::Tile, [$zero]));
        self::assertFalse($rule->invalid(WindowFunctionKind::Lag, [$one, $zero]));
        self::assertTrue($rule->invalid(WindowFunctionKind::Lag, [$one, new NumberLiteral('9223372036854775808')]));
        self::assertTrue($rule->invalid(WindowFunctionKind::NthValue, [$one, new \SqlSemantics\Platform\MySql\Statement\Expression\Grouped(new NumberLiteral('1.0'))]));
        self::assertFalse($rule->invalid(WindowFunctionKind::Tile, [new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new \SqlSemantics\Statement\Identifier\Name('n'))]));
        self::assertFalse($rule->invalid(WindowFunctionKind::Rank, []));
    }
}
