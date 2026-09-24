<?php

declare(strict_types=1);

namespace Tests\Unit\Type\Identity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Identity\StorageAffinity;

#[CoversClass(StorageAffinity::class)]
#[Medium]
final class StorageAffinityTest extends TestCase
{
    public function testRepresentsEverySqliteAffinity(): void
    {
        self::assertSame(['integer', 'text', 'blob', 'real', 'numeric'], array_column(StorageAffinity::cases(), 'value'));
    }

    public function testDerivesAffinityFromTheDeclaredTypeName(): void
    {
        $table = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INT, b CHARACTER(20), c BLOB, d REAL, e DECIMAL(10,5), f FLOATING)')->tables[0];
        self::assertSame([StorageAffinity::Integer, StorageAffinity::Text, StorageAffinity::Blob, StorageAffinity::Real, StorageAffinity::Numeric, StorageAffinity::Real], array_map(static fn ($column): ?StorageAffinity => $column->type->affinity, $table->columns));
    }

    public function testOtherDialectsHaveNoAffinity(): void
    {
        self::assertNull((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER)')->tables[0]->columns[0]->type->affinity);
    }
}
