<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;

#[CoversClass(OptionalWords::class)]
#[Medium]
final class OptionalWordsTest extends TestCase
{
    public function testCasesTellWhetherTheOptionalWordsAreWritten(): void
    {
        self::assertSame(['Written', 'Omitted'], array_column(OptionalWords::cases(), 'name'));
    }

    public function testTheWrittenFormNamesTheColumnDifferently(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT CURRENT_DATE, CURRENT_DATE(), ROW(1, 2) = (1, 2)', []);

        self::assertSame(['CURRENT_DATE', 'CURRENT_DATE()', 'ROW(1, 2) = (1, 2)'], [$operation->field(0)->name?->value, $operation->field(1)->name?->value, $operation->field(2)->name?->value]);
        self::assertSame('SELECT CURRENT_DATE AS a, CURDATE() AS b, ROW(1, 2) = (1, 2) AS c', (new Semantics(Dialect::MySql))->analyze('SELECT current_date AS a, current_date() AS b, row(1, 2) = (1, 2) AS c')->toString());
    }
}
