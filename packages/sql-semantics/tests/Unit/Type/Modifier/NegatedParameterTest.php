<?php

declare(strict_types=1);

namespace Tests\Unit\Type\Modifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;
use SqlSemantics\Type\Modifier\NegatedParameter;

#[CoversClass(NegatedParameter::class)]
#[Medium]
final class NegatedParameterTest extends TestCase
{
    public function testRetainsNestedNegationsAsOperations(): void
    {
        $numeric = new NumericParameter('12');
        $negated = new NegatedParameter($numeric);
        $outer = new NegatedParameter($negated);
        self::assertSame($negated, $outer->operand);
        self::assertSame($numeric, $negated->operand);
        self::assertSame('- (- (12))', \SqlSemantics\Serialization\Type\ModifierSyntax::write($outer)->toString());
    }

}
