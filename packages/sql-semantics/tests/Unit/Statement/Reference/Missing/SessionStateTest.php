<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Reference\Missing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Reference\Missing\SessionState;

#[CoversClass(SessionState::class)]
#[Small]
final class SessionStateTest extends TestCase
{
    public function testDescribeNamesTheSubject(): void
    {
        $missing = new SessionState('user variable @total');

        self::assertSame('the session state: user variable @total', $missing->describe());
        self::assertSame('user variable @total', $missing->subject);
    }
}
