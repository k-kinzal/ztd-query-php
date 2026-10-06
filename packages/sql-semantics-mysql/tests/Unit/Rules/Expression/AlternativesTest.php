<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\Alternatives;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(Alternatives::class)]
#[Small]
final class AlternativesTest extends TestCase
{
    public function testBlockingPrefersAnInvalidTypeAndJoinsTheMissingInputs(): void
    {
        $invalid = new Invalid(new OperandColumns(1, 2));
        $alternatives = new Alternatives();

        self::assertSame($invalid, $alternatives->blocking([new Dependent([new SessionState('x')]), $invalid]));
        self::assertEquals(new Dependent([new SessionState('x'), new SessionState('y')]), $alternatives->blocking([new Dependent([new SessionState('x')]), new Dependent([new SessionState('y')])]));
        self::assertNull($alternatives->blocking([new NullOnly()]));
    }

    public function testOfAnswersTheAlternativesOfAType(): void
    {
        $alternatives = new Alternatives();

        self::assertSame([null], $alternatives->of(new NullOnly()));
        self::assertEquals([new Decimal(), new Integral(IntegralKind::Int)], $alternatives->of(new Choice([new Decimal(), new Integral(IntegralKind::Int)])));
    }

    public function testKnownMergesEqualTypes(): void
    {
        $alternatives = new Alternatives();

        self::assertEquals(new Known(new Decimal()), $alternatives->known([new Decimal(), new Decimal()]));
        self::assertInstanceOf(Choice::class, $alternatives->known([new Integral(IntegralKind::BigInt), new Integral(IntegralKind::BigInt, null, [NumericModifier::Unsigned])]));
        self::assertInstanceOf(NullOnly::class, $alternatives->known([]));
    }

    public function testKeyDistinguishesSignedness(): void
    {
        self::assertNotSame((new Alternatives())->key(new Integral(IntegralKind::Int)), (new Alternatives())->key(new Integral(IntegralKind::Int, null, [NumericModifier::Zerofill])));
    }
}
