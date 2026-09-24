<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\TableAlterations;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\MySqlTable\Table\ConvertCharacterSet;
use SqlSemantics\Model\Definition\MySqlTable\Table\OrderRows;
use SqlSemantics\Model\Definition\MySqlTable\Table\RenameTable;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableAlterations::class)]
#[Medium]
final class TableAlterationsTest extends TestCase
{
    public function testRenameReadsTheNewName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t RENAME TO u');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RenameTable::class, $alteration);
        self::assertSame(['u'], $alteration->newName->parts);
    }

    public function testConvertReadsBinary(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t CONVERT TO CHARSET BINARY');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(ConvertCharacterSet::class, $alteration);
        self::assertSame('BINARY', $alteration->characterSet);
        self::assertNull($alteration->collation);
    }

    public function testOrderReadsQualifiedColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ORDER BY t.n');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(OrderRows::class, $alteration);
        $key = $alteration->orderings[0]->key;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\ColumnReference::class, $key);
        self::assertSame(['t', 'n'], $key->referenceParts());
    }

    public function testOptionsDiagnosesStartTransaction(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::TableOption->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t START TRANSACTION');
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerBindReadsEveryTableAlteration')]
    public function testBindReadsEveryTableAlteration(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, $statement->toString());
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerBindReadsEveryTableAlteration(): iterable
    {
        return [
            'alter table t convert to character set utf8mb4 collate utf8mb4_bin (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'alter table t convert to character set utf8mb4 collate utf8mb4_bin', 'ALTER TABLE `t` CONVERT TO CHARACTER SET `utf8mb4` COLLATE `utf8mb4_bin`'],
            'alter table t convert to character set default (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'alter table t convert to character set default', 'ALTER TABLE `t` CONVERT TO CHARACTER SET DEFAULT'],
            'ALTER TABLE t CONVERT TO CHARACTER SET latin1 (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'ALTER TABLE t CONVERT TO CHARACTER SET latin1', 'ALTER TABLE `t` CONVERT TO CHARACTER SET `latin1`'],
            'ALTER TABLE t CONVERT TO CHARSET \'utf8mb4\' COLLATE \'utf8mb4_bin\' (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'ALTER TABLE t CONVERT TO CHARSET \'utf8mb4\' COLLATE \'utf8mb4_bin\'', 'ALTER TABLE `t` CONVERT TO CHARACTER SET `utf8mb4` COLLATE `utf8mb4_bin`'],
            'alter table t order by a desc, b (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'alter table t order by a desc, b', 'ALTER TABLE `t` ORDER BY `a` DESC, `b`'],
            'alter table t order by t.a asc, b desc (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'alter table t order by t.a asc, b desc', 'ALTER TABLE `t` ORDER BY `t`.`a`, `b` DESC'],
            'ALTER TABLE t ORDER BY a (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'ALTER TABLE t ORDER BY a', 'ALTER TABLE `t` ORDER BY `a`'],
            'alter table t rename to u (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'alter table t rename to u', 'ALTER TABLE `t` RENAME TO `u`'],
            'alter table t engine = innodb, comment = \'x\' (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'alter table t engine = innodb, comment = \'x\'', 'ALTER TABLE `t` ENGINE `innodb`, COMMENT = \'x\''],
            'alter table t convert to character set default collate default (MySql mysql-5.7.44)' => [Dialect::MySql, 'mysql-5.7.44', ['CREATE TABLE t (a INT, b INT)'], 'alter table t convert to character set default collate default', 'ALTER TABLE `t` CONVERT TO CHARACTER SET DEFAULT'],
            'alter table t convert to character set utf8mb4 collate default (MySql mysql-5.7.44)' => [Dialect::MySql, 'mysql-5.7.44', ['CREATE TABLE t (a INT, b INT)'], 'alter table t convert to character set utf8mb4 collate default', 'ALTER TABLE `t` CONVERT TO CHARACTER SET `utf8mb4`'],
            'alter table t convert to character set utf8 collate default (MySql mysql-5.6.51)' => [Dialect::MySql, 'mysql-5.6.51', ['CREATE TABLE t (a INT, b INT)'], 'alter table t convert to character set utf8 collate default', 'ALTER TABLE `t` CONVERT TO CHARACTER SET `utf8`'],
            'alter table t order by test.t.a (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'alter table t order by test.t.a', 'ALTER TABLE `t` ORDER BY `test`.`t`.`a`'],
        ];
    }
}
