<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Utility\SchemaCommands;

#[CoversClass(SchemaCommands::class)]
#[Medium]
final class SchemaCommandsTest extends TestCase
{
    #[TestWith(['CREATE SCHEMA app', 'CREATE SCHEMA "app"'])]
    #[TestWith(['CREATE SCHEMA IF NOT EXISTS AUTHORIZATION CURRENT_ROLE', 'CREATE SCHEMA IF NOT EXISTS AUTHORIZATION CURRENT_ROLE'])]
    #[TestWith(['CREATE SCHEMA app AUTHORIZATION bob CREATE VIEW v AS SELECT 1', 'CREATE SCHEMA "app" AUTHORIZATION "bob" CREATE VIEW "v" AS SELECT 1'])]
    public function testWriteProducesAFixedPointOfBinding(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        self::assertSame($expected, SchemaCommands::write($binder->bind($sql))?->toString());
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testWriteReturnsNullForOtherOperations(): void
    {
        self::assertNull(SchemaCommands::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }
}
