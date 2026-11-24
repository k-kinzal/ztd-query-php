<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger\Removal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Removal\TriggerVerification;

#[CoversClass(TriggerVerification::class)]
#[Small]
final class TriggerVerificationTest extends TestCase
{
    public function testCasesDistinguishTheNativeVerificationScopes(): void
    {
        self::assertSame(['search-path', 'known-schema', 'unknown-schema'], array_column(TriggerVerification::cases(), 'value'));
    }
}
