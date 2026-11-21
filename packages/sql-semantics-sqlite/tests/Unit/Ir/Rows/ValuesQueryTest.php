<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;

#[CoversClass(ValuesQuery::class)]
#[Small]
final class ValuesQueryTest extends TestCase
{
    public function testProfileIdIdentifiesTheFixedDialect(): void
    {
        $row = new ValueRow([new IntegerLiteral('1')]);

        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', (new ValuesQuery([$row]))->profileId());
    }

    public function testToStringPreservesDuplicateRows(): void
    {
        $row = new ValueRow([new IntegerLiteral('1')]);

        self::assertSame('VALUES (1), (1)', (new ValuesQuery([$row, $row]))->toString());
    }
}
