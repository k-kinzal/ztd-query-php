<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Server\ServerOptions;
use SqlSemantics\Model\Statement\Definition\MySql\Server\AlterServerStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterServerStatement::class)]
#[Medium]
final class AlterServerStatementTest extends TestCase
{
    public function testWithOriginRetainsTheAlteration(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("ALTER SERVER s OPTIONS (SOCKET '/tmp/s')");
        self::assertInstanceOf(AlterServerStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame("ALTER SERVER `s` OPTIONS(SOCKET '/tmp/s')", $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("ALTER SERVER s OPTIONS (SOCKET '/tmp/s')");
        self::assertInstanceOf(AlterServerStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithNameReplacesTheServer(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("ALTER SERVER s OPTIONS (SOCKET '/tmp/s')");
        self::assertInstanceOf(AlterServerStatement::class, $statement);
        self::assertSame("ALTER SERVER `t` OPTIONS(SOCKET '/tmp/s')", $statement->withName('t')->toString());
        self::assertSame('s', $statement->name);
    }

    public function testWithOptionsReplacesTheChangedOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("ALTER SERVER s OPTIONS (SOCKET '/tmp/s')");
        self::assertInstanceOf(AlterServerStatement::class, $statement);
        self::assertSame("ALTER SERVER `s` OPTIONS(OWNER 'o', PASSWORD 'p')", $statement->withOptions(new ServerOptions(owner: 'o', password: 'p'))->toString());
        self::assertSame('/tmp/s', $statement->options->socket);
    }
}
