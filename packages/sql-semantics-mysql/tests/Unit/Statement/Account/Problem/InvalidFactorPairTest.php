<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidFactorPair;

#[CoversClass(InvalidFactorPair::class)]
#[Small]
final class InvalidFactorPairTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('The third factor is added before the second (ER_MFA_METHODS_INVALID_ORDER).', (new InvalidFactorPair(false))->message());
    }
}
