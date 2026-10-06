<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Assignment;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Delete;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertInto;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\MutationTarget;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValueRow;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\CommonTable;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithClause;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\CreateTrigger;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\TriggerEvent;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\TriggerTable;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\TriggerTiming;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(CreateTrigger::class)]
#[Medium]
final class CreateTriggerTest extends TestCase
{
    public function testDeriveStatementResolvesNewAndOldToTheWatchedTable(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $u = $semantics->analyze('CREATE TABLE u (a INTEGER NOT NULL, c TEXT)');
        $trigger = $semantics->analyze('CREATE TRIGGER tr AFTER UPDATE ON t WHEN new.a > old.a BEGIN UPDATE u SET a = new.a WHERE a = old.rowid; END', [$t, $u]);

        self::assertInstanceOf(CreateTrigger::class, $trigger->statement);
        self::assertInstanceOf(Binary::class, $trigger->statement->when);
        $new = $trigger->facts->scalar($trigger->statement->when->left)->resolution;
        $old = $trigger->facts->scalar($trigger->statement->when->right)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $new);
        self::assertInstanceOf(ResolvedColumn::class, $old);
        self::assertSame($trigger->statement->table, $new->relation);
        self::assertSame($trigger->statement->table, $old->relation);
        self::assertSame($t->declarations()[0]->columns[1], $new->declaration());
        self::assertInstanceOf(Update::class, $trigger->statement->steps[0]);
        self::assertInstanceOf(Assignment::class, $trigger->statement->steps[0]->assignments[0]);
        $assigned = $trigger->facts->scalar($trigger->statement->steps[0]->assignments[0]->value)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $assigned);
        self::assertSame($trigger->statement->table, $assigned->relation);
        self::assertSame(1, $assigned->depth);
        self::assertSame([], $trigger->facts->diagnostics);
        self::assertSame([], $trigger->declarations());
        self::assertNull($trigger->shape());
    }

    public function testDeriveStatementHidesTheRowThatTheEventDoesNotProvide(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $delete = $semantics->analyze('CREATE TRIGGER tr AFTER DELETE ON t BEGIN SELECT new.a; END', [$t]);
        $insert = $semantics->analyze('CREATE TRIGGER tr AFTER INSERT ON t BEGIN SELECT old.a; END', [$t]);
        $bare = $semantics->analyze('CREATE TRIGGER tr AFTER INSERT ON t BEGIN SELECT a; END', [$t]);

        self::assertInstanceOf(MissingColumn::class, $delete->facts->diagnostics[0]);
        self::assertSame('new', $delete->facts->diagnostics[0]->qualifier?->name->value);
        self::assertInstanceOf(MissingColumn::class, $insert->facts->diagnostics[0]);
        self::assertSame('old', $insert->facts->diagnostics[0]->qualifier?->name->value);
        self::assertInstanceOf(MissingColumn::class, $bare->facts->diagnostics[0]);
        self::assertNull($bare->facts->diagnostics[0]->qualifier);
    }

    public function testDeriveStatementReportsWhatSqliteForbidsInsideATrigger(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $u = $semantics->analyze('CREATE TABLE u (a INTEGER NOT NULL, c TEXT)');
        $qualified = $semantics->analyze('CREATE TRIGGER tr INSERT ON t BEGIN INSERT INTO main.u VALUES (1, 2); END', [$t, $u]);
        $indexed = $semantics->analyze('CREATE TRIGGER tr INSERT ON t BEGIN DELETE FROM u NOT INDEXED; END', [$t, $u]);
        $parameter = $semantics->analyze('CREATE TRIGGER tr INSERT ON t WHEN new.a > ? BEGIN SELECT 1; END', [$t, $u]);
        $raise = $semantics->analyze("CREATE TRIGGER tr INSERT ON t BEGIN SELECT RAISE(ABORT, 'no'); END", [$t, $u]);

        self::assertInstanceOf(Misuse::class, $qualified->facts->diagnostics[0]);
        self::assertSame(MisuseRule::QualifiedTriggerTarget, $qualified->facts->diagnostics[0]->rule);
        self::assertInstanceOf(Misuse::class, $indexed->facts->diagnostics[0]);
        self::assertSame(MisuseRule::IndexedTriggerTarget, $indexed->facts->diagnostics[0]->rule);
        self::assertInstanceOf(Misuse::class, $parameter->facts->diagnostics[0]);
        self::assertSame(MisuseRule::ParameterInTrigger, $parameter->facts->diagnostics[0]->rule);
        self::assertSame([], $raise->facts->diagnostics);
    }

    public function testDeriveStatementChecksTheColumnsOfAnUpdateOfTrigger(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $known = $semantics->analyze('CREATE TRIGGER tr UPDATE OF a, b ON t BEGIN SELECT 1; END', [$t]);
        $unknown = $semantics->analyze('CREATE TRIGGER tr UPDATE OF zz ON t BEGIN SELECT 1; END', [$t]);

        self::assertInstanceOf(CreateTrigger::class, $known->statement);
        self::assertSame(['a', 'b'], array_map(static fn (Name $name): string => $name->value, $known->statement->columns));
        self::assertSame([], $known->facts->diagnostics);
        self::assertInstanceOf(MissingColumn::class, $unknown->facts->diagnostics[0]);
        self::assertSame('zz', $unknown->facts->diagnostics[0]->name->value);
    }

    public function testRenderWritesEveryPartInGrammarOrder(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $trigger = $semantics->analyze('create temp trigger if not exists tr instead of update of a, b on t for each row when new.a > 1 begin select 1; delete from u; insert into u values (1) on conflict do nothing; values (1); end');
        $built = new Operation($semantics->context(), new CreateTrigger(new QualifiedName(new Name('tr'), new Name('main')), TriggerEvent::Delete, new TriggerTable(new QualifiedName(new Name('t'))), [new Select([new ResultColumn(new IntegerLiteral('1'))])], TriggerTiming::Before));

        self::assertSame('CREATE TEMP TRIGGER IF NOT EXISTS tr INSTEAD OF UPDATE OF a, b ON t FOR EACH ROW WHEN new.a > 1 BEGIN SELECT 1; DELETE FROM u; INSERT INTO u VALUES (1) ON CONFLICT DO NOTHING; VALUES (1); END', $trigger->toString());
        self::assertInstanceOf(CreateTrigger::class, $trigger->statement);
        self::assertTrue($trigger->statement->temporary);
        self::assertTrue($trigger->statement->ifNotExists);
        self::assertTrue($trigger->statement->forEachRow);
        self::assertSame(TriggerTiming::InsteadOf, $trigger->statement->timing);
        self::assertSame(TriggerEvent::Update, $trigger->statement->event);
        self::assertCount(4, $trigger->statement->steps);
        self::assertSame('CREATE TRIGGER main.tr BEFORE DELETE ON t BEGIN SELECT 1; END', $built->toString());
    }

    public function testRejectsAStepWithAReturningClause(): void
    {
        $step = new Delete(new MutationTarget(new QualifiedName(new Name('u'))), null, [new Star()]);

        $this->expectExceptionMessage('An UPDATE or DELETE of a trigger program has no WITH clause, no correlation name and no RETURNING clause.');

        new CreateTrigger(new QualifiedName(new Name('tr')), TriggerEvent::Insert, new TriggerTable(new QualifiedName(new Name('t'))), [$step]);
    }

    public function testRejectsAStepWithAWithClause(): void
    {
        $with = new WithClause([new CommonTable(new Name('w'), new Select([new ResultColumn(new IntegerLiteral('1'))]))]);
        $step = new Update(new MutationTarget(new QualifiedName(new Name('u'))), [new Assignment(new Name('a'), new IntegerLiteral('1'))], null, null, [], null, $with);

        $this->expectExceptionMessage('An UPDATE or DELETE of a trigger program has no WITH clause, no correlation name and no RETURNING clause.');

        new CreateTrigger(new QualifiedName(new Name('tr')), TriggerEvent::Insert, new TriggerTable(new QualifiedName(new Name('t'))), [$step]);
    }

    public function testRejectsAnInsertWithACorrelationName(): void
    {
        $into = new InsertInto(new MutationTarget(new QualifiedName(new Name('u')), new Name('x')));
        $step = new InsertRows($into, new ValuesClause([new ValueRow([new IntegerLiteral('1')])]));

        $this->expectExceptionMessage('An INSERT of a trigger program has no WITH clause and no correlation name.');

        new CreateTrigger(new QualifiedName(new Name('tr')), TriggerEvent::Insert, new TriggerTable(new QualifiedName(new Name('t'))), [$step]);
    }

    public function testRejectsColumnsOnAnEventOtherThanUpdate(): void
    {
        $this->expectExceptionMessage('Only an UPDATE trigger names columns.');

        new CreateTrigger(new QualifiedName(new Name('tr')), TriggerEvent::Insert, new TriggerTable(new QualifiedName(new Name('t'))), [new Select([new ResultColumn(new IntegerLiteral('1'))])], null, [new Name('a')]);
    }

    public function testRejectsAnEmptyProgram(): void
    {
        $this->expectExceptionMessage('A trigger program consists of at least one query, INSERT, UPDATE or DELETE statement.');

        new CreateTrigger(new QualifiedName(new Name('tr')), TriggerEvent::Insert, new TriggerTable(new QualifiedName(new Name('t'))), []);
    }
}
