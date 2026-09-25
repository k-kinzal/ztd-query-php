<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RelationAction::class)]
#[Medium]
final class RelationActionTest extends TestCase
{
    public function testActionsKeepTheirRequestedOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t SET LOGGED, CLUSTER ON ix, OWNER TO alice');
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertContainsOnlyInstancesOf(RelationAction::class, $statement->actions);
        self::assertInstanceOf(Relation\Storage\SetLogging::class, $statement->actions[0]);
        self::assertInstanceOf(Relation\ClusterOn::class, $statement->actions[1]);
        self::assertInstanceOf(Relation\ChangeOwner::class, $statement->actions[2]);
        self::assertSame('ALTER TABLE "t" SET LOGGED, CLUSTER ON "ix", OWNER TO "alice"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
