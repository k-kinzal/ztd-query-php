<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\KeyConstraintLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\KeyTiming;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\UniqueKey;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Semantics;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\ColumnUnique;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable as ParsedCreateTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(KeyConstraintLowering::class)]
#[Small]
final class KeyConstraintLoweringTest extends TestCase
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

        (new KeyConstraintLowering())->timing([ConstraintAttribute::InitiallyDeferred, ConstraintAttribute::NotDeferrable]);
    }

    public function testReferencesDecodesTheOrderedNativeFieldNames(): void
    {
        $resolved = (new KeyConstraintLowering())->references([new Name('b'), new Name('a')]);

        self::assertSame(['b', 'a'], array_column($resolved, 'value'));
    }

    public function testKeySelectsTheUniqueRoleAndAcceptsTheTuplesWithNullsPolicy(): void
    {
        $resolved = (new KeyConstraintLowering())->key(
            new ColumnUnique(nullsDistinct: false),
            [new Identifier('a')],
            [],
            [ConstraintAttribute::Deferrable],
        );

        self::assertInstanceOf(UniqueKey::class, $resolved);
        self::assertTrue($resolved->nullsNotDistinct);
        self::assertSame(KeyTiming::InitiallyImmediate, $resolved->timing);
    }

    public function testDefinitionsCollectsInlineAndTableKeyEffectsInWrittenOrder(): void
    {
        $query = (new Inspection())->inspect(
            'CREATE TABLE public.t (a INTEGER UNIQUE, b INTEGER PRIMARY KEY, CONSTRAINT u UNIQUE (b,a))',
        );
        self::assertInstanceOf(ParsedCreateTable::class, $query->statement);
        self::assertInstanceOf(ListedColumns::class, $query->statement->definition);
        [$fields, $primary, $unique] = (new KeyConstraintLowering())->definitions($query->binding(), $query->statement->definition);

        self::assertCount(2, $fields);
        self::assertSame('b', $primary?->keys[0]->value);
        self::assertSame('a', $unique[0]->keys[0]->value);
        self::assertSame('u', $unique[1]->name?->value);
    }
}
