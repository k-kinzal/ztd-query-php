<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Namespace\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexPresence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\CreateSchema;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\Index\SchemaIndexLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SchemaIndexLowering::class)]
final class SchemaIndexLoweringTest extends TestCase
{
    public function testLowerResolvesAndOrdersIndexesAgainstActualMembers(): void
    {
        $result = (new Semantics())->analyze(
            'CREATE SCHEMA batch CREATE INDEX IF NOT EXISTS feed ON readings (amount)'
            . ' CREATE INDEX by_amount ON readings (amount DESC)'
            . ' CREATE INDEX IF NOT EXISTS by_amount ON readings (enabled)'
            . ' CREATE VIEW feed AS SELECT 1 CREATE TABLE readings (amount bigint, enabled boolean)'
        );

        self::assertInstanceOf(CreateSchema::class, $result);
        self::assertSame(
            [IndexPresence::Occupied, IndexPresence::Vacant, IndexPresence::Occupied],
            array_map(static fn ($index) => $index->presence, $result->indexes)
        );
        self::assertSame('batch', $result->indexes[0]->table->schema->value);
        self::assertSame(1, $result->indexes[2]->keys[0]->slot);
        self::assertStringContainsString(
            'CREATE VIEW "batch"."feed" ("?column?") AS SELECT CAST(1 AS pg_catalog.int4) AS "?column?"'
            . ' CREATE INDEX IF NOT EXISTS "feed" ON "batch"."readings"',
            $result->toString()
        );
    }

    public function testLowerRejectsConcurrentCreation(): void
    {
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A schema index cannot be built concurrently.');

        (new Semantics())->analyze(
            'CREATE SCHEMA batch CREATE INDEX CONCURRENTLY bad ON readings (amount)'
            . ' CREATE TABLE readings (amount bigint)'
        );
    }
}
