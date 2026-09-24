<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Inspection\SchemaInspections;

#[CoversClass(SchemaInspections::class)]
#[Medium]
final class SchemaInspectionsTest extends TestCase
{
    #[TestWith(['SHOW DATABASES'])]
    #[TestWith(['SHOW CREATE TABLE `users`'])]
    #[TestWith(['SHOW ENGINE ALL STATUS'])]
    public function testWriteRoutesEachInspectionToItsWriter(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT)'));
        $statement = $binder->bind($sql);
        self::assertSame($sql, SchemaInspections::write($statement)?->toString());
        self::assertSame($sql, $binder->bind($statement->toString())->toString());
    }

    public function testWriteReturnsNullForOtherStatements(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        self::assertNull(SchemaInspections::write($binder->bind('SHOW ENGINES')));
        self::assertNull(SchemaInspections::write($binder->bind('SELECT 1')));
    }
}
