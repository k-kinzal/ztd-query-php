<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\MySqlObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\MySqlObject\ObjectDefinitions;

#[CoversClass(ObjectDefinitions::class)]
#[Medium]
final class ObjectDefinitionsTest extends TestCase
{
    #[TestWith(['CREATE TABLESPACE ts', 'CREATE TABLESPACE `ts` WAIT'])]
    #[TestWith(["ALTER SERVER s OPTIONS (USER 'u')", "ALTER SERVER `s` OPTIONS(USER 'u')"])]
    #[TestWith(['ALTER VIEW v AS SELECT 1', 'ALTER VIEW `v` AS SELECT 1'])]
    public function testWriteRoutesEachObjectKind(string $sql, string $expected): void
    {
        self::assertSame($expected, ObjectDefinitions::write((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql))?->toString());
    }

    public function testWriteReturnsNullForAnotherOperation(): void
    {
        self::assertNull(ObjectDefinitions::write((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP SERVER s')));
    }
}
