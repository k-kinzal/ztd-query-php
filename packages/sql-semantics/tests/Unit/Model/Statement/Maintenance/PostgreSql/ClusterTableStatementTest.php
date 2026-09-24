<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\ClusterTableStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ClusterTableStatement::class)]
#[Medium]
final class ClusterTableStatementTest extends TestCase
{
    public function testWithOriginRetainsTheRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CLUSTER VERBOSE t USING i');
        self::assertInstanceOf(ClusterTableStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame('i', $copy->index);
        self::assertTrue($copy->verbose);
        self::assertSame(StatementKind::Cluster, $copy->kind);
    }

    public function testWithTableClustersAnotherTable(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT); CREATE TABLE u(a INT)'));
        $statement = $binder->bind('CLUSTER t');
        $other = $binder->bind('CLUSTER u');
        self::assertInstanceOf(ClusterTableStatement::class, $statement);
        self::assertInstanceOf(ClusterTableStatement::class, $other);
        self::assertSame('CLUSTER "public"."u"', $statement->withTable($other->table)->toString());
    }

    public function testWithIndexSelectsTheOrderingIndex(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CLUSTER t USING i');
        self::assertInstanceOf(ClusterTableStatement::class, $statement);
        self::assertSame('CLUSTER "public"."t"', $statement->withIndex(null)->toString());
        self::assertSame('CLUSTER "public"."t" USING "j"', $statement->withIndex('j')->toString());
    }

    public function testWithVerboseSelectsProgressMessages(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CLUSTER (VERBOSE) t');
        self::assertInstanceOf(ClusterTableStatement::class, $statement);
        self::assertSame('CLUSTER "public"."t"', $statement->withVerbose(false)->toString());
    }

    public function testRejectsAnEmptyIndexName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('CLUSTER t');
        self::assertInstanceOf(ClusterTableStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withIndex('');
    }
}
