<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\TableOccurrence;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\OnlyTableReference;
use SqlSemantics\Model\Statement\DeleteStatement;
use SqlSemantics\Model\Statement\UpdateStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableOccurrence::class)]
#[Medium]
final class TableOccurrenceTest extends TestCase
{
    #[TestWith(['UPDATE ONLY t SET id=2', 'UPDATE ONLY "public"."t" SET "id" = 2'])]
    #[TestWith(['DELETE FROM ONLY(t) WHERE id=1', 'DELETE FROM ONLY "public"."t" WHERE ("id" = 1)'])]
    public function testBindRetainsMutationRowScope(string $sql, string $serialized): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind($sql);
        self::assertTrue($statement instanceof UpdateStatement || $statement instanceof DeleteStatement);
        self::assertInstanceOf(OnlyTableReference::class, $statement->affectedTables()[0]);
        self::assertSame($serialized, $statement->toString());
        $rebound = $binder->bind($serialized);
        self::assertTrue($rebound instanceof UpdateStatement || $rebound instanceof DeleteStatement);
        self::assertInstanceOf(OnlyTableReference::class, $rebound->affectedTables()[0]);
    }
    public function testResolvePreservesQuotedNamespaceAndAlias(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE `select`.`from`(id INTEGER)');
        $statement = (new Binder($schema))->bind('LOCK TABLES `select`.`from` AS `where` READ');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Locking\LockTablesStatement::class, $statement);
        self::assertSame(['select', 'from'], $statement->locks[0]->table->name->parts);
        self::assertSame('where', $statement->locks[0]->table->alias);
        self::assertSame($schema->tables[0], $statement->locks[0]->table->declaration);
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testHintsReadEveryIndexHintOfATable(string $release): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build('CREATE TABLE t (a INT, KEY k (a))'));
        $statement = $binder->bind('SELECT a FROM t AS x USE INDEX FOR JOIN (k, PRIMARY) USE KEY () FORCE KEY FOR GROUP BY (k) IGNORE INDEX FOR ORDER BY (`k`)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\TableReference::class, $statement->from);
        $hints = $statement->from->indexHints;
        self::assertSame([\SqlSemantics\Model\Query\Optimization\IndexHintAction::Use, \SqlSemantics\Model\Query\Optimization\IndexHintAction::Use, \SqlSemantics\Model\Query\Optimization\IndexHintAction::Force, \SqlSemantics\Model\Query\Optimization\IndexHintAction::Ignore], array_column($hints, 'action'));
        self::assertSame([\SqlSemantics\Model\Query\Optimization\IndexHintScope::Join, null, \SqlSemantics\Model\Query\Optimization\IndexHintScope::GroupBy, \SqlSemantics\Model\Query\Optimization\IndexHintScope::OrderBy], array_column($hints, 'scope'));
        self::assertSame([['k', 'PRIMARY'], [], ['k'], ['k']], array_column($hints, 'indexes'));
        $expected = 'SELECT `a` AS `a` FROM `t` AS `x` USE INDEX FOR JOIN(`k`, `PRIMARY`) USE INDEX() FORCE INDEX FOR GROUP BY(`k`) IGNORE INDEX FOR ORDER BY(`k`)';
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testHintsLeaveOtherDialectsWithoutHints(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('SELECT 1 FROM t');
        self::assertSame([], TableOccurrence::hints($tree, new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql)));
    }

    public function testBindKeepsTheHintsOfAnUpdatedTable(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (a INT, KEY k (a))'));
        $statement = $binder->bind('UPDATE t USE INDEX (k) SET a = 1');
        self::assertInstanceOf(UpdateStatement::class, $statement);
        self::assertSame('UPDATE `t` USE INDEX(`k`) SET `a` = 1', $statement->toString());
    }
}
