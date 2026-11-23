<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewSecurity;

#[CoversClass(ViewSecurity::class)]
#[Small]
final class ViewSecurityTest extends TestCase
{
    public function testCasesExposeTheCheckedPolicies(): void
    {
        self::assertSame(['DEFINER', 'INVOKER'], array_column(ViewSecurity::cases(), 'value'));
    }
}
