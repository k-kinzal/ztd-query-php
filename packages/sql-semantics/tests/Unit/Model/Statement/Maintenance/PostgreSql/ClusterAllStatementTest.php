<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\ClusterAllStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ClusterAllStatement::class)]
#[Medium]
final class ClusterAllStatementTest extends TestCase
{
    public function testWithOriginRetainsTheRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CLUSTER (VERBOSE)');
        self::assertInstanceOf(ClusterAllStatement::class, $statement);
        self::assertTrue($statement->withOrigin($statement->origin)->verbose);
        self::assertSame(StatementKind::Cluster, $statement->kind);
    }

    public function testWithVerboseSelectsProgressMessages(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CLUSTER');
        self::assertInstanceOf(ClusterAllStatement::class, $statement);
        self::assertSame('CLUSTER(VERBOSE)', $statement->withVerbose(true)->toString());
        self::assertSame('CLUSTER', $statement->toString());
    }

    public function testRequiresPostgreSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CLUSTER');
        $this->expectException(InvalidStructure::class);
        new ClusterAllStatement(new Origin($statement->origin->scopeId, $statement->origin->source, Dialect::MySql));
    }

    public function testVerboseDefaultsToOff(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CLUSTER (VERBOSE)');
        self::assertInstanceOf(ClusterAllStatement::class, $statement);
        self::assertFalse((new ClusterAllStatement($statement->origin))->verbose);
    }
}
