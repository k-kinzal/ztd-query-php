<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Locking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Locking\NamedRowLock;
use SqlSemantics\Model\Query\Locking\UnresolvedLockRelation;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\SchemaBuilder;

#[CoversClass(UnresolvedLockRelation::class)]
#[Medium]
final class UnresolvedLockRelationTest extends TestCase
{
    public function testRetainsTheUnboundTargetNameAndWritesItBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('SELECT id FROM t FOR UPDATE OF missing NOWAIT', strict: false);
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(NamedRowLock::class, $statement->locks[0]);
        $relation = $statement->locks[0]->relations[0];
        self::assertInstanceOf(UnresolvedLockRelation::class, $relation);
        self::assertSame(['missing'], $relation->name->parts);
        self::assertSame('SELECT "id" AS "id" FROM "public"."t" FOR UPDATE OF "missing" NOWAIT', $statement->toString());
    }

    public function testKeepsAQualifiedIdentifierPath(): void
    {
        $relation = new UnresolvedLockRelation(new QualifiedName(['app', 'orders']));
        self::assertSame(['app', 'orders'], $relation->name->parts);
    }
}
