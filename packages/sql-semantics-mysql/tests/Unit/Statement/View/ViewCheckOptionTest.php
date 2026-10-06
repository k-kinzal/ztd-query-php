<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\View\ViewCheckOption;

#[CoversClass(ViewCheckOption::class)]
#[Small]
final class ViewCheckOptionTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame('CASCADED', ViewCheckOption::Cascaded->value);
        self::assertSame('', ViewCheckOption::Unqualified->value);
    }

    public function testCascadesTellsWhetherUnderlyingViewsAreChecked(): void
    {
        self::assertTrue(ViewCheckOption::Unqualified->cascades());
        self::assertTrue(ViewCheckOption::Cascaded->cascades());
        self::assertFalse(ViewCheckOption::Local->cascades());
    }
}
