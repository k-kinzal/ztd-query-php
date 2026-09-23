<?php

declare(strict_types=1);

namespace Tests\Unit\Type\Identity\Numeric;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;

#[CoversClass(NumericParameter::class)]
#[Medium]
final class NumericParameterTest extends TestCase
{
    #[TestWith(['1 + 2'])]
    #[TestWith(['1, 2'])]
    #[TestWith(['"12"'])]
    #[TestWith(['NULL'])]
    #[TestWith(['12); DROP TABLE t'])]
    public function testRejectsFragmentsOutsideTheNumericLiteralDomain(string $text): void
    {
        $this->expectException(InvalidStructure::class);
        new NumericParameter($text);
    }

    public function testKeepsArbitraryPrecisionInsteadOfConvertingToAnInteger(): void
    {
        $number = new NumericParameter('999999999999999999999999999999');
        self::assertSame('999999999999999999999999999999', $number->spelling);
    }

}
