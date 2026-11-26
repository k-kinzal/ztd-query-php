<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\KeyTiming;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\PrimaryKey;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\TableKeySet;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\UniqueKey;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(TableKeySet::class)]
#[Small]
final class TableKeySetTest extends TestCase
{
    public function testCloseKeepsThePrimaryAndTransfersTheFirstExplicitRedundantName(): void
    {
        $value = (new Semantics())->analyze(
            'CREATE TABLE t (a integer UNIQUE PRIMARY KEY, b text, CONSTRAINT first_name UNIQUE (a),'
            . ' CONSTRAINT later_name UNIQUE (a), CONSTRAINT distinct_nulls UNIQUE NULLS NOT DISTINCT (a))',
            (new Semantics())->context(['CREATE TABLE public.ir_type_anchor (id integer)']),
        );
        self::assertInstanceOf(CreateTable::class, $value);
        self::assertSame('first_name', $value->primaryKey?->name?->value);
        self::assertCount(1, $value->uniqueKeys);
        self::assertSame('distinct_nulls', $value->uniqueKeys[0]->name?->value);
    }

    public function testCloseRejectsConflictingExplicitNamesAmongRetainedIndexes(): void
    {
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('Retained table keys must use unused explicit index names.');

        (new Semantics())->analyze('CREATE TABLE t (a integer CONSTRAINT same UNIQUE, b integer CONSTRAINT same UNIQUE)');
    }

    public function testSameComparesNullAndTimingAndPayloadRoles(): void
    {
        $key = new Identifier('a');
        $payload = new Identifier('b');
        $primary = new PrimaryKey([$key], [$key, $payload]);

        self::assertTrue(TableKeySet::same($primary, new UniqueKey([$key], [$key, $payload])));
        self::assertFalse(TableKeySet::same($primary, new UniqueKey([$key], [$payload, $key])));
        self::assertFalse(TableKeySet::same($primary, new UniqueKey([$key], [$key, $payload], timing: KeyTiming::InitiallyDeferred)));
        self::assertFalse(TableKeySet::same($primary, new UniqueKey([$key], [$key, $payload], nullsNotDistinct: true)));
    }

    public function testSameReferencesUsesOrderedDecodedValues(): void
    {
        self::assertTrue(TableKeySet::sameReferences([new Identifier('a')], [new Identifier('a')]));
        self::assertFalse(TableKeySet::sameReferences([new Identifier('a')], [new Identifier('A')]));
    }
}
