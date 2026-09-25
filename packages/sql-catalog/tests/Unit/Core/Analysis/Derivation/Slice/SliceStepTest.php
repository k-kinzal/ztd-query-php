<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis\Derivation\Slice;

use PhpParser\Node\Expr;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Analysis\Derivation\Slice\SliceStep;

#[CoversClass(SliceStep::class)]
final class SliceStepTest extends TestCase
{
    public function testEitherHoldsRunsAnyOneOfWhichHappened(): void
    {
        $inner = new SliceStep(new Expr\Variable('a'));
        $step = SliceStep::either([[$inner], []]);

        self::assertNull($step->node);
        self::assertSame([[$inner], []], $step->alternatives);
    }

    public function testSignatureIsTheNodeForAStepThatIsOne(): void
    {
        $node = new Expr\Variable('a');

        self::assertSame((new SliceStep($node))->signature(), (new SliceStep($node))->signature());
        self::assertNotSame((new SliceStep($node))->signature(), (new SliceStep(new Expr\Variable('a')))->signature());
    }

    public function testSignatureTellsTwoGatheredStepsApart(): void
    {
        $first = SliceStep::either([]);
        $second = SliceStep::either([]);

        self::assertStringStartsWith('either', $first->signature());
        self::assertNotSame($first->signature(), $second->signature());
    }

    public function testAStepEnteringAClosureListsTheNamesTheClosureDefines(): void
    {
        self::assertSame(['id'], (new SliceStep(new Expr\Variable('c'), [], ['id']))->names);
    }
}
