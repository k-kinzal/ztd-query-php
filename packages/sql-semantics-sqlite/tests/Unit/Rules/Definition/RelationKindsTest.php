<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Definition\RelationKinds;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Refusal\KindRefusal;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Refusal\WrongRelationKind;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(RelationKinds::class)]
#[Medium]
final class RelationKindsTest extends TestCase
{
    #[TestWith(['DROP TABLE v', KindRefusal::DropTable])]
    #[TestWith(['DROP TABLE IF EXISTS main.v', KindRefusal::DropTable])]
    #[TestWith(['DROP VIEW t', KindRefusal::DropView])]
    #[TestWith(['DROP VIEW IF EXISTS t', KindRefusal::DropView])]
    #[TestWith(['ALTER TABLE v RENAME TO w', KindRefusal::RenameTable])]
    #[TestWith(['ALTER TABLE v ADD COLUMN z UNIQUE', KindRefusal::AddColumn])]
    #[TestWith(['ALTER TABLE v DROP COLUMN x', KindRefusal::DropColumn])]
    #[TestWith(['ALTER TABLE v RENAME COLUMN nope TO y', KindRefusal::RenameColumn])]
    #[TestWith(['CREATE INDEX i ON v (x)', KindRefusal::CreateIndex])]
    #[TestWith(['CREATE TRIGGER tr INSERT ON v BEGIN SELECT 1; END', KindRefusal::BeforeTrigger])]
    #[TestWith(['CREATE TRIGGER tr BEFORE DELETE ON v BEGIN SELECT 1; END', KindRefusal::BeforeTrigger])]
    #[TestWith(['CREATE TRIGGER tr AFTER UPDATE ON v BEGIN SELECT 1; END', KindRefusal::AfterTrigger])]
    #[TestWith(['CREATE TRIGGER tr INSTEAD OF INSERT ON t BEGIN SELECT 1; END', KindRefusal::InsteadOfTrigger])]
    #[TestWith(['INSERT INTO v VALUES (1) ON CONFLICT DO NOTHING', KindRefusal::Upsert])]
    public function testRefuseReportsWhatSqliteRefusesForTheKindOfTheRelation(string $sql, KindRefusal $refusal): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t (a INTEGER)'), $semantics->analyze('CREATE VIEW v (x) AS SELECT a FROM t')]);
        $database = new PDO('sqlite::memory:');
        $database->exec('CREATE TABLE t (a INTEGER); CREATE VIEW v (x) AS SELECT a FROM t; CREATE TRIGGER vi INSTEAD OF INSERT ON v BEGIN SELECT 1; END');

        self::assertInstanceOf(WrongRelationKind::class, $operation->facts->diagnostics[0] ?? null);
        self::assertSame($refusal, $operation->facts->diagnostics[0]->refusal);
        self::assertCount(1, $operation->facts->diagnostics);
        $this->expectException(PDOException::class);
        $database->exec($sql);
    }

    #[TestWith(['DROP VIEW v'])]
    #[TestWith(['DROP TABLE t'])]
    #[TestWith(['CREATE INDEX i ON t (a)'])]
    #[TestWith(['CREATE TRIGGER tr INSTEAD OF UPDATE OF x ON v BEGIN SELECT 1; END'])]
    #[TestWith(['CREATE TRIGGER tr AFTER INSERT ON t BEGIN SELECT 1; END'])]
    #[TestWith(['INSERT INTO t VALUES (1) ON CONFLICT DO NOTHING'])]
    #[TestWith(['INSERT INTO v VALUES (1)'])]
    #[TestWith(['ALTER TABLE t RENAME TO w'])]
    public function testRefuseReportsNothingForTheKindTheRequestNeeds(string $sql): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = $semantics->analyze($sql, [$semantics->analyze('CREATE TABLE t (a INTEGER)'), $semantics->analyze('CREATE VIEW v (x) AS SELECT a FROM t')]);
        $database = new PDO('sqlite::memory:');
        $database->exec('CREATE TABLE t (a INTEGER); CREATE VIEW v (x) AS SELECT a FROM t; CREATE TRIGGER vi INSTEAD OF INSERT ON v BEGIN SELECT 1; END');

        self::assertSame([], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
        self::assertNotFalse($database->exec($sql));
    }

    public function testRefuseLeavesTheKindOfAnUndeclaredRelationUndecided(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        self::assertSame([], $semantics->analyze('DROP TABLE v')->facts->diagnostics);
        self::assertSame([], $semantics->analyze('DROP VIEW v', $semantics->context([$semantics->analyze('CREATE VIEW main.v AS SELECT 1')], false))->facts->diagnostics);
        self::assertSame([], $semantics->analyze('DROP TABLE v', $semantics->context([$semantics->analyze('CREATE VIEW main.v AS SELECT 1')], false))->facts->diagnostics);
    }
}
