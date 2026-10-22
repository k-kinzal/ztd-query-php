<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition\Method;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\DeferredMethod;

#[CoversClass(DeferredMethod::class)]
#[Medium]
final class DeferredMethodTest extends TestCase
{
    public function testDeriveMethodRetainsItsWholeOperandForContextualization(): void
    {
        $row = new M\Expression\Row([new M\Call\KeywordCall(M\Call\KeywordFunction::User, []), new M\Literal\NumberLiteral('1')]);
        $method = new DeferredMethod($row);
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context());
        $method->deriveMethod($derivation, $derivation->environment());
        $diagnostics = $derivation->facts()->diagnostics;
        $problem = $diagnostics[count($diagnostics) - 1];
        self::assertInstanceOf(M\Partition\Problem\InvalidPartitionExpression::class, $problem);
        self::assertSame($row, $problem->expression);
    }

    public function testRenderRetainsTheMethodInsideAStoredAlteration(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() ALTER TABLE t ENGINE=InnoDB PARTITION BY LINEAR HASH((1,2)) PARTITIONS 3');
        self::assertSame('CREATE PROCEDURE p() ALTER TABLE t ENGINE InnoDB PARTITION BY LINEAR HASH ((1, 2)) PARTITIONS 3', $operation->toString());
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(M\Routine\Program\Write\DeferredAlterTable::class, $operation->statement->body);
    }

}
