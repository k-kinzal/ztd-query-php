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
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(InQuery::class)]
#[Medium]
final class InQueryTest extends TestCase
{
    public function testDeriveScalarIsNullWhenAColumnCanBeAndReportsAWidthMismatch(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $test = (new Lowering($platform->productions($profile), new Leaves(), $profile))->expressions->expression($platform->parser($profile)->parse('SELECT 1 IN (SELECT NULL, 2)')->find('expr')[0]);
        $derivation = new Derivation($platform->context($profile, null, [], true));

        self::assertInstanceOf(InQuery::class, $test);
        self::assertSame(Nullability::Nullable, $derivation->scalar($test, $derivation->environment())->nullability);
        self::assertSame('Operand should contain 1 column(s), not 2.', $derivation->facts()->diagnostics[0]->message());
    }

    public function testRenderWritesTheNegatedTest(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $test = (new Lowering($platform->productions($profile), new Leaves(), $profile))->expressions->expression($platform->parser($profile)->parse('SELECT 1 not in (select 1)')->find('expr')[0]);
        $out = new Output($platform->codec($profile));
        $test->render($out);

        self::assertSame('1 NOT IN (SELECT 1)', (new Lexical())->join($out->pieces()));
    }

    public function testAComparisonOperandIsRejected(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $test = (new Lowering($platform->productions($profile), new Leaves(), $profile))->expressions->expression($platform->parser($profile)->parse('SELECT 1 IN (SELECT 1)')->find('expr')[0]);
        self::assertInstanceOf(InQuery::class, $test);

        $this->expectExceptionMessage('The operand of IN needs a grouping to keep its place.');

        new InQuery(new Comparison(ComparisonOperator::Equal, new NumberLiteral('1'), new NumberLiteral('2')), $test->query);
    }
}
