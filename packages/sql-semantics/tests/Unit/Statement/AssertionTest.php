<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Assertion;
use SqlSemantics\Statement\Model\Sqlite\Value\ExprWithExprPlusMinusExpr_82e360dc as Addition;
use SqlSemantics\Statement\Model\Sqlite\Value\ExprWithExprStarSlashRemExpr_6ca99fe8 as Multiplication;
use SqlSemantics\Statement\Model\Sqlite\Value\ExprWithLpExprRp_ad646753 as Parentheses;
use SqlSemantics\Statement\Model\Sqlite\Value\NmWithIdj_a2015ecf as Name;
use SqlSemantics\Statement\Model\Sqlite\Value\TermWithInteger_298801b2 as IntegerValue;

#[CoversClass(Assertion::class)]
#[Medium]
final class AssertionTest extends TestCase
{
    #[TestWith(['0'])]
    #[TestWith(['12345678901234567890'])]
    #[TestWith(['0xFF'])]
    public function testAssertMatchesPatternPreservesIntegerSpelling(string $spelling): void
    {
        $value = new IntegerValue('1');
        $updated = $value->withValue($spelling);
        self::assertSame('1', $value->value);
        self::assertSame($spelling, $updated->value);
    }

    public function testAssertMatchesPatternPreservesQuotedIdentifierSpelling(): void
    {
        $name = new Name('foo');
        $updated = $name->withName('"foo.bar"');
        self::assertSame('foo', $name->name);
        self::assertSame('"foo.bar"', $updated->name);
    }

    public function testAssertOperandBindingStrengthAcceptsExplicitGrouping(): void
    {
        $sum = new Addition(new IntegerValue('1'), '+', new IntegerValue('2'));
        $grouped = new Parentheses($sum);
        $value = new Multiplication(new IntegerValue('1'), '*', new IntegerValue('3'));
        $updated = $value->withExpr($grouped);
        self::assertSame($grouped, $updated->expr);
        self::assertSame($value->expr2, $updated->expr2);
        self::assertNotSame($value, $updated);
    }
}
