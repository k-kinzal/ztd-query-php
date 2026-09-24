<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\ColumnDeclarations;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Column\AddColumn;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Schema\Constraint\PrimaryKey;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ColumnDeclarations::class)]
#[Medium]
final class ColumnDeclarationsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    public function testNodeReducesAQualifiedNameToItsColumn(string $version): void
    {
        $item = (new DialectParser(Dialect::MySql, $version))->parse('ALTER TABLE t CHANGE a t.b INT')->find('alter_list_item')[0];
        $column = ColumnDeclarations::node($item);
        self::assertNotNull($column);
        self::assertSame('b', ColumnDeclarations::parse($column, new Scope(new Identifiers(Dialect::MySql)))[0]->name);
    }

    public function testNodeReturnsNullWithoutADeclaration(): void
    {
        self::assertNull(ColumnDeclarations::node((new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t DROP COLUMN a')->find('alter_list_item')[0]));
    }

    public function testFlattenExpandsTheFieldSpecification(): void
    {
        $declaration = (new DialectParser(Dialect::MySql, 'mysql-5.7.44'))->parse('ALTER TABLE t ADD a INT NOT NULL')->find('column_def')[0];
        self::assertSame(['field_ident', 'field_def', 'opt_check_constraint'], array_map(static fn ($child): string => $child->name, ColumnDeclarations::flatten($declaration)));
    }

    public function testBindReadsLocalConstraints(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ADD COLUMN c INT PRIMARY KEY');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(AddColumn::class, $alteration);
        self::assertInstanceOf(PrimaryKey::class, $alteration->constraints[0]);
    }

    public function testDeclaredListsEveryDeclaredColumn(): void
    {
        $items = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t ADD c INT, ADD (d INT, e INT), DROP f')->find('alter_list_item');
        self::assertSame(['c', 'd', 'e'], array_map(static fn ($column): string => $column->name, ColumnDeclarations::declared($items, new Scope(new Identifiers(Dialect::MySql)))));
    }

    public function testParseReadsTheColumnConstraints(): void
    {
        $item = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t ADD c INT UNIQUE')->find('alter_list_item')[0];
        $column = ColumnDeclarations::node($item);
        self::assertNotNull($column);
        [$parsed, $constraints] = ColumnDeclarations::parse($column, new Scope(new Identifiers(Dialect::MySql)));
        self::assertSame('c', $parsed->name);
        self::assertCount(1, $constraints);
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerBindReadsEveryColumnDeclaration')]
    public function testBindReadsEveryColumnDeclaration(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, $statement->toString());
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerBindReadsEveryColumnDeclaration(): iterable
    {
        return [
            'ALTER TABLE t ADD COLUMN c INT FIRST (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'ALTER TABLE t ADD COLUMN c INT FIRST', 'ALTER TABLE `t` ADD COLUMN `c` integer FIRST'],
            'ALTER TABLE t ADD c INT AFTER a (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'ALTER TABLE t ADD c INT AFTER a', 'ALTER TABLE `t` ADD COLUMN `c` integer AFTER `a`'],
            'ALTER TABLE t ADD COLUMN (c INT, d TEXT) (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'ALTER TABLE t ADD COLUMN (c INT, d TEXT)', 'ALTER TABLE `t` ADD COLUMN(`c` integer, `d` text)'],
            'ALTER TABLE t CHANGE COLUMN a z BIGINT NOT NULL AFTER b (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'ALTER TABLE t CHANGE COLUMN a z BIGINT NOT NULL AFTER b', 'ALTER TABLE `t` CHANGE COLUMN `a` `z` bigint NOT NULL AFTER `b`'],
            'ALTER TABLE t CHANGE a A INT FIRST (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'ALTER TABLE t CHANGE a A INT FIRST', 'ALTER TABLE `t` CHANGE COLUMN `a` `A` integer FIRST'],
            'ALTER TABLE t MODIFY COLUMN a TEXT FIRST (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'ALTER TABLE t MODIFY COLUMN a TEXT FIRST', 'ALTER TABLE `t` MODIFY COLUMN `a` text FIRST'],
            'ALTER TABLE t MODIFY a INT DEFAULT 1 AFTER b (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'ALTER TABLE t MODIFY a INT DEFAULT 1 AFTER b', 'ALTER TABLE `t` MODIFY COLUMN `a` integer DEFAULT 1 AFTER `b`'],
            'ALTER TABLE t ADD COLUMN c INT, ADD INDEX (c) (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'ALTER TABLE t ADD COLUMN c INT, ADD INDEX (c)', 'ALTER TABLE `t` ADD COLUMN `c` integer, ADD INDEX(`c`)'],
            'ALTER TABLE t ADD COLUMN C INT, ADD INDEX (c) (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'ALTER TABLE t ADD COLUMN C INT, ADD INDEX (c)', 'ALTER TABLE `t` ADD COLUMN `C` integer, ADD INDEX(`C`)'],
            'ALTER TABLE t CHANGE a A BIGINT, ADD INDEX (a) (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'ALTER TABLE t CHANGE a A BIGINT, ADD INDEX (a)', 'ALTER TABLE `t` CHANGE COLUMN `a` `A` bigint, ADD INDEX(`A`)'],
            'ALTER TABLE t ADD COLUMN c INT, DROP COLUMN c (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'ALTER TABLE t ADD COLUMN c INT, DROP COLUMN c', 'ALTER TABLE `t` ADD COLUMN `c` integer, DROP COLUMN `c`'],
            'ALTER TABLE t ADD COLUMN t.c INT (MySql mysql-5.7.44)' => [Dialect::MySql, 'mysql-5.7.44', ['CREATE TABLE t (a INT, b INT)'], 'ALTER TABLE t ADD COLUMN t.c INT', 'ALTER TABLE `t` ADD COLUMN `c` integer'],
            'ALTER TABLE t CHANGE a test.t.z INT FIRST (MySql mysql-5.7.44)' => [Dialect::MySql, 'mysql-5.7.44', ['CREATE TABLE t (a INT, b INT)'], 'ALTER TABLE t CHANGE a test.t.z INT FIRST', 'ALTER TABLE `t` CHANGE COLUMN `a` `z` integer FIRST'],
            'ALTER TABLE t ADD .c INT AFTER a (MySql mysql-5.6.51)' => [Dialect::MySql, 'mysql-5.6.51', ['CREATE TABLE t (a INT, b INT)'], 'ALTER TABLE t ADD .c INT AFTER a', 'ALTER TABLE `t` ADD COLUMN `c` integer AFTER `a`'],
            'ALTER TABLE t MODIFY t.a BIGINT (MySql mysql-5.7.44)' => [Dialect::MySql, 'mysql-5.7.44', ['CREATE TABLE t (a INT, b INT)'], 'ALTER TABLE t MODIFY t.a BIGINT', 'ALTER TABLE `t` MODIFY COLUMN `a` bigint'],
        ];
    }
}
