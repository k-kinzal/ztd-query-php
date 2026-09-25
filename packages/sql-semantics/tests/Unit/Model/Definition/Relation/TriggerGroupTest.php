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

#[CoversClass(Relation\TriggerGroup::class)]
#[Medium]
final class TriggerGroupTest extends TestCase
{
    public function testSpellsEachChoiceAsItsKeywords(): void
    {
        self::assertSame(['ALL', 'USER'], array_column(Relation\TriggerGroup::cases(), 'value'));
    }

    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t DISABLE TRIGGER ALL', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals(new Relation\SetFiring(Relation\FiringTarget::Trigger, Relation\TriggerGroup::All, \SqlSemantics\Model\Definition\Trigger\TriggerFiring::Disabled), $statement->actions[0]);
        self::assertSame('ALTER TABLE "t" DISABLE TRIGGER ALL', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
