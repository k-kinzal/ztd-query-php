<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseOption;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseParameter;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Utility\DatabaseCommands;

#[CoversClass(DatabaseCommands::class)]
#[Medium]
final class DatabaseCommandsTest extends TestCase
{
    public function testWriteReturnsNullForOtherOperations(): void
    {
        self::assertNull(DatabaseCommands::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }

    #[TestWith(["CREATE DATABASE app WITH OWNER = bob CONNECTION LIMIT -1 is_template true LOCALE 'C' TEMPLATE DEFAULT", 'CREATE DATABASE "app" WITH OWNER = \'bob\' CONNECTION LIMIT = -1 IS_TEMPLATE = TRUE LOCALE = \'C\' TEMPLATE = DEFAULT'])]
    #[TestWith(['ALTER DATABASE app ALLOW_CONNECTIONS 0', 'ALTER DATABASE "app" WITH ALLOW_CONNECTIONS = FALSE'])]
    #[TestWith(['ALTER DATABASE app TABLESPACE fast', 'ALTER DATABASE "app" SET TABLESPACE "fast"'])]
    #[TestWith(['ALTER DATABASE app REFRESH COLLATION VERSION', 'ALTER DATABASE "app" REFRESH COLLATION VERSION'])]
    #[TestWith(['ALTER DATABASE app SET x.y TO 1, \'a\'', 'ALTER DATABASE "app" SET "x"."y" = 1, \'a\''])]
    #[TestWith(['ALTER DATABASE app RESET TRANSACTION ISOLATION LEVEL', 'ALTER DATABASE "app" RESET "transaction_isolation"'])]
    #[TestWith(['ALTER DATABASE app RESET ALL', 'ALTER DATABASE "app" RESET ALL'])]
    #[TestWith(['DROP DATABASE IF EXISTS app WITH (FORCE)', 'DROP DATABASE IF EXISTS "app" WITH (FORCE)'])]
    public function testWriteProducesAFixedPointOfBinding(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertSame($expected, $statement->toString());
        self::assertEquals($statement->toString(), $binder->bind($expected)->toString());
    }

    public function testTargetNamesTheAlteredDatabase(): void
    {
        self::assertSame('ALTER DATABASE "a b"', DatabaseCommands::target('a b')->toString());
    }

    public function testNameQuotesEmbeddedQuotes(): void
    {
        self::assertSame('"x""y"', DatabaseCommands::name('x"y')->toString());
    }

    public function testOptionsOmitsAnEmptyList(): void
    {
        self::assertSame([], DatabaseCommands::options([]));
        self::assertCount(2, DatabaseCommands::options([new DatabaseOption(DatabaseParameter::Encoding, 'UTF8')]));
    }

    #[TestWith([null, 'DEFAULT'])]
    #[TestWith([false, 'FALSE'])]
    #[TestWith([-1, '-1'])]
    #[TestWith(["it's", "'it''s'"])]
    public function testValueWritesConstantsAndDefault(string|int|bool|null $value, string $expected): void
    {
        self::assertSame($expected, DatabaseCommands::value($value)->toString());
    }
}
