<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Schema\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Schema\Constraint\PostgreSqlKeyMerges;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\CreateTableStatement;
use SqlSemantics\Schema\Constraint;
use SqlSemantics\Schema\TableConstraint;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PostgreSqlKeyMerges::class)]
#[Medium]
final class PostgreSqlKeyMergesTest extends TestCase
{
    /**
     * @param list<string> $sql
     * @param list<?string> $expected
     */
    #[TestWith([['CREATE TABLE t (a int, b int, UNIQUE (a), UNIQUE (a), UNIQUE (a, b), UNIQUE (a, b))'], ['t_a_key', 't_a_b_key']])]
    #[TestWith([['CREATE TABLE u (a int UNIQUE UNIQUE, b int)'], ['u_a_key']])]
    #[TestWith([['CREATE TABLE x (a int PRIMARY KEY, UNIQUE (a))'], ['x_pkey']])]
    #[TestWith([['CREATE TABLE a1 (a int, CONSTRAINT u UNIQUE (a), PRIMARY KEY (a))'], ['u']])]
    #[TestWith([['CREATE TABLE a5 (a int UNIQUE DEFERRABLE INITIALLY DEFERRED, UNIQUE (a) DEFERRABLE)'], ['a5_a_key', 'a5_a_key1']])]
    #[TestWith([['CREATE TABLE y (a int, b int, UNIQUE (a, b), UNIQUE (b, a), UNIQUE NULLS NOT DISTINCT (a, b), UNIQUE (a, b) DEFERRABLE, CONSTRAINT named UNIQUE (a, b), UNIQUE (a, b) INCLUDE (a))'], ['named', 'y_b_a_key', 'y_a_b_key', 'y_a_b_key1', 'y_a_b_a1_key']])]
    #[TestWith([['CREATE TABLE z (a int, b int)', 'ALTER TABLE z ADD UNIQUE (a)', 'ALTER TABLE z ADD UNIQUE (a), ADD UNIQUE (a)'], ['z_a_key', 'z_a_key1', 'z_a_key2']])]
    public function testMergeKeepsTheKeysTheServerCreates(array $sql, array $expected): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql, grammarVersion: 'pg-17.2'))->build(...$sql)->tables[0];
        self::assertSame($expected, array_map(static fn (TableConstraint $constraint): ?string => $constraint->name, $table->constraints));
    }

    public function testMergeLeavesTheBoundStatementAsWritten(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t (a int PRIMARY KEY, UNIQUE (a), UNIQUE (a))');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        self::assertCount(3, $statement->definition->table->constraints);
        self::assertCount(1, PostgreSqlKeyMerges::merge($statement->definition->table->constraints));
    }

    public function testPriorFindsTheKeptKeyOfTheSameIndex(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t (a int, b int, CHECK (a > 0), UNIQUE (b), UNIQUE (a), UNIQUE (a))');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        $constraints = $statement->definition->table->constraints;
        self::assertInstanceOf(Constraint\UniqueKey::class, $constraints[3]);
        self::assertSame(2, PostgreSqlKeyMerges::prior($constraints, [0, 1, 2], $constraints[3]));
        self::assertNull(PostgreSqlKeyMerges::prior($constraints, [1], $constraints[3]));
    }

    public function testSignatureReadsColumnsIncludedColumnsNullsAndDeferrability(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t (a int, b int, UNIQUE NULLS NOT DISTINCT (a, b) INCLUDE (a) DEFERRABLE)');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        $key = $statement->definition->table->constraints[0];
        self::assertInstanceOf(Constraint\UniqueKey::class, $key);
        self::assertSame([['a', 'b'], ['a'], false, 'deferrable-immediate'], PostgreSqlKeyMerges::signature($key));
    }
}
