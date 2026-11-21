<?php

declare(strict_types=1);

namespace Tests\Unit\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\Mode;

#[CoversClass(Mode::class)]
#[Small]
final class ModeTest extends TestCase
{
    public function testToStringIsAvailableOnTheContract(): void
    {
        $value = new class () implements Mode {
            public function toString(): string
            {
                return 'ANSI_QUOTES';
            }
        };

        self::assertSame('ANSI_QUOTES', $value->toString());
    }
}
