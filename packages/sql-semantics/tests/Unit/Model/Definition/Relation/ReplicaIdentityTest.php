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

#[CoversClass(Relation\ReplicaIdentity::class)]
#[Medium]
final class ReplicaIdentityTest extends TestCase
{
    public function testSpellsEachChoiceAsItsKeywords(): void
    {
        self::assertSame(['NOTHING', 'FULL', 'DEFAULT'], array_column(Relation\ReplicaIdentity::cases(), 'value'));
    }

    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t REPLICA IDENTITY FULL', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals(new Relation\SetReplicaIdentity(Relation\ReplicaIdentity::Full), $statement->actions[0]);
        self::assertSame('ALTER TABLE "t" REPLICA IDENTITY FULL', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
