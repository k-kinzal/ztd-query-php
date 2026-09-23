<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Storage\CompletionWait;

#[CoversClass(CompletionWait::class)]
#[Medium]
final class CompletionWaitTest extends TestCase
{
    public function testCasesDistinguishBothCompletionPolicies(): void
    {
        self::assertSame([CompletionWait::Wait, CompletionWait::NoWait], CompletionWait::cases());
        self::assertSame('WAIT', CompletionWait::Wait->value);
        self::assertSame('NO_WAIT', CompletionWait::NoWait->value);
    }
}
