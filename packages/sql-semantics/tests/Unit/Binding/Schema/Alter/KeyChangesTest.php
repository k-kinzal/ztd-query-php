<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Schema\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Schema\Alter\KeyChanges;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\MySqlTable\Key\KeyKind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Schema\Constraint\Check;
use SqlSemantics\Schema\Constraint\ForeignKey;
use SqlSemantics\Schema\Constraint\PrimaryKey;
use SqlSemantics\Schema\Constraint\UniqueKey;
use SqlSemantics\Schema\TableConstraint;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(KeyChanges::class)]
#[Medium]
final class KeyChangesTest extends TestCase
{
    public function testActionRecordsAnAdditionUntilTheDropsApply(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT, b INT)');
        $table = $schema->tables[0];
        $item = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t ADD PRIMARY KEY (a)')->find('alter_list_item')[0];
        $context = new QueryContext(new TableResolver($schema, new Identifiers(Dialect::MySql), ''));
        $scope = new Scope(new Identifiers(Dialect::MySql), [new TableReference('declaration', 'declaration', $table, new QualifiedName(['t']), null, $item)], queries: $context);
        $changes = new KeyChanges($table, [], []);
        $recorded = $changes->action($item, $scope, $context);
        self::assertNotNull($recorded);
        self::assertSame([$item], $recorded->additions);
        self::assertSame([], $recorded->constraints);
        self::assertSame([], $changes->additions);
        [$constraints] = $recorded->add($scope, $context);
        self::assertCount(1, $constraints);
        self::assertInstanceOf(PrimaryKey::class, $constraints[0]);
        self::assertSame(['a'], $constraints[0]->localColumns());
    }

    #[TestWith(['ALTER TABLE t DROP COLUMN a'])]
    #[TestWith(['ALTER TABLE t ADD COLUMN c INT'])]
    #[TestWith(['ALTER TABLE t RENAME TO u'])]
    public function testActionIgnoresAnActionWithoutATableLevelKey(string $sql): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT)');
        $item = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse($sql)->find('alter_list_item')[0];
        $context = new QueryContext(new TableResolver($schema, new Identifiers(Dialect::MySql), ''));
        self::assertNull((new KeyChanges($schema->tables[0], [], []))->action($item, new Scope(new Identifiers(Dialect::MySql), queries: $context), $context));
    }

    #[TestWith(['ALTER TABLE t DROP INDEX i', true])]
    #[TestWith(['ALTER TABLE t DROP KEY i', true])]
    #[TestWith(['ALTER TABLE t DROP PRIMARY KEY', true])]
    #[TestWith(['ALTER TABLE t DROP FOREIGN KEY f', true])]
    #[TestWith(['ALTER TABLE t DROP CHECK c', true])]
    #[TestWith(['ALTER TABLE t DROP CONSTRAINT c', true])]
    #[TestWith(['ALTER TABLE t DROP COLUMN a', false])]
    #[TestWith(['ALTER TABLE t DROP a', false])]
    public function testKeyDropTellsAKeyOrConstraintFromAColumn(string $sql, bool $expected): void
    {
        $item = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse($sql)->find('alter_list_item')[0];
        self::assertSame($expected, KeyChanges::keyDrop($item));
    }

    #[TestWith(['mysql-5.6.51', 'ALTER TABLE t DROP PRIMARY KEY', 'unique,foreign-key', 2])]
    #[TestWith(['mysql-5.7.44', 'ALTER TABLE t DROP INDEX uk', 'primary-key,foreign-key', 2])]
    #[TestWith(['mysql-8.0.44', 'ALTER TABLE t DROP KEY `PRIMARY`', 'unique,foreign-key', 2])]
    #[TestWith(['mysql-8.4.7', 'ALTER TABLE t DROP INDEX c', 'primary-key,unique,foreign-key', 1])]
    #[TestWith(['mysql-8.4.7', 'ALTER TABLE t DROP INDEX named', 'primary-key,unique,foreign-key', 1])]
    #[TestWith(['mysql-8.4.7', 'ALTER TABLE t DROP FOREIGN KEY fk', 'primary-key,unique', 2])]
    #[TestWith(['mysql-9.1.0', 'ALTER TABLE t DROP CONSTRAINT UK', 'primary-key,foreign-key', 2])]
    #[TestWith(['mysql-8.4.7', 'ALTER TABLE t DROP FOREIGN KEY uk', 'primary-key,unique,foreign-key', 2])]
    public function testDropRemovesTheAddressedMySqlKeyOrIndex(string $version, string $sql, string $kinds, int $indexes): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t (a INT PRIMARY KEY, b INT, c INT, d INT, UNIQUE KEY uk (b), INDEX (c), INDEX named (d), CONSTRAINT fk FOREIGN KEY (d) REFERENCES t (a))', $sql);
        $table = $schema->tables[0];
        self::assertSame($kinds, implode(',', array_map(static fn (TableConstraint $constraint): string => $constraint->kind->value, $table->constraints)));
        self::assertCount($indexes, $table->indexes);
        self::assertSame(Nullability::NotNull, $table->columns[0]->nullability);
    }

    public function testDropRemovesANamedMySqlCheck(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT, CONSTRAINT ch CHECK (a > 0), CONSTRAINT other CHECK (a < 9))', 'ALTER TABLE t DROP CHECK ch')->tables[0];
        self::assertCount(1, $table->constraints);
        self::assertSame('other', $table->constraints[0]->name);
    }

    #[TestWith(['ALTER TABLE t DROP CONSTRAINT t_pkey', 'unique,foreign-key,check'])]
    #[TestWith(['ALTER TABLE t DROP CONSTRAINT t_b_key', 'primary-key,foreign-key,check'])]
    #[TestWith(['ALTER TABLE t DROP CONSTRAINT t_c_fkey', 'primary-key,unique,check'])]
    #[TestWith(['ALTER TABLE t DROP CONSTRAINT ch CASCADE', 'primary-key,unique,foreign-key'])]
    #[TestWith(['ALTER TABLE t DROP CONSTRAINT IF EXISTS missing', 'primary-key,unique,foreign-key,check'])]
    public function testDropRemovesTheNamedPostgreSqlConstraint(string $sql, string $kinds): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a int PRIMARY KEY, b int UNIQUE, c int REFERENCES t (a), CONSTRAINT ch CHECK (a > 0))', $sql)->tables[0];
        self::assertSame($kinds, implode(',', array_map(static fn (TableConstraint $constraint): string => $constraint->kind->value, $table->constraints)));
        self::assertSame(Nullability::NotNull, $table->columns[0]->nullability);
    }

    public function testIndexNameNamesAnUnnamedMySqlIndexAfterItsFirstColumn(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT, b INT, INDEX (b, a), INDEX named (a))')->tables[0];
        self::assertSame('b', KeyChanges::indexName($table->indexes[0]));
        self::assertSame('named', KeyChanges::indexName($table->indexes[1]));
    }

    public function testAddressedMatchesTheConstraintKindsADropReaches(): void
    {
        $constraints = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT PRIMARY KEY, b INT UNIQUE, CONSTRAINT fk FOREIGN KEY (b) REFERENCES t (a), CHECK (a > 0))')->tables[0]->constraints;
        self::assertSame([true, false, false, false], array_map(static fn (TableConstraint $constraint): bool => KeyChanges::addressed($constraint, null), $constraints));
        self::assertSame([true, true, false, false], array_map(static fn (TableConstraint $constraint): bool => KeyChanges::addressed($constraint, KeyKind::Index), $constraints));
        self::assertSame([false, false, true, false], array_map(static fn (TableConstraint $constraint): bool => KeyChanges::addressed($constraint, KeyKind::ForeignKey), $constraints));
        self::assertSame([false, false, false, true], array_map(static fn (TableConstraint $constraint): bool => KeyChanges::addressed($constraint, KeyKind::Check), $constraints));
        self::assertSame([true, true, true, true], array_map(static fn (TableConstraint $constraint): bool => KeyChanges::addressed($constraint, KeyKind::Constraint), $constraints));
    }

    public function testNameGivesTheMySqlServerNames(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT, b INT, c INT, CONSTRAINT pk PRIMARY KEY (a), UNIQUE (b), UNIQUE KEY uk (c), CONSTRAINT fk FOREIGN KEY (b) REFERENCES t (a), CHECK (a > 0))')->tables[0];
        $changes = new KeyChanges($table, $table->constraints, $table->indexes);
        self::assertSame(['PRIMARY', 'b', 'uk', 'fk', ''], array_map(static fn (TableConstraint $constraint): string => $changes->name($constraint, Dialect::MySql), $table->constraints));
    }

    public function testNameGivesThePostgreSqlDefaultNames(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a int, b int, c int, PRIMARY KEY (a), UNIQUE (b, c), FOREIGN KEY (c) REFERENCES t (a), CHECK (a > 0), CONSTRAINT named UNIQUE (c))')->tables[0];
        $changes = new KeyChanges($table, $table->constraints, $table->indexes);
        self::assertSame(['t_pkey', 't_b_c_key', 't_c_fkey', 't_a_check', 'named'], array_map(static fn (TableConstraint $constraint): string => $changes->name($constraint, Dialect::PostgreSql), $table->constraints));
    }

    public function testNameDerivesThePostgreSqlNameOfAConstraintNotYetNamed(): void
    {
        $statement = (new \SqlSemantics\Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t (a int CHECK (a > 0), b int, c int, PRIMARY KEY (a), UNIQUE (b, c), FOREIGN KEY (c) REFERENCES t (a), CHECK (a > b))');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateTableStatement::class, $statement);
        $table = $statement->definition->table;
        $changes = new KeyChanges($table, $table->constraints, $table->indexes);
        self::assertSame([null, null, null, null, null], array_map(static fn (TableConstraint $constraint): ?string => $constraint->name, $table->constraints));
        self::assertSame(['t_a_check', 't_pkey', 't_b_c_key', 't_c_fkey', 't_check'], array_map(static fn (TableConstraint $constraint): string => $changes->name($constraint, Dialect::PostgreSql), $table->constraints));
    }

    #[TestWith(['mysql-5.6.51', 'ALTER TABLE t ADD PRIMARY KEY (a), ADD UNIQUE (b), ADD INDEX (c)'])]
    #[TestWith(['mysql-5.7.44', 'ALTER TABLE t ADD CONSTRAINT pk PRIMARY KEY (a), ADD UNIQUE KEY (b), ADD KEY (c)'])]
    #[TestWith(['mysql-8.0.44', 'ALTER TABLE t ADD PRIMARY KEY (a), ADD INDEX (c), ADD UNIQUE INDEX (b)'])]
    #[TestWith(['mysql-8.4.7', 'ALTER TABLE t ADD PRIMARY KEY (a), ADD CONSTRAINT u UNIQUE (b), ADD INDEX i (c)'])]
    #[TestWith(['mysql-9.1.0', 'ALTER TABLE t ADD PRIMARY KEY (a), ADD UNIQUE (b), ADD INDEX (c)'])]
    public function testAddAppliesMySqlKeysAndIndexesToTheSchema(string $version, string $sql): void
    {
        $table = (new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t (a INT NULL, b INT, c INT)', $sql)->tables[0];
        self::assertSame(['primary-key', 'unique'], array_map(static fn (TableConstraint $constraint): string => $constraint->kind->value, $table->constraints));
        self::assertSame([['a'], ['b']], array_map(static fn (TableConstraint $constraint): array => $constraint->localColumns(), $table->constraints));
        self::assertCount(1, $table->indexes);
        self::assertSame([Nullability::NotNull, Nullability::MaybeNull, Nullability::MaybeNull], array_column($table->columns, 'nullability'));
    }

    public function testAddAppliesMySqlForeignKeysAndChecks(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT PRIMARY KEY, b INT)', 'ALTER TABLE t ADD CONSTRAINT fk FOREIGN KEY (b) REFERENCES t (a), ADD CONSTRAINT ch CHECK (b > 0)')->tables[0];
        self::assertInstanceOf(ForeignKey::class, $table->constraints[1]);
        self::assertSame('fk', $table->constraints[1]->name);
        self::assertInstanceOf(Check::class, $table->constraints[2]);
        self::assertSame('ch', $table->constraints[2]->name);
    }

    #[TestWith(['mysql-8.4.7', 'ALTER TABLE t DROP PRIMARY KEY, ADD PRIMARY KEY (b)'])]
    #[TestWith(['mysql-5.7.44', 'ALTER TABLE t ADD PRIMARY KEY (b), DROP PRIMARY KEY'])]
    public function testAddReplacesAPrimaryKeyTheStatementDrops(string $version, string $sql): void
    {
        $table = (new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t (a INT PRIMARY KEY, b INT)', $sql)->tables[0];
        self::assertCount(1, $table->constraints);
        self::assertSame(['b'], $table->constraints[0]->localColumns());
        self::assertSame([Nullability::NotNull, Nullability::NotNull], array_column($table->columns, 'nullability'));
    }

    public function testAddAppliesPostgreSqlConstraintsWithTheirNotNullKeys(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a int NULL, b int, c int)', 'ALTER TABLE t ADD CONSTRAINT pk PRIMARY KEY (a, b), ADD UNIQUE (c), ADD CHECK (a > 0), ADD FOREIGN KEY (c) REFERENCES t (a)')->tables[0];
        self::assertSame(['primary-key', 'unique', 'check', 'foreign-key'], array_map(static fn (TableConstraint $constraint): string => $constraint->kind->value, $table->constraints));
        self::assertSame('pk', $table->constraints[0]->name);
        self::assertSame([Nullability::NotNull, Nullability::NotNull, Nullability::MaybeNull], array_column($table->columns, 'nullability'));
    }

    #[TestWith(['mysql', 'mysql-8.4.7', 'CREATE TABLE t (a INT PRIMARY KEY, b INT)', 'ALTER TABLE t ADD PRIMARY KEY (b)'])]
    #[TestWith(['postgresql', 'pg-17.2', 'CREATE TABLE t (a int PRIMARY KEY, b int)', 'ALTER TABLE t ADD CONSTRAINT other PRIMARY KEY (b)'])]
    public function testAddDiagnosesASecondPrimaryKey(string $dialect, string $version, string $table, string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::MultiplePrimaryKeys->message());
        (new SchemaBuilder(Dialect::from($dialect), grammarVersion: $version))->build($table, $sql);
    }

    #[TestWith(['ALTER TABLE t ADD PRIMARY KEY USING INDEX i', 'primary-key', 'i', 'not-null'])]
    #[TestWith(['ALTER TABLE t ADD CONSTRAINT u UNIQUE USING INDEX i', 'unique', 'u', 'maybe-null'])]
    public function testAdoptTurnsTheNamedIndexIntoTheConstraint(string $sql, string $kind, string $name, string $nullability): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a int, b int)', 'CREATE UNIQUE INDEX i ON t (b)', $sql)->tables[0];
        self::assertSame([], $table->indexes);
        self::assertCount(1, $table->constraints);
        self::assertSame($kind, $table->constraints[0]->kind->value);
        self::assertSame($name, $table->constraints[0]->name);
        self::assertSame(['b'], $table->constraints[0]->localColumns());
        self::assertSame(Nullability::from($nullability), $table->columns[1]->nullability);
    }

    public function testAdoptLeavesTheConstraintsWhenTheIndexIsUnknown(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a int)', 'ALTER TABLE t ADD PRIMARY KEY USING INDEX missing')->tables[0];
        self::assertSame([], $table->constraints);
        self::assertInstanceOf(UniqueKey::class, (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a int)', 'CREATE UNIQUE INDEX i ON t (a)', 'ALTER TABLE t ADD UNIQUE USING INDEX i')->tables[0]->constraints[0]);
    }
}
