<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Transaction\Xa;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaEndOption;

#[CoversClass(XaEndOption::class)]
#[Small]
final class XaEndOptionTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['SUSPEND', 'SUSPEND FOR MIGRATE'], array_column(XaEndOption::cases(), 'value'));
    }
}
