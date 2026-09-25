<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Schema\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binding\Schema\Constraint\PostgreSqlConstraintNames;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Constraint;
use SqlSemantics\Schema\TableConstraint;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PostgreSqlConstraintNames::class)]
#[Medium]
final class PostgreSqlConstraintNamesTest extends TestCase
{
    public function testAssignNamesEveryUnnamedConstraintAsTheServerDoes(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql, grammarVersion: 'pg-17.2'))->build('CREATE TABLE t (a int CHECK (a > 0), b int NOT NULL CHECK (b > 0 AND b < 9), c int, CHECK (a > c), CHECK (a < 100), CHECK (true), UNIQUE (a, b), PRIMARY KEY (c), FOREIGN KEY (a) REFERENCES t (c), FOREIGN KEY (a) REFERENCES t (c), FOREIGN KEY (a, b) REFERENCES t (a, b))')->tables[0];
        self::assertSame(['t_a_check', 't_b_check', 't_check', 't_a_check1', 't_check1', 't_a_b_key', 't_pkey', 't_a_fkey', 't_a_fkey1', 't_a_b_fkey'], array_map(static fn (TableConstraint $constraint): ?string => $constraint->name, $table->constraints));
    }

    public function testAssignKeepsNamesWhenTheTableIsRenamedAndReusesADroppedName(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql, grammarVersion: 'pg-17.2'))->build(
            'CREATE TABLE u (a int CHECK (a > 0), b int CHECK (b > 0) CHECK (b > 1) CHECK (b > 2))',
            'ALTER TABLE u DROP CONSTRAINT u_b_check',
            'ALTER TABLE u ADD CHECK (b > 5)',
            'ALTER TABLE u ADD CHECK (b > 6)',
            'ALTER TABLE u RENAME TO v',
            'ALTER TABLE v DROP CONSTRAINT u_b_check2',
            'ALTER TABLE v ADD UNIQUE (a)',
        )->tables[0];
        self::assertSame(['u_a_check', 'u_b_check1', 'u_b_check', 'u_b_check3', 'v_a_key'], array_map(static fn (TableConstraint $constraint): ?string => $constraint->name, $table->constraints));
    }

    public function testAssignLeavesTablesWithoutUnnamedConstraints(): void
    {
        $tables = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a int CONSTRAINT c CHECK (a > 0))')->tables;
        self::assertSame($tables, PostgreSqlConstraintNames::assign($tables));
    }

    public function testTakenListsTheConstraintAndRelationNamesOfOneNamespace(): void
    {
        $tables = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a int CHECK (a > 0))', 'CREATE INDEX i ON t (a)', 'CREATE TABLE app.o (a int UNIQUE)')->tables;
        self::assertSame([['t_a_check'], ['t', 'i']], PostgreSqlConstraintNames::taken($tables, 'public'));
        self::assertSame([['o_a_key'], ['o']], PostgreSqlConstraintNames::taken($tables, 'app'));
    }

    public function testTableAvoidsTakenConstraintNamesAndKeysAvoidRelationNames(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql, grammarVersion: 'pg-17.2'))->build(
            'CREATE TABLE t4 (a int UNIQUE, CONSTRAINT t4_a_key CHECK (a < 5))',
            'CREATE TABLE t7 (a int, CONSTRAINT t7_pkey CHECK (a > 0), PRIMARY KEY (a))',
            'CREATE TABLE u1 (a int, b int, c int, UNIQUE (a) INCLUDE (b), CHECK (a > 0 AND a < b), CONSTRAINT u1_a_excl CHECK (a <> 5))',
            'CREATE INDEX u1_b_key ON u1 (b)',
            'ALTER TABLE u1 ADD UNIQUE (b), ADD CHECK (c > 0)',
            'CREATE TABLE t2 (a int CHECK (a > 0), CONSTRAINT t2_a_check1 CHECK (a < 5), CHECK (a < 7))',
        );
        self::assertSame([['t4_a_key1', 't4_a_key'], ['t7_pkey', 't7_pkey1'], ['u1_a_b_key', 'u1_check', 'u1_a_excl', 'u1_b_key1', 'u1_c_check'], ['t2_a_check', 't2_a_check1', 't2_a_check2']], array_map(static fn ($table): array => array_map(static fn (TableConstraint $constraint): ?string => $constraint->name, $table->constraints), $schema->tables));
        $unnamed = (new \SqlSemantics\Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t (a int CHECK (a > 0), UNIQUE (a))');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateTableStatement::class, $unnamed);
        $named = PostgreSqlConstraintNames::table($unnamed->definition->table, ['t_a_check'], ['t_a_key']);
        self::assertSame(['t_a_check1', 't_a_key1'], array_map(static fn (TableConstraint $constraint): ?string => $constraint->name, $named->constraints));
    }

    public function testPartsNamesTheColumnsAndTheLabelOfEachKind(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a int, b int, CHECK (a > 0 AND a < 9), CHECK (a > b), CHECK (true), PRIMARY KEY (a), UNIQUE (a) INCLUDE (b), FOREIGN KEY (a, b) REFERENCES t (a, b))')->tables[0];
        self::assertSame([['a', 'check'], [null, 'check'], [null, 'check'], [null, 'pkey'], ['a_b', 'key'], ['a_b', 'fkey']], array_map(PostgreSqlConstraintNames::parts(...), $table->constraints));
    }

    /**
     * @param list<string> $taken
     */
    #[TestWith(['t', 'a', 'check', [], 't_a_check'])]
    #[TestWith(['t', 'a', 'check', ['t_a_check', 't_a_check1'], 't_a_check2'])]
    #[TestWith(['t', null, 'pkey', ['t_pkey'], 't_pkey1'])]
    public function testChooseNumbersATakenName(string $table, ?string $addition, string $label, array $taken, string $expected): void
    {
        self::assertSame($expected, PostgreSqlConstraintNames::choose($table, $addition, $label, $taken));
    }

    #[TestWith(['a_very_long_table_name_that_goes_on_and_on_and_on_for_a_while_x', 'a_long_column_name_that_is_also_quite_long_really', 'check', 'a_very_long_table_name_that__a_long_column_name_that_is_a_check'])]
    #[TestWith(['a_very_long_table_name_that_goes_on_and_on_and_on_for_a_while_x', 'b', 'check', 'a_very_long_table_name_that_goes_on_and_on_and_on_for_a_b_check'])]
    #[TestWith(['a_very_long_table_name_that_goes_on_and_on_and_on_for_a_while_x', 'a_long_column_name_that_is_also_quite_long_really_b', 'key', 'a_very_long_table_name_that_g_a_long_column_name_that_is_al_key'])]
    #[TestWith(['t', null, 'pkey', 't_pkey'])]
    #[TestWith(['täääääääääääääääääääääääääääääääääääääääääää', null, 'check', 'tääääääääääääääääääääääääääää_check'])]
    public function testObjectNameCutsTheLongerPartToSixtyThreeBytes(string $table, ?string $addition, string $label, string $expected): void
    {
        $name = PostgreSqlConstraintNames::objectName($table, $addition, $label);
        self::assertSame($expected, $name);
        self::assertLessThanOrEqual(63, strlen($name));
    }

    public function testAdditionJoinsColumnsUntilTheNameIsFull(): void
    {
        self::assertSame('a_b_c', PostgreSqlConstraintNames::addition(['a', 'b', 'c']));
        self::assertSame(str_repeat('x', 40) . '_' . str_repeat('y', 40), PostgreSqlConstraintNames::addition([str_repeat('x', 40), str_repeat('y', 40), 'z']));
    }

    public function testIndexColumnsNumbersARepeatedName(): void
    {
        self::assertSame(['expr', 'expr1', 'a', 'expr2'], PostgreSqlConstraintNames::indexColumns(['expr', 'expr', 'a', 'expr']));
    }

    public function testNamedRenamesEveryKindAndKeepsTheOperands(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a int, b int, CHECK (a > 0) NO INHERIT, PRIMARY KEY (a), UNIQUE NULLS NOT DISTINCT (b), FOREIGN KEY (b) REFERENCES t (a) ON DELETE CASCADE)')->tables[0];
        $renamed = array_map(static fn (TableConstraint $constraint): TableConstraint => PostgreSqlConstraintNames::named($constraint, 'x'), $table->constraints);
        self::assertSame(['x', 'x', 'x', 'x'], array_map(static fn (TableConstraint $constraint): ?string => $constraint->name, $renamed));
        self::assertInstanceOf(Constraint\Check::class, $renamed[0]);
        self::assertTrue($renamed[0]->noInherit);
        self::assertInstanceOf(Constraint\UniqueKey::class, $renamed[2]);
        self::assertFalse($renamed[2]->nullsDistinct);
        self::assertInstanceOf(Constraint\ForeignKey::class, $renamed[3]);
        self::assertSame(\SqlSemantics\Schema\ReferentialAction::Cascade, $renamed[3]->onDelete);
        self::assertNull(PostgreSqlConstraintNames::named($renamed[1], null)->name);
        self::assertSame('t_a_check', $table->constraints[0]->name);
    }

    public function testCopiedKeepsChecksAndUnnamesKeysOfATemplate(): void
    {
        $constraints = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a int, CHECK (a > 0), CHECK (a < 9) NO INHERIT, PRIMARY KEY (a), FOREIGN KEY (a) REFERENCES t (a))')->tables[0]->constraints;
        self::assertSame(['t_a_check'], array_map(static fn (TableConstraint $constraint): ?string => $constraint->name, PostgreSqlConstraintNames::copied($constraints, true)));
        self::assertSame(['t_a_check', null], array_map(static fn (TableConstraint $constraint): ?string => $constraint->name, PostgreSqlConstraintNames::copied($constraints, false)));
    }
}
