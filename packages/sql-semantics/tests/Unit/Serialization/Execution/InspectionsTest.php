<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Execution\Inspections;

#[CoversClass(Inspections::class)]
#[Medium]
final class InspectionsTest extends TestCase
{
    #[TestWith(['SHOW ENGINES'])]
    #[TestWith(['SHOW PLUGINS'])]
    #[TestWith(['SHOW PRIVILEGES'])]
    #[TestWith(['SHOW PROCESSLIST'])]
    #[TestWith(['SHOW FULL PROCESSLIST'])]
    public function testWriteUsesTheConcreteMetadataRequest(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind($sql);
        self::assertSame($sql, Inspections::write($statement)?->toString());
        self::assertSame($sql, $binder->bind($statement->toString())->toString());
    }

    public function testWriteReturnsNullForANonInspectionOperation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertNull(Inspections::write($statement));
    }

}
