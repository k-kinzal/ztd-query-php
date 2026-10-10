<?php

declare(strict_types=1);

namespace Tests\Unit\Value\Json;

use MySqlMemory\Value\Json\Json;
use MySqlMemory\Value\Json\JsonNumber;
use MySqlMemory\Value\Json\JsonSyntax;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonNumber::class)]
#[Small]
final class JsonNumberTest extends TestCase
{
    public function testReadRefusesAMissingFraction(): void
    {
        $this->expectExceptionObject(new JsonSyntax('Miss fraction part in number.', 2));

        (new JsonNumber(new Json('1.e5')))->read();
    }

    public function testReadRefusesAMissingExponent(): void
    {
        $this->expectExceptionObject(new JsonSyntax('Miss exponent in number.', 3));

        (new JsonNumber(new Json('1e+')))->read();
    }

    public function testReadRefusesANumberTooBigForADouble(): void
    {
        $this->expectExceptionObject(new JsonSyntax('Number too big to be stored in double.', 0));

        (new JsonNumber(new Json('0e400')))->read();
    }

    public function testReadRefusesAMinusWithoutDigits(): void
    {
        $this->expectExceptionObject(new JsonSyntax('Invalid value.', 1));

        (new JsonNumber(new Json('-x')))->read();
    }

    public function testReadWritesAnIntegerBeyondSixtyFourBitsAsADouble(): void
    {
        self::assertSame(['18446744073709551615', '1.8446744073709552e19', '0', '1e15'], [(new JsonNumber(new Json('18446744073709551615')))->read(), (new JsonNumber(new Json('18446744073709551616')))->read(), (new JsonNumber(new Json('-0')))->read(), (new JsonNumber(new Json('1e15')))->read()]);
    }

    public function testReadMovesThePositionPastTheNumber(): void
    {
        $json = new Json('[012]');
        $json->at = 1;

        self::assertSame(['0', 2], [(new JsonNumber($json))->read(), $json->at]);
    }

    public function testExponentReadsTheSignedExponentAfterTheE(): void
    {
        $json = new Json('1e-0012x');
        $json->at = 1;
        $large = new Json('E+12345678901');
        $plain = new Json('e7');

        self::assertSame([-12, 7, 999999999], [(new JsonNumber($json))->exponent(), (new JsonNumber($plain))->exponent(), (new JsonNumber($large))->exponent()]);
        self::assertSame(7, $json->at);
    }

    public function testExponentRefusesAMissingExponent(): void
    {
        $this->expectExceptionObject(new JsonSyntax('Miss exponent in number.', 2));

        (new JsonNumber(new Json('e-')))->exponent();
    }

    public function testIntegralHoldsForIntegersThatFitSixtyFourBits(): void
    {
        self::assertSame([true, true, false, true, false, true], [JsonNumber::integral('18446744073709551615'), JsonNumber::integral('-9223372036854775808'), JsonNumber::integral('18446744073709551616'), JsonNumber::integral('-0'), JsonNumber::integral('-9223372036854775809'), JsonNumber::integral('12')]);
    }
}
