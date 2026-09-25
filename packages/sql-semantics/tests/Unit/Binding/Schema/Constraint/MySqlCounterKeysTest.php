<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Schema\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Schema\Constraint\MySqlCounterKeys;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\CreateTableStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MySqlCounterKeys::class)]
#[Medium]
final class MySqlCounterKeysTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', 'CREATE TABLE t (a INT AUTO_INCREMENT PRIMARY KEY, b INT AUTO_INCREMENT UNIQUE)'])]
    #[TestWith(['mysql-5.7.44', 'CREATE TABLE t (a INT AUTO_INCREMENT)'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE t (a INT AUTO_INCREMENT, b INT, KEY (b, a))'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE t (a INT AUTO_INCREMENT, b INT, KEY (b, a)) ENGINE=MEMORY'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE t (a INT AUTO_INCREMENT, b INT, KEY (b), KEY (b, a))'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE t (a INT AUTO_INCREMENT, INDEX ((a + 1)))'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE t (a SERIAL, b SERIAL)'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE t (a INT SERIAL DEFAULT VALUE, b INT AUTO_INCREMENT KEY)'])]
    public function testCheckRejectsWhatTheServerRejectsWithError1075(string $version, string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::AutoIncrementKey->message());
        (new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build($sql);
    }

    #[TestWith(['mysql-5.6.51', 'CREATE TABLE t (a INT AUTO_INCREMENT, b INT, KEY (b, a)) ENGINE=MyISAM'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE t (a INT AUTO_INCREMENT, b INT, UNIQUE (b, a)) ENGINE=BLACKHOLE'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE t (a INT AUTO_INCREMENT, b INT, KEY (a, b))'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE t (a INT AUTO_INCREMENT, b INT, KEY (b, a), KEY (a))'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE t (a INT AUTO_INCREMENT, b INT, KEY (a DESC))'])]
    #[TestWith(['mysql-5.7.44', 'CREATE TABLE t (a SERIAL)'])]
    #[TestWith(['mysql-8.4.7', 'CREATE TABLE t (a INT SERIAL DEFAULT VALUE, b INT)'])]
    public function testCheckAcceptsACounterAKeyCovers(string $version, string $sql): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build($sql);
        self::assertCount(1, $schema->tables);
    }

    public function testCheckAcceptsTheIndexAForeignKeyCreates(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE p (a INT PRIMARY KEY)', 'CREATE TABLE t (a INT AUTO_INCREMENT, FOREIGN KEY (a) REFERENCES p (a))');
        self::assertCount(2, $schema->tables);
    }

    public function testCheckRejectsTheCreateTableStatementTheBinderBinds(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build());
        self::assertInstanceOf(CreateTableStatement::class, $binder->bind('CREATE TABLE t (a INT AUTO_INCREMENT PRIMARY KEY)'));
        $this->expectException(InvalidSql::class);
        $binder->bind('CREATE TABLE t (a INT AUTO_INCREMENT, b INT)');
    }

    #[TestWith(['ALTER TABLE t MODIFY a INT AUTO_INCREMENT'])]
    #[TestWith(['ALTER TABLE u ADD c INT AUTO_INCREMENT UNIQUE'])]
    #[TestWith(['ALTER TABLE u DROP KEY a'])]
    #[TestWith(['DROP INDEX a ON u'])]
    public function testCheckRejectsASchemaAlterationThatBreaksTheRule(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::AutoIncrementKey->message());
        (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT, b INT, KEY (b))', 'CREATE TABLE u (a INT AUTO_INCREMENT, b INT, KEY (a, b))', $sql);
    }

    public function testCheckAcceptsASchemaAlterationThatKeepsTheRule(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT, b INT, KEY (b))', 'ALTER TABLE t MODIFY b INT AUTO_INCREMENT', 'CREATE TABLE u (a INT AUTO_INCREMENT, b INT, KEY (a, b))', 'ALTER TABLE u DROP KEY a, ADD KEY (a)');
        self::assertInstanceOf(\SqlSemantics\Schema\Column\AutoIncrementColumn::class, $schema->tables[0]->columns[1]->generation);
        self::assertCount(1, $schema->tables[1]->indexes);
    }

    public function testKeysListsEveryKeyInKeyOrder(): void
    {
        $tables = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE p (a INT, b INT, PRIMARY KEY (a, b))', 'CREATE TABLE t (a INT, b INT, c INT, PRIMARY KEY (b, a), UNIQUE (c), FOREIGN KEY (c, a) REFERENCES p (a, b), KEY ((a + b), c))')->tables;
        self::assertSame([['b', 'a'], ['c'], ['c', 'a'], ['', 'c']], MySqlCounterKeys::keys($tables[1]));
    }

    public function testNameReadsAColumnKeyAndLeavesAnExpressionUnnamed(): void
    {
        $index = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT, KEY ((a + 1), a))')->tables[0]->indexes[0];
        self::assertSame(['', 'a'], [MySqlCounterKeys::name($index->elements[0]), MySqlCounterKeys::name($index->elements[1])]);
    }
}
