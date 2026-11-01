<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Legacy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Query\Legacy\UnionEnclosures;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\UnenclosedUnion;

#[CoversClass(UnionEnclosures::class)]
#[Medium]
final class UnionEnclosuresTest extends TestCase
{
    public function testRejectedRetainsTheFirstCompleteUnenclosedOperand(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.6.51');
        $tree = $semantics->parser()->parse('(SELECT 1) UNION SELECT 2 UNION (SELECT 3)');
        $failure = (new \SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings())->enclosures($tree, $semantics->profile());
        self::assertNotNull($failure);
        self::assertInstanceOf(UnenclosedUnion::class, $failure->problem);
        self::assertSame(25, $failure->problem->offset);
    }

    public function testAtRetainsADerivedOperandBoundary(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.6.51');
        $tree = $semantics->parser()->parse('SELECT * FROM ((SELECT 1) UNION SELECT 2) q');
        $failure = (new \SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings())->enclosures($tree, $semantics->profile());
        self::assertNotNull($failure);
        self::assertInstanceOf(UnenclosedUnion::class, $failure->problem);
        self::assertSame(40, $failure->problem->offset);
    }

    public function testTreeLeavesAnUnenclosedStartUnrestricted(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.6.51');
        $tree = $semantics->parser()->parse('SELECT 1 UNION (SELECT 2) UNION SELECT 3');
        self::assertNull((new \SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings())->enclosures($tree, $semantics->profile()));
    }

    public function testDerivedAcceptsMixedEnclosuresOnTheLaterRelease(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $tree = $semantics->parser()->parse('SELECT * FROM ((SELECT 1) UNION SELECT 2) AS q');
        self::assertNull((new \SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings())->enclosures($tree, $semantics->profile()));
    }
}
