<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows\Values;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesCell;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesQuery;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesRow;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesSql;

#[CoversClass(ValuesSql::class)]
#[Small]
final class ValuesSqlTest extends TestCase
{
    public function testLocalBindsEveryOutputPositionToItsGeneratedName(): void
    {
        $row = new ValuesRow([new ValuesCell(new BooleanLiteral(false)), new ValuesCell(new NullLiteral())]);
        $query = new ValuesQuery([$row]);

        self::assertSame(
            'SELECT "__ztd_c0", "__ztd_c1" FROM (VALUES (FALSE, NULL)) AS "__ztd_values" ("__ztd_c0", "__ztd_c1")',
            (new ValuesSql())->local($query),
        );
    }
}
