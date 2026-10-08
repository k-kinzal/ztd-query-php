<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Expression;

use MySqlMemory\Command\Definition\Expression\Conditions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Not;
use SqlSemantics\Platform\MySql\Statement\Expression\NullTest;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;

#[CoversClass(Conditions::class)]
#[Small]
final class ConditionsTest extends TestCase
{
    public function testBooleanTellsConditionsFromOtherExpressions(): void
    {
        $one = new NumberLiteral('1');

        self::assertSame(
            [true, true, true, true, false, false],
            [(new Conditions())->boolean(new NullTest($one)), (new Conditions())->boolean(new Not($one)), (new Conditions())->boolean(new BooleanLiteral(true)), (new Conditions())->boolean(new Grouped(new NullTest($one))), (new Conditions())->boolean($one), (new Conditions())->boolean(new StringLiteral(['x']))],
        );
    }
}
