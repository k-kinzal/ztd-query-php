<?php

declare(strict_types=1);

namespace Tests\Unit\Connection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Connection\ResultColumn;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(ResultColumn::class)]
#[UsesClass(ColumnDeclaration::class)]
final class ResultColumnTest extends TestCase
{
    public function testCarriesNameAndType(): void
    {
        $type = new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'int4');
        $column = new ResultColumn('id', $type);

        self::assertSame('id', $column->name);
        self::assertSame($type, $column->type);
    }
}
