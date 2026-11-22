<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Rules\Utility\ExplainFacts;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(ExplainFacts::class)]
#[Medium]
final class ExplainFactsTest extends TestCase
{
    public function testRowsTypesThePlanByTheLastFormat(): void
    {
        $semantics = new Inspection();
        self::assertEquals(
            [new Known(Builtin::Text), new Known(Builtin::Xml), new Known(Builtin::Json), new Known(Builtin::Text)],
            [
                $semantics->diagnose('EXPLAIN SELECT 1')->field(0)->type,
                $semantics->diagnose('EXPLAIN (FORMAT json, FORMAT xml) SELECT 1')->field(0)->type,
                $semantics->diagnose("EXPLAIN (FORMAT 'json') SELECT 1")->field(0)->type,
                $semantics->diagnose('EXPLAIN (FORMAT yaml) SELECT 1')->field(0)->type,
            ],
        );
    }

    public function testRowsNamesTheColumnQueryPlan(): void
    {
        $field = (new Inspection())->diagnose('EXPLAIN SELECT 1')->field(0);
        self::assertSame(['QUERY PLAN', \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull], [$field->name?->value, $field->nullability]);
    }

    public function testRowsReportsAnUnknownFormatAndTypesItAsText(): void
    {
        $operation = (new Inspection())->diagnose('EXPLAIN (FORMAT "JSON") SELECT 1');
        self::assertSame(['unrecognized value for EXPLAIN option "format": "JSON"'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
        self::assertEquals(new Known(Builtin::Text), $operation->field(0)->type);
    }

    public function testChecksReportsOptionsThatRequireAnalyze(): void
    {
        self::assertSame(
            ['EXPLAIN option WAL requires ANALYZE', 'EXPLAIN option TIMING requires ANALYZE', 'EXPLAIN option SERIALIZE requires ANALYZE'],
            array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Inspection('pg-17.2'))->diagnose('EXPLAIN (WAL, TIMING, SERIALIZE binary) SELECT 1')->facts->diagnostics),
        );
    }

    public function testChecksAcceptsTheOptionsWithAnalyze(): void
    {
        self::assertSame([], (new Inspection('pg-17.2'))->diagnose('EXPLAIN (ANALYZE on, WAL, TIMING 1, SERIALIZE, MEMORY) SELECT 1')->facts->diagnostics);
    }

    public function testChecksAcceptsOptionsThatAreOff(): void
    {
        self::assertSame([], (new Inspection('pg-17.2'))->diagnose("EXPLAIN (ANALYZE false, WAL off, TIMING 'false', SERIALIZE none, SERIALIZE off) SELECT 1")->facts->diagnostics);
    }

    public function testChecksReportsAnalyzeWithGenericPlan(): void
    {
        self::assertSame(
            ['EXPLAIN options ANALYZE and GENERIC_PLAN cannot be used together'],
            array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Inspection())->diagnose('EXPLAIN (ANALYZE, GENERIC_PLAN) SELECT 1')->facts->diagnostics),
        );
    }

    public function testChecksReportsAnUnknownSerializeValue(): void
    {
        self::assertSame(
            ['unrecognized value for EXPLAIN option "serialize": "json"', 'unrecognized value for EXPLAIN option "serialize": "TEXT"'],
            array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Inspection('pg-17.2'))->diagnose("EXPLAIN (ANALYZE, SERIALIZE json, SERIALIZE 'TEXT') SELECT 1")->facts->diagnostics),
        );
    }

    public function testChecksLeavesSerializeToTheUnknownOptionOf16(): void
    {
        self::assertSame(
            ['unrecognized EXPLAIN option "serialize"'],
            array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Inspection('pg-16.6'))->diagnose('EXPLAIN (SERIALIZE json) SELECT 1')->facts->diagnostics),
        );
    }
}
