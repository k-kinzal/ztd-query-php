<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Server\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Administration\BinaryLogReset;
use SqlSemantics\Model\Configuration\Administration\QueryCacheReset;
use SqlSemantics\Model\Configuration\Administration\ReplicaReset;
use SqlSemantics\Model\Statement\Server\Administration\ResetServerStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ResetServerStatement::class)]
#[Medium]
final class ResetServerStatementTest extends TestCase
{
    public function testWithOriginPreservesTheTargets(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("RESET REPLICA ALL FOR CHANNEL 'c', BINARY LOGS AND GTIDS TO 4");
        self::assertInstanceOf(ResetServerStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame("RESET REPLICA ALL FOR CHANNEL 'c', BINARY LOGS AND GTIDS TO 4", $copy->toString());
    }

    public function testWithTargetsReplacesTheOptionsImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build()))->bind('RESET MASTER');
        self::assertInstanceOf(ResetServerStatement::class, $statement);
        self::assertSame('RESET SLAVE, MASTER', (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('RESET SLAVE, MASTER')));
        self::assertSame('RESET REPLICA, MASTER', $statement->withTargets([new ReplicaReset(), new BinaryLogReset()])->toString());
        self::assertCount(1, $statement->targets);
    }

    public function testRejectsATargetMissingFromTheRelease(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('RESET REPLICA');
        $this->expectException(InvalidStructure::class);
        new ResetServerStatement($statement->origin, [new QueryCacheReset()]);
    }

    public function testRejectsAnEmptyTargetList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('RESET REPLICA');
        $this->expectException(InvalidStructure::class);
        new ResetServerStatement($statement->origin, []);
    }
}
