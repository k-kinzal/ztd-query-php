<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseBranch;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;

#[CoversClass(CaseBranch::class)]
#[Small]
final class CaseBranchTest extends TestCase
{
    public function testBranchPreservesTheOperandRoles(): void
    {
        $when = new IntegerLiteral('1');
        $then = new NullLiteral();
        $branch = new CaseBranch($when, $then);

        self::assertSame($when, $branch->when);
        self::assertSame($then, $branch->then);
    }
}
