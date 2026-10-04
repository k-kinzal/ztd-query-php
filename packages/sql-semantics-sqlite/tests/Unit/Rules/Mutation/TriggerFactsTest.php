<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Mutation\TriggerFacts;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Assignment;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Delete;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\MutationTarget;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Relation\IndexChoice;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\CreateTrigger;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\TriggerEvent;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\TriggerTable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(TriggerFacts::class)]
#[Medium]
final class TriggerFactsTest extends TestCase
{
    public function testDeriveMakesTheRowsOfTheEventVisibleByQualifierOnly(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $derivation = new Derivation($semantics->context([$t]));
        $new = new ColumnUse(new Name('a'), new QualifiedName(new Name('new')));
        $old = new ColumnUse(new Name('rowid'), new QualifiedName(new Name('old')));
        $bare = new ColumnUse(new Name('a'));
        $table = new TriggerTable(new QualifiedName(new Name('t')));
        $trigger = new CreateTrigger(new QualifiedName(new Name('tr')), TriggerEvent::Update, $table, [new Select([new ResultColumn($new), new ResultColumn($old), new ResultColumn($bare)])]);
        (new TriggerFacts())->derive($trigger, $derivation);
        $facts = $derivation->facts();
        $resolved = $facts->scalar($new)->resolution;
        $implicit = $facts->scalar($old)->resolution;

        self::assertInstanceOf(DeclaredTable::class, $facts->relation($table)->table);
        self::assertInstanceOf(ResolvedColumn::class, $resolved);
        self::assertSame($table, $resolved->relation);
        self::assertSame(1, $resolved->depth);
        self::assertSame($t->declarations()[0]->columns[1], $resolved->declaration());
        self::assertInstanceOf(ResolvedColumn::class, $implicit);
        self::assertSame($t->declarations()[0]->columns[0], $implicit->declaration());
        self::assertInstanceOf(MissingColumn::class, $facts->scalar($bare)->resolution);
        self::assertNull($facts->output);
        self::assertSame([], $facts->declarations);
    }

    public function testDeriveHidesTheRowThatTheEventDoesNotProvide(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $derivation = new Derivation($semantics->context([$t]));
        $new = new ColumnUse(new Name('a'), new QualifiedName(new Name('new')));
        $old = new ColumnUse(new Name('a'), new QualifiedName(new Name('old')));
        $table = new TriggerTable(new QualifiedName(new Name('t')));
        $delete = new CreateTrigger(new QualifiedName(new Name('tr')), TriggerEvent::Delete, $table, [new Select([new ResultColumn($new)])], null, [], $old);
        (new TriggerFacts())->derive($delete, $derivation);
        $facts = $derivation->facts();

        self::assertInstanceOf(ResolvedColumn::class, $facts->scalar($old)->resolution);
        self::assertInstanceOf(MissingColumn::class, $facts->scalar($new)->resolution);
        self::assertSame('new', $facts->scalar($new)->resolution->qualifier?->name->value);
        self::assertCount(1, $facts->diagnostics);
    }

    public function testDeriveReportsTriggerOnlyMisusesAndTheColumnsOfUpdateOf(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $u = $semantics->analyze('CREATE TABLE u (a INTEGER NOT NULL, c TEXT)');
        $derivation = new Derivation($semantics->context([$t, $u]));
        $qualified = new Update(new MutationTarget(new QualifiedName(new Name('u'), new Name('main'))), [new Assignment(new Name('a'), new ColumnUse(new Name('a'), new QualifiedName(new Name('new'))))]);
        $indexed = new Delete(new MutationTarget(new QualifiedName(new Name('u')), null, new IndexChoice()));
        $trigger = new CreateTrigger(new QualifiedName(new Name('tr')), TriggerEvent::Update, new TriggerTable(new QualifiedName(new Name('t'))), [$qualified, $indexed], null, [new Name('a'), new Name('zz')]);
        (new TriggerFacts())->derive($trigger, $derivation);
        $facts = $derivation->facts();
        $rules = array_map(static fn (object $diagnostic): string => $diagnostic instanceof Misuse ? $diagnostic->rule->name : $diagnostic::class, $facts->diagnostics);

        self::assertSame([MissingColumn::class, MisuseRule::QualifiedTriggerTarget->name, MisuseRule::IndexedTriggerTarget->name], $rules);
        self::assertInstanceOf(ResolvedColumn::class, $facts->scalar($qualified->assignments[0]->value)->resolution);
        self::assertTrue($facts->covers($qualified->target));
        self::assertTrue($facts->covers($indexed->target));
    }

    public function testDeriveReportsAParameterAnywhereInTheProgram(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $statement = $semantics->analyze('CREATE TRIGGER tr INSERT ON t BEGIN SELECT 1; UPDATE t SET a = :x; END', [$t])->statement;
        $derivation = new Derivation($semantics->context([$t]));

        self::assertInstanceOf(CreateTrigger::class, $statement);
        (new TriggerFacts())->derive($statement, $derivation);
        $facts = $derivation->facts();
        self::assertCount(1, $facts->diagnostics);
        self::assertInstanceOf(Misuse::class, $facts->diagnostics[0]);
        self::assertSame(MisuseRule::ParameterInTrigger, $facts->diagnostics[0]->rule);
        self::assertInstanceOf(Select::class, $statement->steps[0]);
        self::assertTrue($facts->covers($statement->steps[0]));
    }
}
