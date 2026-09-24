<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Column\Format;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Format::class)]
#[Medium]
final class FormatTest extends TestCase
{
    public function testRepresentsEveryColumnFormat(): void
    {
        self::assertSame(['default', 'fixed', 'dynamic'], array_column(Format::cases(), 'value'));
    }

    public function testClassifiesTheColumnFormatOption(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT COLUMN_FORMAT DYNAMIC, b INT COLUMN_FORMAT DEFAULT, c INT)')->tables[0];
        self::assertSame(Format::Dynamic, $table->columns[0]->attributes->format);
        self::assertSame(Format::Default, $table->columns[1]->attributes->format);
        self::assertNull($table->columns[2]->attributes->format);
    }
}
