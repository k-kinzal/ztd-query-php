<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\CallMisuseKind;

#[CoversClass(CallMisuseKind::class)]
#[Small]
final class CallMisuseKindTest extends TestCase
{
    public function testCasesCarryTheServerMessages(): void
    {
        self::assertSame('aggregate ORDER BY is not implemented for window functions', CallMisuseKind::OrderInWindow->value);
        self::assertCount(13, CallMisuseKind::cases());
    }
}
