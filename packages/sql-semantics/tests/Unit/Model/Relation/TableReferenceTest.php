<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableReference::class)]
final class TableReferenceTest extends TestCase
{
    public function testWithScopeRetainsAnExplicitEmptyNamespace(): void
    {
        $sql = "DELETE FROM '' . 'text'";
        $table = 'text';
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\DeleteStatement::class, $statement);
        $target = $statement->affectedTables()[0];
        self::assertInstanceOf(TableReference::class, $target);
        self::assertSame(['', $table], $target->name->parts);
        self::assertSame($target->name, $target->withScope('inner')->name);
        self::assertSame(['', $table], $binder->bind($statement->toString(), strict: false)->affectedTables()[0]->name->parts);
        self::assertStringContainsString('""."text"', $statement->toString());
    }
    public function testInsertTargetPreservesItsExplicitNamespace(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $statement = $binder->bind("INSERT INTO '' . 'text' DEFAULT VALUES", strict: false);
        self::assertSame(['', 'text'], $statement->insertion->target->name->parts);
        self::assertSame(['', 'text'], $binder->bind($statement->toString(), strict: false)->insertion->target->name->parts);
    }

}
