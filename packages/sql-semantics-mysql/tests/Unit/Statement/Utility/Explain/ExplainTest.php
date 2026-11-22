<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Explain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(Explain::class)]
#[Medium]
final class ExplainTest extends TestCase
{
    public function testDeriveStatementInspectsTheStatement(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT)');
        $explain = $semantics->diagnose('EXPLAIN FORMAT = JSON SELECT a FROM t', [$table]);
        self::assertInstanceOf(Explain::class, $explain->statement);
        self::assertSame([], $explain->facts->diagnostics);
        self::assertSame('EXPLAIN', $explain->field(0)->name?->value);
        self::assertSame([], $explain->declarations());
    }

    public function testDeriveStatementSearchesTheDatabaseOfForDatabase(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE shop.t (a INT)');
        $explain = $semantics->diagnose('EXPLAIN FORMAT = TREE FOR DATABASE shop SELECT a FROM t', [$table]);
        self::assertInstanceOf(Explain::class, $explain->statement);
        self::assertSame([], $explain->facts->diagnostics);
        self::assertSame('shop', $explain->statement->database?->value);
    }

    public function testDeriveStatementReportsARefusedFormat(): void
    {
        $explain = (new Inspection())->diagnose('EXPLAIN ANALYZE FORMAT = traditional SELECT 1');
        self::assertSame('EXPLAIN ANALYZE does not support the TRADITIONAL format', $explain->facts->diagnostics[0]->message());
        self::assertNull($explain->shape());
    }

    public function testDeriveStatementStoresThePlanInto(): void
    {
        $explain = (new Inspection())->diagnose('EXPLAIN FORMAT = JSON INTO @plan SELECT 1');
        self::assertNull($explain->shape());
        self::assertSame([], $explain->facts->diagnostics);
    }

    public function testRenderWritesTheOptions(): void
    {
        self::assertSame('EXPLAIN EXTENDED SELECT 1', (new Inspection('mysql-5.6.51'))->diagnose('desc extended select 1')->toString());
        self::assertSame('EXPLAIN ANALYZE FORMAT = tree INTO @v SELECT 1', (new Inspection('mysql-8.1.0'))->diagnose('explain analyze format = tree into @v select 1')->toString());
    }

    public function testRefusesAModifierWithOptions(): void
    {
        $select = (new Inspection())->diagnose('SELECT 1')->statement;
        $this->expectExceptionMessage('EXTENDED and PARTITIONS are written alone.');
        new Explain($select, null, true, ExplainModifier::Extended);
    }
}
