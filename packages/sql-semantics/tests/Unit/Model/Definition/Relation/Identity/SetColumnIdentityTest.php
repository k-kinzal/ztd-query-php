<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Identity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Identity\SetColumnIdentity::class)]
#[Medium]
final class SetColumnIdentityTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id SET GENERATED ALWAYS RESTART SET NO CYCLE', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals(new Relation\Identity\SetColumnIdentity('id', [\SqlSemantics\Schema\Column\IdentityMode::Always, new Relation\Identity\RestartIdentity(null), Relation\Identity\SequenceFlag::NoCycle]), $statement->actions[0]);
        self::assertSame('ALTER TABLE "t" ALTER COLUMN "id" SET GENERATED ALWAYS RESTART SET NO CYCLE', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRejectsAnEmptyColumn(): void
    {
        $this->expectException(InvalidStructure::class);
        new Relation\Identity\SetColumnIdentity('', [Relation\Identity\SequenceFlag::Cycle]);
    }
}
