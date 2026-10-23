<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredCollated;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(DeferredCollated::class)]
#[Medium]
final class DeferredCollatedTest extends TestCase
{
    public function testDeriveScalarPreservesLegacyDefinitionScope(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('CREATE PROCEDURE p() SELECT (1, 2) COLLATE missing');
        self::assertSame([], $operation->facts->diagnostics);
        self::assertSame([], $operation->facts->warnings);
    }

    public function testOperandsRetainsTheCompleteRow(): void
    {
        $row = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        self::assertSame([$row], (new DeferredCollated($row, new Name('binary')))->operands());
    }

    public function testProblemPreservesTheOperandCondition(): void
    {
        $value = new DeferredCollated(new Row([new NumberLiteral('1'), new NumberLiteral('2')]), new Name('binary'));
        self::assertInstanceOf(OperandColumns::class, $value->problem());
    }

    public function testRenderRetainsTheNameAndOperandWithoutInputText(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');
        $sql = 'CREATE PROCEDURE p() SELECT (1, 2) COLLATE missing';
        self::assertSame($sql, $semantics->analyze($sql)->toString());
    }
}
