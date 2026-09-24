<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Administration\FlushedTables;
use SqlSemantics\Model\Statement\Server\Administration\FlushTablesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(FlushedTables::class)]
#[Medium]
final class FlushedTablesTest extends TestCase
{
    public function testCheckReturnsTheTablesAndRequiresOneWhenAsked(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('FLUSH TABLES t');
        self::assertInstanceOf(FlushTablesStatement::class, $statement);
        self::assertSame($statement->tables, FlushedTables::check($statement->origin, $statement->tables, 'FLUSH', true));
        self::assertSame([], FlushedTables::check($statement->origin, [], 'FLUSH'));
        $this->expectException(InvalidStructure::class);
        FlushedTables::check($statement->origin, [], 'FLUSH', true);
    }

    public function testCheckReturnsEveryListedTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)', 'CREATE TABLE u(id INT)')))->bind('FLUSH TABLES t, u');
        self::assertInstanceOf(FlushTablesStatement::class, $statement);
        self::assertSame(['t', 'u'], array_map(static fn (\SqlSemantics\Model\Relation\TableReference $table): string => $table->name->parts[count($table->name->parts) - 1], FlushedTables::check($statement->origin, $statement->tables, 'FLUSH')));
    }

    public function testCheckRejectsAnAliasedTable(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)'));
        $statement = $binder->bind('FLUSH TABLES t');
        $query = $binder->bind('SELECT 1 FROM t AS x');
        self::assertInstanceOf(FlushTablesStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\TableReference::class, $query->from);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('FLUSH cannot use table aliases.');
        FlushedTables::check($statement->origin, [$query->from], 'FLUSH');
    }

    public function testCheckRejectsATableOfAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('FLUSH TABLES t');
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT)')))->bind('SELECT 1 FROM t');
        self::assertInstanceOf(FlushTablesStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\TableReference::class, $query->from);
        $this->expectException(InvalidStructure::class);
        FlushedTables::check($statement->origin, [$query->from], 'FLUSH');
    }

    public function testCheckRequiresMySql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        FlushedTables::check($statement->origin, [], 'FLUSH');
    }
}
