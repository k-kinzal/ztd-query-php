<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Keyword;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Keyword\DateArithmeticAlias;

#[CoversClass(DateArithmeticAlias::class)]
#[Small]
final class DateArithmeticAliasTest extends TestCase
{
    public function testCasesKeepTheFunctionNames(): void
    {
        self::assertSame(['ADDDATE', 'SUBDATE'], array_column(DateArithmeticAlias::cases(), 'value'));
    }

    public function testSubtractsKeepsTheFunctionRole(): void
    {
        self::assertFalse(DateArithmeticAlias::AddDate->subtracts());
        self::assertTrue(DateArithmeticAlias::SubDate->subtracts());
    }
}
