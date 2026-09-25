<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\MySqlObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Server\ServerOptions;
use SqlSemantics\Model\Statement\Definition\MySql\View\AlterViewStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\MySqlObject\ServerDefinitions;

#[CoversClass(ServerDefinitions::class)]
#[Medium]
final class ServerDefinitionsTest extends TestCase
{
    #[TestWith(["CREATE SERVER s FOREIGN DATA WRAPPER mysql OPTIONS (PORT 1, HOST 'h')", "CREATE SERVER `s` FOREIGN DATA WRAPPER `mysql` OPTIONS(HOST 'h', PORT 1)"])]
    #[TestWith(["ALTER SERVER s OPTIONS (DATABASE 'd')", "ALTER SERVER `s` OPTIONS(DATABASE 'd')"])]
    #[TestWith(["CREATE AGGREGATE FUNCTION IF NOT EXISTS f RETURNS REAL SONAME 'u.so'", "CREATE AGGREGATE FUNCTION IF NOT EXISTS `f` RETURNS REAL SONAME 'u.so'"])]
    #[TestWith(['ALTER DEFINER = CURRENT_USER VIEW v AS SELECT 1', 'ALTER DEFINER = CURRENT_USER VIEW `v` AS SELECT 1'])]
    public function testWriteRoundTripsEveryForm(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind($sql);
        self::assertSame($expected, ServerDefinitions::write($statement)?->toString());
        self::assertSame($statement::class, $binder->bind($expected)::class);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testWriteReturnsNullForAnotherOperation(): void
    {
        self::assertNull(ServerDefinitions::write((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE TABLESPACE ts')));
    }

    public function testOptionsWritesTheOptionsInAFixedOrder(): void
    {
        self::assertSame("OPTIONS(USER 'u', HOST 'h', DATABASE 'd', OWNER 'o', PASSWORD 'p', SOCKET 's', PORT 7)", ServerDefinitions::options(new ServerOptions('u', 'h', 'd', 'o', 'p', 's', 7))->toString());
    }

    public function testViewWritesTheColumnsAndCheckOption(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER ALGORITHM = UNDEFINED VIEW v (a) AS SELECT 1 WITH LOCAL CHECK OPTION');
        self::assertInstanceOf(AlterViewStatement::class, $statement);
        self::assertSame('ALTER VIEW `v`(`a`) AS SELECT 1 WITH LOCAL CHECK OPTION', ServerDefinitions::view($statement)->toString());
    }
}
