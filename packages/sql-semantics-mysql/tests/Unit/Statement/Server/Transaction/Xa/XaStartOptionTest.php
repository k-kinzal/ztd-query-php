<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction\Xa;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaStartOption;

#[CoversClass(XaStartOption::class)]
#[Small]
final class XaStartOptionTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['JOIN', 'RESUME'], array_column(XaStartOption::cases(), 'value'));
    }
}
