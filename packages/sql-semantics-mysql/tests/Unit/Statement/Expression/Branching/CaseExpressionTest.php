<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Branching;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseBranch;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(CaseExpression::class)]
#[Medium]
final class CaseExpressionTest extends TestCase
{
    public function testDeriveScalarAggregatesTheResultsAndIsNullWithoutElse(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $fact = $derivation->scalar(new CaseExpression(null, [new CaseBranch(new NumberLiteral('1'), new NumberLiteral('2')), new CaseBranch(new NumberLiteral('0'), new NumberLiteral('2.5'))]), $derivation->environment());

        self::assertEquals(new Known(new Decimal()), $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testDeriveScalarReportsAValueOfAnotherWidthThanTheOperand(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $derivation->scalar(new CaseExpression(new NumberLiteral('1'), [new CaseBranch(new Row([new NumberLiteral('1'), new NumberLiteral('2')]), new NullLiteral())], new StringLiteral(['x'])), $derivation->environment());

        self::assertSame('Operand should contain 1 column(s), not 2.', $derivation->facts()->diagnostics[0]->message());
    }

    public function testRenderWritesTheSimpleForm(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new CaseExpression(new NumberLiteral('1'), [new CaseBranch(new NumberLiteral('1'), new StringLiteral(['a']))], new StringLiteral(['b'])))->render($out);

        self::assertSame("CASE 1 WHEN 1 THEN 'a' ELSE 'b' END", (new Lexical())->join($out->pieces()));
    }

    public function testACaseWithoutBranchesIsRejected(): void
    {
        $this->expectExceptionMessage('A CASE expression has at least one WHEN branch.');

        new CaseExpression(null, []);
    }
}
