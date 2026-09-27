<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Mode;

#[CoversClass(Mode::class)]
#[Small]
final class ModeTest extends TestCase
{
    public function testToStringDescribesAnApplicationsOwnSettings(): void
    {
        $mode = new class () implements Mode {
            public function toString(): string
            {
                return 'application';
            }
        };
        $describe = static fn (Mode $mode): string => $mode->toString();
        self::assertSame('application', $describe($mode));
    }
}
