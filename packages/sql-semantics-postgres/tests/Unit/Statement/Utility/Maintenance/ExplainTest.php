<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Explain;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(Explain::class)]
#[Medium]
final class ExplainTest extends TestCase
{
    public function testDeriveStatementPublishesThePlanRowInsteadOfTheRowsOfTheStatement(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('EXPLAIN SELECT 1 AS a, 2 AS b');
        self::assertSame([1, 'QUERY PLAN'], [count($operation->shape()->slots ?? []), $operation->field(0)->name?->value]);
    }

    public function testDeriveStatementDerivesTheExplainedStatement(): void
    {
        $table = new Table(new QualifiedName(new Name('t')), new LanguageProfile(GrammarRelease::PostgreSql172), [new Column(new Name('a'), Builtin::Int4)]);
        self::assertSame(
            ['Column b does not exist.'],
            array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics(Dialect::PostgreSql))->analyze('EXPLAIN ANALYZE UPDATE t SET a = b', [$table])->facts->diagnostics),
        );
    }

    public function testDeriveStatementReportsUnknownOptions(): void
    {
        self::assertSame(
            ['unrecognized EXPLAIN option "fast"', 'costs requires a Boolean value'],
            array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics(Dialect::PostgreSql))->analyze('EXPLAIN (fast, costs 5) SELECT 1')->facts->diagnostics),
        );
    }

    public function testRenderWritesEachSyntax(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            ['EXPLAIN ANALYSE VERBOSE SELECT 1', 'EXPLAIN VERBOSE VALUES (1)', 'EXPLAIN (ANALYZE, FORMAT json) DELETE FROM t', 'EXPLAIN ("format" json, format text) EXECUTE p'],
            [$semantics->analyze('explain analyse verbose select 1')->toString(), $semantics->analyze('EXPLAIN VERBOSE VALUES (1)')->toString(), $semantics->analyze('EXPLAIN (ANALYZE, FORMAT JSON) DELETE FROM t')->toString(), $semantics->analyze('EXPLAIN ("format" json, format text) EXECUTE p')->toString()],
        );
    }
}
