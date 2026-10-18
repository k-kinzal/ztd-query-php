<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DormantCall;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(DormantCall::class)]
#[Medium]
final class DormantCallTest extends TestCase
{
    public function testNamedPreservesArgumentAliases(): void
    {
        $call = new DormantCall(new Name('ABS'), [new M\Call\CallArgument(new M\Literal\NumberLiteral('1'), new Name('x'))]);
        self::assertTrue($call->named());
        self::assertFalse((new DormantCall(new Name('ABS')))->named());
    }

    public function testOperandsKeepsEveryArgumentOccurrence(): void
    {
        $one = new M\Literal\NumberLiteral('1');
        $two = new M\Literal\NumberLiteral('2');
        $call = new DormantCall(new Name('ABS'), [new M\Call\CallArgument($one), new M\Call\CallArgument($two)]);
        self::assertSame([$one, $two], $call->operands());
        self::assertSame([], (new DormantCall(new Name('ABS')))->operands());
    }

    public function testProblemRetainsItsNativeSignatureCondition(): void
    {
        self::assertSame("Incorrect parameter count in the call to native function 'ABS'", (new DormantCall(new Name('ABS')))->problem()->message());
    }

    public function testDeriveScalarHasNoInventedScalarResult(): void
    {
        $derivation = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql))->context([]));
        $call = new DormantCall(new Name('ABS'));
        $fact = $derivation->scalar($call, $derivation->environment());
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Invalid::class, $fact->type);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderRetainsTheUnusedCall(): void
    {
        $sql = 'INSERT INTO t VALUES ROW(1) ORDER BY ABS()';
        self::assertSame($sql, (new Semantics(Dialect::MySql))->analyze($sql)->toString());
    }
}
