<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Maintenance\IndexCache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\IndexCache as Cache;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Cache\TableIndexes::class)]
#[Medium]
final class TableIndexesTest extends TestCase
{
    public function testRejectsAQueryAliasAsAPhysicalStorageTarget(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('SELECT id FROM t AS q');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\TableReference::class, $query->relations[0]);
        $this->expectException(InvalidStructure::class);
        new Cache\TableIndexes($query->relations[0]);
    }

    public function testRejectsATableFromAnotherDatabaseLanguage(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT)')))->bind('SELECT id FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\TableReference::class, $query->relations[0]);
        $this->expectException(InvalidStructure::class);
        new Cache\TableIndexes($query->relations[0]);
    }

}
