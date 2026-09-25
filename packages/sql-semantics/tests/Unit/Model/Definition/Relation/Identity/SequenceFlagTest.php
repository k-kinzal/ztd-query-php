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
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Identity\SequenceFlag::class)]
#[Medium]
final class SequenceFlagTest extends TestCase
{
    public function testSpellsEachChoiceAsItsKeywords(): void
    {
        self::assertSame(['CYCLE', 'NO CYCLE', 'NO MINVALUE', 'NO MAXVALUE', 'LOGGED', 'UNLOGGED'], array_column(Relation\Identity\SequenceFlag::cases(), 'value'));
    }

    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id SET CYCLE SET NO MINVALUE', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals(new Relation\Identity\SetColumnIdentity('id', [Relation\Identity\SequenceFlag::Cycle, Relation\Identity\SequenceFlag::NoMinValue]), $statement->actions[0]);
        self::assertSame('ALTER TABLE "t" ALTER COLUMN "id" SET CYCLE SET NO MINVALUE', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
