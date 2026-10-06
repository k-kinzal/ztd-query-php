<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(Grouped::class)]
#[Medium]
final class GroupedTest extends TestCase
{
    public function testDeriveScalarHasTheFactsOfTheOperand(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $fact = $derivation->scalar(new Grouped(new NullLiteral()), $derivation->environment());

        self::assertInstanceOf(NullOnly::class, $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testRenderLetsAWeakerOperandStandOnTheRight(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new Logical(LogicalOperator::And, new NumberLiteral('1'), new Grouped(new Logical(LogicalOperator::Or, new NumberLiteral('2'), new NumberLiteral('3')))))->render($out);

        self::assertSame('1 AND (2 OR 3)', (new Lexical())->join($out->pieces()));
    }
}
