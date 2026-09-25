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
use SqlSemantics\Binding\Schema\Alter\ColumnRedeclarations;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Schema\Constraint\PrimaryKey;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ColumnRedeclarations::class)]
#[Medium]
final class ColumnRedeclarationsTest extends TestCase
{
    public function testApplyReplacesTheChangedColumnAndReturnsItsConstraints(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT, b INT)');
        $item = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t CHANGE a c BIGINT PRIMARY KEY')->find('alter_list_item')[0];
        $table = $schema->tables[0];
        $declaration = new \SqlSemantics\Schema\TableDefinition($table->schema, 't', [...$table->columns, $table->columns[0]->withName('c')], [], $item);
        $scope = new Scope(new Identifiers(Dialect::MySql), [new \SqlSemantics\Model\Relation\TableReference('declaration', 'declaration', $declaration, new \SqlSemantics\Model\Relation\QualifiedName(['t']), null, $item)], queries: new QueryContext(new TableResolver($schema, new Identifiers(Dialect::MySql), '')));
        $result = ColumnRedeclarations::apply($item, $table->columns, $scope);
        self::assertNotNull($result);
        [$columns, $constraints] = $result;
        self::assertSame(['c', 'b'], array_column($columns, 'name'));
        self::assertSame('bigint', $columns[0]->type->name);
        self::assertCount(1, $constraints);
        self::assertInstanceOf(PrimaryKey::class, $constraints[0]);
        self::assertSame(['a', 'b'], array_column($schema->tables[0]->columns, 'name'));
    }

    public function testApplyIgnoresAnItemThatDeclaresNoColumn(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT)');
        $item = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t DROP COLUMN a')->find('alter_list_item')[0];
        self::assertNull(ColumnRedeclarations::apply($item, $schema->tables[0]->columns, new Scope(new Identifiers(Dialect::MySql))));
    }

    #[TestWith(['mysql-5.6.51', 'ALTER TABLE t CHANGE COLUMN s.t.a b INT', 'a'])]
    #[TestWith(['mysql-8.4.7', 'ALTER TABLE t MODIFY `A` INT', 'A'])]
    public function testReplacedReturnsTheLastPartOfTheChangedName(string $version, string $sql, string $expected): void
    {
        $item = (new DialectParser(Dialect::MySql, $version))->parse($sql)->find('alter_list_item')[0];
        self::assertSame($expected, ColumnRedeclarations::replaced($item, new Identifiers(Dialect::MySql)));
    }

    public function testReplacedIsNullForAnItemWithoutAColumnName(): void
    {
        $item = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t ADD (c INT)')->find('alter_list_item')[0];
        self::assertNull(ColumnRedeclarations::replaced($item, new Identifiers(Dialect::MySql)));
    }

    /**
     * @param list<string> $expected
     */
    #[TestWith(['ALTER TABLE t ADD x INT', null, ['a', 'b', 'c', 'x']])]
    #[TestWith(['ALTER TABLE t ADD x INT FIRST', null, ['x', 'a', 'b', 'c']])]
    #[TestWith(['ALTER TABLE t ADD x INT AFTER A', null, ['a', 'x', 'b', 'c']])]
    #[TestWith(['ALTER TABLE t CHANGE b x INT', 'B', ['a', 'x', 'c']])]
    #[TestWith(['ALTER TABLE t CHANGE c x INT FIRST', 'c', ['x', 'a', 'b']])]
    #[TestWith(['ALTER TABLE t CHANGE a x INT AFTER c', 'a', ['b', 'c', 'x']])]
    public function testPlacePutsTheColumnWhereTheItemAsks(string $sql, ?string $replaced, array $expected): void
    {
        $columns = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT, b INT, c INT)')->tables[0]->columns;
        $item = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse($sql)->find('alter_list_item')[0];
        $placed = ColumnRedeclarations::place($columns, $columns[0]->withName('x'), $replaced, \SqlSemantics\Ast\Tree::child($item, ['opt_place']), new Identifiers(Dialect::MySql));
        self::assertSame($expected, array_map(static fn (ColumnDefinition $column): string => $column->name, $placed));
    }
}
