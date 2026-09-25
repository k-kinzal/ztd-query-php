<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\ChangeOwner::class)]
#[Medium]
final class ChangeOwnerTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t OWNER TO CURRENT_ROLE', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals(new Relation\ChangeOwner(\SqlSemantics\Model\Configuration\Role\SessionRole::CurrentRole), $statement->actions[0]);
        self::assertSame('ALTER TABLE "t" OWNER TO CURRENT_ROLE', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testBindsANamedOwner(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t OWNER TO alice', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals(new Relation\ChangeOwner(new \SqlSemantics\Model\Configuration\Role\NamedRole('alice')), $statement->actions[0]);
        self::assertSame('ALTER TABLE "t" OWNER TO "alice"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
