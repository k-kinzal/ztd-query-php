<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Constraint\KeyIndex;
use SqlSemantics\Schema\Constraint\PrimaryKey;
use SqlSemantics\Schema\Constraint\UniqueKey;
use SqlSemantics\Schema\Index\Kind;
use SqlSemantics\Schema\Index\Properties;
use SqlSemantics\SchemaBuilder;

#[CoversClass(KeyIndex::class)]
#[Medium]
final class KeyIndexTest extends TestCase
{
    public function testMySqlKeyKeepsItsIndexNameMethodAndOptions(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build("CREATE TABLE t(a INT, b INT, PRIMARY KEY USING HASH (a) COMMENT 'p', CONSTRAINT c UNIQUE KEY uk (b) INVISIBLE KEY_BLOCK_SIZE = 4)")->tables[0];
        $primary = $table->constraints[0];
        $unique = $table->constraints[1];
        self::assertInstanceOf(PrimaryKey::class, $primary);
        self::assertInstanceOf(UniqueKey::class, $unique);
        self::assertSame([null, 'hash', 'p'], [$primary->index->name, $primary->index->method, $primary->index->properties->comment]);
        self::assertSame(['c', 'uk', false, 4], [$unique->name, $unique->index->name, $unique->index->properties->visible, $unique->index->properties->keyBlockSize]);
    }

    public function testPostgreSqlKeyKeepsIncludeParametersAndTablespace(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER, UNIQUE (a) INCLUDE (b) WITH (fillfactor = 70) USING INDEX TABLESPACE fast)')->tables[0];
        $unique = $table->constraints[0];
        self::assertInstanceOf(UniqueKey::class, $unique);
        self::assertSame(['b'], $unique->index->include);
        self::assertSame(['fillfactor'], $unique->index->properties->storageParameters[0]->name->parts);
        self::assertSame('fast', $unique->index->properties->tablespace);
    }

    public function testCheckAcceptsOptionsOfTheKeyDialect(): void
    {
        $index = new KeyIndex('uk', 'btree', [], new Properties(comment: 'c'));
        $index->check(Dialect::MySql);
        (new KeyIndex(include: ['b'], properties: new Properties(tablespace: 'fast')))->check(Dialect::PostgreSql);
        (new KeyIndex())->check(Dialect::Sqlite);
        self::assertSame(['uk', 'btree', 'c'], [$index->name, $index->method, $index->properties->comment]);
    }

    /**
     * @param list<string> $include
     */
    #[TestWith([Dialect::PostgreSql, 'uk', []])]
    #[TestWith([Dialect::Sqlite, null, ['b']])]
    #[TestWith([Dialect::MySql, null, ['b']])]
    public function testCheckRejectsOptionsOfAnotherDialect(Dialect $dialect, ?string $name, array $include): void
    {
        $this->expectException(InvalidStructure::class);
        (new KeyIndex($name, null, $include))->check($dialect);
    }

    #[TestWith([''])]
    public function testRejectsAnEmptyName(string $name): void
    {
        $this->expectException(InvalidStructure::class);
        new KeyIndex($name);
    }

    #[TestWith([Kind::FullText, true])]
    #[TestWith([Kind::Ordinary, false])]
    public function testRejectsAnIndexKindOrNullTreatmentOfItsOwn(Kind $kind, bool $nullsDistinct): void
    {
        $this->expectException(InvalidStructure::class);
        new KeyIndex(properties: new Properties($kind, nullsDistinct: $nullsDistinct));
    }
}
