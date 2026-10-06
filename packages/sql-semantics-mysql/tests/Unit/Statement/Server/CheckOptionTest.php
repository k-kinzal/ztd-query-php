<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\CheckOption;

#[CoversClass(CheckOption::class)]
#[Small]
final class CheckOptionTest extends TestCase
{
    public function testCasesSpellEveryCheckOption(): void
    {
        self::assertSame(['QUICK', 'FAST', 'MEDIUM', 'EXTENDED', 'CHANGED', 'FOR UPGRADE'], array_column(CheckOption::cases(), 'value'));
    }
}
