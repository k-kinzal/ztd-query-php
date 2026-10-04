<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Truth;
use SqlSemantics\Platform\MySql\Statement\Expression\TruthTest;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(TruthTest::class)]
#[Medium]
final class TruthTestTest extends TestCase
{
    public function testDeriveScalarIsNeverNull(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $fact = $derivation->scalar(new TruthTest(new NullLiteral(), Truth::Unknown), $derivation->environment());

        self::assertEquals([new Known(new Integral(IntegralKind::BigInt)), Nullability::NotNull], [$fact->type, $fact->nullability]);
    }

    public function testRenderWritesTheNegatedTest(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new TruthTest(new Comparison(ComparisonOperator::Equal, new NumberLiteral('1'), new NumberLiteral('2')), Truth::False, true))->render($out);

        self::assertSame('1 = 2 IS NOT FALSE', (new Lexical())->join($out->pieces()));
    }

    public function testATruthTestOperandIsRejected(): void
    {
        $this->expectExceptionMessage('The operand of a truth test needs a grouping to keep its place.');

        new TruthTest(new TruthTest(new NumberLiteral('1'), Truth::True), Truth::True);
    }
}
