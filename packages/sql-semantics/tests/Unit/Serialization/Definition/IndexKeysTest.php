<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\CreateIndexStatement;
use SqlSemantics\Schema\Index\ColumnKey;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\IndexKeys;

#[CoversClass(IndexKeys::class)]
#[Medium]
final class IndexKeysTest extends TestCase
{
    public function testWriteSerializesExpressionKeysWithCollationOperatorClassAndOrdering(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT, n TEXT)'));
        $statement = $binder->bind('CREATE INDEX ix ON t ((lower(n)) COLLATE "C" text_pattern_ops ASC NULLS FIRST, id)');
        self::assertInstanceOf(CreateIndexStatement::class, $statement);
        $elements = $statement->index->definition->elements;
        self::assertSame('("lower"("n")) COLLATE "C" "text_pattern_ops" ASC NULLS FIRST', IndexKeys::write($elements[0], Dialect::PostgreSql)->toString());
        self::assertSame('"id"', IndexKeys::write($elements[1], Dialect::PostgreSql)->toString());
        $rebound = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(CreateIndexStatement::class, $rebound);
        self::assertSame('ASC', $rebound->index->definition->elements[0]->direction?->value);
        self::assertSame('FIRST', $rebound->index->definition->elements[0]->nulls?->value);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($rebound));
    }

    public function testWriteKeepsTheMysqlPrefixLengthAfterTheColumn(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n VARCHAR(10))'));
        $statement = $binder->bind('CREATE INDEX ix ON t (n(5) DESC)');
        self::assertInstanceOf(CreateIndexStatement::class, $statement);
        $key = $statement->index->definition->elements[0];
        self::assertInstanceOf(ColumnKey::class, $key);
        self::assertSame(5, $key->prefixLength);
        self::assertSame('`n`(5) DESC', IndexKeys::write($key, Dialect::MySql)->toString());
    }

    public function testWriteUsesTheUnqualifiedColumnNameForSqliteKeys(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INT, n INT)'));
        $statement = $binder->bind('CREATE INDEX ix ON t (id COLLATE nocase ASC, n DESC)');
        self::assertInstanceOf(CreateIndexStatement::class, $statement);
        $elements = $statement->index->definition->elements;
        self::assertSame('"id" COLLATE "nocase" ASC', IndexKeys::write($elements[0], Dialect::Sqlite)->toString());
        self::assertSame('"n" DESC', IndexKeys::write($elements[1], Dialect::Sqlite)->toString());
    }
}
