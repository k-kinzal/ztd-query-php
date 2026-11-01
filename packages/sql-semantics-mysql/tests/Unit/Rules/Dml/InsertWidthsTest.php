<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Dml\InsertWidths;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(InsertWidths::class)]
#[Small]
final class InsertWidthsTest extends TestCase
{
    public function testProblemRetainsDifferentRowsOnTheCompleteRequest(): void
    {
        $into = new M\Dml\Insert\InsertInto(false, null, false, new M\Dml\WriteTarget(new QualifiedName(new Name('t'))));
        $rows = [new M\Dml\Insert\InsertedRow([new M\Literal\NumberLiteral('1')]), new M\Dml\Insert\InsertedRow([])];
        self::assertEquals(new M\Dml\Problem\ValueCountMismatch(1, 0, 2), (new InsertWidths())->problem($into, $rows));
        $statement = new M\Dml\Insert\InsertRows($into, $rows);
        self::assertSame($rows, $statement->rows);
        self::assertTrue($statement->hasExecutionCondition());
    }

    public function testProblemRetainsAnExplicitColumnMismatchOnTheCompleteRequest(): void
    {
        $into = new M\Dml\Insert\InsertInto(true, null, false, new M\Dml\WriteTarget(new QualifiedName(new Name('t'))), new M\Dml\Insert\ColumnList([new M\Name\ColumnUse(new Name('a'))]));
        $rows = [new M\Dml\Insert\InsertedRow([new M\Literal\NumberLiteral('1'), new M\Dml\DefaultRequest()])];
        self::assertEquals(new M\Dml\Problem\ValueCountMismatch(1, 2, 1), (new InsertWidths())->problem($into, $rows));
        $statement = new M\Dml\Insert\InsertRows($into, $rows);
        self::assertSame($rows, $statement->rows);
        self::assertTrue($statement->hasExecutionCondition());
    }

    public function testProblemKeepsEmptyDefaultsAndUnresolvedDestinationWidths(): void
    {
        $into = new M\Dml\Insert\InsertInto(false, null, false, new M\Dml\WriteTarget(new QualifiedName(new Name('t'))), new M\Dml\Insert\ColumnList([]));
        $empty = [new M\Dml\Insert\InsertedRow([]), new M\Dml\Insert\InsertedRow([])];
        $rows = [new M\Dml\Insert\InsertedRow([new M\Literal\NumberLiteral('1')]), new M\Dml\Insert\InsertedRow([new M\Literal\NumberLiteral('2')])];
        self::assertNull((new InsertWidths())->problem($into, $empty));
        self::assertNull((new InsertWidths())->problem($into, $rows));
        self::assertSame($rows, (new M\Dml\Insert\InsertRows($into, $rows))->rows);
        self::assertSame($empty, (new M\Dml\Insert\InsertRows($into, $empty))->rows);
    }
}
