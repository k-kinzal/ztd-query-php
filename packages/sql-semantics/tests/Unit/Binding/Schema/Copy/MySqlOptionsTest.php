<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Schema\Copy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binding\Schema\Copy\MySqlOptions;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Table\MySqlProperties;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MySqlOptions::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class MySqlOptionsTest extends TestCase
{
    public function testCopyPreservesStorageAndResetsInstanceOptions(): void
    {
        $source = new MySqlProperties(temporary: true, engine: 'InnoDB', comment: 'copied', dataDirectory: '/data', indexDirectory: '/index', autoIncrement: 42, encryption: 'N');
        $copy = MySqlOptions::copy($source, false);
        self::assertSame('InnoDB', $copy->engine);
        self::assertSame('copied', $copy->comment);
        self::assertFalse($copy->temporary);
        self::assertNull($copy->dataDirectory);
        self::assertNull($copy->indexDirectory);
        self::assertNull($copy->autoIncrement);
        self::assertNull($copy->encryption);
        self::assertSame(42, $source->autoIncrement);
        self::assertTrue($source->temporary);
    }

    public function testCopyTakesTemporaryStatusFromTheNewStatement(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build("CREATE TEMPORARY TABLE base(id INTEGER) ENGINE=InnoDB COMMENT='copied'", 'CREATE TABLE regular LIKE base', 'CREATE TEMPORARY TABLE transient LIKE regular');
        self::assertInstanceOf(MySqlProperties::class, $schema->tables[1]->properties);
        self::assertInstanceOf(MySqlProperties::class, $schema->tables[2]->properties);
        self::assertFalse($schema->tables[1]->properties->temporary);
        self::assertTrue($schema->tables[2]->properties->temporary);
        self::assertSame('InnoDB', $schema->tables[2]->properties->engine);
        self::assertSame('copied', $schema->tables[2]->properties->comment);
    }
}
