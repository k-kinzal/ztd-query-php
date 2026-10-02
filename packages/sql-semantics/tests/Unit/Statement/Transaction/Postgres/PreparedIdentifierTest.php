<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Transaction\Postgres;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Transaction\Postgres\PreparedIdentifier;

#[CoversClass(PreparedIdentifier::class)]
#[Small]
final class PreparedIdentifierTest extends TestCase
{
    #[TestWith([199, false])]
    #[TestWith([200, true])]
    public function testExceedsLengthDiagnosesByteLengthWithoutDiscardingTheIdentifier(int $length, bool $expected): void
    {
        $value = str_repeat('a', $length);
        $identifier = new PreparedIdentifier($value);
        self::assertSame($expected, $identifier->exceedsLength());
        self::assertSame($value, $identifier->value);
    }

    public function testExceedsLengthCountsBytesRatherThanUnicodeCharacters(): void
    {
        self::assertTrue((new PreparedIdentifier(str_repeat('é', 100)))->exceedsLength());
    }

    #[TestWith(['', "E''"])]
    #[TestWith(["O'Neil", "E'O''Neil'"])]
    #[TestWith(['a\\b', "E'a\\\\b'"])]
    public function testToStringEscapesDecodedBytesIndependentlyOfSessionDefaults(string $value, string $expected): void
    {
        self::assertSame($expected, (new PreparedIdentifier($value))->toString());
    }
}
