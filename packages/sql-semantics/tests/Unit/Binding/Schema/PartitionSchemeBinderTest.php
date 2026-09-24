<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Schema\PartitionSchemeBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\CreateTableStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Schema\Partition\PartitionStrategy;
use SqlSemantics\Schema\Table\PostgreSqlProperties;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PartitionSchemeBinder::class)]
#[Medium]
final class PartitionSchemeBinderTest extends TestCase
{
    public function testReadBindsTheSchemeOfAPartitionedTableAndRoundTrips(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE TABLE p (id int, t text) PARTITION BY range (id, lower(t) COLLATE "C" text_pattern_ops) USING heap');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        $properties = $statement->definition->table->properties;
        self::assertInstanceOf(PostgreSqlProperties::class, $properties);
        self::assertSame(PartitionStrategy::Range, $properties->partitioning?->strategy);
        self::assertCount(2, $properties->partitioning->keys);
        self::assertSame('CREATE TABLE "public"."p"("id" integer, "t" text) PARTITION BY RANGE("id", ("lower"("t")) COLLATE "C" "text_pattern_ops") USING "heap"', $statement->toString());
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(CreateTableStatement::class, $rebound);
        self::assertSame($statement->toString(), $rebound->toString());
    }

    public function testReadReturnsNullForAnUnpartitionedTable(): void
    {
        $tree = (new DialectParser(Dialect::PostgreSql))->parse('CREATE TABLE t (id int)');
        self::assertNull(PartitionSchemeBinder::read($tree, new Scope(new Identifiers(Dialect::PostgreSql))));
    }

    #[TestWith(['CREATE TABLE p (id int, t text) PARTITION BY list (id, t)'])]
    #[TestWith(['CREATE TABLE p (id int) PARTITION BY interval (id)'])]
    public function testBindDiagnosesUnknownStrategiesAndWideListKeys(string $sql): void
    {
        try {
            (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
            self::fail('The partitioning must be diagnosed.');
        } catch (InvalidSql $error) {
            self::assertSame(InputViolation::PartitionKey, $error->violation);
        }
    }

    public function testKeyReadsTheCollationAndOperatorClass(): void
    {
        $tree = (new DialectParser(Dialect::PostgreSql))->parse('CREATE TABLE p (id int) PARTITION BY HASH ((2 + 1) COLLATE s.c s.ops)');
        $key = PartitionSchemeBinder::key(Tree::outer($tree, ['part_elem'])[0], new Scope(new Identifiers(Dialect::PostgreSql)));
        self::assertSame(['s', 'c'], $key->collation?->parts);
        self::assertSame(['s', 'ops'], $key->operatorClass?->parts);
        self::assertSame('(2 + 1)', $key->value->structure()->toString());
    }

    public function testValueResolvesBareColumnsInTheTableScope(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE p (id int) PARTITION BY HASH (missing)', strict: false);
        self::assertSame(['unknown-column'], array_column($statement->diagnostics, 'reason'));
        $tree = (new DialectParser(Dialect::PostgreSql))->parse('CREATE TABLE p (id int) PARTITION BY HASH (abs(2))');
        self::assertSame('"abs"(2)', PartitionSchemeBinder::value(Tree::outer($tree, ['part_elem'])[0], new Scope(new Identifiers(Dialect::PostgreSql)))->structure()->toString());
    }
}
