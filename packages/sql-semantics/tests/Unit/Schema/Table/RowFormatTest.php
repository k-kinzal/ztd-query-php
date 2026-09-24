<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Table\MySqlProperties;
use SqlSemantics\Schema\Table\RowFormat;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Storage;

#[CoversClass(RowFormat::class)]
#[Medium]
final class RowFormatTest extends TestCase
{
    public function testRepresentsEveryRowFormat(): void
    {
        self::assertSame(['default', 'dynamic', 'fixed', 'compressed', 'redundant', 'compact'], array_column(RowFormat::cases(), 'value'));
    }

    public function testClassifiesRowFormatCaseInsensitivelyAndSerializesItUpperCase(): void
    {
        $properties = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT) ROW_FORMAT=dynamic')->tables[0]->properties;
        self::assertInstanceOf(MySqlProperties::class, $properties);
        self::assertSame(RowFormat::Dynamic, $properties->rowFormat);
        self::assertSame('ROW_FORMAT = DYNAMIC', Storage::table($properties, Dialect::MySql)->toString());
    }
}
