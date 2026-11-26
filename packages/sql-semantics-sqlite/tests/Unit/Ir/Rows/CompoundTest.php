<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Rows\Compound;
use SqlSemantics\Platform\Sqlite\Ir\Rows\CompoundOperator;
use SqlSemantics\Platform\Sqlite\Ir\Rows\CompoundStep;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;

#[CoversClass(Compound::class)]
#[Small]
final class CompoundTest extends TestCase
{
    public function testToStringKeepsTheDetachedStepList(): void
    {
        $arm = new ValuesQuery([new ValueRow([new IntegerLiteral('1')])]);
        $step = new CompoundStep(CompoundOperator::UnionAll, $arm);
        $compound = new Compound($arm, [&$step]);
        $step = new CompoundStep(CompoundOperator::Except, $arm);

        self::assertSame('VALUES (1) UNION ALL VALUES (1)', $compound->toString());
    }

    public function testProfileIdMatchesTheArms(): void
    {
        $arm = new ValuesQuery([new ValueRow([new IntegerLiteral('1')])]);
        $compound = new Compound($arm, [new CompoundStep(CompoundOperator::UnionAll, $arm)]);

        self::assertSame($arm->profileId(), $compound->profileId());
    }
}
