<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Subquery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Quantifier;
use SqlSemantics\Platform\MySql\Statement\Expression\Truth;
use SqlSemantics\Platform\MySql\Statement\Expression\TruthTest;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(QuantifiedComparison::class)]
#[Medium]
final class QuantifiedComparisonTest extends TestCase
{
    public function testDeriveScalarIsNotNullForNotNullOperands(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $comparison = (new Lowering($platform->productions($profile), new Leaves(), $profile))->expressions->expression($platform->parser($profile)->parse('SELECT 1 > SOME (SELECT 2)')->find('expr')[0]);
        $derivation = new Derivation($platform->context($profile, null, [], true));

        self::assertInstanceOf(QuantifiedComparison::class, $comparison);
        self::assertSame(Quantifier::Any, $comparison->quantifier);
        self::assertSame(Nullability::NotNull, $derivation->scalar($comparison, $derivation->environment())->nullability);
    }

    public function testRenderWritesTheOperatorAndTheQuantifier(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $comparison = (new Lowering($platform->productions($profile), new Leaves(), $profile))->expressions->expression($platform->parser($profile)->parse('SELECT 1 <> all (select 2)')->find('expr')[0]);
        $out = new Output($platform->codec($profile));
        $comparison->render($out);

        self::assertSame('1 <> ALL (SELECT 2)', (new Lexical())->join($out->pieces()));
    }

    public function testATruthTestOperandIsRejected(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $comparison = (new Lowering($platform->productions($profile), new Leaves(), $profile))->expressions->expression($platform->parser($profile)->parse('SELECT 1 = ALL (SELECT 2)')->find('expr')[0]);
        self::assertInstanceOf(QuantifiedComparison::class, $comparison);

        $this->expectExceptionMessage('The operand of a quantified comparison needs a grouping to keep its place.');

        new QuantifiedComparison(new TruthTest(new NumberLiteral('1'), Truth::True), ComparisonOperator::Equal, Quantifier::All, $comparison->query);
    }
}
