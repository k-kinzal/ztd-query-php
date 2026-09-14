<?php

declare(strict_types=1);

namespace Tests\Unit\Connection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Connection\ResultColumn;
use ZtdQuery\Connection\ResultSet;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(ResultSet::class)]
#[UsesClass(ColumnDeclaration::class)]
#[UsesClass(ResultColumn::class)]
final class ResultSetTest extends TestCase
{
    public function testCarriesEmptyRowsAndColumnsIndependently(): void
    {
        $column = new ResultColumn('id', new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'int4'));
        $result = new ResultSet([], [$column]);

        self::assertSame([], $result->rows);
        self::assertSame([$column], $result->columns);
    }
}
