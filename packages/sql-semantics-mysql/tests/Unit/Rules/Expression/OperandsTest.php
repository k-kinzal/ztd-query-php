<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Statement\Expression\Tuple;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(Operands::class)]
#[Medium]
final class OperandsTest extends TestCase
{
    public function testWidthCountsTheColumnsOfAKnownValue(): void
    {
        $operands = new Operands();

        self::assertSame([2, 1, null], [
            $operands->width(new ScalarFact(new Known(new Tuple(2)), Nullability::NotNull)),
            $operands->width(new ScalarFact(new NullOnly(), Nullability::Nullable)),
            $operands->width(new ScalarFact(new Dependent([new SessionState('x')]), Nullability::Dependent)),
        ]);
    }

    public function testSingleReportsARow(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        (new Operands())->single(new ScalarFact(new Known(new Tuple(3)), Nullability::NotNull), $derivation);

        self::assertSame('Operand should contain 1 column(s), not 3.', $derivation->facts()->diagnostics[0]->message());
    }

    public function testSingleAnswersAValueWithoutTypeForARowAndTheFactOtherwise(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $one = new ScalarFact(new Known(new Integral(IntegralKind::Int)), Nullability::Nullable);
        $row = (new Operands())->single(new ScalarFact(new Known(new Tuple(2)), Nullability::Nullable), $derivation);

        self::assertSame($one, (new Operands())->single($one, $derivation));
        self::assertInstanceOf(Invalid::class, $row->type);
        self::assertSame($derivation->facts()->diagnostics[0], $row->type->cause);
        self::assertSame(Nullability::Nullable, $row->nullability);
    }

    public function testComparableReportsTheFirstDifferentWidth(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        (new Operands())->comparable([new ScalarFact(new Known(new Tuple(2)), Nullability::NotNull), new ScalarFact(new NullOnly(), Nullability::Nullable), new ScalarFact(new Known(new Tuple(3)), Nullability::NotNull)], $derivation);

        self::assertCount(1, $derivation->facts()->diagnostics);
        self::assertSame('Operand should contain 2 column(s), not 1.', $derivation->facts()->diagnostics[0]->message());
    }

    public function testTruthIsAnInteger(): void
    {
        self::assertEquals(new ScalarFact(new Known(new Integral(IntegralKind::BigInt)), Nullability::Dependent), (new Operands())->truth(Nullability::Dependent));
    }
}
