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

}
