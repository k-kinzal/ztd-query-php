<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Index\Kind;
use SqlSemantics\Schema\Index\Properties;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Properties::class)]
#[Medium]
final class PropertiesTest extends TestCase
{
    public function testDefaultsDescribeAnOrdinaryVisibleIndex(): void
    {
        $properties = new Properties();
        self::assertSame(Kind::Ordinary, $properties->kind);
        self::assertNull($properties->visible);
        self::assertNull($properties->keyBlockSize);
        self::assertNull($properties->comment);
        self::assertTrue($properties->nullsDistinct);
        self::assertSame([], $properties->storageParameters);
    }

    public function testBindsMySqlIndexOptions(): void
    {
        $properties = (new SchemaBuilder(Dialect::MySql))->build("CREATE TABLE t(id INT); CREATE INDEX ix ON t(id) KEY_BLOCK_SIZE=8 COMMENT 'c' INVISIBLE")->tables[0]->indexes[0]->properties;
        self::assertSame(8, $properties->keyBlockSize);
        self::assertSame('c', $properties->comment);
        self::assertFalse($properties->visible);
        self::assertSame(Kind::Ordinary, $properties->kind);
    }

    public function testBindsPostgreSqlStorageParametersAndNullsDistinct(): void
    {
        $properties = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER); CREATE UNIQUE INDEX ix ON t(id) NULLS NOT DISTINCT WITH (fillfactor = 80)')->tables[0]->indexes[0]->properties;
        self::assertFalse($properties->nullsDistinct);
        self::assertCount(1, $properties->storageParameters);
        self::assertSame(['fillfactor'], $properties->storageParameters[0]->name->parts);
    }
}
