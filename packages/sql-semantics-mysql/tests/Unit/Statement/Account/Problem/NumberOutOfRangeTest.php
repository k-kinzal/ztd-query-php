<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\NumberOutOfRange;

#[CoversClass(NumberOutOfRange::class)]
#[Small]
final class NumberOutOfRangeTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('The value 40000 is not accepted for PASSWORD_LOCK_TIME (ER_WRONG_VALUE).', (new NumberOutOfRange('PASSWORD_LOCK_TIME', '40000', 'ER_WRONG_VALUE'))->message());
    }
}
