<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseBranch;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;

#[CoversClass(CaseExpression::class)]
#[Small]
final class CaseExpressionTest extends TestCase
{
    public function testChoiceDetachesBranchReferences(): void
    {
        $first = new CaseBranch(new IntegerLiteral('0'), new IntegerLiteral('1'));
        $entry = $first;
        $branches = [&$entry];
        $base = new NullLiteral();
        $choice = new CaseExpression($base, $branches);

        $entry = new CaseBranch(new IntegerLiteral('1'), new IntegerLiteral('2'));

        self::assertSame([$first], $choice->branches);
        self::assertSame($base, $choice->base);
        self::assertNull($choice->otherwise);
    }
}
