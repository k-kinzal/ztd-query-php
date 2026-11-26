<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Binding\Parameter;
use SqlSemantics\Platform\PostgreSql\Ir\Binding\ParameterSignature;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\BuiltinCast;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\CastDestination;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\ResultColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Select;

#[CoversClass(ParameterSignature::class)]
#[Small]
final class ParameterSignatureTest extends TestCase
{
    public function testTypeForKeepsTheTypeOfANewSlot(): void
    {
        self::assertSame(CastDestination::WideInteger, ParameterSignature::typeFor(null, new Parameter(1, CastDestination::WideInteger)));
    }

    public function testCheckReachesTheInputBehindARepeatedConversion(): void
    {
        $left = new Select([new ResultColumn(new Parameter(1), new Identifier('a'))]);
        $wide = new Parameter(1, CastDestination::WideInteger);
        $right = new Select([new ResultColumn(new BuiltinCast(CastDestination::Integer, $wide), new Identifier('b'))]);
        $this->expectExceptionMessage('parameter position must have one input type');

        ParameterSignature::check($left, $right);
    }

    public function testCheckQueriesRejectsAConflictOutsideTheFirstMember(): void
    {
        $first = new Select([new ResultColumn(new Parameter(1), new Identifier('first'))]);
        $second = new Select([new ResultColumn(new Parameter(1, CastDestination::Boolean), new Identifier('second'))]);
        $this->expectExceptionMessage('parameter position must have one input type');

        ParameterSignature::checkQueries([$first, $first, $second]);
    }
}
