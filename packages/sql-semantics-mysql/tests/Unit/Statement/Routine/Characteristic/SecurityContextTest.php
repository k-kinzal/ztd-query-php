<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Characteristic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\SecurityContext;

#[CoversClass(SecurityContext::class)]
#[Small]
final class SecurityContextTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame(['DEFINER', 'INVOKER'], array_map(static fn (SecurityContext $context): string => $context->value, SecurityContext::cases()));
    }
}
