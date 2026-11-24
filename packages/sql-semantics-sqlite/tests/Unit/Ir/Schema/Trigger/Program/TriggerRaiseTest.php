<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Program\TriggerRaise;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Program\TriggerRaisePolicy;

#[CoversClass(TriggerRaise::class)]
#[Small]
final class TriggerRaiseTest extends TestCase
{
    public function testFailKeepsAnExplicitNullMessageOperand(): void
    {
        $message = new NullLiteral();
        $raise = new TriggerRaise(TriggerRaisePolicy::Fail, $message);

        self::assertSame($message, $raise->message);
        self::assertSame(TriggerRaisePolicy::Fail, $raise->policy);
    }

    public function testIgnoreCanCarryNoMessage(): void
    {
        $ignore = new TriggerRaise(TriggerRaisePolicy::Ignore);

        self::assertNull($ignore->message);
        self::assertSame(TriggerRaisePolicy::Ignore, $ignore->policy);
    }
}
