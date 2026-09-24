<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
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

    /**
     * @return array<string, array{string, string, string|null}>
     */
    public static function providerActions(): array
    {
        return [
            'owner' => ['ALTER TABLE t OWNER TO bob', 'OWNER TO "bob"', null],
            'cluster' => ['ALTER TABLE t CLUSTER ON ix', 'CLUSTER ON "ix"', null],
            'without cluster' => ['ALTER TABLE t SET WITHOUT CLUSTER', 'SET WITHOUT CLUSTER', null],
            'named trigger' => ['ALTER TABLE t ENABLE REPLICA TRIGGER tr', 'ENABLE REPLICA TRIGGER "tr"', null],
            'inherit' => ['ALTER TABLE t INHERIT p', 'INHERIT "p"', null],
            'no inherit' => ['ALTER TABLE t NO INHERIT p', 'NO INHERIT "p"', null],
            'typed table' => ['ALTER TABLE t OF typ', 'OF "typ"', null],
            'replica identity' => ['ALTER TABLE t REPLICA IDENTITY FULL', 'REPLICA IDENTITY FULL', null],
            'row security' => ['ALTER TABLE t NO FORCE ROW LEVEL SECURITY', 'NO FORCE ROW LEVEL SECURITY', null],
            'column' => ['ALTER TABLE t ADD COLUMN c INTEGER', 'ADD COLUMN "c" integer', null],
            'constraint' => ['ALTER TABLE t ADD CONSTRAINT k UNIQUE (n)', 'ADD CONSTRAINT "k" UNIQUE("n")', null],
            'partition' => ['ALTER TABLE t DETACH PARTITION p', 'DETACH PARTITION "p"', null],
            'logged' => ['ALTER TABLE t SET LOGGED', 'SET LOGGED', 'SET LOGGED'],
            'unlogged' => ['ALTER TABLE t SET UNLOGGED', 'SET UNLOGGED', 'SET UNLOGGED'],
            'tablespace' => ['ALTER TABLE t SET TABLESPACE ts', 'SET TABLESPACE "ts"', 'SET TABLESPACE "ts"'],
            'access method' => ['ALTER TABLE t SET ACCESS METHOD heap', 'SET ACCESS METHOD "heap"', 'SET ACCESS METHOD "heap"'],
            'storage parameters' => ['ALTER TABLE t SET (fillfactor=70, autovacuum_enabled=false)', 'SET ("fillfactor" = 70, "autovacuum_enabled" = false)', 'SET ("fillfactor" = 70, "autovacuum_enabled" = false)'],
            'storage reset' => ['ALTER TABLE t RESET (fillfactor, toast.autovacuum_enabled)', 'RESET("fillfactor", "toast"."autovacuum_enabled")', 'RESET ("fillfactor", "toast"."autovacuum_enabled")'],
            'foreign options' => ["ALTER FOREIGN TABLE t OPTIONS (ADD a '1', SET b '2', DROP c)", 'OPTIONS(ADD "a" \'1\', SET "b" \'2\', DROP "c")', 'OPTIONS (ADD "a" \'1\', SET "b" \'2\', DROP "c")'],
        ];
    }

    #[DataProvider('providerActions')]
    public function testWriteWritesEachAction(string $sql, string $expected, ?string $storage): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind($sql, strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertSame($expected, RelationActions::write($statement->actions[0])->toString());
    }

    #[DataProvider('providerActions')]
    public function testStorageWritesOnlyStorageActions(string $sql, string $expected, ?string $storage): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind($sql, strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        $trees = RelationActions::storage($statement->actions[0]);
        self::assertSame($storage, $trees === null ? null : implode(' ', array_map(static fn ($tree): string => $tree->toString(), $trees)));
    }

    public function testWriteRejectsAnUnclassifiedAction(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessageMatches('/^Unclassified relation action: SqlSemantics\\\\Model\\\\Definition\\\\RelationAction@anonymous/');
        RelationActions::write(new class () implements RelationAction {
        });
    }
}
