<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Table\MySqlProperties;
use SqlSemantics\Schema\Table\TableStorage;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableStorage::class)]
#[Medium]
final class TableStorageTest extends TestCase
{
    public function testIsReadFromTheDeclaration(): void
    {
        $properties = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT) STORAGE DISK')->tables[0]->properties;
        self::assertInstanceOf(MySqlProperties::class, $properties);
        self::assertSame(TableStorage::Disk, $properties->storage);
    }
}
