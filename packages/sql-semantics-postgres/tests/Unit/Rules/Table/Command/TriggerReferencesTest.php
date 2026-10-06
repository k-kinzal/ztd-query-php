<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Command\TriggerReferences;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnStar;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\CreateTrigger;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(TriggerReferences::class)]
#[Medium]
final class TriggerReferencesTest extends TestCase
{
    public function testCheckReportsOldInAnInsertTrigger(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $table = $semantics->analyze('CREATE TABLE t (a int)', []);
        $trigger = $semantics->analyze('CREATE TRIGGER tr BEFORE INSERT OR UPDATE ON t FOR EACH ROW WHEN (old.a = 1) EXECUTE FUNCTION f()', $table->declarations());
        self::assertSame(["INSERT trigger's WHEN condition cannot reference OLD values"], array_map(static fn ($diagnostic): string => $diagnostic->message(), $trigger->facts->diagnostics));
    }

    public function testCheckReportsNewInADeleteTrigger(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $table = $semantics->analyze('CREATE TABLE t (a int)', []);
        $trigger = $semantics->analyze('CREATE TRIGGER tr AFTER DELETE ON t FOR EACH ROW WHEN (new IS NULL) EXECUTE FUNCTION f()', $table->declarations());
        self::assertSame(["DELETE trigger's WHEN condition cannot reference NEW values"], array_map(static fn ($diagnostic): string => $diagnostic->message(), $trigger->facts->diagnostics));
    }

    public function testCheckReportsANewSystemColumnInABeforeTrigger(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $table = $semantics->analyze('CREATE TABLE t (a int)', []);
        $trigger = $semantics->analyze('CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW WHEN (new.xmin IS NULL) EXECUTE FUNCTION f()', $table->declarations());
        self::assertSame(["BEFORE trigger's WHEN condition cannot reference NEW system columns"], array_map(static fn ($diagnostic): string => $diagnostic->message(), $trigger->facts->diagnostics));
    }

    public function testCheckReportsAnyColumnInAStatementTrigger(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $table = $semantics->analyze('CREATE TABLE t (a int)', []);
        $trigger = $semantics->analyze('CREATE TRIGGER tr AFTER UPDATE ON t FOR EACH STATEMENT WHEN (old.a = new.a) EXECUTE FUNCTION f()', $table->declarations());
        self::assertSame(["statement trigger's WHEN condition cannot reference column values"], array_map(static fn ($diagnostic): string => $diagnostic->message(), $trigger->facts->diagnostics));
    }

    public function testCheckAcceptsOldAndNewInAnAfterUpdateTrigger(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $table = $semantics->analyze('CREATE TABLE t (a int)', []);
        $trigger = $semantics->analyze('CREATE TRIGGER tr AFTER UPDATE ON t FOR EACH ROW WHEN (old.* IS DISTINCT FROM new.* AND new.ctid IS NOT NULL) EXECUTE FUNCTION f()', $table->declarations());
        self::assertSame([], $trigger->facts->diagnostics);
    }

    public function testReferenceTellsOldNewAndSystemColumns(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $table = $semantics->analyze('CREATE TABLE t (a int, old int)', []);
        $trigger = $semantics->analyze('CREATE TRIGGER tr AFTER UPDATE ON t FOR EACH ROW WHEN (true) EXECUTE FUNCTION f()', $table->declarations());
        self::assertInstanceOf(CreateTrigger::class, $trigger->statement);
        $fact = $trigger->facts->relation($trigger->statement);
        $references = new TriggerReferences();
        self::assertSame(
            [[true, false, false], [false, true, false], null, [false, false, false], null, null],
            [
                $references->reference(new ColumnReference([new Name('old'), new Name('a')]), $fact),
                $references->reference(new ColumnReference([new Name('new'), new Name('ctid')]), $fact),
                $references->reference(new ColumnReference([new Name('old')]), $fact),
                $references->reference(new ColumnStar([new Name('new')]), $fact),
                $references->reference(new ColumnReference([new Name('new'), new Name('b')]), $fact),
                $references->reference(new ColumnReference([new Name('t'), new Name('a')]), $fact),
            ],
        );
    }

    public function testCheckReportsANewGeneratedColumnInABeforeTrigger(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-16.6');
        $table = $semantics->analyze('CREATE TABLE g (a int, b int GENERATED ALWAYS AS (a * 2) STORED)', []);
        $trigger = $semantics->analyze('CREATE TRIGGER tr BEFORE UPDATE ON g FOR EACH ROW WHEN (new.a > 0 AND new.b > 0) EXECUTE FUNCTION f()', $table->declarations());
        self::assertSame(["BEFORE trigger's WHEN condition cannot reference NEW generated columns"], array_map(static fn ($diagnostic): string => $diagnostic->message(), $trigger->facts->diagnostics));
    }

    public function testCheckReportsTheWholeNewRowOfATableWithAGeneratedColumn(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $table = $semantics->analyze('CREATE TABLE g (a int, b int GENERATED ALWAYS AS (a * 2) STORED)', []);
        $trigger = $semantics->analyze('CREATE TRIGGER tr BEFORE UPDATE ON g FOR EACH ROW WHEN (new IS DISTINCT FROM old) EXECUTE FUNCTION f()', $table->declarations());
        self::assertSame(["BEFORE trigger's WHEN condition cannot reference NEW generated columns"], array_map(static fn ($diagnostic): string => $diagnostic->message(), $trigger->facts->diagnostics));
    }

    public function testCheckAcceptsNewGeneratedColumnsInAnAfterTrigger(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $table = $semantics->analyze('CREATE TABLE g (a int, b int GENERATED ALWAYS AS (a * 2) STORED)', []);
        $trigger = $semantics->analyze('CREATE TRIGGER tr AFTER UPDATE ON g FOR EACH ROW WHEN (new.b > 0 AND (new).b > 0) EXECUTE FUNCTION f()', $table->declarations());
        self::assertSame([], $trigger->facts->diagnostics);
    }

    public function testReferencesAnswersTheReferencesInWrittenOrder(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql, 'pg-17.2');
        $table = $semantics->analyze('CREATE TABLE g (a int, b int GENERATED ALWAYS AS (a * 2) STORED)', []);
        $trigger = $semantics->analyze('CREATE TRIGGER tr BEFORE UPDATE ON g FOR EACH ROW WHEN (new.b > 0 AND new.ctid IS NOT NULL AND old.a = (new).a) EXECUTE FUNCTION f()', $table->declarations());
        self::assertInstanceOf(CreateTrigger::class, $trigger->statement);
        self::assertNotNull($trigger->statement->when);
        self::assertSame(
            [[false, false, true], [false, true, false], [true, false, false], [false, false, false]],
            (new TriggerReferences())->references($trigger->statement->when, $trigger->facts->relation($trigger->statement)),
        );
    }

    public function testSelectedReadsAFieldOfTheWholeRowAsAColumn(): void
    {
        $references = new TriggerReferences();
        $new = new ColumnReference([new Name('new')]);
        self::assertEquals(
            [new ColumnReference([new Name('new'), new Name('b')]), null],
            [
                $references->selected(new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Indirection(new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped($new), [new \SqlSemantics\Platform\PostgreSql\Statement\Expression\FieldSelection(new Name('b'))])),
                $references->selected($new),
            ],
        );
    }
}
