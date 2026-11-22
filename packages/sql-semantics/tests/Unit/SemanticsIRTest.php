<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\SemanticsIR;

#[CoversClass(SemanticsIR::class)]
#[Small]
final class SemanticsIRTest extends TestCase
{
    #[DataProvider('providerValues')]
    public function testProfileIdIsAvailableOnTheContract(SemanticsIR $value): void
    {
        self::assertSame('test-profile', $value->profileId());
    }

    #[DataProvider('providerValues')]
    public function testToStringIsAvailableOnTheContract(SemanticsIR $value): void
    {
        self::assertSame('SELECT 1', $value->toString());
    }

    /**
     * @return array{array{SemanticsIR}}
     */
    public static function providerValues(): array
    {
        return [[new class () implements SemanticsIR {
            public function profileId(): string
            {
                return 'test-profile';
            }

            public function toString(): string
            {
                return 'SELECT 1';
            }
        }]];
    }
}
