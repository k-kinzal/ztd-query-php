<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\View\ViewSecurity;

#[CoversClass(ViewSecurity::class)]
final class ViewSecurityTest extends TestCase
{
    public function testRepresentsBothPrivilegeContexts(): void
    {
        self::assertSame(['DEFINER', 'INVOKER'], array_column(ViewSecurity::cases(), 'value'));
    }

}
