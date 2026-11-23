<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows\Values;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesCell;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesQuery;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesRow;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(ValuesQuery::class)]
#[Small]
final class ValuesQueryTest extends TestCase
{
    public function testColumnKindUsesTheWholeColumn(): void
    {
        $unknown = new ValuesRow([new ValuesCell(new NullLiteral())]);
        $boolean = new ValuesRow([new ValuesCell(new BooleanLiteral(true))]);
        $query = new ValuesQuery([$unknown, $unknown, $boolean]);

        self::assertSame(ColumnKind::Boolean, $query->columnKind(0));
    }

    public function testProfileIdIdentifiesTheSelectedRelease(): void
    {
        $row = new ValuesRow([new ValuesCell(new NullLiteral())]);

        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', (new ValuesQuery([$row], GrammarRelease::PostgreSql166))->profileId());
    }

    public function testToStringKeepsEveryRepeatedRow(): void
    {
        $row = new ValuesRow([new ValuesCell(new BooleanLiteral(true))]);

        self::assertSame('VALUES (TRUE), (TRUE)', (new ValuesQuery([$row, $row]))->toString());
    }
}
