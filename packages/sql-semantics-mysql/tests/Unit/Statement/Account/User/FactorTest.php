<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\User\Factor;

#[CoversClass(Factor::class)]
#[Small]
final class FactorTest extends TestCase
{
    public function testTheDomainContainsExactlyTheTwoAdditionalFactors(): void
    {
        self::assertSame(['2', '3'], array_column(Factor::cases(), 'value'));
        self::assertSame([null, null, null, null], array_map(Factor::tryFrom(...), ['1', '02', '003', '4']));
    }
}
