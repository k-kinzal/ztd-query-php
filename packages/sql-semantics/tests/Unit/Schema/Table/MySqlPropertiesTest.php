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

#[CoversClass(MySqlProperties::class)]
#[Medium]
final class MySqlPropertiesTest extends TestCase
{
    public function testDialectIsMySql(): void
    {
        $properties = new MySqlProperties();
        self::assertSame(Dialect::MySql, $properties->dialect());
        self::assertFalse($properties->temporary);
        self::assertNull($properties->engine);
        self::assertNull($properties->rowFormat);
    }

    public function testBindsNamedTableOptions(): void
    {
        $properties = (new SchemaBuilder(Dialect::MySql))->build("CREATE TEMPORARY TABLE t(id INT) ENGINE=InnoDB ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8 COMMENT='c'")->tables[0]->properties;
        self::assertInstanceOf(MySqlProperties::class, $properties);
        self::assertTrue($properties->temporary);
        self::assertSame('InnoDB', $properties->engine);
        self::assertSame(RowFormat::Compressed, $properties->rowFormat);
        self::assertSame(8, $properties->keyBlockSize);
        self::assertSame('c', $properties->comment);
        self::assertSame("ENGINE `InnoDB` COMMENT = 'c' KEY_BLOCK_SIZE = 8 ROW_FORMAT = COMPRESSED", Storage::table($properties, Dialect::MySql)->toString());
    }

    public function testRejectsUseAsAnotherDialectsTableProperties(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t()');
        $declared = $schema->tables[0];
        $table = new \SqlSemantics\Schema\TableDefinition($declared->schema, $declared->name, $declared->columns, $declared->constraints, $declared->source, $declared->resolved, $declared->indexes, new MySqlProperties());
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new \SqlSemantics\Schema(Dialect::PostgreSql, [$table], $schema->defaultSchema, $schema->grammarVersion);
    }
}
