<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\Command\Triggers::class)]
#[Medium]
final class TriggersTest extends TestCase
{
    public function testDeriveGivesAStatementTriggerOldAndNew(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE TRIGGER g AFTER INSERT ON t FOR EACH STATEMENT WHEN (a > 0) EXECUTE FUNCTION f()', $context);
        self::assertSame([
          0 => 'Column a is ambiguous.',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testDeriveConstraintChecksTheAttributes(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE CONSTRAINT TRIGGER g AFTER INSERT ON t NOT VALID FOR EACH ROW EXECUTE FUNCTION f()', $context);
        self::assertSame([
          0 => 'TRIGGER constraints cannot be marked NOT VALID',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testEventReportsAnUnknownEvent(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE EVENT TRIGGER e ON whenever EXECUTE FUNCTION f()', []);
        self::assertSame([
          0 => 'unrecognized event name "whenever"',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testWriteWritesTheTrigger(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TRIGGER g AFTER INSERT OR DELETE ON t EXECUTE FUNCTION f()', []);
        self::assertSame('CREATE TRIGGER g AFTER INSERT OR DELETE ON t EXECUTE FUNCTION f()', $statement->toString());
    }

    public function testWriteConstraintWritesTheConstraintTrigger(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE CONSTRAINT TRIGGER g AFTER INSERT ON t FROM u FOR EACH ROW EXECUTE FUNCTION f()', []);
        self::assertSame('CREATE CONSTRAINT TRIGGER g AFTER INSERT ON t FROM u FOR EACH ROW EXECUTE FUNCTION f()', $statement->toString());
    }

    public function testTailWritesTheConditionAndTheFunction(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TRIGGER g AFTER INSERT ON t FOR EACH ROW WHEN (new.a > 0) EXECUTE FUNCTION f(1)', []);
        self::assertSame('CREATE TRIGGER g AFTER INSERT ON t FOR EACH ROW WHEN (new.a > 0) EXECUTE FUNCTION f(1)', $statement->toString());
    }

    public function testTruncatesTellsWhetherATriggerFiresOnTruncate(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [$semantics->analyze('CREATE VIEW v AS SELECT 1 AS a')];
        self::assertSame(['"v" is a view'], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('CREATE TRIGGER g AFTER INSERT OR TRUNCATE ON v EXECUTE FUNCTION f()', $context)->facts->diagnostics));
        self::assertSame([], $semantics->analyze('CREATE TRIGGER g AFTER INSERT ON v EXECUTE FUNCTION f()', $context)->facts->diagnostics);
    }
}
