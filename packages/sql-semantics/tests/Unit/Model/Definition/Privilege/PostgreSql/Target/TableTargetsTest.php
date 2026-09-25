<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege\PostgreSql\Target;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\TableTargets;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableTargets::class)]
#[Medium]
final class TableTargetsTest extends TestCase
{
    public function testRetainsTheResolvedTablesInRequestOrder(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $statement = (new Binder($schema))->bind('GRANT SELECT ON t TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertInstanceOf(TableTargets::class, $statement->target);
        $table = $statement->target->tables[0];
        $targets = new TableTargets([$table, $table]);
        self::assertSame([$table, $table], $targets->tables);
        self::assertSame('t', $targets->tables[0]->declaration->name);
    }

    public function testReadsTheTablesOfABoundGrant(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER); CREATE TABLE u(id INTEGER)');
        $binder = new Binder($schema);
        $statement = $binder->bind('GRANT SELECT ON TABLE t, u TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertInstanceOf(TableTargets::class, $statement->target);
        self::assertCount(2, $statement->target->tables);
        self::assertSame('t', $statement->target->tables[0]->declaration->name);
        self::assertSame('u', $statement->target->tables[1]->declaration->name);
        self::assertSame('GRANT SELECT ON TABLE "public"."t", "public"."u" TO "a"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testDiagnosesAnUnknownTableWithoutRejectingIt(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('GRANT SELECT ON missing TO a', strict: false);
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertInstanceOf(TableTargets::class, $statement->target);
        self::assertCount(1, $statement->target->tables);
        self::assertSame('unknown-table', $statement->diagnostics[0]->reason);
    }
}
