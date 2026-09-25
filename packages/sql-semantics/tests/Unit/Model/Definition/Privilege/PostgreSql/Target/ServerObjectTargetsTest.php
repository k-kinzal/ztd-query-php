<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege\PostgreSql\Target;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ServerObjectClass;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ServerObjectTargets;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ServerObjectTargets::class)]
#[Medium]
final class ServerObjectTargetsTest extends TestCase
{
    public function testRetainsTheClassAndNamesInRequestOrder(): void
    {
        $targets = new ServerObjectTargets(ServerObjectClass::Schema, ['app', 'public']);
        self::assertSame(ServerObjectClass::Schema, $targets->class);
        self::assertSame(['app', 'public'], $targets->names);
    }

    public function testReadsTheDatabasesOfABoundGrant(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('GRANT CONNECT ON DATABASE d, e TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertEquals(new ServerObjectTargets(ServerObjectClass::Database, ['d', 'e']), $statement->target);
        self::assertSame('GRANT CONNECT ON DATABASE "d", "e" TO "a"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRejectsAnEmptyObjectName(): void
    {
        $this->expectException(InvalidStructure::class);
        new ServerObjectTargets(ServerObjectClass::Database, ['d', '']);
    }
}
