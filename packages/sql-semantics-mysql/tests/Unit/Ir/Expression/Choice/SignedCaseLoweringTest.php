<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression\Choice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\Expression\Choice\SignedCaseLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral as ParsedBoolean;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(SignedCaseLowering::class)]
#[Small]
final class SignedCaseLoweringTest extends TestCase
{
    public function testLowerPublishesNestedResultsThroughAnalyze(): void
    {
        $result = (new Semantics())->analyze('SELECT CASE WHEN NULL THEN -000 ELSE CASE 1 WHEN 2 THEN 4 END END AS choice');

        self::assertSame('SELECT (CASE WHEN NULL THEN -000 ELSE (CASE 1 WHEN 2 THEN 4 END) END) AS `choice`', $result->toString());
    }

    public function testResultPreservesSignedLiteralSpelling(): void
    {
        $binding = new SourceBinding((new Inspection())->inspect('SELECT 1')->binding(), null);
        $atom = (new SignedCaseLowering())->result(new NumberLiteral('0002'), $binding, 0);
        self::assertInstanceOf(RangeIntegerLiteral::class, $atom);
        self::assertSame('0002', $atom->toString());
    }

    public function testAtomKeepsTheBooleanRole(): void
    {
        $binding = new SourceBinding((new Inspection())->inspect('SELECT 1')->binding(), null);
        self::assertEquals(new BooleanLiteral(true), (new SignedCaseLowering())->atom(new ParsedBoolean(true), $binding));
    }

    public function testUngroupReturnsTheOriginalLeaf(): void
    {
        $leaf = new NumberLiteral('2');
        self::assertSame($leaf, (new SignedCaseLowering())->ungroup(new Grouped(new Grouped($leaf))));
    }
}
