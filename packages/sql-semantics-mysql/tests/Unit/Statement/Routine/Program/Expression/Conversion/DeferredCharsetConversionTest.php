<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression\Conversion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Conversion\DeferredCharsetConversion;

#[CoversClass(DeferredCharsetConversion::class)]
#[Medium]
final class DeferredCharsetConversionTest extends TestCase
{
    public function testDeriveScalarBindsWithoutInvokingTheCondition(): void
    {
        $row = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $value = new DeferredCharsetConversion($row, new \SqlSemantics\Platform\MySql\Statement\Name\CharsetName(new \SqlSemantics\Statement\Identifier\Name('utf8mb4')));
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $fact = $derivation->scalar($value, $derivation->environment());
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Invalid::class, $fact->type);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testOperandsRetainsTheCompletePreparationOrder(): void
    {
        $row = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $value = new DeferredCharsetConversion($row, new \SqlSemantics\Platform\MySql\Statement\Name\CharsetName(new \SqlSemantics\Statement\Identifier\Name('utf8mb4')));
        self::assertCount(1, $value->operands());
        self::assertSame($row, $value->operands()[0]);
    }

    public function testProblemRetainsTheActualRowWidth(): void
    {
        $row = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $value = new DeferredCharsetConversion($row, new \SqlSemantics\Platform\MySql\Statement\Name\CharsetName(new \SqlSemantics\Statement\Identifier\Name('utf8mb4')));
        $problem = $value->problem();
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns::class, $problem);
        self::assertSame([1, 2], [$problem->expected, $problem->actual]);
    }

    public function testRenderRoundTripsEveryRetainedRole(): void
    {
        $row = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $value = new DeferredCharsetConversion($row, new \SqlSemantics\Platform\MySql\Statement\Name\CharsetName(new \SqlSemantics\Statement\Identifier\Name('utf8mb4')));
        $sql = 'CREATE PROCEDURE p() SELECT CONVERT((1, 2) USING utf8mb4)';
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze($sql);
        self::assertInstanceOf(CreateProcedure::class, $operation);
        self::assertInstanceOf(DeferredSelection::class, $operation->body);
        $item = $operation->body->items[0];
        self::assertInstanceOf(DeferredProjection::class, $item);
        self::assertNull((new \SqlSemantics\Validation\Equivalence())->difference($value, $item->expression));
        self::assertSame($sql, $operation->toString());
        self::assertSame($sql, $semantics->analyze($operation->toString())->toString());
    }
}
