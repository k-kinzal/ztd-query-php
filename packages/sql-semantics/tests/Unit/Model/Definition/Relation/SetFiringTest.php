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
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\SetFiring::class)]
#[Medium]
final class SetFiringTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ENABLE ALWAYS TRIGGER audit', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals(new Relation\SetFiring(Relation\FiringTarget::Trigger, 'audit', \SqlSemantics\Model\Definition\Trigger\TriggerFiring::Always), $statement->actions[0]);
        self::assertSame('ALTER TABLE "t" ENABLE ALWAYS TRIGGER "audit"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRejectsAReplicaPolicyForATriggerGroup(): void
    {
        $this->expectException(InvalidStructure::class);
        new Relation\SetFiring(Relation\FiringTarget::Trigger, Relation\TriggerGroup::All, \SqlSemantics\Model\Definition\Trigger\TriggerFiring::Replica);
    }

    public function testRejectsATriggerGroupForARule(): void
    {
        $this->expectException(InvalidStructure::class);
        new Relation\SetFiring(Relation\FiringTarget::Rule, Relation\TriggerGroup::User, \SqlSemantics\Model\Definition\Trigger\TriggerFiring::Origin);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        new Relation\SetFiring(Relation\FiringTarget::Trigger, '', \SqlSemantics\Model\Definition\Trigger\TriggerFiring::Origin);
    }
}
