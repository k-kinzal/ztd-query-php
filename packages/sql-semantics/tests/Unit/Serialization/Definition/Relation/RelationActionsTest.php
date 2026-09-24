<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Relation\RelationActions;

#[CoversClass(RelationActions::class)]
#[Medium]
final class RelationActionsTest extends TestCase
{
    public function testWriteWritesRelationLevelActions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('ALTER TABLE t REPLICA IDENTITY USING INDEX ix', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertSame('REPLICA IDENTITY USING INDEX "ix"', RelationActions::write($statement->actions[0])->toString());
    }

    public function testStorageWritesStorageChanges(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('ALTER TABLE t SET ACCESS METHOD DEFAULT', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertSame('SET ACCESS METHOD DEFAULT', implode(' ', array_map(static fn ($tree): string => $tree->toString(), RelationActions::storage($statement->actions[0]) ?? [])));
    }

    public function testFiringWritesGroupsAsKeywords(): void
    {
        self::assertSame('DISABLE TRIGGER ALL', implode(' ', array_map(static fn ($tree): string => $tree->toString(), RelationActions::firing(new Relation\SetFiring(Relation\FiringTarget::Trigger, Relation\TriggerGroup::All, \SqlSemantics\Model\Definition\Trigger\TriggerFiring::Disabled)))));
        self::assertSame('ENABLE ALWAYS RULE "r"', implode(' ', array_map(static fn ($tree): string => $tree->toString(), RelationActions::firing(new Relation\SetFiring(Relation\FiringTarget::Rule, 'r', \SqlSemantics\Model\Definition\Trigger\TriggerFiring::Always)))));
    }
}
