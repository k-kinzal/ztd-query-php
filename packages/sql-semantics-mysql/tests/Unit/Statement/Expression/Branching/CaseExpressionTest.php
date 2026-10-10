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
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(CaseExpression::class)]
#[Medium]
final class CaseExpressionTest extends TestCase
{
    public function testDeriveScalarInfersAbsentVariablesFromTheFirstResultBranch(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        $context = $semantics->context([], session: new \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_0900_ai_ci'), userVariables: []));
        $query = $semantics->analyze('SELECT CASE WHEN 0 THEN @v ELSE 1 END,CASE WHEN 0 THEN @v END,CASE WHEN 0 THEN @v ELSE NULL END', $context);

        self::assertEquals(new Known(Domain::integer()), $query->field(0)->type);
        self::assertEquals(new Known(Domain::string(65532, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary())), $query->field(1)->type);
        self::assertEquals(new Known(Domain::string(65535, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary())), $query->field(2)->type);
        self::assertSame(Nullability::Nullable, $query->field(0)->nullability);
    }
    public function testDeriveScalarAggregatesTheResultsAndIsNullWithoutElse(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $fact = $derivation->scalar(new CaseExpression(null, [new CaseBranch(new NumberLiteral('1'), new NumberLiteral('2')), new CaseBranch(new NumberLiteral('0'), new NumberLiteral('2.5'))]), $derivation->environment());

        self::assertEquals(new Known(Domain::decimal(2, 1)), $fact->type);
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
