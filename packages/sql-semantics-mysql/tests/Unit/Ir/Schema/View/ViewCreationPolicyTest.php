<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\View\ViewCreationPolicy;

#[CoversClass(ViewCreationPolicy::class)]
#[Small]
final class ViewCreationPolicyTest extends TestCase
{
    public function testCasesExposeTheCheckedPolicies(): void
    {
        self::assertSame(['require-new', 'replace', 'preserve'], array_column(ViewCreationPolicy::cases(), 'value'));
    }
}
