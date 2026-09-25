<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\Definition\PrimaryKeyNulls;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\ColumnDeclarations;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\CreateTableStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(PrimaryKeyNulls::class)]
#[Medium]
final class PrimaryKeyNullsTest extends TestCase
{
    #[TestWith(['mysql-5.7.44', 'CREATE TABLE x (c INT NULL PRIMARY KEY)'])]
    #[TestWith(['mysql-8.0.44', 'CREATE TABLE x (c INT NULL KEY)'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE x (c INT PRIMARY KEY NULL)'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE x (c INT NOT NULL NULL PRIMARY KEY)'])]
    #[TestWith(['mysql-9.1.0', 'CREATE TABLE x (c INT NULL, d INT, PRIMARY KEY (d, C))'])]
    public function testRejectDiagnosesANullPrimaryKeyPartFromMySql573(string $version, string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::NullablePrimaryKey->message());
        $binder->bind($sql);
    }

    public function testRejectStopsTheSchemaBuilderFromDeclaringTheTable(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::NullablePrimaryKey->message());
        (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE x (c INT NULL PRIMARY KEY)');
    }

    #[TestWith(['CREATE TABLE x (c INT NULL PRIMARY KEY)', 'CREATE TABLE `x`(`c` integer NOT NULL, PRIMARY KEY(`c`))'])]
    #[TestWith(['CREATE TABLE x (c INT NULL, PRIMARY KEY (c))', 'CREATE TABLE `x`(`c` integer NOT NULL, PRIMARY KEY(`c`))'])]
    public function testRejectKeepsMySql56WhichDeclaresTheColumnNotNull(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        self::assertSame(Nullability::NotNull, $statement->definition->table->columns[0]->nullability);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    #[TestWith(['mysql-5.7.44', 'CREATE TABLE x (c INT NULL UNIQUE KEY)', 'maybe-null'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE x (c INT NOT NULL PRIMARY KEY, d INT NULL)', 'not-null'])]
    public function testRejectAcceptsNullColumnsOutsideThePrimaryKey(string $version, string $sql, string $nullability): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind($sql);
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        self::assertSame(Nullability::from($nullability), $statement->definition->table->columns[0]->nullability);
    }

    public function testRejectAppliesOnlyToMySqlReleasesThatRejectTheDeclaration(): void
    {
        $column = ColumnDeclarations::node((new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t ADD c INT NULL PRIMARY KEY')->find('alter_list_item')[0]);
        self::assertNotNull($column);
        [$parsed, $constraints] = ColumnDeclarations::parse($column, new Scope(new Identifiers(Dialect::MySql)));
        PrimaryKeyNulls::reject([$parsed], $constraints, Dialect::PostgreSql, null);
        PrimaryKeyNulls::reject([$parsed], $constraints, Dialect::MySql, 'mysql-5.6.51');
        PrimaryKeyNulls::reject([$parsed], [], Dialect::MySql, 'mysql-8.4.7');
        $this->expectException(InvalidSql::class);
        PrimaryKeyNulls::reject([$parsed], $constraints, Dialect::MySql, 'mysql-5.7.44');
    }

    #[TestWith(['mysql-5.6.51', 'CREATE TABLE x (c INT DEFAULT NULL PRIMARY KEY)'])]
    #[TestWith(['mysql-5.7.44', 'CREATE TABLE x (c INT NULL DEFAULT NULL PRIMARY KEY)'])]
    #[TestWith(['mysql-8.0.44', 'CREATE TABLE x (c INT PRIMARY KEY DEFAULT NULL)'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE x (c INT DEFAULT NULL KEY)'])]
    #[TestWith(['mysql-9.1.0', 'CREATE TABLE x (c INT DEFAULT 1 DEFAULT NULL PRIMARY KEY)'])]
    #[TestWith(['mysql-5.6.51', 'CREATE TABLE x (c INT NOT NULL DEFAULT NULL)'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE x (c TIMESTAMP DEFAULT NULL NOT NULL)'])]
    #[TestWith(['mysql-5.6.51', 'ALTER TABLE t ADD c INT DEFAULT NULL PRIMARY KEY'])]
    #[TestWith(['mysql-5.7.44', 'ALTER TABLE t MODIFY a INT DEFAULT NULL PRIMARY KEY'])]
    #[TestWith(['mysql-8.4.7', 'ALTER TABLE t CHANGE a a INT DEFAULT NULL PRIMARY KEY'])]
    #[TestWith(['mysql-8.4.7', 'ALTER TABLE t ADD (c INT NOT NULL DEFAULT NULL)'])]
    public function testRejectDiagnosesDefaultNullOnANotNullColumnInEveryRelease(string $version, string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t (a INT)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::NullDefault->message());
        $binder->bind($sql);
    }

    #[TestWith(['mysql-5.6.51', 'CREATE TABLE x (c INT DEFAULT NULL PRIMARY KEY)'])]
    #[TestWith(['mysql-5.7.44', 'ALTER TABLE t ADD c INT DEFAULT NULL PRIMARY KEY'])]
    #[TestWith(['mysql-8.4.7', 'ALTER TABLE t MODIFY a INT NOT NULL DEFAULT NULL'])]
    #[TestWith(['mysql-9.1.0', 'ALTER TABLE t CHANGE a b INT DEFAULT NULL PRIMARY KEY'])]
    public function testRejectStopsTheSchemaBuilderFromApplyingDefaultNullOnANotNullColumn(string $version, string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::NullDefault->message());
        (new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t (a INT)', $sql);
    }

    #[TestWith(['mysql-5.7.44', 'CREATE TABLE x (c INT DEFAULT NULL, PRIMARY KEY (c))'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE x (c INT DEFAULT NULL, d INT, PRIMARY KEY (d, c))'])]
    #[TestWith(['mysql-5.7.44', 'CREATE TABLE x (c INT DEFAULT NULL AUTO_INCREMENT PRIMARY KEY)'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE x (c INT NOT NULL DEFAULT NULL AUTO_INCREMENT KEY)'])]
    #[TestWith(['mysql-5.7.44', 'CREATE TABLE x (c INT DEFAULT NULL DEFAULT 1 PRIMARY KEY)'])]
    #[TestWith(['mysql-8.4.7', 'ALTER TABLE t ADD c INT DEFAULT NULL, ADD PRIMARY KEY (c)'])]
    #[TestWith(['mysql-5.7.44', 'ALTER TABLE t MODIFY a INT DEFAULT NULL, ADD PRIMARY KEY (a)'])]
    public function testRejectDiagnosesADefaultNullPrimaryKeyPartFromMySql573(string $version, string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t (a INT)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::NullablePrimaryKey->message());
        $binder->bind($sql);
    }

    #[TestWith(['mysql-5.7.44', 'ALTER TABLE t ADD c INT NULL PRIMARY KEY'])]
    #[TestWith(['mysql-8.4.7', 'ALTER TABLE t ADD c INT DEFAULT NULL, ADD PRIMARY KEY (c)'])]
    public function testRejectStopsTheSchemaBuilderFromApplyingANullPrimaryKeyPart(string $version, string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::NullablePrimaryKey->message());
        (new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t (a INT)', $sql);
    }

    #[TestWith(['mysql-5.6.51', 'CREATE TABLE x (c INT DEFAULT NULL, PRIMARY KEY (c))'])]
    #[TestWith(['mysql-5.6.51', 'CREATE TABLE x (c INT DEFAULT NULL AUTO_INCREMENT PRIMARY KEY)'])]
    #[TestWith(['mysql-5.6.51', 'CREATE TABLE x (c INT PRIMARY KEY NULL DEFAULT NULL)'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE x (c INT DEFAULT NULL DEFAULT 1 PRIMARY KEY)'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE x (c INT NOT NULL DEFAULT NULL NULL)'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE x (c INT DEFAULT NULL NOT NULL AUTO_INCREMENT UNIQUE)'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE x (c INT DEFAULT NULL SERIAL DEFAULT VALUE)'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE x (c INT DEFAULT NULL UNIQUE)'])]
    public function testRejectAcceptsTheDefaultsTheServerAccepts(string $version, string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind($sql);
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        self::assertSame($sql, $statement->toString());
    }

    public function testRejectLeavesPostgreSqlDefaultsAlone(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE x (c int DEFAULT NULL PRIMARY KEY)');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        self::assertSame(Nullability::NotNull, $statement->definition->table->columns[0]->nullability);
    }

    #[TestWith(['ALTER TABLE t ADD c INT NULL DEFAULT 1', 'mysql-8.4.7', 'NULL'])]
    #[TestWith(['ALTER TABLE t ADD c INT NOT NULL DEFAULT NULL', 'mysql-8.4.7', 'DEFAULT NULL'])]
    #[TestWith(['ALTER TABLE t ADD c INT DEFAULT NULL DEFAULT 1 NULL', 'mysql-8.4.7', 'NULL'])]
    #[TestWith(['ALTER TABLE t ADD c INT DEFAULT NULL DEFAULT 1', 'mysql-5.7.44', 'DEFAULT NULL'])]
    public function testWrittenNullReturnsTheAttributeThatDeclaresTheColumnNullable(string $sql, string $version, string $expected): void
    {
        $column = ColumnDeclarations::node((new DialectParser(Dialect::MySql, $version))->parse($sql)->find('alter_list_item')[0]);
        self::assertNotNull($column);
        $written = PrimaryKeyNulls::writtenNull(ColumnDeclarations::parse($column, new Scope(new Identifiers(Dialect::MySql)))[0], $version === 'mysql-5.7.44' ? 50744 : 80407);
        self::assertNotNull($written);
        self::assertSame($expected, Tree::text($written));
    }

    #[TestWith(['ALTER TABLE t ADD c INT DEFAULT NULL DEFAULT 1'])]
    #[TestWith(['ALTER TABLE t ADD c INT NOT NULL DEFAULT 0'])]
    public function testWrittenNullIgnoresADefaultNullThatALaterDefaultReplaces(string $sql): void
    {
        $column = ColumnDeclarations::node((new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse($sql)->find('alter_list_item')[0]);
        self::assertNotNull($column);
        self::assertNull(PrimaryKeyNulls::writtenNull(ColumnDeclarations::parse($column, new Scope(new Identifiers(Dialect::MySql)))[0]));
    }

    #[TestWith(['ALTER TABLE t ADD c INT DEFAULT NULL PRIMARY KEY', 'DEFAULT NULL'])]
    #[TestWith(['ALTER TABLE t ADD c INT NULL NOT NULL DEFAULT 1 DEFAULT NULL', 'DEFAULT NULL'])]
    public function testNullDefaultReturnsTheDefaultOfANotNullDeclaration(string $sql, string $expected): void
    {
        $column = ColumnDeclarations::node((new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse($sql)->find('alter_list_item')[0]);
        self::assertNotNull($column);
        $default = PrimaryKeyNulls::nullDefault(ColumnDeclarations::parse($column, new Scope(new Identifiers(Dialect::MySql)))[0]);
        self::assertNotNull($default);
        self::assertSame($expected, Tree::text($default));
    }

    #[TestWith(['ALTER TABLE t ADD c INT DEFAULT NULL'])]
    #[TestWith(['ALTER TABLE t ADD c INT PRIMARY KEY NULL DEFAULT NULL'])]
    #[TestWith(['ALTER TABLE t ADD c INT NOT NULL DEFAULT NULL DEFAULT 2'])]
    #[TestWith(['ALTER TABLE t ADD c INT NOT NULL DEFAULT NULL AUTO_INCREMENT'])]
    public function testNullDefaultIgnoresADeclarationThatAllowsItsDefault(string $sql): void
    {
        $column = ColumnDeclarations::node((new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse($sql)->find('alter_list_item')[0]);
        self::assertNotNull($column);
        self::assertNull(PrimaryKeyNulls::nullDefault(ColumnDeclarations::parse($column, new Scope(new Identifiers(Dialect::MySql)))[0]));
    }

    #[TestWith([80042, false])]
    #[TestWith([80043, true])]
    #[TestWith([80044, true])]
    #[TestWith([80100, false])]
    #[TestWith([80300, false])]
    #[TestWith([80405, false])]
    #[TestWith([80406, true])]
    #[TestWith([80407, true])]
    #[TestWith([90100, false])]
    #[TestWith([90400, true])]
    #[TestWith([PHP_INT_MAX, true])]
    #[TestWith([50744, false])]
    public function testReplacesDefaultFollowsTheReleasesThatLetALaterDefaultReplaceDefaultNull(int $release, bool $expected): void
    {
        self::assertSame($expected, PrimaryKeyNulls::replacesDefault($release));
    }

    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    public function testRejectAcceptsAReplacedDefaultNullWhereTheServerDoes(string $version): void
    {
        $table = (new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE x (c INT DEFAULT NULL DEFAULT 1 PRIMARY KEY)')->tables[0];
        self::assertSame(Nullability::NotNull, $table->columns[0]->nullability);
    }

    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testRejectDiagnosesAReplacedDefaultNullWhereTheServerDoes(string $version): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::NullablePrimaryKey->message());
        (new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE x (c INT DEFAULT NULL DEFAULT 1 PRIMARY KEY)');
    }
}
