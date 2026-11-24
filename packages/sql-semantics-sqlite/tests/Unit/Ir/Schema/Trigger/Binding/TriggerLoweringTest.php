<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Binding\TriggerLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\CreateTrigger;
use SqlSemantics\Platform\Sqlite\Model\Contract\SearchPath;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(TriggerLowering::class)]
#[Small]
final class TriggerLoweringTest extends TestCase
{
    public function testLowerUsesTheTriggerOwnerForAnUnqualifiedWatchedAndWrittenTable(): void
    {
        $semantics = new Semantics(searchPath: new SearchPath('main', 'archive'));
        $context = $semantics->context([
            'CREATE TABLE main.t (wrong)', 'CREATE TABLE temp.t (wrong)', 'CREATE TABLE archive.t (id)',
            'CREATE TABLE temp.audit (wrong)', 'CREATE TABLE archive.audit (copied)',
        ]);
        $result = $semantics->analyze('CREATE TRIGGER archive.tr AFTER INSERT ON t BEGIN INSERT INTO audit(copied) VALUES (new.id); END', $context);

        self::assertInstanceOf(CreateTrigger::class, $result);
        self::assertSame('archive', $result->watched->schema->value);
        self::assertSame('archive', $result->program[0]->destination->table->schema->value);
    }

    public function testWatchedKeepsAnInsteadOfViewLayout(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE t (id)', 'CREATE VIEW v AS SELECT id FROM t', 'CREATE TABLE audit (id)']);
        $result = $semantics->analyze('CREATE TRIGGER tr INSTEAD OF DELETE ON v BEGIN INSERT INTO audit VALUES (OLD.id); END', $context);

        self::assertInstanceOf(CreateTrigger::class, $result);
        self::assertSame('view', $result->watched->kind->value);
        self::assertSame('id', $result->watched->columns[0]->value);
    }

    public function testSelectorsRetainTheOrderOfAbsentAndResolvedMembers(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE t (id, value)', 'CREATE TABLE audit (id)']);
        $result = $semantics->analyze('CREATE TRIGGER tr UPDATE OF future, VALUE ON t BEGIN INSERT INTO audit VALUES (NEW.id); END', $context);

        self::assertInstanceOf(CreateTrigger::class, $result);
        self::assertSame(1, $result->selectors[1]->selector);
        self::assertNotSame(0, $result->selectors[0]->selector);
    }

    public function testSchemaNormalizesTheKnownAttachmentAlias(): void
    {
        $semantics = new Semantics(searchPath: new SearchPath('main', 'archive'));
        $context = $semantics->context(['CREATE TABLE archive.t (id)', 'CREATE TABLE archive.audit (id)']);
        $result = $semantics->analyze('CREATE TRIGGER ARCHIVE.tr INSERT ON t BEGIN INSERT INTO audit VALUES (NEW.id); END', $context);

        self::assertInstanceOf(CreateTrigger::class, $result);
        self::assertSame('archive', $result->schema->value);
    }

    public function testProgramKeepsMixedMutationStepsInSourceOrder(): void
    {
        $semantics = new Semantics();
        $declarations = $semantics->context(['CREATE TABLE watched (id)', 'CREATE TABLE audit (id)']);
        $result = $semantics->analyze('CREATE TRIGGER tr INSERT ON watched BEGIN UPDATE audit SET id=NEW.id; DELETE FROM audit; END', $declarations);

        self::assertInstanceOf(CreateTrigger::class, $result);
        self::assertSame([
            \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerUpdate::class,
            \SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerDelete::class,
        ], array_map(static fn (object $step): string => $step::class, $result->program));
    }
}
