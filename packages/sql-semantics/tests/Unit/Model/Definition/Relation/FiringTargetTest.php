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

#[CoversClass(Relation\FiringTarget::class)]
#[Medium]
final class FiringTargetTest extends TestCase
{
    public function testSpellsEachChoiceAsItsKeywords(): void
    {
        self::assertSame(['TRIGGER', 'RULE'], array_column(Relation\FiringTarget::cases(), 'value'));
    }

    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ENABLE REPLICA RULE r', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals(new Relation\SetFiring(Relation\FiringTarget::Rule, 'r', \SqlSemantics\Model\Definition\Trigger\TriggerFiring::Replica), $statement->actions[0]);
        self::assertSame('ALTER TABLE "t" ENABLE REPLICA RULE "r"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
