<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Routine\RowAliases;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(RowAliases::class)]
#[Medium]
final class RowAliasesTest extends TestCase
{
    public function testVisibleResolvesTheOldRowToTheTriggerTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $operation = $semantics->analyze('CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW SET NEW.a = OLD.a + 1', [$table]);
        $trigger = $operation->statement;
        self::assertInstanceOf(CreateTrigger::class, $trigger);
        $set = $trigger->body;
        self::assertInstanceOf(SetVariables::class, $set);
        $item = $set->items[0];
        self::assertInstanceOf(NameAssignment::class, $item);
        $value = $item->value;
        self::assertInstanceOf(Arithmetic::class, $value);
        $resolution = $operation->facts->scalar($value->left)->resolution;

        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($trigger->table, $resolution->relation);
        self::assertSame('a', $resolution->declaration()?->name->value);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testVisibleMakesBothRowsVisibleInAnUpdateTriggerUnderAnySpelling(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');

        self::assertSame([], $semantics->analyze('CREATE TRIGGER tr AFTER UPDATE ON t FOR EACH ROW SET @x = OLD.a + New.a + nEw.a + old.a', [$table])->facts->diagnostics);
    }

    public function testVisibleHasNoOldRowInAnInsertTrigger(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $operation = $semantics->analyze('CREATE TRIGGER tr AFTER INSERT ON t FOR EACH ROW SET @x = NEW.a + OLD.a', [$table]);

        self::assertSame(['Column OLD.a does not exist.'], array_map(static fn (Diagnostic $problem): string => $problem->message(), $operation->facts->diagnostics));
    }

    public function testVisibleHasNoNewRowInADeleteTrigger(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $operation = $semantics->analyze('CREATE TRIGGER tr AFTER DELETE ON t FOR EACH ROW SET @x = OLD.a + NEW.a', [$table]);

        self::assertSame(['Column NEW.a does not exist.'], array_map(static fn (Diagnostic $problem): string => $problem->message(), $operation->facts->diagnostics));
    }

    public function testVisibleReachesNoColumnWithoutTheQualifier(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $operation = $semantics->analyze('CREATE TRIGGER tr AFTER INSERT ON t FOR EACH ROW SET @x = a + 1', [$table]);

        self::assertSame(['Column a does not exist.'], array_map(static fn (Diagnostic $problem): string => $problem->message(), $operation->facts->diagnostics));
    }

    public function testSpellingsListsEveryLetterCaseUnderCaseSensitiveNames(): void
    {
        $context = new AnalysisContext((new Semantics(Dialect::MySql))->profile(), [new Name('db')]);

        self::assertSame(['NEW', 'nEW', 'NeW', 'neW', 'NEw', 'nEw', 'New', 'new'], (new RowAliases())->spellings('NEW', $context));
    }

    public function testSpellingsKeepsOneSpellingUnderCaseInsensitiveNames(): void
    {
        $context = new AnalysisContext((new Semantics(Dialect::MySql))->profile(), [new Name('db')], [], true, Comparison::AsciiInsensitive);

        self::assertSame(['OLD'], (new RowAliases())->spellings('OLD', $context));
    }
}
