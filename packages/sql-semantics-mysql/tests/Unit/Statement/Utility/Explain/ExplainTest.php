<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Explain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier;

#[CoversClass(Explain::class)]
#[Medium]
final class ExplainTest extends TestCase
{
    public function testDeriveStatementInspectsTheStatement(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $explain = $semantics->analyze('EXPLAIN FORMAT = JSON SELECT a FROM t', [$table]);
        self::assertInstanceOf(Explain::class, $explain->statement);
        self::assertSame([], $explain->facts->diagnostics);
        self::assertSame('EXPLAIN', $explain->field(0)->name?->value);
        self::assertSame([], $explain->declarations());
    }

    public function testDeriveStatementSearchesTheDatabaseOfForDatabase(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE shop.t (a INT)');
        $explain = $semantics->analyze('EXPLAIN FORMAT = TREE FOR DATABASE shop SELECT a FROM t', [$table]);
        self::assertInstanceOf(Explain::class, $explain->statement);
        self::assertSame([], $explain->facts->diagnostics);
        self::assertSame('shop', $explain->statement->database?->value);
    }

    public function testDeriveStatementReportsARefusedFormat(): void
    {
        $explain = (new Semantics(Dialect::MySql))->analyze('EXPLAIN ANALYZE FORMAT = traditional SELECT 1');
        self::assertSame('EXPLAIN ANALYZE does not support the TRADITIONAL format', $explain->facts->diagnostics[0]->message());
        self::assertNull($explain->shape());
    }

    public function testDeriveStatementStoresThePlanInto(): void
    {
        $explain = (new Semantics(Dialect::MySql))->analyze('EXPLAIN FORMAT = JSON INTO @plan SELECT 1');
        self::assertNull($explain->shape());
        self::assertSame([], $explain->facts->diagnostics);
    }

    public function testRenderWritesTheOptions(): void
    {
        self::assertSame('EXPLAIN EXTENDED SELECT 1', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('desc extended select 1')->toString());
        self::assertSame('EXPLAIN FORMAT = `JSON` INTO @v SELECT 1', (new Semantics(Dialect::MySql, 'mysql-8.1.0'))->analyze('explain format = JSON into @v select 1')->toString());
    }

    public function testRefusesAModifierWithOptions(): void
    {
        $select = (new Semantics(Dialect::MySql))->analyze('SELECT 1')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Explainable::class, $select);
        $this->expectExceptionMessage('EXTENDED and PARTITIONS are written alone.');
        new Explain($select, null, true, ExplainModifier::Extended);
    }
    public function testRefusesAnIntoWithoutExplicitJsonFormat(): void
    {
        $query = (new Semantics(Dialect::MySql))->analyze('SELECT 1')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Explainable::class, $query);
        $this->expectExceptionMessage('EXPLAIN INTO has an explicit JSON format.');
        new Explain($query, into: new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new \SqlSemantics\Statement\Identifier\Name('p')));
    }

}
