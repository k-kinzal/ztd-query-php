<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\SemanticsIR;

#[CoversClass(SemanticsIR::class)]
#[Small]
final class SemanticsIRTest extends TestCase
{
    public function testProfileIdAndSqlAreAvailableOnTheContract(): void
    {
        $value = new class () implements SemanticsIR {
            public function profileId(): string
            {
                return 'test-profile';
            }

            public function toString(): string
            {
                return 'SELECT 1';
            }
        };

        self::assertSame('test-profile', $value->profileId());
        self::assertSame('SELECT 1', $value->toString());
    }
}
