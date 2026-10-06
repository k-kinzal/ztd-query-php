<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\PrefixTyping;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(PrefixTyping::class)]
#[Small]
final class PrefixTypingTest extends TestCase
{
    public function testPrefixFoldsANegativeConstant(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        $constant = new Constant(new NumericConstant('9223372036854775808'));
        $fact = (new PrefixTyping())->prefix($context, new OperatorName(new Name('-')), new ScalarFact(new Known(Builtin::Numeric), Nullability::NotNull), $constant);
        self::assertEquals(new Known(Builtin::Int8), $fact->type);
    }

    public function testNegativeTypesByValue(): void
    {
        self::assertSame(
            [Builtin::Int4, Builtin::Int4, Builtin::Int8, Builtin::Int8, Builtin::Numeric, Builtin::Numeric],
            [
                (new PrefixTyping())->negative(new IntegerConstant('2147483647')), (new PrefixTyping())->negative(new NumericConstant('0x8000_0000')),
                (new PrefixTyping())->negative(new NumericConstant('2147483649')), (new PrefixTyping())->negative(new NumericConstant('9223372036854775808')),
                (new PrefixTyping())->negative(new NumericConstant('9223372036854775809')), (new PrefixTyping())->negative(new NumericConstant('1.5')),
            ],
        );
    }

    public function testUnaryKeepsANumberType(): void
    {
        $context = (new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true);
        self::assertEquals(new Known(Builtin::Float8), (new PrefixTyping())->unary($context, new OperatorName(new Name('|/')), new Known(Builtin::Int4)));
    }

}
