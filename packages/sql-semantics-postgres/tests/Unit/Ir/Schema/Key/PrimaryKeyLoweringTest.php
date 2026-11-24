<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\KeyTiming;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\PrimaryKeyLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute;

#[CoversClass(PrimaryKeyLowering::class)]
#[Small]
final class PrimaryKeyLoweringTest extends TestCase
{
    public function testColumnCanonicalizesInlineConstraintAndNullability(): void
    {
        $value = (new Semantics())->analyze('CREATE TABLE t (a int NULL CONSTRAINT "My Key" PRIMARY KEY INITIALLY DEFERRED, b pg_catalog.text)');
        self::assertInstanceOf(CreateTable::class, $value);
        self::assertNotNull($value->primaryKey);
        self::assertSame('My Key', $value->primaryKey->name?->value);
        self::assertSame(KeyTiming::InitiallyDeferred, $value->primaryKey->timing);
        self::assertSame('a', $value->primaryKey->keys[0]->value);
        self::assertFalse($value->columns[0]->nullable);
        self::assertTrue($value->columns[1]->nullable);
    }

    public function testTableRetainsWrittenKeyAndIncludedOrder(): void
    {
        $value = (new Semantics())->analyze('CREATE TABLE t (a pg_catalog.text, b BOOLEAN, c bigint, PRIMARY KEY (b,a) INCLUDE (a,c,c) DEFERRABLE)');
        self::assertInstanceOf(CreateTable::class, $value);
        self::assertNotNull($value->primaryKey);
        self::assertSame(KeyTiming::InitiallyImmediate, $value->primaryKey->timing);
        self::assertSame('b', $value->primaryKey->keys[0]->value);
        self::assertSame('c', $value->primaryKey->included[2]->value);
        self::assertTrue($value->columns[2]->nullable);
    }

    public function testTimingRejectsExplicitlyIncompatibleDeferral(): void
    {
        $this->expectException(AnalysisException::class);

        (new PrimaryKeyLowering())->timing([ConstraintAttribute::InitiallyDeferred, ConstraintAttribute::NotDeferrable]);
    }
}
