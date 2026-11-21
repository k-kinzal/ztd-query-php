<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Semantics;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Explain;

#[CoversClass(Explain::class)]
#[Medium]
final class ExplainTest extends TestCase
{
    public function testDeriveStatementPublishesThePlanRowInsteadOfTheRowsOfTheStatement(): void
    {
        $operation = (new Semantics())->inspect('EXPLAIN SELECT 1 AS a, 2 AS b');
        self::assertSame([1, 'QUERY PLAN'], [count($operation->shape()->slots ?? []), $operation->field(0)->name?->value]);
    }

    public function testDeriveStatementDerivesTheExplainedStatement(): void
    {
        $table = new Table(new QualifiedName(new Name('t')), new LanguageProfile(GrammarRelease::PostgreSql172), [new Column(new Name('a'), Builtin::Int4)]);
        self::assertSame(
            ['Column b does not exist.'],
            array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics())->inspect('EXPLAIN ANALYZE UPDATE t SET a = b', [$table])->facts->diagnostics),
        );
    }

    public function testDeriveStatementReportsUnknownOptions(): void
    {
        self::assertSame(
            ['unrecognized EXPLAIN option "fast"', 'costs requires a Boolean value'],
            array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics())->inspect('EXPLAIN (fast, costs 5) SELECT 1')->facts->diagnostics),
        );
    }

    public function testRenderWritesEachSyntax(): void
    {
        $semantics = new Semantics();
        self::assertSame(
            ['EXPLAIN ANALYSE VERBOSE SELECT 1', 'EXPLAIN VERBOSE VALUES (1)', 'EXPLAIN (ANALYZE, FORMAT json) DELETE FROM t', 'EXPLAIN ("format" json, format text) EXECUTE p'],
            [$semantics->inspect('explain analyse verbose select 1')->toString(), $semantics->inspect('EXPLAIN VERBOSE VALUES (1)')->toString(), $semantics->inspect('EXPLAIN (ANALYZE, FORMAT JSON) DELETE FROM t')->toString(), $semantics->inspect('EXPLAIN ("format" json, format text) EXECUTE p')->toString()],
        );
    }
}
