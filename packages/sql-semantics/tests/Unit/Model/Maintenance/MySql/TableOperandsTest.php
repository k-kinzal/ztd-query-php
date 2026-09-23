<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Maintenance\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\MySql\TableOperands;
use SqlSemantics\Model\Statement\Maintenance\MySql\CheckTablesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableOperands::class)]
#[Medium]
final class TableOperandsTest extends TestCase
{
    public function testValidateRejectsQueryAliases(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)'));
        $statement = $binder->bind('CHECK TABLE t');
        $query = $binder->bind('SELECT id FROM t AS q');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\TableReference::class, $query->from);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        TableOperands::validate($statement->origin, [$query->from]);
    }

    public function testValidateRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('CHECK TABLE t');
        self::assertInstanceOf(CheckTablesStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        TableOperands::validate($origin, $statement->tables);
    }
}
