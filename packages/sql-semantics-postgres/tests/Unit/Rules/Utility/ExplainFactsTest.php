<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Rules\Utility\ExplainFacts;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(ExplainFacts::class)]
#[Medium]
final class ExplainFactsTest extends TestCase
{
    public function testRowsTypesThePlanByTheLastFormat(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertEquals(
            [new Known(Builtin::Text), new Known(Builtin::Xml), new Known(Builtin::Json), new Known(Builtin::Text)],
            [
                $semantics->analyze('EXPLAIN SELECT 1')->field(0)->type,
                $semantics->analyze('EXPLAIN (FORMAT json, FORMAT xml) SELECT 1')->field(0)->type,
                $semantics->analyze("EXPLAIN (FORMAT 'json') SELECT 1")->field(0)->type,
                $semantics->analyze('EXPLAIN (FORMAT yaml) SELECT 1')->field(0)->type,
            ],
        );
    }

    public function testRowsNamesTheColumnQueryPlan(): void
    {
        $field = (new Semantics(Dialect::PostgreSql))->analyze('EXPLAIN SELECT 1')->field(0);
        self::assertSame(['QUERY PLAN', \SqlSemantics\Statement\Type\Nullability::NotNull], [$field->name?->value, $field->nullability]);
    }

    public function testRowsReportsAnUnknownFormatAndTypesItAsText(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('EXPLAIN (FORMAT "JSON") SELECT 1');
        self::assertSame(['unrecognized value for EXPLAIN option "format": "JSON"'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
        self::assertEquals(new Known(Builtin::Text), $operation->field(0)->type);
    }

    public function testChecksReportsOptionsThatRequireAnalyze(): void
    {
        self::assertSame(
            ['EXPLAIN option WAL requires ANALYZE', 'EXPLAIN option TIMING requires ANALYZE', 'EXPLAIN option SERIALIZE requires ANALYZE'],
            array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics(Dialect::PostgreSql, 'pg-17.2'))->analyze('EXPLAIN (WAL, TIMING, SERIALIZE binary) SELECT 1')->facts->diagnostics),
        );
    }

    public function testChecksAcceptsTheOptionsWithAnalyze(): void
    {
        self::assertSame([], (new Semantics(Dialect::PostgreSql, 'pg-17.2'))->analyze('EXPLAIN (ANALYZE on, WAL, TIMING 1, SERIALIZE, MEMORY) SELECT 1')->facts->diagnostics);
    }

    public function testChecksAcceptsOptionsThatAreOff(): void
    {
        self::assertSame([], (new Semantics(Dialect::PostgreSql, 'pg-17.2'))->analyze("EXPLAIN (ANALYZE false, WAL off, TIMING 'false', SERIALIZE none, SERIALIZE off) SELECT 1")->facts->diagnostics);
    }

    public function testChecksReportsAnalyzeWithGenericPlan(): void
    {
        self::assertSame(
            ['EXPLAIN options ANALYZE and GENERIC_PLAN cannot be used together'],
            array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics(Dialect::PostgreSql))->analyze('EXPLAIN (ANALYZE, GENERIC_PLAN) SELECT 1')->facts->diagnostics),
        );
    }

    public function testChecksReportsAnUnknownSerializeValue(): void
    {
        self::assertSame(
            ['unrecognized value for EXPLAIN option "serialize": "json"', 'unrecognized value for EXPLAIN option "serialize": "TEXT"'],
            array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics(Dialect::PostgreSql, 'pg-17.2'))->analyze("EXPLAIN (ANALYZE, SERIALIZE json, SERIALIZE 'TEXT') SELECT 1")->facts->diagnostics),
        );
    }

    public function testChecksLeavesSerializeToTheUnknownOptionOf16(): void
    {
        self::assertSame(
            ['unrecognized EXPLAIN option "serialize"'],
            array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics(Dialect::PostgreSql, 'pg-16.6'))->analyze('EXPLAIN (SERIALIZE json) SELECT 1')->facts->diagnostics),
        );
    }
}
