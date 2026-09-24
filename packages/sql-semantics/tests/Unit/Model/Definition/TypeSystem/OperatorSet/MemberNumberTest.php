<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\OperatorSet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\OperatorSet\MemberNumber;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(MemberNumber::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class MemberNumberTest extends TestCase
{
    #[TestWith([0])]
    #[TestWith([32768])]
    public function testValidateRejectsANumberOutOfRange(int $number): void
    {
        MemberNumber::validate(32767);
        $this->expectException(InvalidStructure::class);
        MemberNumber::validate($number);
    }

    public function testTypesRequiresBothOrNeither(): void
    {
        MemberNumber::types(null, null);
        $this->expectException(InvalidStructure::class);
        MemberNumber::types(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), null);
    }
}
