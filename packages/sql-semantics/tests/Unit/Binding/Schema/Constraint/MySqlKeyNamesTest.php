<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Schema\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binding\Schema\Constraint\MySqlKeyNames;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\IndexDefinition;
use SqlSemantics\Schema\TableConstraint;
use SqlSemantics\Schema\TableDefinition;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MySqlKeyNames::class)]
#[Medium]
final class MySqlKeyNamesTest extends TestCase
{
    /**
     * @param list<string> $sql
     * @param list<?string> $constraints
     * @param list<?string> $indexes
     */
    #[TestWith([['CREATE TABLE t (a INT UNIQUE, b INT, UNIQUE KEY (a), KEY (a))'], ['a', 'a_2'], ['a_3']])]
    #[TestWith([['CREATE TABLE t (a SERIAL, b INT, UNIQUE KEY (a), KEY (a))'], ['a', 'a_2'], ['a_3']])]
    #[TestWith([['CREATE TABLE t (a INT, b INT, KEY a (b), KEY (a))'], [], ['a', 'a_2']])]
    #[TestWith([['CREATE TABLE t (`primary` INT, KEY (`primary`))'], [], ['primary_2']])]
    #[TestWith([['CREATE TABLE t (a INT, b INT, KEY ((a + 1)), KEY ((b + 1)), KEY functional_index_3 (a))'], [], ['functional_index', 'functional_index_2', 'functional_index_3']])]
    #[TestWith([['CREATE TABLE t (a INT, b INT, KEY (a))', 'ALTER TABLE t ADD KEY (a), ADD UNIQUE (a)', 'ALTER TABLE t DROP KEY a, ADD KEY (a)'], ['a_3'], ['a_2', 'a']])]
    #[TestWith([['CREATE TABLE t (a INT, UNIQUE (a), b INT UNIQUE, KEY (b), c SERIAL)'], ['b', 'c', 'a'], ['b_2']])]
    #[TestWith([['CREATE TABLE t (a INT UNIQUE UNIQUE KEY, KEY (a))'], ['a'], ['a_2']])]
    public function testAssignNamesUnnamedKeysAsTheServerDoes(array $sql, array $constraints, array $indexes): void
    {
        $table = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build(...$sql)->tables[0];
        self::assertSame($constraints, array_map(static fn (TableConstraint $constraint): ?string => $constraint->name, $table->constraints));
        self::assertSame($indexes, array_map(static fn (IndexDefinition $index): ?string => $index->name, $table->indexes));
    }

    public function testAssignLetsDropIndexFindANumberedKey(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build('CREATE TABLE t (a INT UNIQUE, b INT, KEY (a), KEY (a, b))', 'DROP INDEX a_2 ON t', 'ALTER TABLE t DROP INDEX a_3')->tables[0];
        self::assertSame(['a'], array_map(static fn (TableConstraint $constraint): ?string => $constraint->name, $table->constraints));
        self::assertSame([], $table->indexes);
    }

    public function testTableLeavesATableWithoutUnnamedKeysAsItIs(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (a INT PRIMARY KEY, b INT, KEY named (b))')->tables[0];
        self::assertSame($table, MySqlKeyNames::table($table));
    }

    public function testBaseNamesAKeyAfterItsFirstColumnOrAsFunctional(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (a INT, b INT, KEY named (b, a), KEY other ((a + b)))')->tables[0];
        self::assertSame(['b', 'functional_index', 'functional_index'], [MySqlKeyNames::base($table->indexes[0]->elements), MySqlKeyNames::base($table->indexes[1]->elements), MySqlKeyNames::base([])]);
    }

    public function testChooseNumbersATakenNameWithoutCase(): void
    {
        self::assertSame(['a', 'A_2', 'b_3'], [MySqlKeyNames::choose('a', ['PRIMARY']), MySqlKeyNames::choose('A', ['a']), MySqlKeyNames::choose('b', ['B', 'b_2'])]);
        self::assertSame(str_repeat('x', 61) . '_2', MySqlKeyNames::choose(str_repeat('x', 64), [str_repeat('x', 64)]));
    }

    public function testIndexRenamesOnlyTheName(): void
    {
        $plain = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (a INT, KEY i (a))')->tables[0]->indexes[0];
        $renamed = MySqlKeyNames::index($plain, 'j');
        self::assertSame(['j', 'i'], [$renamed->name, $plain->name]);
        self::assertSame($plain->elements, $renamed->elements);
    }

    public function testOffsetOrdersKeysByTheirPositionAndPutsAnEmptySourceLast(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (a INT, KEY i (a))')->tables[0];
        self::assertGreaterThan(0, MySqlKeyNames::offset($table->indexes[0]->source));
        self::assertSame(PHP_INT_MAX, MySqlKeyNames::offset(new \SqlParser\Parser\Node('empty', 0, [])));
    }

    public function testAssignNamesTheKeysOfEveryTable(): void
    {
        $tables = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (a INT, KEY (a))', 'CREATE TABLE u (b INT, KEY (b), KEY (b))')->tables;
        self::assertSame([['a'], ['b', 'b_2']], array_map(static fn (TableDefinition $table): array => array_map(static fn (IndexDefinition $index): ?string => $index->name, $table->indexes), MySqlKeyNames::assign($tables)));
    }
}
