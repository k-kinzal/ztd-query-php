<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Partition\KeyAlgorithms;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;

#[CoversClass(KeyAlgorithms::class)]
#[Small]
final class KeyAlgorithmsTest extends TestCase
{
    /**
     * @return iterable<array{string, bool, bool}>
     */
    public static function providerNumbers(): iterable
    {
        foreach ([false, true] as $hexadecimal) {
            foreach (['1', '2', '001', '0002', '0', '3', '4294967297', '100000001'] as $text) {
                yield [$text, $hexadecimal, in_array($text, ['1', '2', '001', '0002'], true)];
            }
        }
        yield ['', true, false];
        yield ['1.0', false, false];
        yield ['1e0', false, false];
    }

    #[DataProvider('providerNumbers')]
    public function testAcceptsKeepsTheTwoAlgorithmValues(string $text, bool $hexadecimal, bool $expected): void
    {
        self::assertSame($expected, (new KeyAlgorithms())->accepts(new Numeral($text, $hexadecimal)));
    }
}
