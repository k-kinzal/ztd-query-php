<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Cursor\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Cursor\Handler\OpenHandlerStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(OpenHandlerStatement::class)]
#[Medium]
final class OpenHandlerStatementTest extends TestCase
{
    public function testWithOriginRetainsTheOpenedTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('HANDLER t OPEN AS h');
        self::assertInstanceOf(OpenHandlerStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('HANDLER `t` OPEN AS `h`', $copy->toString());
        self::assertSame('HANDLER', $copy->kind->value);
    }

    public function testHandlerIsTheAliasOrTheTableName(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)'));
        $aliased = $binder->bind('HANDLER t OPEN h');
        $plain = $binder->bind('HANDLER t OPEN');
        self::assertInstanceOf(OpenHandlerStatement::class, $aliased);
        self::assertInstanceOf(OpenHandlerStatement::class, $plain);
        self::assertSame(['h', 't'], [$aliased->handler(), $plain->handler()]);
    }

    public function testWithTableOpensAnotherTable(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)', 'CREATE TABLE u(id INT)'));
        $statement = $binder->bind('HANDLER t OPEN');
        $other = $binder->bind('HANDLER u OPEN AS v');
        self::assertInstanceOf(OpenHandlerStatement::class, $statement);
        self::assertInstanceOf(OpenHandlerStatement::class, $other);
        self::assertSame('HANDLER `u` OPEN AS `v`', $statement->withTable($other->table)->toString());
        self::assertSame('HANDLER `t` OPEN', $statement->toString());
    }

    public function testRejectsAnotherDatabaseDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('HANDLER t OPEN');
        self::assertInstanceOf(OpenHandlerStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new OpenHandlerStatement(new Origin('s0', $statement->source, Dialect::PostgreSql), $statement->table);
    }
}
