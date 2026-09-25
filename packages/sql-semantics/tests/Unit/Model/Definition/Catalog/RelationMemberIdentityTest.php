<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog;
use SqlSemantics\Model\Definition\Catalog\Kind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Catalog\RelationMemberIdentity::class)]
#[Medium]
final class RelationMemberIdentityTest extends TestCase
{
    /**
     * @param list<string> $relation
     */
    #[TestWith(["COMMENT ON COLUMN app.users.email IS 'x'", Kind\RelationMemberKind::Column, 'email', ['app', 'users']])]
    #[TestWith(["COMMENT ON TRIGGER audit ON app.users IS 'x'", Kind\RelationMemberKind::Trigger, 'audit', ['app', 'users']])]
    #[TestWith(["COMMENT ON POLICY own ON users IS 'x'", Kind\RelationMemberKind::Policy, 'own', ['users']])]
    #[TestWith(["COMMENT ON CONSTRAINT pk ON users IS 'x'", Kind\RelationMemberKind::Constraint, 'pk', ['users']])]
    public function testRetainsTheMemberAndItsRelation(string $sql, Kind\RelationMemberKind $kind, string $name, array $relation): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(Statement\CommentOnStatement::class, $statement);
        self::assertEquals(new Catalog\RelationMemberIdentity($kind, $name, new QualifiedName($relation)), $statement->object);
        $rebound = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(Statement\CommentOnStatement::class, $rebound);
        self::assertEquals($statement->object, $rebound->object);
    }

    /**
     * @param list<string> $relation
     */
    #[TestWith(['', ['users']])]
    #[TestWith(['c', ['a', 'b', 'c', 'd']])]
    public function testRejectsAnEmptyNameOrAnOverQualifiedRelation(string $name, array $relation): void
    {
        $this->expectException(InvalidStructure::class);
        new Catalog\RelationMemberIdentity(Kind\RelationMemberKind::Column, $name, new QualifiedName($relation));
    }
}
