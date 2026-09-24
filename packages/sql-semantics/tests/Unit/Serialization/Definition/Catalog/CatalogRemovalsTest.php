<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Catalog\CatalogRemovals;

#[CoversClass(CatalogRemovals::class)]
#[Medium]
final class CatalogRemovalsTest extends TestCase
{
    public function testWriteReturnsNullForUnrelatedRequests(): void
    {
        self::assertNull(CatalogRemovals::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }

    #[TestWith(['DROP SEQUENCE IF EXISTS s CASCADE'])]
    #[TestWith(['DROP COLLATION c'])]
    #[TestWith(['DROP EXTENSION a, b RESTRICT'])]
    #[TestWith(['DROP RULE r ON t'])]
    #[TestWith(['DROP TYPE integer[]'])]
    public function testWriteProducesTheStatementText(string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind($sql, strict: false);
        self::assertSame($statement->toString(), CatalogRemovals::write($statement)?->toString());
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerWriteSpellsEveryRemoval')]
    public function testWriteSpellsEveryRemoval(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, CatalogRemovals::write($statement)?->toString());
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerWriteSpellsEveryRemoval(): iterable
    {
        return [
            'DROP SEQUENCE IF EXISTS s CASCADE (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'DROP SEQUENCE IF EXISTS s CASCADE', 'DROP SEQUENCE IF EXISTS "s" CASCADE'],
            'DROP COLLATION IF EXISTS c (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'DROP COLLATION IF EXISTS c', 'DROP COLLATION IF EXISTS "c"'],
            'DROP EXTENSION a, b RESTRICT (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'DROP EXTENSION a, b RESTRICT', 'DROP EXTENSION "a", "b" RESTRICT'],
            'DROP RULE r ON t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'DROP RULE r ON t', 'DROP RULE "r" ON "t"'],
            'DROP RULE IF EXISTS r ON t CASCADE (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'DROP RULE IF EXISTS r ON t CASCADE', 'DROP RULE IF EXISTS "r" ON "t" CASCADE'],
            'DROP POLICY p ON t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'DROP POLICY p ON t', 'DROP POLICY "p" ON "t"'],
            'DROP TYPE integer[] (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'DROP TYPE integer[]', 'DROP TYPE integer []'],
            'DROP TYPE IF EXISTS a, b CASCADE (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'DROP TYPE IF EXISTS a, b CASCADE', 'DROP TYPE IF EXISTS "a", "b" CASCADE'],
            'DROP DOMAIN d RESTRICT (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'DROP DOMAIN d RESTRICT', 'DROP DOMAIN "d" RESTRICT'],
            'DROP DOMAIN IF EXISTS d (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int)'], 'DROP DOMAIN IF EXISTS d', 'DROP DOMAIN IF EXISTS "d"'],
        ];
    }
}
