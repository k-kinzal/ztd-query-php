<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Program\TriggerRaisePolicy;

#[CoversClass(TriggerRaisePolicy::class)]
#[Small]
final class TriggerRaisePolicyTest extends TestCase
{
    public function testCasesPreserveEachNativePolicy(): void
    {
        self::assertSame(['IGNORE', 'FAIL', 'ABORT', 'ROLLBACK'], array_column(TriggerRaisePolicy::cases(), 'value'));
    }
}
