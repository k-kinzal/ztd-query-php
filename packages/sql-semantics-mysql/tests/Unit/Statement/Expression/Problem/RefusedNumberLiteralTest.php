<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedNumberLiteral;

#[CoversClass(RefusedNumberLiteral::class)]
#[Small]
final class RefusedNumberLiteralTest extends TestCase
{
    public function testMessageRetainsTheOverflowingFloatingOperand(): void
    {
        $problem = new RefusedNumberLiteral('2e381');

        self::assertSame(['A floating literal exceeds the finite DOUBLE domain.', '2e381'], [$problem->message(), $problem->text]);
    }
}
