<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis\Derivation;

use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Analysis\Derivation\CallerSet;

#[CoversClass(CallerSet::class)]
final class CallerSetTest extends TestCase
{
    public function testHoldsTheCallsAndWhetherTheyArePartial(): void
    {
        $call = new Expr\FuncCall(new Name('run'));

        $set = new CallerSet([$call], true);

        self::assertSame([$call], $set->calls);
        self::assertTrue($set->partial);
    }

    public function testIsCompleteUnlessSaidOtherwise(): void
    {
        self::assertFalse((new CallerSet([]))->partial);
    }
}
